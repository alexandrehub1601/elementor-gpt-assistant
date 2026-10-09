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

const LADO = 700;      // as artes dos cards tem ~370px no desktop
const QUALIDADE = 0.78;
const CHROME = process.env.CHROME_PATH || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

(async () => {
  const html = fs.readFileSync('index.html', 'utf8');
  const usadas = [...new Set([...html.matchAll(/src="(img\/produtos\/[^"]+)"/g)].map(m => m[1]))];
  if (!usadas.length) { console.log('nenhuma foto relativa encontrada'); return; }

  const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
  const page = await browser.newPage();
  await page.goto('about:blank');

  let saida = html, total = 0;
  for (const rel of usadas) {
    const b64 = fs.readFileSync(rel).toString('base64');
    const uri = await page.evaluate(async ({ origem, lado, q }) => {
      const img = new Image();
      img.src = origem;
      await img.decode();
      const c = document.createElement('canvas');
      c.width = lado; c.height = lado;
      const cx = c.getContext('2d');
      cx.imageSmoothingQuality = 'high';
      cx.drawImage(img, 0, 0, lado, lado);
      return c.toDataURL('image/webp', q);
    }, { origem: 'data:image/webp;base64,' + b64, lado: LADO, q: QUALIDADE });

    saida = saida.split('src="' + rel + '"').join('src="' + uri + '"');
    total += uri.length;
    console.log(path.basename(rel).padEnd(26), (uri.length / 1024).toFixed(0) + ' KB');
  }
  await browser.close();

  fs.writeFileSync('mr-ice-lp.html', saida);
  console.log('\nmr-ice-lp.html:', (saida.length / 1024 / 1024).toFixed(2), 'MB',
              '(' + usadas.length + ' fotos,', (total / 1024 / 1024).toFixed(2), 'MB embutidos)');
})();
