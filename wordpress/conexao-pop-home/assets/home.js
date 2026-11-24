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
