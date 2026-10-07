/* global MVF_VOTE, turnstile */
(function () {
	'use strict';

	var cfg = window.MVF_VOTE || {};
	var i18n = cfg.i18n || {};
	var widgetId = null;
	var pending = null;
	var turnstileReady = !cfg.siteKey;
	var readyQueue = [];

	/* ---------------- Turnstile ---------------- */

	function captchaBox() {
		return document.querySelector('.mvf-captcha');
	}

	function showCaptcha(show) {
		var box = captchaBox();
		if (!box) return;
		box.classList.toggle('is-visible', show);
		var label = box.querySelector('.mvf-captcha__label');
		if (label) {
			label.textContent = i18n.human || '';
			label.hidden = !show;
		}
	}

	function settle(ok, value) {
		if (!pending) return;
		var p = pending;
		pending = null;
		showCaptcha(false);
		if (ok) p.resolve(value); else p.reject(value);
	}

	// Chamado pelo script do Turnstile (parâmetro onload).
	window.mvfTurnstileReady = function () {
		if (!cfg.siteKey || !document.getElementById('mvf-turnstile')) return;
		widgetId = turnstile.render('#mvf-turnstile', {
			sitekey: cfg.siteKey,
			action: 'vote',
			execution: 'execute',
			appearance: 'interaction-only',
			callback: function (token) { settle(true, token); },
			'error-callback': function () { settle(false, 'captcha'); },
			'expired-callback': function () { settle(false, 'captcha'); },
			'timeout-callback': function () { settle(false, 'captcha'); },
			'before-interactive-callback': function () { showCaptcha(true); },
			'after-interactive-callback': function () { showCaptcha(false); }
		});
		turnstileReady = true;
		readyQueue.splice(0).forEach(function (fn) { fn(); });
	};

	function getToken() {
		if (!cfg.siteKey) return Promise.resolve('');
		return new Promise(function (resolve, reject) {
			// Se o Turnstile não carregar ou o desafio travar, libera o botão.
			var timer = setTimeout(function () {
				var i = readyQueue.indexOf(run);
				if (i > -1) readyQueue.splice(i, 1);
				if (pending && pending.resolve === done) pending = null;
				showCaptcha(false);
				reject('captcha');
			}, 60000);
			var done = function (token) { clearTimeout(timer); resolve(token); };
			var fail = function (err) { clearTimeout(timer); reject(err); };
			var run = function () {
				if (pending) { fail('busy'); return; }
				pending = { resolve: done, reject: fail };
				// Cada token só vale para um voto: sempre gera um novo.
				turnstile.reset(widgetId);
				turnstile.execute(widgetId);
			};
			if (turnstileReady && widgetId !== null) run(); else readyQueue.push(run);
		});
	}

	/* ---------------- Voto ---------------- */

	function toast(message, isError) {
		var el = document.querySelector('.mvf-toast');
		if (!el) return;
		el.textContent = message;
		el.classList.toggle('is-error', !!isError);
		el.hidden = false;
		clearTimeout(el._t);
		el._t = setTimeout(function () { el.hidden = true; }, 4000);
	}

	function vote(button) {
		var card = button.closest('.mvf-card');
		var feedback = card.querySelector('.mvf-feedback');
		button.disabled = true;
		button.classList.add('is-loading');
		button.textContent = i18n.voting;
		if (feedback) { feedback.textContent = ''; feedback.classList.remove('is-error'); }

		getToken()
			.then(function (token) {
				return fetch(cfg.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						clip_id: parseInt(button.dataset.clip, 10),
						category_id: parseInt(button.dataset.category, 10),
						token: token
					})
				}).then(function (res) {
					return res.json().catch(function () { return {}; }).then(function (data) {
						if (!res.ok) throw (data && data.message) || i18n.error;
						return data;
					});
				});
			})
			.then(function (data) {
				button.classList.add('is-voted');
				button.textContent = i18n.again;
				if (feedback) feedback.textContent = data.message || '';
				var share = card.querySelector('.mvf-share');
				if (share) share.hidden = false;
				toast(data.message || '');
			})
			.catch(function (err) {
				var msg = (typeof err === 'string' && err !== 'captcha' && err !== 'busy') ? err : i18n.error;
				button.textContent = button.classList.contains('is-voted') ? i18n.again : i18n.vote;
				if (feedback) { feedback.textContent = msg; feedback.classList.add('is-error'); }
				toast(msg, true);
			})
			.then(function () {
				button.disabled = false;
				button.classList.remove('is-loading');
			});
	}

	/* ---------------- Player leve ---------------- */

	function play(button) {
		var id = button.dataset.youtube;
		if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
		var iframe = document.createElement('iframe');
		iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&playsinline=1';
		iframe.title = button.getAttribute('aria-label') || i18n.play;
		iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
		iframe.allowFullscreen = true;
		var wrap = document.createElement('div');
		wrap.className = 'mvf-player is-playing';
		wrap.appendChild(iframe);
		button.replaceWith(wrap);
	}

	/* ---------------- Compartilhar ---------------- */

	function copy(button) {
		var url = button.dataset.url;
		var text = button.dataset.text;
		if (navigator.share && window.matchMedia('(pointer: coarse)').matches) {
			navigator.share({ text: text, url: url }).catch(function () {});
			return;
		}
		var done = function () { toast(i18n.copied); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(done, function () { window.prompt('', url); });
		} else {
			window.prompt('', url);
		}
	}

	/* ---------------- Eventos ---------------- */

	document.addEventListener('click', function (e) {
		var target = e.target.closest('.mvf-vote, .mvf-player[data-youtube], .mvf-copy');
		if (!target || !target.closest('.mvf-voting')) return;
		if (target.classList.contains('mvf-vote')) vote(target);
		else if (target.classList.contains('mvf-copy')) copy(target);
		else play(target);
	});

	// Link compartilhado (#mvf-clip-...) destaca o card.
	function highlightHash() {
		var hash = window.location.hash;
		if (!/^#mvf-clip-\d+-\d+$/.test(hash)) return;
		var card = document.querySelector(hash);
		if (!card) return;
		card.classList.add('is-highlighted');
		card.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', highlightHash);
	else highlightHash();
})();
