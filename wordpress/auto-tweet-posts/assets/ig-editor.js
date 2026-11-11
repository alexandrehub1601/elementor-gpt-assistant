/* Editor de artes para Instagram (Auto Post Social). */
(function ($) {
	'use strict';

	var D = window.ATP_IG;
	if (!D) { return; }

	var W = 1080;
	var HEIGHTS = { feed: 1350, story: 1920 };
	var DEFAULT_POS = { faixa: 0.8, escuro: 0.9, tweet: 0.92, stories: 0.75 };

	var canvas = document.getElementById('atp-ig-canvas');
	var ctx = canvas.getContext('2d');

	var st = $.extend({
		template: D.defaultTemplate || 'faixa',
		format: 'feed',
		layout: 1,
		photos: null,
		tagLogo: 'pink',
		fontScale: 1, pos: null,
		text: D.title,
		caption: D.defaultCaption,
		desc: null
	}, D.state || {});
	if (st.desc === null || st.desc === undefined) { st.desc = D.excerpt || ''; }
	if (!st.text) { st.text = D.title; }
	st.fontScale = 1; // tamanho do texto é fixo (padrão da marca)
	if (!st.caption) { st.caption = D.defaultCaption; }

	// Até 3 fotos lado a lado, cada uma com seu enquadramento.
	if (!Array.isArray(st.photos)) {
		var extra = D.postImages || [];
		st.photos = [
			{ id: st.imageId || D.thumbId || 0, zoom: st.zoom || 1, ox: st.ox === undefined ? 0.5 : st.ox, oy: st.oy === undefined ? 0.5 : st.oy },
			{ id: extra[0] || 0, zoom: 1, ox: 0.5, oy: 0.5 },
			{ id: extra[1] || 0, zoom: 1, ox: 0.5, oy: 0.5 }
		];
	}
	while (st.photos.length < 3) { st.photos.push({ id: 0, zoom: 1, ox: 0.5, oy: 0.5 }); }
	delete st.imageId; delete st.zoom; delete st.ox; delete st.oy;

	var imgs = [null, null, null], logo = null, avatar = null, active = 0;

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
	function isStory() { return st.format === 'story'; }
	function tpl() { return isStory() ? 'stories' : st.template; }
	function pos() {
		var key = isStory() ? 'posStory' : 'pos';
		return st[key] === null || st[key] === undefined ? DEFAULT_POS[tpl()] : st[key];
	}

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

	/** Colunas das fotos: [{x, w}] */
	function slots() {
		var n = isStory() ? 1 : st.layout, out = [];
		for (var i = 0; i < n; i++) {
			var x = Math.round(i * W / n);
			out.push({ x: x, w: Math.round((i + 1) * W / n) - x });
		}
		return out;
	}

	function fit(img, p, slot, h) {
		var s = Math.max(slot.w / img.width, h / img.height) * p.zoom;
		var dw = img.width * s, dh = img.height * s;
		return { s: s, dw: dw, dh: dh, spanX: slot.w - dw, spanY: h - dh };
	}

	function drawPhoto(h) {
		ctx.fillStyle = '#111';
		ctx.fillRect(0, 0, W, h);
		slots().forEach(function (slot, i) {
			var img = imgs[i], p = st.photos[i];
			ctx.save();
			ctx.beginPath();
			ctx.rect(slot.x, 0, slot.w, h);
			ctx.clip();
			if (img) {
				var f = fit(img, p, slot, h);
				ctx.drawImage(img, slot.x + f.spanX * p.ox, f.spanY * p.oy, f.dw, f.dh);
			} else {
				ctx.fillStyle = i % 2 ? '#1c1c1c' : '#262626';
				ctx.fillRect(slot.x, 0, slot.w, h);
				ctx.fillStyle = '#777';
				ctx.font = '400 36px Archivo, sans-serif';
				ctx.textBaseline = 'middle';
				ctx.textAlign = 'center';
				ctx.fillText('Escolha a foto ' + (i + 1), slot.x + slot.w / 2, h * 0.3);
				ctx.textAlign = 'left';
			}
			ctx.restore();
		});
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

			ctx.fillStyle = '#0d0d0d';
			ctx.fillRect(margin, boxY - tagH, tagW, tagH);
			brand(margin + tagPadX, boxY - tagH + (tagH - brandH) / 2, brandH, st.tagLogo === 'white' ? '#fff' : D.color);

			ctx.fillStyle = D.color;
			ctx.fillRect(margin, boxY, boxW, boxH);
			drawLines(lines, margin + pad, boxY + pad - fs * 0.04, lh, font, '#fff');
		},

		/* Gradiente escuro: marca na cor da marca + título branco condensado. */
		escuro: function (h) {
			// Margens largas: o grid do perfil corta as laterais do 4:5.
			var margin = 130, fs = 54, lh = Math.round(fs * 1.08);
			var font = '700 condensed ' + fs + 'px "Bricolage Grotesque", sans-serif';
			var lines = wrap(st.text, font, 740);
			var brandH = 30, gap = 16;
			var blockH = brandH + gap + lines.length * lh;
			var top = Math.max(margin, h * pos() - blockH);

			bottomGradient(h, Math.max(0, Math.min(0.5, (top - 160) / h)), 0.92);
			brand(margin, top, brandH, D.color);
			drawLines(lines, margin, top + brandH + gap, lh, font, '#fff');
		},

		/* Stories: gradiente escuro, marca, título condensado e descrição.
		   Embaixo fica livre para o adesivo de link. */
		stories: function (h) {
			var margin = 108, maxW = W - margin * 2;
			var fs = Math.round(64 * st.fontScale), lh = Math.round(fs * 1.04);
			var font = '700 condensed ' + fs + 'px "Bricolage Grotesque", sans-serif';
			var ds = Math.round(41 * st.fontScale), dlh = Math.round(ds * 1.18);
			var dfont = '400 condensed ' + ds + 'px "Bricolage Grotesque", sans-serif';
			var lines = wrap(st.text, font, maxW * 0.79);
			var dlines = String(st.desc || '').trim() ? wrap(st.desc, dfont, maxW * 0.88) : [];
			var brandH = 40, gap = 40, dgap = 26;
			var blockH = brandH + gap + lines.length * lh + (dlines.length ? dgap + dlines.length * dlh : 0);
			var top = Math.max(margin, h * pos() - blockH);

			var g = ctx.createLinearGradient(0, h * 0.3, 0, h);
			g.addColorStop(0, 'rgba(0,0,0,0)');
			g.addColorStop(0.45, 'rgba(0,0,0,.55)');
			g.addColorStop(1, 'rgba(0,0,0,.92)');
			ctx.fillStyle = g;
			ctx.fillRect(0, h * 0.3, W, h * 0.7);

			brand(margin, top, brandH, D.color);
			var y = top + brandH + gap;
			drawLines(lines, margin, y, lh, font, '#fff');
			if (dlines.length) {
				drawLines(dlines, margin, y + lines.length * lh + dgap, dlh, dfont, '#fff');
			}
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
		(TEMPLATES[tpl()] || TEMPLATES.faixa)(h);
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
		$('#atp-ig-layout .button').each(function () {
			$(this).toggleClass('is-active', +$(this).data('layout') === st.layout);
		});
		if (active >= st.layout || isStory()) { active = 0; }
		$('.atp-ig-feed-only').toggle(!isStory());
		$('.atp-ig-story-only').toggle(isStory());
		$('#atp-ig-slots').toggle(st.layout > 1 && !isStory());
		$('#atp-ig-slots .button').each(function () {
			var i = +$(this).data('slot');
			$(this).toggle(i < st.layout).toggleClass('is-active', i === active);
		});
		$('#atp-ig-change').text(st.layout > 1 && !isStory() ? 'Trocar foto ' + (active + 1) : 'Trocar imagem');
		$('#atp-ig-zoom').val(st.photos[active].zoom);
		$('input[name="atp-ig-taglogo"][value="' + st.tagLogo + '"]').prop('checked', true);
		$('#atp-ig-tag-row').toggle(tpl() === 'faixa');
		$('#atp-ig-pos').val(pos());
		$('#atp-ig-count').text(st.caption.length);
		$('#atp-ig-publish').prop('disabled', !D.canPublish);
	}

	function status(msg, type) {
		$('#atp-ig-status').removeClass('is-error is-ok').addClass(type ? 'is-' + type : '').html(msg);
	}

	$('.atp-ig-templates').on('click', '.button', function () {
		update({ template: $(this).data('template'), pos: null });
	});
	$('input[name="atp-ig-format"]').on('change', function () { update({ format: this.value }); });
	$('#atp-ig-zoom').on('input', function () { st.photos[active].zoom = parseFloat(this.value); update(); });
	$('#atp-ig-layout').on('click', '.button', function () { update({ layout: +$(this).data('layout') }); });
	$('#atp-ig-slots').on('click', '.button', function () { active = +$(this).data('slot'); syncUI(); });
	$('input[name="atp-ig-taglogo"]').on('change', function () { update({ tagLogo: this.value }); });
	$('#atp-ig-pos').on('input', function () {
		var ch = {};
		ch[isStory() ? 'posStory' : 'pos'] = parseFloat(this.value);
		update(ch);
	});
	$('#atp-ig-desc').val(st.desc).on('input', function () { update({ desc: this.value }); });
	$('#atp-ig-copy').on('click', function () {
		var done = function () { status('Link copiado! Cole no adesivo de link do Stories.', 'ok'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(D.permalink).then(done, function () { window.prompt('Copie o link:', D.permalink); });
		} else {
			window.prompt('Copie o link:', D.permalink);
		}
	});
	$('#atp-ig-text').val(st.text).on('input', function () { update({ text: this.value }); });
	$('#atp-ig-caption').val(st.caption).on('input', function () { update({ caption: this.value }); });

	var frame;
	$('#atp-ig-change').on('click', function () {
		if (!frame) {
			frame = wp.media({ title: 'Escolher imagem da arte', library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var slot = active;
				loadImage(a.id).then(function (img) {
					imgs[slot] = img;
					st.photos[slot] = { id: a.id, zoom: 1, ox: 0.5, oy: 0.5 };
					update();
				});
			});
		}
		frame.open();
	});

	// Arrastar para reenquadrar.
	var drag = null;
	canvas.addEventListener('pointerdown', function (e) {
		var rect = canvas.getBoundingClientRect();
		var cx = (e.clientX - rect.left) * canvas.width / rect.width;
		slots().forEach(function (slot, i) { if (cx >= slot.x && cx < slot.x + slot.w) { active = i; } });
		syncUI();
		if (!imgs[active]) { return; }
		drag = { x: e.clientX, y: e.clientY };
		canvas.setPointerCapture(e.pointerId);
	});
	canvas.addEventListener('pointermove', function (e) {
		if (!drag) { return; }
		var k = canvas.width / canvas.getBoundingClientRect().width;
		var p = st.photos[active];
		var f = fit(imgs[active], p, slots()[active], H());
		var dx = (e.clientX - drag.x) * k, dy = (e.clientY - drag.y) * k;
		drag = { x: e.clientX, y: e.clientY };
		if (f.spanX < -0.5) { p.ox = Math.min(1, Math.max(0, p.ox + dx / f.spanX)); }
		if (f.spanY < -0.5) { p.oy = Math.min(1, Math.max(0, p.oy + dy / f.spanY)); }
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
		if (isStory()) { return; }
		if (!window.confirm('Publicar esta arte no feed do Instagram agora?')) { return; }
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

	var fonts = ['400 condensed 50px "Bricolage Grotesque"', '700 50px "Bricolage Grotesque"', '700 condensed 50px "Bricolage Grotesque"', '800 50px "Bricolage Grotesque"', '400 30px Archivo', '700 30px Archivo'];
	var fontsReady = document.fonts ? Promise.all(fonts.map(function (f) { return document.fonts.load(f).catch(function () {}); })) : Promise.resolve();

	syncUI();
	Promise.all([
		loadImage(st.photos[0].id), loadImage(st.photos[1].id), loadImage(st.photos[2].id),
		loadImage(D.logoId, D.defaultLogo), loadImage(D.avatarId, D.defaultAvatar), fontsReady
	]).then(function (r) {
		imgs = [r[0], r[1], r[2]]; logo = r[3]; avatar = r[4];
		render();
	});
})(jQuery);
