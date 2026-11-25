/* Home Conexão Pop: setas dos slides. */
(function () {
	document.querySelectorAll(".cph-slides").forEach(function (box) {
		var track = box.querySelector(".cph-track");
		if (!track) return;
		box.querySelectorAll(".cph-nav").forEach(function (b) {
			b.addEventListener("click", function () {
				var item = track.querySelector(".cph-slide");
				var passo = item ? item.getBoundingClientRect().width + 16 : 300;
				track.scrollBy({ left: passo * Number(b.dataset.dir) * 2, behavior: "smooth" });
			});
		});
	});
})();
/* Matéria: copiar link */
(function () {
	document.querySelectorAll(".cph-copiar").forEach(function (b) {
		b.addEventListener("click", function () {
			var url = b.dataset.url, txt = b.querySelector("span");
			var ok = function () { if (txt) { txt.textContent = "Link copiado"; setTimeout(function () { txt.textContent = "Copiar link"; }, 2000); } };
			if (navigator.clipboard) navigator.clipboard.writeText(url).then(ok, function () { prompt("Copie o link:", url); });
			else prompt("Copie o link:", url);
		});
	});
})();
/* Newsletter: inscrição pelo WordPress */
(function () {
	document.querySelectorAll(".cph-nform").forEach(function (f) {
		var msg = f.querySelector(".cph-nform-msg"), btn = f.querySelector("button[type=submit]");
		f.addEventListener("submit", function (e) {
			e.preventDefault();
			var email = f.email.value.trim();
			var avisar = function (t, ok) { msg.hidden = false; msg.textContent = t; msg.className = "cph-nform-msg" + (ok ? " is-ok" : " is-erro"); };
			if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { avisar("Esse e-mail parece incompleto.", false); f.email.focus(); return; }
			var ints = Array.prototype.map.call(f.querySelectorAll("input[name='interesses[]']:checked"), function (i) { return i.value; });
			btn.disabled = true; btn.textContent = "Enviando…";
			fetch(f.dataset.acao, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ email: email, nome: f.nome.value.trim(), interesses: ints, site: f.site.value }) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok) throw new Error(res.j && res.j.message ? res.j.message : "Não deu certo agora.");
					f.classList.add("is-feito");
					avisar(res.j.ja ? "Você já está na lista. Obrigado!" : "Falta só um passo: abra o e-mail que enviamos e confirme a inscrição.", true);
				})
				.catch(function (x) { avisar(x.message + " Tente de novo.", false); })
				.then(function () { btn.disabled = false; btn.textContent = "Quero receber"; });
		});
	});
})();
