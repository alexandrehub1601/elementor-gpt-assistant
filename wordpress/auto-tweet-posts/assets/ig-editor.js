/* Editor de artes para Instagram (Auto Post Social). */
(function ($) {
	'use strict';

	var D = window.ATP_IG;
	if (!D) { return; }

	var W = 1080;
	var HEIGHTS = { feed: 1350, story: 1920 };
	// Identidade nova (conexaopop.com.br).
	var PINK = '#FF1574', LIME = '#D7FF47';
	var TITLE_FONT = 'kensington, Anton, Impact, sans-serif';
	// Blocos "pixel" rosa de cada modelo, em coordenadas da arte 1080x1350 (x, y, largura, altura).
	var PIXELS = {"pixel1":[[0,0,84,12],[0,12,120,60],[0,72,84,48],[0,132,24,72],[72,1224,96,60],[912,1236,72,48],[540,1236,96,60],[912,1284,168,12],[228,1272,48,36],[0,1284,168,24],[432,1296,216,12],[0,1308,276,12],[432,1308,336,24],[0,1320,288,12],[852,1296,228,48],[0,1332,768,12],[0,1344,1080,12]],"pixel2":[[0,0,264,24],[0,24,192,36],[0,60,84,36],[0,96,120,60],[0,156,84,48],[0,216,24,72],[948,1128,12,72],[948,1200,72,120],[948,1320,132,36]],"pixel3":[[492,0,324,12],[264,0,72,48],[0,0,192,48],[492,12,240,60],[504,72,216,12],[192,48,60,60],[0,48,120,60],[0,108,180,12],[600,84,60,48],[0,120,192,72],[0,192,48,12],[0,204,36,72],[96,420,60,60],[0,492,96,96],[0,588,36,84]]};
	// pos = centro do card (card) ou topo da etiqueta (pixel*).
	var DEFAULT_POS = { card: 0.472, pixel1: 0.6415, pixel2: 0.6415, pixel3: 0.6415, stories: 0.75 };
	// Modelos antigos salvos em rascunhos.
	var OLD_TEMPLATES = { faixa: 'pixel1', escuro: 'pixel2', tweet: 'card' };

	var canvas = document.getElementById('atp-ig-canvas');
	var ctx = canvas.getContext('2d');

	var st = $.extend({
		template: D.defaultTemplate || 'pixel1',
		format: 'feed',
		layout: 1,
		photos: null,
		tag: null,
		fontScale: 1, pos: null,
		text: D.title,
		caption: D.defaultCaption,
		desc: null
	}, D.state || {});
	if (st.desc === null || st.desc === undefined) { st.desc = D.excerpt || ''; }
	if (OLD_TEMPLATES[st.template]) { st.template = OLD_TEMPLATES[st.template]; st.pos = null; }
	if (!DEFAULT_POS[st.template] || st.template === 'stories') { st.template = 'pixel1'; }
	if (st.tag === null || st.tag === undefined) { st.tag = D.category || ''; }
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

	var imgs = [null, null, null], logo = null, avatar = null, logoBox = null, active = 0;
	var cardBox = null; // área do card no modelo Card (para arrastar)

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
		if (/^pixel/.test(tpl())) { return DEFAULT_POS[tpl()]; } // modelos pixel: tudo fixo
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

	/** Áreas das fotos: [{x, y, w, h}]. No card ficam empilhadas; nos outros, lado a lado. */
	function slots() {
		var n = isStory() ? 1 : st.layout, out = [], h = H();
		var rows = tpl() === 'card';
		for (var i = 0; i < n; i++) {
			if (rows) {
				var y = Math.round(i * h / n);
				out.push({ x: 0, y: y, w: W, h: Math.round((i + 1) * h / n) - y });
			} else {
				var x = Math.round(i * W / n);
				out.push({ x: x, y: 0, w: Math.round((i + 1) * W / n) - x, h: h });
			}
		}
		return out;
	}

	function fit(img, p, slot) {
		var s = Math.max(slot.w / img.width, slot.h / img.height) * p.zoom;
		var dw = img.width * s, dh = img.height * s;
		return { s: s, dw: dw, dh: dh, spanX: slot.w - dw, spanY: slot.h - dh };
	}

	function drawPhoto(h) {
		ctx.fillStyle = '#111';
		ctx.fillRect(0, 0, W, h);
		slots().forEach(function (slot, i) {
			var img = imgs[i], p = st.photos[i];
			ctx.save();
			ctx.beginPath();
			ctx.rect(slot.x, slot.y, slot.w, slot.h);
			ctx.clip();
			if (img) {
				var f = fit(img, p, slot);
				ctx.drawImage(img, slot.x + f.spanX * p.ox, slot.y + f.spanY * p.oy, f.dw, f.dh);
			} else {
				ctx.fillStyle = i % 2 ? '#1c1c1c' : '#262626';
				ctx.fillRect(slot.x, slot.y, slot.w, slot.h);
				ctx.fillStyle = '#777';
				ctx.font = '400 36px Archivo, sans-serif';
				ctx.textBaseline = 'middle';
				ctx.textAlign = 'center';
				ctx.fillText('Escolha a foto ' + (i + 1), slot.x + slot.w / 2, slot.y + slot.h * 0.3);
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
		/* Stories: gradiente escuro, marca, título condensado e descrição.
		   Embaixo fica livre para o adesivo de link. */
		stories: function (h) {
			var margin = 108;
			var fs = 80, lh = 81;
			var font = '700 ' + fs + 'px ' + TITLE_FONT;
			var ds = 38, dlh = 48;
			var dfont = '400 ' + ds + 'px "TikTok Sans", sans-serif';
			var lines = wrap(String(st.text || '').toLocaleUpperCase('pt-BR'), font, 800);
			var dlines = String(st.desc || '').trim() ? wrap(st.desc, dfont, 780) : [];
			var logoW = 230, logoH = logoBox ? Math.round(logoW * logoBox.height / logoBox.width) : 0;
			var gap = 36, dgap = 30;
			var blockH = logoH + gap + lines.length * lh + (dlines.length ? dgap + dlines.length * dlh : 0);
			var top = Math.max(margin, h * pos() - blockH);

			var g = ctx.createLinearGradient(0, h * 0.3, 0, h);
			g.addColorStop(0, 'rgba(0,0,0,0)');
			g.addColorStop(0.45, 'rgba(0,0,0,.55)');
			g.addColorStop(1, 'rgba(0,0,0,.92)');
			ctx.fillStyle = g;
			ctx.fillRect(0, h * 0.3, W, h * 0.7);

			// Blocos pixel no rodapé (os mesmos do Pixel 1).
			ctx.fillStyle = PINK;
			PIXELS.pixel1.forEach(function (r) { if (r[1] >= 1100) { ctx.fillRect(r[0], r[1] + (h - 1350), r[2], r[3]); } });

			if (logoBox) { ctx.drawImage(logoBox, margin, top, logoW, logoH); }
			ctx.textBaseline = 'alphabetic';
			ctx.font = font;
			ctx.fillStyle = '#fff';
			var y = top + logoH + gap + 61; // linha de base da 1ª linha do título
			lines.forEach(function (l, i) { ctx.fillText(l, margin - 4, y + i * lh); });
			if (dlines.length) {
				ctx.font = dfont;
				var dy = y + (lines.length - 1) * lh + dgap + 38;
				dlines.forEach(function (l, i) { ctx.fillText(l, margin, dy + i * dlh); });
			}
		},

		/* Card de post (Canva p.1): card branco com avatar, nome, @ e texto,
		   sobre 1 ou 2 fotos empilhadas. */
		card: function (h) {
			var cw = 864, x = 108, pad = 34, av = 74;
			var fs = 35, lh = 44;
			var font = '400 ' + fs + 'px Inter, sans-serif';
			var lines = wrap(st.text, font, cw - pad * 2 + 20);
			var ch = 171 + (lines.length - 1) * lh + 36;
			var y = Math.round(Math.min(h - ch - 20, Math.max(20, h * pos() - ch / 2)));
			cardBox = { x: x, y: y, w: cw, h: ch };

			ctx.save();
			ctx.shadowColor = 'rgba(0,0,0,.10)';
			ctx.shadowBlur = 30;
			roundRect(x, y, cw, ch, 30);
			ctx.fillStyle = '#fff';
			ctx.fill();
			ctx.restore();

			var ax = x + pad, ay = y + 31;
			ctx.save();
			ctx.beginPath();
			ctx.arc(ax + av / 2, ay + av / 2, av / 2, 0, Math.PI * 2);
			ctx.clip();
			ctx.fillStyle = PINK;
			ctx.fillRect(ax, ay, av, av);
			if (avatar) {
				var s = Math.max(av / avatar.width, av / avatar.height);
				ctx.drawImage(avatar, ax + (av - avatar.width * s) / 2, ay + (av - avatar.height * s) / 2, avatar.width * s, avatar.height * s);
			}
			ctx.restore();

			ctx.textBaseline = 'alphabetic';
			ctx.fillStyle = '#070707';
			ctx.font = '600 29px Inter, sans-serif';
			ctx.fillText(D.accountName, x + 137, y + 62);
			ctx.fillStyle = '#53565E';
			ctx.font = '400 25px Inter, sans-serif';
			ctx.fillText(D.handle, x + 137, y + 95);

			ctx.fillStyle = '#000';
			ctx.font = font;
			lines.forEach(function (l, i) { ctx.fillText(l, x + pad, y + 171 + i * lh); });
		}
	};

	/* Pixel 1/2/3 (Canva p.2-4): foto, gradiente, blocos rosa, logo em caixas,
	   etiqueta verde e título em caixa alta. */
	function pixelTemplate(name) {
		return function (h) {
			var g = ctx.createLinearGradient(0, h * 0.4, 0, h);
			g.addColorStop(0, 'rgba(0,0,0,0)');
			g.addColorStop(0.45, 'rgba(0,0,0,.6)');
			g.addColorStop(1, 'rgba(0,0,0,.92)');
			ctx.fillStyle = g;
			ctx.fillRect(0, h * 0.4, W, h * 0.6);

			ctx.fillStyle = PINK;
			PIXELS[name].forEach(function (r) { ctx.fillRect(r[0], r[1], r[2], r[3]); });

			if (logoBox) {
				ctx.drawImage(logoBox, 774, 108, 222, 222 * logoBox.height / logoBox.width);
			}

			var top = Math.round(h * pos());
			ctx.textBaseline = 'alphabetic';
			var tag = noEmoji(st.tag).trim().toLocaleUpperCase('pt-BR');
			if (tag) {
				ctx.font = '400 33px "Geist Pixel", monospace';
				ctx.letterSpacing = '4px';
				var tw = ctx.measureText(tag).width - 4;
				ctx.fillStyle = LIME;
				ctx.fillRect(102, top, tw + 20, 42);
				ctx.fillStyle = '#111';
				ctx.fillText(tag, 112, top + 33);
				ctx.letterSpacing = '0px';
			}

			var font = '700 80px ' + TITLE_FONT;
			var lines = wrap(String(st.text || '').toLocaleUpperCase('pt-BR'), font, 830);
			ctx.font = font;
			ctx.fillStyle = '#fff';
			lines.forEach(function (l, i) { ctx.fillText(l, 104, top + 154 + i * 81); });
		};
	}
	TEMPLATES.pixel1 = pixelTemplate('pixel1');
	TEMPLATES.pixel2 = pixelTemplate('pixel2');
	TEMPLATES.pixel3 = pixelTemplate('pixel3');

	function render() {
		var h = H();
		if (canvas.height !== h) { canvas.height = h; }
		ctx.textAlign = 'left';
		ctx.letterSpacing = '0px';
		drawPhoto(h);
		(TEMPLATES[tpl()] || TEMPLATES.pixel1)(h);
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
		$('.atp-ig-templates [data-template]').each(function () {
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
		$('#atp-ig-tag-row').toggle(/^pixel/.test(tpl()));
		$('#atp-ig-pos').val(pos()).closest('label').toggle(!/^pixel/.test(tpl()));
		$('#atp-ig-count').text(st.caption.length);
		$('#atp-ig-publish').prop('disabled', !D.canPublish);
		if (!D.permalink) { $('#atp-ig-copy').hide(); }
	}

	function status(msg, type) {
		$('#atp-ig-status').removeClass('is-error is-ok').addClass(type ? 'is-' + type : '').html(msg);
	}

	$('.atp-ig-templates').on('click', '[data-template]', function () {
		var t = $(this).data('template'), ch = { template: t, pos: null };
		if (t === 'card' && st.layout === 1) { ch.layout = 2; } // card do Canva: 2 fotos empilhadas
		if (t !== 'card' && st.template === 'card' && st.layout === 2) { ch.layout = 1; }
		update(ch);
	});
	$('input[name="atp-ig-format"]').on('change', function () { update({ format: this.value }); });
	$('#atp-ig-zoom').on('input', function () { st.photos[active].zoom = parseFloat(this.value); update(); });
	$('#atp-ig-layout').on('click', '.button', function () { update({ layout: +$(this).data('layout') }); });
	$('#atp-ig-slots').on('click', '.button', function () { active = +$(this).data('slot'); syncUI(); });
	$('#atp-ig-tag').val(st.tag).on('input', function () { update({ tag: this.value }); });
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
	$('#atp-ig-text').attr('placeholder', 'Escreva o texto da arte').val(st.text).on('input', function () { update({ text: this.value }); });
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
		var cy = (e.clientY - rect.top) * canvas.height / rect.height;
		// Card: arrastar o próprio card muda a altura dele.
		if (tpl() === 'card' && cardBox && cx >= cardBox.x && cx <= cardBox.x + cardBox.w && cy >= cardBox.y && cy <= cardBox.y + cardBox.h) {
			drag = { x: e.clientX, y: e.clientY, card: true };
			canvas.setPointerCapture(e.pointerId);
			return;
		}
		slots().forEach(function (slot, i) { if (cx >= slot.x && cx < slot.x + slot.w && cy >= slot.y && cy < slot.y + slot.h) { active = i; } });
		syncUI();
		if (!imgs[active]) { return; }
		drag = { x: e.clientX, y: e.clientY };
		canvas.setPointerCapture(e.pointerId);
	});
	canvas.addEventListener('pointermove', function (e) {
		if (!drag) { return; }
		var k = canvas.width / canvas.getBoundingClientRect().width;
		if (drag.card) {
			var h = H();
			var cur = pos();
			st.pos = Math.min(0.92, Math.max(0.08, cur + (e.clientY - drag.y) * k / h));
			drag.y = e.clientY;
			$('#atp-ig-pos').val(st.pos);
			render();
			return;
		}
		var p = st.photos[active];
		var f = fit(imgs[active], p, slots()[active]);
		var dx = (e.clientX - drag.x) * k, dy = (e.clientY - drag.y) * k;
		drag = { x: e.clientX, y: e.clientY };
		if (f.spanX < -0.5) { p.ox = Math.min(1, Math.max(0, p.ox + dx / f.spanX)); }
		if (f.spanY < -0.5) { p.oy = Math.min(1, Math.max(0, p.oy + dy / f.spanY)); }
		render();
	});
	canvas.addEventListener('mousemove', function (e) {
		if (drag) { return; }
		var rect = canvas.getBoundingClientRect();
		var cx = (e.clientX - rect.left) * canvas.width / rect.width;
		var cy = (e.clientY - rect.top) * canvas.height / rect.height;
		var onCard = tpl() === 'card' && cardBox && cx >= cardBox.x && cx <= cardBox.x + cardBox.w && cy >= cardBox.y && cy <= cardBox.y + cardBox.h;
		canvas.style.cursor = onCard ? 'ns-resize' : '';
	});
	function endDrag() { if (drag) { drag = null; save(); } }
	canvas.addEventListener('pointerup', endDrag);
	canvas.addEventListener('pointercancel', endDrag);

	/* Baixar: no computador baixa o JPG; no celular abre a folha de compartilhar
	   ("Salvar imagem" no iPhone). Tudo síncrono para não perder o toque do usuário. */
	function dataUrlToBlob(url) {
		var parts = url.split(','), bin = atob(parts[1]), arr = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) { arr[i] = bin.charCodeAt(i); }
		return new Blob([arr], { type: 'image/jpeg' });
	}

	function showSaveOverlay(url) {
		var $o = $('<div class="atp-ig-save"><div class="atp-ig-save-box"><p><strong>Toque e segure a imagem</strong> e escolha <strong>"Adicionar às Fotos"</strong> (ou "Salvar imagem").</p><img alt="Arte"><button type="button" class="button">Fechar</button></div></div>');
		$o.find('img').attr('src', url);
		$o.on('click', function (e) { if (e.target === this || $(e.target).is('button')) { $o.remove(); } });
		$('body').append($o);
	}

	$('#atp-ig-download').on('click', function () {
		var name = D.slug + '-' + st.format + '.jpg';
		var url = canvas.toDataURL('image/jpeg', 0.92);
		var blob = dataUrlToBlob(url);
		var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

		if (touch) {
			var file = null;
			try { file = new File([blob], name, { type: 'image/jpeg' }); } catch (e) { file = null; }
			if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
				navigator.share({ files: [file] }).catch(function (err) {
					if (!err || err.name !== 'AbortError') { showSaveOverlay(url); }
				});
				return;
			}
			showSaveOverlay(url);
			return;
		}

		var obj = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.download = name;
		a.href = obj;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(function () { URL.revokeObjectURL(obj); }, 10000);
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

	var fonts = ['700 80px kensington', '400 38px "TikTok Sans"', '400 29px "Geist Pixel"', '400 36px Inter', '600 29px Inter', '400 25px Inter', '400 condensed 50px "Bricolage Grotesque"', '700 50px "Bricolage Grotesque"', '700 condensed 50px "Bricolage Grotesque"', '800 50px "Bricolage Grotesque"', '400 30px Archivo', '700 30px Archivo'];
	var fontsReady = document.fonts ? Promise.all(fonts.map(function (f) { return document.fonts.load(f).catch(function () {}); })) : Promise.resolve();

	syncUI();
	Promise.all([
		loadImage(st.photos[0].id), loadImage(st.photos[1].id), loadImage(st.photos[2].id),
		loadImage(D.logoId, D.defaultLogo), loadImage(D.avatarId, D.avatarPop), loadUrl(D.logoBox), fontsReady
	]).then(function (r) {
		imgs = [r[0], r[1], r[2]]; logo = r[3]; avatar = r[4]; logoBox = r[5];
		render();
	});
})(jQuery);
