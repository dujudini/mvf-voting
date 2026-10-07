(function () {
	'use strict';

	// Prévia da thumbnail ao colar a URL do YouTube.
	var url = document.getElementById('mvf-youtube-url');
	var preview = document.querySelector('.mvf-yt-preview');
	if (url && preview) {
		var parse = function (value) {
			value = (value || '').trim();
			if (/^[A-Za-z0-9_-]{11}$/.test(value)) return value;
			var m = value.match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/i);
			return m ? m[1] : '';
		};
		var update = function () {
			var id = parse(url.value);
			preview.hidden = !id;
			url.setCustomValidity(url.value && !id ? 'URL do YouTube inválida' : '');
			if (id) preview.querySelector('img').src = 'https://i.ytimg.com/vi/' + id + '/hqdefault.jpg';
		};
		url.addEventListener('input', update);
		url.addEventListener('change', update);
	}

	// Ao trocar a edição, mostra só as categorias dela (e desmarca as das outras).
	var edition = document.getElementById('mvf-edition');
	if (edition) {
		edition.addEventListener('change', function () {
			document.querySelectorAll('.mvf-cat-group').forEach(function (group) {
				var active = group.dataset.edition === edition.value;
				group.hidden = !active;
				if (!active) {
					group.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
				}
			});
		});
	}
})();
