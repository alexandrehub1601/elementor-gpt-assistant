/* Agenda Conexão Pop: filtros, ingressos e "Me avisa". Dados em window.CPAGENDA (vindos do WordPress). */
(function () {
	var C = window.CPAGENDA;
	var root = document.getElementById("cpa");
	if (!C || !root) return;

	var E = C.eventos || [];
	var GENRES = C.generos || {};
	var UFN = C.ufs || {};
	var POPISH = { pop: 1, popbr: 1, rnb: 1, kpop: 1, fest: 1 };
	var MES = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];
	var MES3 = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"];
	var DOW = ["dom", "seg", "ter", "qua", "qui", "sex", "sáb"];
	var ICON = {
		bell: '<svg class="cpa-i" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
		check: '<svg class="cpa-i" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>',
		cal: '<svg class="cpa-i" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M12 14v4M10 16h4"/></svg>',
		ticket: '<svg class="cpa-i" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 0 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 0 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/></svg>',
		pin: '<svg class="cpa-i" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>'
	};
	function $(id) { return document.getElementById(id); }
	function esc(s) { return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }
	function pd(s) { var p = s.split("-").map(Number); return new Date(p[0], p[1] - 1, p[2]); }
	function iso(d) { return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0"); }
	var store = {
		get: function (k, f) { try { var v = localStorage.getItem(k); return v ? JSON.parse(v) : f; } catch (e) { return f; } },
		set: function (k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
	};

	var today = pd(C.hoje || iso(new Date()));
	var T = iso(today);
	var wkStart = new Date(today); wkStart.setDate(today.getDate() - ((today.getDay() + 6) % 7));
	var wkEnd = new Date(wkStart); wkEnd.setDate(wkStart.getDate() + 6);
	var in30 = new Date(today); in30.setDate(today.getDate() + 30);
	var follows = new Set(store.get("cpa-follows", []));
	var st = { view: "shows", period: "all", q: "", uf: store.get("cpa-uf", ""), onlyPop: false, genre: "" };
	function isFest(e) { return e.g.indexOf("fest") > -1; }

	var ALL = [];
	E.forEach(function (e) {
		e.d.forEach(function (r) {
			if (r[0] >= T) ALL.push({ e: e, date: r[0], city: r[1], uf: r[2], venue: r[3], time: r[4] || "", status: r[5] || "", doors: r[6] || "" });
		});
	});

	function periodOk(o, p) {
		var d = pd(o.date);
		if (p === "all") return true;
		if (p === "week") return d >= wkStart && d <= wkEnd;
		if (p === "30") return d <= in30;
		return o.date.slice(0, 7) === p;
	}
	function otherOk(o) {
		if ((st.view === "fests") !== isFest(o.e)) return false;
		if (st.view === "shows") {
			if (st.onlyPop && !o.e.g.some(function (g) { return POPISH[g]; })) return false;
			if (st.genre && o.e.g.indexOf(st.genre) < 0) return false;
		}
		if (st.q) {
			var q = st.q.toLowerCase();
			if ([o.e.n, o.e.sub || "", o.city, o.venue, o.uf].join(" ").toLowerCase().indexOf(q) < 0) return false;
		}
		return true;
	}

	/* Seletores */
	var months = Array.from(new Set(ALL.map(function (o) { return o.date.slice(0, 7); }))).sort();
	var periods = [["all", "Qualquer data"], ["week", "Esta semana"], ["30", "Próximos 30 dias"]].concat(months.map(function (m) {
		var y = m.split("-")[0], l = MES[+m.split("-")[1] - 1];
		return [m, l[0].toUpperCase() + l.slice(1) + (y !== String(today.getFullYear()) ? " " + y : "")];
	}));
	var whenEl = $("cpa-when");
	function drawPeriods() {
		whenEl.innerHTML = periods.map(function (p) {
			var n = ALL.filter(function (o) { return periodOk(o, p[0]) && otherOk(o); }).length;
			if (!n && p[0] !== st.period && p[0].length === 7) return "";
			return '<option value="' + p[0] + '"' + (st.period === p[0] ? " selected" : "") + ">" + esc(p[1]) + " (" + n + ")</option>";
		}).join("");
	}
	whenEl.addEventListener("change", function () { st.period = whenEl.value; render(); jump(); });

	var gEl = $("cpa-genre");
	Object.keys(GENRES).forEach(function (k) { var o = document.createElement("option"); o.value = k; o.textContent = GENRES[k]; gEl.appendChild(o); });
	gEl.addEventListener("change", function () { st.genre = gEl.value; render(); });

	var ufEl = $("cpa-uf");
	Object.keys(UFN).forEach(function (u) { var o = document.createElement("option"); o.value = u; o.textContent = UFN[u]; ufEl.appendChild(o); });
	ufEl.value = st.uf;
	ufEl.addEventListener("change", function () { st.uf = ufEl.value; store.set("cpa-uf", st.uf); render(); });
	$("cpa-q").addEventListener("input", function (e) { st.q = e.target.value.trim(); render(); });
	$("cpa-pop").addEventListener("change", function (e) { st.onlyPop = e.target.checked; render(); });

	root.querySelectorAll("[data-view]").forEach(function (t) {
		t.addEventListener("click", function () {
			st.view = t.dataset.view; st.period = "all";
			root.querySelectorAll("[data-view]").forEach(function (x) { x.setAttribute("aria-selected", x === t); });
			$("cpa-genre-wrap").hidden = st.view === "fests";
			$("cpa-pop-wrap").hidden = st.view === "fests";
			render(); jump();
		});
	});
	function jump() {
		var f = root.querySelector(".cpa-filters");
		var y = root.querySelector(".cpa-main").getBoundingClientRect().top + window.scrollY - f.offsetHeight - 40;
		if (window.scrollY > y) window.scrollTo({ top: y });
	}

	/* Cards */
	function gcal(e, o, last) {
		var ymd = o.date.replace(/-/g, ""), dates;
		var m = o.time && !last ? o.time.match(/(\d+)h(\d*)/) : null;
		if (m) {
			var hh = String(+m[1]).padStart(2, "0"), mi = String(+(m[2] || 0)).padStart(2, "0"), end = String(Math.min(+m[1] + 3, 23)).padStart(2, "0");
			dates = ymd + "T" + hh + mi + "00/" + ymd + "T" + end + mi + "00&ctz=America/Sao_Paulo";
		} else {
			var n = pd(last || o.date); n.setDate(n.getDate() + 1); dates = ymd + "/" + iso(n).replace(/-/g, "");
		}
		var p = new URLSearchParams({ action: "TEMPLATE", text: e.n, location: o.venue + ", " + o.city + " - " + o.uf, details: "Da agenda do Conexão Pop. Confira o horário no site da ticketeira." });
		return "https://calendar.google.com/calendar/render?" + p.toString() + "&dates=" + dates;
	}
	function fmtD(x) { return DOW[x.getDay()] + ", " + x.getDate() + "/" + (x.getMonth() + 1); }
	var POS = ["20% 70%", "50% 80%", "80% 65%", "35% 55%", "65% 90%", "10% 85%", "90% 80%"];
	function hash(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0; return Math.abs(h); }
	function cover(e, stamp) {
		if (e.img) return '<div class="cpa-cover"><img src="' + esc(e.img) + '" alt="' + esc(e.n) + '" loading="lazy">' + stamp + (e.cred ? '<span class="cpa-credit">' + esc(e.cred) + "</span>" : "") + "</div>";
		return '<div class="cpa-cover cpa-duo"><img src="' + esc(C.plateia) + '" alt="" loading="lazy" style="object-position:' + POS[hash(e.id) % POS.length] + '">' + stamp + "</div>";
	}
	function seller(e) {
		if (e.link) return '<a class="cpa-seller cpa-link" href="' + esc(e.link) + '" target="_blank" rel="noopener sponsored">' + ICON.ticket + "Ingressos" + (e.tk ? " na " + esc(e.tk) : "") + "</a>";
		return '<span class="cpa-seller">' + ICON.ticket + (e.tk ? esc(e.tk) : "Ticketeira a confirmar") + "</span>";
	}
	function followBtn(e) {
		var f = follows.has(e.id);
		return '<button type="button" class="cpa-btn cpa-sm cpa-follow" data-follow="' + e.id + '" aria-pressed="' + f + '">' + (f ? ICON.check + "Seguindo" : ICON.bell + "Me avisa") + "</button>";
	}
	function calBtn(e, o, last) { return '<a class="cpa-btn cpa-sm cpa-icon" href="' + gcal(e, o, last) + '" target="_blank" rel="noopener" aria-label="Salvar na agenda" title="Salvar na agenda">' + ICON.cal + "</a>"; }
	function stub(d, extra) { return '<div class="cpa-stub" aria-hidden="true"><span class="cpa-wd">' + DOW[d.getDay()] + '</span><span class="cpa-dd">' + d.getDate() + '</span><span class="cpa-mm">' + MES3[d.getMonth()] + "</span>" + (extra || "") + "</div>"; }
	function tags(e, near) {
		return (near ? '<span class="cpa-near-pill">No seu estado</span>' : "") + e.g.filter(function (g) { return g !== "fest" && GENRES[g]; }).map(function (g) { return '<span class="cpa-tag">' + esc(GENRES[g].toLowerCase()) + "</span>"; }).join("");
	}

	function showCard(group) {
		var e = group.e, first = group.items[0], d = pd(first.date), multi = group.items.length > 1;
		var near = st.uf && group.items.some(function (o) { return o.uf === st.uf; });
		var allSold = group.items.every(function (o) { return o.status === "esgotado"; });
		var datesHtml = multi ? '<div class="cpa-dates">' + group.items.map(function (o) {
			var x = pd(o.date);
			return '<span class="cpa-dchip' + (o.status === "esgotado" ? " cpa-x" : o.status === "extra" ? " cpa-ex" : "") + '">' + fmtD(x) + (o.time ? ", " + esc(o.time) : "") + (o.status === "esgotado" ? " (esgotado)" : o.status === "extra" ? " (extra)" : "") + "</span>";
		}).join("") + "</div>" : "";
		var stamp = allSold ? '<span class="cpa-stamp">Esgotado</span>' : (!multi && first.status === "extra") ? '<span class="cpa-stamp cpa-extra">Data extra</span>' : "";
		return '<article class="cpa-ticket' + (near ? " cpa-near" : "") + '">' + stub(d, multi ? '<span class="cpa-plus">+' + (group.items.length - 1) + "</span>" : "") +
			'<div class="cpa-tbody">' + cover(e, stamp) + '<div class="cpa-info">' +
			'<h3 class="cpa-name">' + esc(e.n) + "</h3>" + (e.sub ? '<p class="cpa-tour">' + esc(e.sub) + "</p>" : "") +
			'<div class="cpa-where">' + ICON.pin + "<span><b>" + esc(first.venue) + "</b>" + (first.city ? ", " + esc(first.city) + (first.uf ? " (" + esc(first.uf) + ")" : "") : "") + "" + (!multi && first.time ? ", às " + esc(first.time) : "") + "</span></div>" +
			datesHtml + '<div class="cpa-tags">' + tags(e, near) + "</div>" +
			'<div class="cpa-actions">' + seller(e) + followBtn(e) + calBtn(e, first) + "</div>" +
			"</div></div></article>";
	}
	function festCard(group) {
		var e = group.e, first = group.items[0], last = group.items[group.items.length - 1], d = pd(first.date);
		var near = st.uf && first.uf === st.uf;
		var days = group.items.map(function (o) { var x = pd(o.date); return '<span class="cpa-day"><b>' + x.getDate() + "</b><span>" + DOW[x.getDay()] + "</span></span>"; }).join("");
		var span = group.items.length > 1 ? group.items.length + " dias" : "";
		return '<article class="cpa-ticket cpa-fest' + (near ? " cpa-near" : "") + '">' + stub(d, span ? '<span class="cpa-plus">' + span + "</span>" : "") +
			'<div class="cpa-tbody">' + cover(e, "") + '<div class="cpa-info">' +
			'<h3 class="cpa-name">' + esc(e.n) + "</h3>" + (e.sub ? '<p class="cpa-tour">' + esc(e.sub) + "</p>" : "") +
			'<div class="cpa-where">' + ICON.pin + "<span><b>" + esc(first.venue) + "</b>" + (first.city ? ", " + esc(first.city) + (first.uf ? " (" + esc(first.uf) + ")" : "") : "") + "</span></div>" +
			'<div class="cpa-days" aria-label="Dias do festival">' + days + "</div>" +
			'<p class="cpa-lineup">O line-up de cada dia aparece aqui assim que o festival anunciar. Siga para receber o aviso.</p>' +
			'<div class="cpa-tags">' + tags(e, near) + "</div>" +
			'<div class="cpa-actions">' + seller(e) + followBtn(e) + calBtn(e, first, last.date) + "</div>" +
			"</div></div></article>";
	}

	var out = $("cpa-out");
	function render() {
		drawPeriods();
		var rows = ALL.filter(function (o) { return periodOk(o, st.period) && otherOk(o); });
		var map = new Map();
		rows.forEach(function (o) { var k = o.e.id + "|" + o.venue + "|" + o.city; if (!map.has(k)) map.set(k, { e: o.e, items: [] }); map.get(k).items.push(o); });
		var groups = Array.from(map.values());
		groups.forEach(function (g) { g.items.sort(function (a, b) { return a.date.localeCompare(b.date); }); });
		groups.sort(function (a, b) { return a.items[0].date.localeCompare(b.items[0].date) || a.e.n.localeCompare(b.e.n); });
		var fest = st.view === "fests";
		var n = fest ? groups.length : rows.length, cities = new Set(rows.map(function (o) { return o.city; })).size;
		$("cpa-summary").innerHTML = "<b>" + n + " " + (fest ? (n === 1 ? "festival" : "festivais") : (n === 1 ? "show" : "shows")) + "</b> em " + cities + " " + (cities === 1 ? "cidade" : "cidades");
		$("cpa-sort").textContent = st.uf ? "O que acontece em " + UFN[st.uf] + " aparece primeiro em cada mês." : "Escolha seu estado para ver primeiro o que tem perto de você.";
		if (!groups.length) {
			out.innerHTML = '<div class="cpa-empty"><p>nada com esses filtros</p><button class="cpa-btn" type="button" id="cpa-clear">Limpar filtros</button></div>';
			$("cpa-clear").onclick = function () { st.q = ""; st.genre = ""; st.onlyPop = false; st.period = "all"; $("cpa-q").value = ""; $("cpa-pop").checked = false; gEl.value = ""; render(); };
			return;
		}
		var byMonth = new Map();
		groups.forEach(function (g) { var m = g.items[0].date.slice(0, 7); if (!byMonth.has(m)) byMonth.set(m, []); byMonth.get(m).push(g); });
		var html = "";
		byMonth.forEach(function (gs, m) {
			if (st.uf) gs.sort(function (a, b) { return (b.items.some(function (o) { return o.uf === st.uf; }) - a.items.some(function (o) { return o.uf === st.uf; })) || a.items[0].date.localeCompare(b.items[0].date); });
			html += '<div class="cpa-month"><h2>' + MES[+m.split("-")[1] - 1] + "</h2><span>" + m.split("-")[0] + '</span></div><div class="cpa-grid' + (fest ? " cpa-fests" : "") + '">' + gs.map(fest ? festCard : showCard).join("") + "</div>";
		});
		out.innerHTML = html;
	}

	/* Me avisa */
	var sheet = $("cpa-sheet"), pending = null, lastBtn = null;
	function updCount() { $("cpa-following-label").innerHTML = "Meus alertas" + (follows.size ? ' <span class="cpa-badge">' + follows.size + "</span>" : ""); }
	function closeSheet() { sheet.hidden = true; if (lastBtn) lastBtn.focus(); }
	out.addEventListener("click", function (e) {
		var b = e.target.closest("[data-follow]");
		if (!b) return;
		var id = b.dataset.follow;
		if (follows.has(id)) { follows.delete(id); store.set("cpa-follows", Array.from(follows)); updCount(); render(); return; }
		pending = id; lastBtn = b;
		var ev = E.find(function (x) { return x.id === id; });
		$("cpa-sheet-title").textContent = ev.n;
		$("cpa-sheet-sub").textContent = "Escolha quando quer receber um aviso por e-mail.";
		$("cpa-alert-err").hidden = true;
		var saved = store.get("cpa-mail", "");
		if (saved) $("cpa-alert-mail").value = saved;
		sheet.hidden = false; $("cpa-alert-mail").focus();
	});
	$("cpa-sheet-close").onclick = closeSheet;
	sheet.addEventListener("click", function (e) { if (e.target === sheet) closeSheet(); });
	document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !sheet.hidden) closeSheet(); });
	$("cpa-alert-form").addEventListener("submit", function (e) {
		e.preventDefault();
		var mail = $("cpa-alert-mail").value.trim(), err = $("cpa-alert-err"), btn = e.target.querySelector("[type=submit]");
		if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(mail)) { err.textContent = "Esse e-mail parece incompleto."; err.hidden = false; return; }
		var opcoes = Array.from(e.target.querySelectorAll(".cpa-checks input:checked")).map(function (i) { return i.value; });
		btn.disabled = true; btn.textContent = "Salvando…";
		fetch(C.alerta, { method: "POST", headers: { "Content-Type": "application/json", "X-WP-Nonce": C.nonce }, body: JSON.stringify({ email: mail, show: pending, opcoes: opcoes }) })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				if (!res.ok) throw new Error(res.j && res.j.message ? res.j.message : "Não foi possível salvar agora.");
				follows.add(pending); store.set("cpa-follows", Array.from(follows)); store.set("cpa-mail", mail);
				sheet.hidden = true; updCount(); render();
			})
			.catch(function (x) { err.textContent = x.message + " Tente de novo."; err.hidden = false; })
			.then(function () { btn.disabled = false; btn.textContent = "Seguir"; });
	});
	$("cpa-following").onclick = function () {
		if (!follows.size) { var l = $("cpa-following-label"); l.textContent = "Você ainda não segue nenhum show"; setTimeout(updCount, 2200); return; }
		var firstF = E.find(function (x) { return follows.has(x.id); });
		if (!firstF) return;
		st.view = isFest(firstF) ? "fests" : "shows"; st.period = "all"; st.q = ""; $("cpa-q").value = "";
		root.querySelectorAll("[data-view]").forEach(function (x) { x.setAttribute("aria-selected", x.dataset.view === st.view); });
		render();
		var f = out.querySelector('[data-follow="' + firstF.id + '"]');
		if (f) f.closest(".cpa-ticket").scrollIntoView({ behavior: "smooth", block: "center" });
	};

	updCount(); render();
})();
