/* Editor de artes para Instagram (Auto Post Social). */
(function ($) {
	'use strict';

	var D = window.ATP_IG;
	if (!D) { return; }

	var W = 1080;
	var HEIGHTS = { feed: 1350, story: 1920 };
	var DEFAULT_POS = { faixa: 0.8, escuro: 0.93, tweet: 0.92 };

	var canvas = document.getElementById('atp-ig-canvas');
	var ctx = canvas.getContext('2d');

	var st = $.extend({
		template: D.defaultTemplate || 'faixa',
		format: 'feed',
		imageId: D.thumbId || 0,
		zoom: 1, ox: 0.5, oy: 0.5,
		fontScale: 1, pos: null,
		text: D.title,
		caption: D.defaultCaption
	}, D.state || {});
	if (!st.text) { st.text = D.title; }
	if (!st.caption) { st.caption = D.defaultCaption; }

	var photo = null, logo = null, avatar = null;

	/* ------------------------------------------------------------ util */

	function loadUrl(src) {
		return new Promise(function (resolve) {
			if (!src) { return resolve(null); }
			var img = new Image();
			img.onload = function () { resolve(img); };
			img.onerror = function () { resolve(null); };
			img.src = src;
		});
	}

	function loadImage(id, fallback) {
		return id ? loadUrl(D.imgUrl + '&id=' + id) : loadUrl(fallback);
	}

	function H() { return HEIGHTS[st.format] || 1350; }
	function pos() { return st.pos === null || st.pos === undefined ? DEFAULT_POS[st.template] : st.pos; }

	/** A arte não usa emojis (cada sistema desenha de um jeito); a legenda pode ter. */
	var EMOJI = /(?:(?![\u00A9\u00AE\u2122])\p{Extended_Pictographic}|\p{Regional_Indicator})(?:\uFE0F|\u20E3|\p{Emoji_Modifier}|\u200D(?:\p{Extended_Pictographic})\uFE0F?)*|\uFE0F|\u200D/gu;
	function noEmoji(text) {
		return String(text || '').replace(EMOJI, '').replace(/[ \t]{2,}/g, ' ').replace(/^[ \t]+|[ \t]+$/gm, '');
	}

	function wrap(text, font, maxW) {
		ctx.font = font;
		var lines = [];
		noEmoji(text).split('\n').forEach(function (par) {
			var words = par.split(/\s+/).filter(Boolean);
			if (!words.length) { lines.push(''); return; }
			var line = words[0];
			for (var i = 1; i < words.length; i++) {
				var test = line + ' ' + words[i];
				if (ctx.measureText(test).width <= maxW) { line = test; } else { lines.push(line); line = words[i]; }
			}
			lines.push(line);
		});
		return lines;
	}

	function widest(lines, font) {
		ctx.font = font;
		return lines.reduce(function (m, l) { return Math.max(m, ctx.measureText(l).width); }, 0);
	}

	function roundRect(x, y, w, h, r) {
		ctx.beginPath();
		ctx.moveTo(x + r, y);
		ctx.arcTo(x + w, y, x + w, y + h, r);
		ctx.arcTo(x + w, y + h, x, y + h, r);
		ctx.arcTo(x, y + h, x, y, r);
		ctx.arcTo(x, y, x + w, y, r);
		ctx.closePath();
	}

	function tinted(img, color) {
		var c = document.createElement('canvas');
		c.width = img.width; c.height = img.height;
		var g = c.getContext('2d');
		g.drawImage(img, 0, 0);
		g.globalCompositeOperation = 'source-in';
		g.fillStyle = color;
		g.fillRect(0, 0, c.width, c.height);
		return c;
	}

	/** Marca (logo ou texto). Retorna a largura desenhada. */
	function brand(x, y, height, color, measureOnly) {
		if (logo) {
			var w = logo.width * height / logo.height;
			if (!measureOnly) { ctx.drawImage(tinted(logo, color), x, y, w, height); }
			return w;
		}
		ctx.font = '800 ' + height + 'px "Bricolage Grotesque", sans-serif';
		ctx.textBaseline = 'top';
		var tw = ctx.measureText(D.brandText).width;
		if (!measureOnly) { ctx.fillStyle = color; ctx.fillText(D.brandText, x, y - height * 0.1); }
		return tw;
	}

	function drawLines(lines, x, y, lh, font, color) {
		ctx.font = font;
		ctx.fillStyle = color;
		ctx.textBaseline = 'top';
		lines.forEach(function (l, i) { ctx.fillText(l, x, y + i * lh); });
	}

	/* ------------------------------------------------------------ desenho */

	function drawPhoto(h) {
		ctx.fillStyle = '#111';
		ctx.fillRect(0, 0, W, h);
		if (!photo) {
			ctx.fillStyle = '#666';
			ctx.font = '400 40px Archivo, sans-serif';
			ctx.textBaseline = 'top';
			ctx.fillText('Escolha uma imagem', 60, 60);
			return;
		}
		var s = Math.max(W / photo.width, h / photo.height) * st.zoom;
		var dw = photo.width * s, dh = photo.height * s;
		ctx.drawImage(photo, (W - dw) * st.ox, (h - dh) * st.oy, dw, dh);
	}

	function bottomGradient(h, from, alpha) {
		var g = ctx.createLinearGradient(0, h * from, 0, h);
		g.addColorStop(0, 'rgba(0,0,0,0)');
		g.addColorStop(1, 'rgba(0,0,0,' + alpha + ')');
		ctx.fillStyle = g;
		ctx.fillRect(0, h * from, W, h * (1 - from));
	}

	var TEMPLATES = {
		/* Faixa rosa: selo com a marca + caixa rosa com o título em branco. */
		faixa: function (h) {
			var margin = 60, pad = 32, fs = Math.round(60 * st.fontScale), lh = Math.round(fs * 1.16);
			var font = '700 ' + fs + 'px "Bricolage Grotesque", sans-serif';
			var lines = wrap(st.text, font, W * 0.74 - pad * 2);
			var boxW = widest(lines, font) + pad * 2;
			var boxH = lines.length * lh - (lh - fs) + pad * 2;
			var tagH = 62, tagPadX = 20, brandH = 30;
			var tagW = brand(0, 0, brandH, '#fff', true) + tagPadX * 2;
			var boxY = Math.max(margin + tagH, h * pos() - boxH);

			ctx.fillStyle = D.color;
			ctx.fillRect(margin, boxY - tagH, tagW, tagH);
			brand(margin + tagPadX, boxY - tagH + (tagH - brandH) / 2, brandH, '#fff');

			ctx.fillStyle = D.color;
			ctx.fillRect(margin, boxY, boxW, boxH);
			drawLines(lines, margin + pad, boxY + pad - fs * 0.04, lh, font, '#fff');
		},

		/* Gradiente escuro: marca na cor da marca + título branco condensado. */
		escuro: function (h) {
			var margin = 64, fs = Math.round(64 * st.fontScale), lh = Math.round(fs * 1.1);
			var font = '700 condensed ' + fs + 'px "Bricolage Grotesque", sans-serif';
			var lines = wrap(st.text, font, W - margin * 2);
			var brandH = 38, gap = 20;
			var blockH = brandH + gap + lines.length * lh;
			var top = Math.max(margin, h * pos() - blockH);

			bottomGradient(h, Math.max(0, Math.min(0.5, (top - 160) / h)), 0.92);
			brand(margin, top, brandH, D.color);
			drawLines(lines, margin, top + brandH + gap, lh, font, '#fff');
		},

		/* Card de post: cartão branco com avatar, nome, @ e o texto. */
		tweet: function (h) {
			var cw = Math.round(W * 0.76), pad = 34, av = 76;
			var fs = Math.round(35 * st.fontScale), lh = Math.round(fs * 1.3);
			var font = '400 ' + fs + 'px Archivo, sans-serif';
			var lines = wrap(st.text, font, cw - pad * 2);
			var ch = pad + av + 22 + lines.length * lh - (lh - fs) + pad;
			var x = Math.round((W - cw) / 2);
			var y = Math.max(40, h * pos() - ch);

			bottomGradient(h, 0.5, 0.6);

			ctx.save();
			ctx.shadowColor = 'rgba(0,0,0,.25)';
			ctx.shadowBlur = 24;
			roundRect(x, y, cw, ch, 26);
			ctx.fillStyle = '#fff';
			ctx.fill();
			ctx.restore();

			// Avatar.
			var ax = x + pad, ay = y + pad;
			ctx.save();
			ctx.beginPath();
			ctx.arc(ax + av / 2, ay + av / 2, av / 2, 0, Math.PI * 2);
			ctx.clip();
			if (avatar) {
				var s = Math.max(av / avatar.width, av / avatar.height);
				ctx.drawImage(avatar, ax + (av - avatar.width * s) / 2, ay + (av - avatar.height * s) / 2, avatar.width * s, avatar.height * s);
			} else {
				ctx.fillStyle = D.color;
				ctx.fillRect(ax, ay, av, av);
				ctx.font = '800 15px "Bricolage Grotesque", sans-serif';
				ctx.fillStyle = '#fff';
				ctx.textBaseline = 'middle';
				ctx.textAlign = 'center';
				ctx.fillText(D.brandText, ax + av / 2, ay + av / 2);
				ctx.textAlign = 'left';
			}
			ctx.restore();

			ctx.textBaseline = 'top';
			ctx.fillStyle = '#0f1419';
			ctx.font = '700 30px Archivo, sans-serif';
			ctx.fillText(D.accountName, ax + av + 18, ay + 6);
			ctx.fillStyle = '#536471';
			ctx.font = '400 25px Archivo, sans-serif';
			ctx.fillText(D.handle, ax + av + 18, ay + 44);

			drawLines(lines, x + pad, ay + av + 22, lh, font, '#0f1419');
		}
	};

	function render() {
		var h = H();
		if (canvas.height !== h) { canvas.height = h; }
		ctx.textAlign = 'left';
		drawPhoto(h);
		(TEMPLATES[st.template] || TEMPLATES.faixa)(h);
	}

	/* ------------------------------------------------------------ controles */

	var saveTimer;
	function save() {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			$.post(D.ajaxUrl, { action: 'atp_ig_save', _ajax_nonce: D.nonce, post_id: D.postId, state: JSON.stringify(st) });
		}, 1200);
	}

	function update(changes) {
		$.extend(st, changes || {});
		syncUI();
		render();
		save();
	}

	function syncUI() {
		$('.atp-ig-templates .button').each(function () {
			$(this).toggleClass('is-active', $(this).data('template') === st.template);
		});
		$('input[name="atp-ig-format"][value="' + st.format + '"]').prop('checked', true);
		$('#atp-ig-zoom').val(st.zoom);
		$('#atp-ig-size').val(st.fontScale);
		$('#atp-ig-pos').val(pos());
		$('#atp-ig-count').text(st.caption.length);
		$('#atp-ig-story-note').toggle(st.format === 'story');
		$('#atp-ig-caption').prop('disabled', st.format === 'story');
		$('#atp-ig-publish').prop('disabled', !D.canPublish)
			.text(st.format === 'story' ? 'Publicar no Stories' : 'Publicar no feed');
	}

	function status(msg, type) {
		$('#atp-ig-status').removeClass('is-error is-ok').addClass(type ? 'is-' + type : '').html(msg);
	}

	$('.atp-ig-templates').on('click', '.button', function () {
		update({ template: $(this).data('template'), pos: null });
	});
	$('input[name="atp-ig-format"]').on('change', function () { update({ format: this.value }); });
	$('#atp-ig-zoom').on('input', function () { update({ zoom: parseFloat(this.value) }); });
	$('#atp-ig-size').on('input', function () { update({ fontScale: parseFloat(this.value) }); });
	$('#atp-ig-pos').on('input', function () { update({ pos: parseFloat(this.value) }); });
	$('#atp-ig-text').val(st.text).on('input', function () { update({ text: this.value }); });
	$('#atp-ig-caption').val(st.caption).on('input', function () { update({ caption: this.value }); });

	var frame;
	$('#atp-ig-change').on('click', function () {
		if (!frame) {
			frame = wp.media({ title: 'Escolher imagem da arte', library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				loadImage(a.id).then(function (img) {
					photo = img;
					update({ imageId: a.id, zoom: 1, ox: 0.5, oy: 0.5 });
				});
			});
		}
		frame.open();
	});

	// Arrastar para reenquadrar.
	var drag = null;
	canvas.addEventListener('pointerdown', function (e) {
		if (!photo) { return; }
		drag = { x: e.clientX, y: e.clientY };
		canvas.setPointerCapture(e.pointerId);
	});
	canvas.addEventListener('pointermove', function (e) {
		if (!drag) { return; }
		var k = canvas.width / canvas.getBoundingClientRect().width;
		var h = H();
		var s = Math.max(W / photo.width, h / photo.height) * st.zoom;
		var spanX = W - photo.width * s, spanY = h - photo.height * s;
		var dx = (e.clientX - drag.x) * k, dy = (e.clientY - drag.y) * k;
		drag = { x: e.clientX, y: e.clientY };
		var ch = {};
		if (spanX < -0.5) { ch.ox = Math.min(1, Math.max(0, st.ox + dx / spanX)); }
		if (spanY < -0.5) { ch.oy = Math.min(1, Math.max(0, st.oy + dy / spanY)); }
		$.extend(st, ch);
		render();
	});
	function endDrag() { if (drag) { drag = null; save(); } }
	canvas.addEventListener('pointerup', endDrag);
	canvas.addEventListener('pointercancel', endDrag);

	$('#atp-ig-download').on('click', function () {
		var a = document.createElement('a');
		a.download = D.slug + '-' + st.format + '.jpg';
		a.href = canvas.toDataURL('image/jpeg', 0.92);
		document.body.appendChild(a);
		a.click();
		a.remove();
	});

	$('#atp-ig-publish').on('click', function () {
		var where = st.format === 'story' ? 'no Stories' : 'no feed';
		if (!window.confirm('Publicar esta arte ' + where + ' do Instagram agora?')) { return; }
		var $btn = $(this).prop('disabled', true);
		status('Publicando… (pode levar até 30 segundos)');
		canvas.toBlob(function (blob) {
			var fd = new FormData();
			fd.append('action', 'atp_ig_publish');
			fd.append('_ajax_nonce', D.nonce);
			fd.append('post_id', D.postId);
			fd.append('format', st.format);
			fd.append('caption', st.caption);
			fd.append('state', JSON.stringify(st));
			fd.append('image', blob, 'arte.jpg');
			$.ajax({ url: D.ajaxUrl, method: 'POST', data: fd, processData: false, contentType: false })
				.done(function (r) {
					if (r && r.success) {
						status('Publicado! ' + (r.data.permalink ? $('<div>').append($('<a target="_blank" rel="noopener">Ver no Instagram</a>').attr('href', r.data.permalink)).html() : ''), 'ok');
						$('#atp-ig-published').html(r.data.list);
					} else {
						status($('<span>').text((r && r.data && r.data.message) || 'Erro ao publicar.').html(), 'error');
					}
				})
				.fail(function () { status('Erro de conexão com o WordPress.', 'error'); })
				.always(function () { $btn.prop('disabled', !D.canPublish); });
		}, 'image/jpeg', 0.92);
	});

	/* ------------------------------------------------------------ início */

	var fonts = ['700 50px "Bricolage Grotesque"', '700 condensed 50px "Bricolage Grotesque"', '800 50px "Bricolage Grotesque"', '400 30px Archivo', '700 30px Archivo'];
	var fontsReady = document.fonts ? Promise.all(fonts.map(function (f) { return document.fonts.load(f).catch(function () {}); })) : Promise.resolve();

	syncUI();
	Promise.all([loadImage(st.imageId), loadImage(D.logoId, D.defaultLogo), loadImage(D.avatarId, D.defaultAvatar), fontsReady]).then(function (r) {
		photo = r[0]; logo = r[1]; avatar = r[2];
		render();
	});
})(jQuery);
