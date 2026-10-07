/* global MVF_JURY, jQuery */
(function ($) {
	'use strict';

	var cfg = window.MVF_JURY || {};
	var i18n = cfg.i18n || {};
	var $root = $('.mvf-ballot');
	if (!$root.length) return;

	var $list = $('#mvf-ballot-list');
	var readonly = $root.data('readonly') === 1 || $root.data('readonly') === '1';
	var dirty = false;

	function points(pos) {
		return (cfg.points && cfg.points[pos]) || 0;
	}

	function renumber() {
		$list.children('.mvf-ballot-item').each(function (i) {
			var pos = i + 1;
			$(this).find('.mvf-ballot-item__pos').text(pos + 'º');
			$(this).find('.mvf-ballot-item__pts').text(points(pos) + ' ' + i18n.pts);
		});
	}

	function markDirty() {
		dirty = true;
		renumber();
	}

	if (!readonly) {
		$list.sortable({
			axis: 'y',
			handle: '.mvf-ballot-item__handle, .mvf-ballot-item__info, .mvf-ballot-item__pos',
			placeholder: 'mvf-ballot-item mvf-ballot-item--placeholder',
			forcePlaceholderSize: true,
			update: markDirty
		});

		$list.on('click', '.mvf-move', function () {
			var $item = $(this).closest('.mvf-ballot-item');
			var dir = parseInt($(this).data('dir'), 10);
			if (dir < 0 && $item.prev().length) $item.insertBefore($item.prev());
			else if (dir > 0 && $item.next().length) $item.insertAfter($item.next());
			else return;
			markDirty();
			$item.addClass('is-moved');
			setTimeout(function () { $item.removeClass('is-moved'); }, 400);
			$(this).trigger('focus');
		});
	}

	function save(submit) {
		if (submit && !window.confirm(i18n.confirm)) return;
		var $msg = $root.find('.mvf-ballot-msg');
		var $buttons = $root.find('.mvf-save-draft, .mvf-submit').prop('disabled', true);
		$msg.removeClass('is-error').text(i18n.saving);

		var ids = $list.children('.mvf-ballot-item').map(function () {
			return parseInt($(this).data('clip'), 10);
		}).get();

		fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify({ category_id: parseInt($root.data('category'), 10), clip_ids: ids, submit: !!submit })
		})
			.then(function (res) {
				return res.json().catch(function () { return {}; }).then(function (data) {
					if (!res.ok) throw (data && data.message) || i18n.error;
					return data;
				});
			})
			.then(function (data) {
				dirty = false;
				$msg.text(data.message || '');
				if (data.status === 'submitted') {
					$root.attr('data-status', 'submitted');
					$root.find('.mvf-save-draft').prop('hidden', true);
					$root.find('.mvf-submit').text(i18n.update);
					$root.find('.mvf-ballot-status').html('<span class="mvf-badge mvf-badge--open"></span>')
						.find('.mvf-badge').text(i18n.sent);
				}
			})
			.catch(function (err) {
				$msg.addClass('is-error').text(typeof err === 'string' ? err : i18n.error);
			})
			.then(function () {
				$buttons.prop('disabled', false);
			});
	}

	$root.on('click', '.mvf-save-draft', function () { save(false); });
	$root.on('click', '.mvf-submit', function () { save(true); });

	window.addEventListener('beforeunload', function (e) {
		if (dirty) { e.preventDefault(); e.returnValue = ''; }
	});

	/* Player em modal */
	var $modal = $root.find('.mvf-modal');
	var $player = $modal.find('.mvf-modal__player');

	function closeModal() {
		$player.empty();
		$modal.prop('hidden', true);
	}

	$list.on('click', '.mvf-ballot-item__thumb', function () {
		var id = String($(this).data('youtube') || '');
		if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
		var iframe = document.createElement('iframe');
		iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
		iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
		iframe.allowFullscreen = true;
		$player.empty().append(iframe);
		$modal.prop('hidden', false);
		$modal.find('.mvf-modal__close').trigger('focus');
	});
	$modal.on('click', function (e) {
		if (e.target === this || $(e.target).hasClass('mvf-modal__close')) closeModal();
	});
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && !$modal.prop('hidden')) closeModal();
	});
})(jQuery);
