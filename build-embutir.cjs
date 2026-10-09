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

(async () => {
  const html = fs.readFileSync('index.html', 'utf8');
  const usadas = [...new Set([...html.matchAll(/src="(img\/[^"]+)"/g)].map(m => m[1]))];
  if (!usadas.length) { console.log('nenhuma foto relativa encontrada'); return; }

  const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('about:blank');

  let saida = html, total = 0;
  for (const rel of usadas) {
    const b64 = fs.readFileSync(rel).toString('base64');
    const origem = 'data:image/webp;base64,' + b64;
    // Reencodar um WebP que ja esta no tamanho certo so perde qualidade: nesse
    // caso os bytes originais vao inteiros para o data: URI.
    const uri = await page.evaluate(async ({ origem, largura, q }) => {
      const img = new Image();
      img.src = origem;
      await img.decode();
      if (img.naturalWidth <= largura) return origem;
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

    saida = saida.split('src="' + rel + '"').join('src="' + uri + '"');
    total += uri.length;
    console.log(path.basename(rel).padEnd(26), (uri.length / 1024).toFixed(0) + ' KB',
                uri === origem ? '(original, sem reencode)' : '');
  }
  await browser.close();

  fs.writeFileSync('mr-ice-lp.html', saida);
  console.log('\nmr-ice-lp.html:', (saida.length / 1024 / 1024).toFixed(2), 'MB',
              '(' + usadas.length + ' fotos,', (total / 1024 / 1024).toFixed(2), 'MB embutidos)');
})();
