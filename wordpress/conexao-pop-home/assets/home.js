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
