/**
 * Gera mr-ice-lp.html: a landing page com as fotos de img/produtos embutidas
 * como data: URI, para colar no Elementor sem depender de upload de arquivos.
 * O index.html continua sendo a fonte, com caminhos relativos.
 *
 *   node build-embutir.js
 */
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const LARGURA_PADRAO = 700;   // as artes dos cards tem ~370px no desktop
const LARGURAS = { 'img/hero.webp': 2400 };  // a hero ocupa a tela inteira
const QUALIDADE = 0.78;
const CHROME = process.env.CHROME_PATH || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';


/**
 * Encaixota a folha de estilo sob um id.
 *
 * Colada num widget HTML, a pagina disputa o cascata com o tema do WordPress.
 * Regras como `.elementor h3{font-family:serif;color:#d6336c}` tem duas
 * classes de especificidade e vencem as nossas, que quase todas tem uma so —
 * tipografia e cor saiam erradas em 16 dos 20 componentes medidos.
 * Prefixar cada seletor com um id soma 1-0-0 e devolve a vitoria para a nossa
 * folha, sem espalhar !important por centenas de regras.
 *
 * `html` fica de fora: scroll-behavior e scroll-padding precisam continuar no
 * documento. `:root` e `body` viram o proprio container.
 */
function encaixotaCss(css, id) {
  const escopo = '#' + id;

  const umSeletor = (sel) => {
    sel = sel.trim();
    if (!sel) return sel;
    if (sel === ':root' || sel === 'body') return escopo;
    if (/^html\b/.test(sel)) return sel;                 // fica no documento
    if (/^body\b/.test(sel)) return escopo + sel.slice(4);
    return escopo + ' ' + sel;
  };

  const listaDeSeletores = (lista) => lista.split(',').map(umSeletor).join(',');

  // percorre o css acompanhando a profundidade das chaves
  const transforma = (texto) => {
    let saida = '', i = 0;
    while (i < texto.length) {
      const abre = texto.indexOf('{', i);
      if (abre === -1) { saida += texto.slice(i); break; }

      const cabecalho = texto.slice(i, abre);
      // fecha o bloco correspondente contando chaves
      let nivel = 1, j = abre + 1;
      while (j < texto.length && nivel > 0) {
        if (texto[j] === '{') nivel++;
        else if (texto[j] === '}') nivel--;
        j++;
      }
      const corpo = texto.slice(abre + 1, j - 1);

      if (/@media|@supports/.test(cabecalho)) {
        saida += cabecalho + '{' + transforma(corpo) + '}';
      } else if (/@/.test(cabecalho)) {
        saida += cabecalho + '{' + corpo + '}';           // keyframes e afins: intocados
      } else {
        // preserva comentarios que vem antes do seletor
        const m = cabecalho.match(/^([\s\S]*?\*\/\s*)?([\s\S]*)$/);
        saida += (m[1] || '') + listaDeSeletores(m[2]) + '{' + corpo + '}';
      }
      i = j;
    }
    return saida;
  };

  return transforma(css);
}

(async () => {
  const html = fs.readFileSync('index.html', 'utf8');
  const usadas = [...new Set([...html.matchAll(/(?:src|href)="(img\/[^"]+)"/g)].map(m => m[1]))];
  if (!usadas.length) { console.log('nenhuma foto relativa encontrada'); return; }

  const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('about:blank');

  let saida = html, total = 0;
  for (const rel of usadas) {
    const b64 = fs.readFileSync(rel).toString('base64');
    const ext = path.extname(rel).slice(1).toLowerCase();
    const mime = ext === 'ico' ? 'image/x-icon' : 'image/' + (ext === 'jpg' ? 'jpeg' : ext);
    const origem = `data:${mime};base64,` + b64;
    // Reencodar um WebP que ja esta no tamanho certo so perde qualidade: nesse
    // caso os bytes originais vao inteiros para o data: URI.
    const uri = await page.evaluate(async ({ origem, largura, q }) => {
      const img = new Image();
      img.src = origem;
      await img.decode();
      if (img.naturalWidth <= largura || !/^data:image\/webp/.test(origem)) return origem;
      // mantem a proporcao original: so as fotos de produto sao quadradas
      const l = largura;
      const a = Math.round(img.naturalHeight * l / img.naturalWidth);
      const c = document.createElement('canvas');
      c.width = l; c.height = a;
      const cx = c.getContext('2d');
      cx.imageSmoothingQuality = 'high';
      cx.drawImage(img, 0, 0, l, a);
      return c.toDataURL('image/webp', q);
    }, { origem, largura: LARGURAS[rel] || LARGURA_PADRAO, q: QUALIDADE });

    for (const attr of ['src', 'href']) {
      saida = saida.split(attr + '="' + rel + '"').join(attr + '="' + uri + '"');
    }
    total += uri.length;
    console.log(path.basename(rel).padEnd(26), (uri.length / 1024).toFixed(0) + ' KB',
                uri === origem ? '(original, sem reencode)' : '');
  }
  await browser.close();

  // --- escopo contra o CSS do tema ---
  const ID = 'mrice';
  // Encaixotar resolve o que a nossa folha declara, mas nao o que ela deixa
  // herdar: o tema crava color e font-family no proprio elemento, e regra
  // direta sempre vence valor herdado. Esta base devolve a heranca com
  // especificidade 1-0-1 — acima do tema, abaixo das nossas regras de classe.
  const BASE_HERANCA = '\n#' + ID + ' :is(h1,h2,h3,h4,h5,h6,p,li,dt,dd,dl,ul,ol,span,a,button,small,strong,b,em,i,figcaption,label){'
    + 'color:inherit;font-family:inherit}\n';
  saida = saida.replace(/<style>([\s\S]*?)<\/style>/, (_, css) =>
    '<style>' + encaixotaCss(css, ID) + BASE_HERANCA + '</style>');
  saida = saida.replace(/<body>/, '<body>\n<div id="' + ID + '">')
               .replace(/<\/body>/, '</div>\n</body>');

  fs.writeFileSync('mr-ice-lp.html', saida);
  console.log('\nmr-ice-lp.html:', (saida.length / 1024 / 1024).toFixed(2), 'MB',
              '(' + usadas.length + ' fotos,', (total / 1024 / 1024).toFixed(2), 'MB embutidos)');
})();
