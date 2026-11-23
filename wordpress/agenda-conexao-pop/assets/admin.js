(function () {
	var tabela = document.querySelector('.cpa-datas tbody');
	var tmpl = document.getElementById('tmpl-cpa-linha');
	if (!tabela || !tmpl) return;
	var n = tabela.rows.length + 1000;
	document.querySelector('.cpa-add').addEventListener('click', function () {
		var t = document.createElement('tbody');
		t.innerHTML = tmpl.innerHTML.replace(/__i__/g, String(n++));
		tabela.appendChild(t.firstElementChild);
	});
	tabela.addEventListener('click', function (e) {
		var b = e.target.closest('.cpa-del');
		if (!b) return;
		if (tabela.rows.length > 1) b.closest('tr').remove();
		else b.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = ''; });
	});
})();
