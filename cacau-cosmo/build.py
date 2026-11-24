#!/usr/bin/env python3
"""Monta as páginas HTML e os templates JSON do Elementor da Cacau Cosmo.

Uso:  python3 cacau-cosmo/build.py
Saída: cacau-cosmo/paginas/*.html  e  cacau-cosmo/elementor/*.json
"""
import hashlib
import json
import urllib.parse
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SRC = ROOT / "src"
SHARED = SRC / "shared"


def read(p):
    return (p if isinstance(p, Path) else SRC / p).read_text(encoding="utf-8")


HEAD = read(SHARED / "head.html")
HEADER = read(SHARED / "header.html")
FOOTER = read(SHARED / "footer.html")
BASE_JS = read(SHARED / "base.js")


# ---------------------------------------------------------------- páginas
def build_page(slug):
    d = SRC / "pages" / slug
    meta = json.loads(read(d / "meta.json"))
    style = read(d / "style.css")
    body = read(d / "body.html")
    dict_js = read(d / "dict.js") if (d / "dict.js").exists() else ""
    script = read(d / "script.js") if (d / "script.js").exists() else ""
    html = f"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{meta['title']}</title>
<meta name="description" content="{meta['description']}">
<meta property="og:title" content="{meta['title']}">
<meta property="og:description" content="{meta['description']}">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:url" content="{meta['url']}">
<meta property="og:image" content="https://cacaucosmo.com.br/wp-content/uploads/2026/08/mark-bege-transparente.png">
<meta name="theme-color" content="#00062E">

<!--
=====================================================================
CACAU COSMO — {meta['title'].split(' — ')[0].upper()}
Cole este arquivo inteiro num widget HTML do Elementor, numa página com
layout "Elementor Canvas" (igual à Home). Header, menu e rodapé já
estão incluídos. Fotos: procure por [ TROCAR FOTO ].
=====================================================================
-->
{HEAD}
<style>
{style}</style>
</head>

<body>
{HEADER}

<div class="cc">
<main>
{body}
</main>
</div>

{FOOTER}
<script>
{dict_js}</script>
{BASE_JS}{('<script>' + chr(10) + script + '</script>' + chr(10)) if script else ''}</body>
</html>
"""
    out = ROOT / "paginas" / f"{slug}.html"
    out.write_text(html, encoding="utf-8")
    return out


# ---------------------------------------------------------------- elementor
def make_id(seed):
    return hashlib.md5(seed.encode()).hexdigest()[:7]


class Tpl:
    """Gera a árvore de elementos com IDs estáveis."""

    def __init__(self, name):
        self.name = name
        self.n = 0

    def _id(self):
        self.n += 1
        return make_id(f"{self.name}-{self.n}")

    def con(self, classes, children, direction="column", **extra):
        settings = {
            "content_width": "full",
            "flex_direction": direction,
            "flex_wrap": "nowrap",
            "flex_gap": {"unit": "px", "size": 0, "column": "0", "row": "0", "isLinked": True},
            "padding": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
            "margin": {"unit": "px", "top": "0", "right": "0", "bottom": "0", "left": "0", "isLinked": True},
            "css_classes": classes,
        }
        settings.update(extra)
        return {"id": self._id(), "elType": "container", "isInner": False,
                "settings": settings, "elements": children}

    def w(self, widget, settings=None, classes=""):
        s = dict(settings or {})
        if classes:
            s["_css_classes"] = classes
        return {"id": self._id(), "elType": "widget", "widgetType": widget,
                "settings": s, "elements": []}

    def html(self, code, classes=""):
        return self.w("html", {"html": code}, classes)

    def tag(self, name, settings=None):
        enc = urllib.parse.quote(json.dumps(settings or {}, separators=(",", ":")))
        return f'[elementor-tag id="{self._id()}" name="{name}" settings="{enc}"]'

    def dyn_heading(self, tag_name, tag_settings=None, size="p", classes=""):
        return self.w("heading", {
            "title": "",
            "header_size": size,
            "__dynamic__": {"title": self.tag(tag_name, tag_settings)},
        }, classes)


def tpl_header(extra_css):
    css = read("elementor/base.css") + "\n" + extra_css
    return (HEAD + f"\n<style>\n{css}</style>\n" + HEADER +
            "\n<script>document.body.classList.add('cc-tpl');</script>\n")


def tpl_footer(extra_js=""):
    return FOOTER + "\n" + BASE_JS + (f"<script>\n{extra_js}</script>\n" if extra_js else "")


DECO = ('<div class="cc-deco__in"><canvas class="stars" data-density="4600" aria-hidden="true"></canvas>'
        '<div class="orb" aria-hidden="true"></div></div>')


def export(name, title, doc_type, content):
    data = {
        "version": "0.4",
        "title": title,
        "type": doc_type,
        "page_settings": {"template": "elementor_canvas", "page_template": "elementor_canvas", "hide_title": "yes"},
        "content": content,
    }
    out = ROOT / "elementor" / f"{name}.json"
    out.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")
    return out


# ---- LOJA ------------------------------------------------------------
TRUST_HTML = """<!-- [ REVISAR ] confirme estas promessas antes de publicar -->
<section class="cc-trust">
  <div class="cc-trust__grid">
    <div class="cc-trust__item">
      <svg viewBox="0 0 260 150"><use href="#art-bar"></use></svg>
      <div><h3 data-i18n="t-trust1-h">Pequenos lotes</h3><p data-i18n="t-trust1-p">Nibs to bar, feito à mão. Quando um lote acaba, acaba.</p></div>
    </div>
    <div class="cc-trust__item">
      <svg viewBox="0 0 200 320"><use href="#art-pod"></use></svg>
      <div><h3 data-i18n="t-trust2-h">Cacau de origem</h3><p data-i18n="t-trust2-p">Respeitando pessoas, territórios e o tempo da natureza.</p></div>
    </div>
    <div class="cc-trust__item">
      <svg viewBox="0 0 200 200"><use href="#art-orbit"></use></svg>
      <div><h3 data-i18n="t-trust3-h">Envio cuidadoso</h3><p data-i18n="t-trust3-p">Embalagem pensada para o chocolate chegar inteiro, em todo o Brasil.</p></div>
    </div>
  </div>
</section>"""

CIRCLE_HTML = """<section class="cc-circle cc-band">
  <canvas class="stars" data-density="5600" aria-hidden="true"></canvas>
  <p class="label" style="font-family:var(--font-mono);font-size:clamp(9px,.66vw,11px);letter-spacing:.36em;text-transform:uppercase;font-weight:600;color:var(--cc-gold)" data-i18n="t-circ-label">Journal</p>
  <h2 data-i18n="t-circ-h2">Cartas do Cosmo.</h2>
  <p data-i18n="t-circ-p">Rituais, origem e o tempo do cacau, escritos para quem já está no Círculo.</p>
  <a class="btn btn--pink" href="/journal/" data-i18n="t-circ-cta">Ler o Journal</a>
</section>"""

FEATURE_HTML = """<section class="cc-feature">
  <div class="cc-feature__media">
    <!-- [ TROCAR FOTO ] <img src="https://cacaucosmo.com.br/wp-content/uploads/2026/08/barra.jpg" alt="Barras de chocolate quebradas" loading="lazy"> -->
    <svg viewBox="0 0 260 150"><use href="#art-bar"></use></svg>
  </div>
  <div class="cc-feature__body">
    <p class="label" style="color:var(--cc-brown)" data-i18n="t-feat-label">Nibs to bar</p>
    <h2 data-i18n="t-feat-h2">O tempo é ingrediente.</h2>
    <p data-i18n="t-feat-p">Partimos dos nibs de cacau e fazemos cada barra à mão, em pequenos lotes, no tempo que o chocolate pede. Cada barra carrega o tempo que levou para existir.</p>
    <a class="cta" href="/quem-somos/" data-i18n="t-feat-cta">Conheça a Cacau Cosmo &mdash;</a>
  </div>
</section>"""

SHOP_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-shop-label': 'Cacaucosmo / Shop', 't-shop-h1': 'Small universes.', 't-shop-sub': 'Origin chocolate, made in nature\\u2019s own time.',
  't-shop-nota': 'Limited runs. Each batch, a new chapter.',
  't-trust1-h': 'Small batches', 't-trust1-p': 'Nibs to bar, handmade. When a batch is gone, it\\u2019s gone.',
  't-trust2-h': 'Origin cacao', 't-trust2-p': 'Respecting people, territories and nature\\u2019s own time.',
  't-trust3-h': 'Careful shipping', 't-trust3-p': 'Packaging designed so the chocolate arrives whole, anywhere in Brazil.',
  't-feat-label': 'Nibs to bar', 't-feat-h2': 'Time is an ingredient.',
  't-feat-p': 'We start from cacao nibs and make every bar by hand, in small batches, at the pace chocolate asks for. Every bar carries the time it took to exist.',
  't-feat-cta': 'Meet Cacau Cosmo &mdash;',
  't-circ-label': 'Journal', 't-circ-h2': 'Letters from the Cosmos.',
  't-circ-p': 'Rituals, origin and the time of cacao, written for those already in the Circle.', 't-circ-cta': 'Read the Journal'
});
"""


WOO_CARDS_JS = """/* ===== CARTÕES DE PRODUTO ================================= */
(function(){
  var en=document.documentElement.lang==='en';
  document.querySelectorAll('.cc-root ul.products li.product').forEach(function(li){
    var img=li.querySelector('a img');
    if(img&&!img.closest('.cc-imgbox')){
      /* imagem nítida: usa o arquivo original em vez da miniatura de 300px */
      var src=(img.getAttribute('data-src')||img.getAttribute('src')||'').replace(/-\\d+x\\d+(?=\\.(jpe?g|png|webp)$)/i,'');
      if(src&&src.indexOf('placeholder')<0){img.removeAttribute('srcset');img.removeAttribute('sizes');img.src=src;}
      img.loading='lazy';
      var box=document.createElement('span'); box.className='cc-imgbox';
      img.parentNode.insertBefore(box,img); box.appendChild(img);
    }
    var b=li.querySelector('.button.add_to_cart_button');
    if(b&&/carrinho|cart/i.test(b.textContent)) b.textContent=en?'Add':'Adicionar';
    if(li.classList.contains('outofstock')){ var v=li.querySelector('a.button'); if(v) v.textContent=en?'Unavailable':'Indisponível'; }
  });
})();
"""


def build_loja():
    t = Tpl("loja")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/loja.css")), "cc-header"),
        t.con("phero cc-shop-hero", [
            t.html(DECO, "cc-deco"),
            t.html('<p class="label" data-i18n="t-shop-label">Cacaucosmo / Loja</p>', "cc-label"),
            t.html('<h1 class="cc-shop-h1" data-i18n="t-shop-h1">Pequenos universos.</h1>'),
            t.html('<p class="cc-hero-sub" data-i18n="t-shop-sub">Chocolate de origem, feito no tempo da natureza.</p>'
                   '<p class="cc-shop-nota" data-i18n="t-shop-nota">Produções limitadas. Cada lote, um novo capítulo.</p>'),
        ]),
        t.con("cc-shop-main", [
            t.w("woocommerce-notices"),
            # vitrine: todos os produtos publicados, na ordem do campo Posição (Sol, Lua, Saturno)
            # vitrine: um card por astro (o de 100 g); na página do produto escolhe-se 33 g ou 100 g
            t.w("shortcode", {"shortcode": '[products ids="900120,900122,900124" orderby="post__in" columns="3"]'}),
        ]),
        t.html(TRUST_HTML),
        t.html(FEATURE_HTML),
        t.html(CIRCLE_HTML),
        t.html(tpl_footer(SHOP_DICT + WOO_CARDS_JS + SHOP_TRANSP_JS), "cc-footer"),
    ])]
    # versão "página": entra por qualquer importador e depois é inserida no
    # template de Arquivo de produtos pela biblioteca (Meus modelos → Inserir)
    export("loja-woocommerce-alternativo", "Cacau Cosmo — Loja (inserir no Arquivo de produtos)", "page", content)
    return export("loja-woocommerce", "Cacau Cosmo — Loja (Arquivo de produtos)", "product-archive", content)


# Só na loja: os cartões trocam a foto pela caixa sem fundo (cc-card-<astro>-<peso>.png
# na Biblioteca de Mídia). A página do produto continua com a foto de fundo azul.
SHOP_TRANSP_JS = r"""/* ===== LOJA: UM CARTÃO POR ASTRO, SEM PREÇO =============== */
(function(){
  var en=document.documentElement.lang==='en';
  document.querySelectorAll('.cc-shop-main ul.products li.product').forEach(function(li){
    var link=li.querySelector('a.woocommerce-LoopProduct-link'), h=li.querySelector('.woocommerce-loop-product__title');
    if(h&&!h.dataset.cc){ h.dataset.cc=1;
      h.textContent=h.textContent.split('·')[0].trim();                       /* "Saturno 47% · 100 g" -> "Saturno 47%" */
      h.insertAdjacentHTML('afterend','<p class="cc-tamanhos">33 g &middot; 100 g</p>');
    }
    var b=li.querySelector('a.button');
    if(b&&link){
      b.href=link.href; b.className='button cc-escolher'; ['data-product_id','data-product_sku','data-quantity','aria-describedby','rel'].forEach(function(k){b.removeAttribute(k)});
      b.textContent=li.classList.contains('outofstock')?(en?'Unavailable':'Indisponível'):(en?'Choose size':'Escolher tamanho');
    }
  });
})();
/* ===== LOJA: CAIXAS SEM FUNDO NOS CARTÕES ================ */
(function(){
  var cards=[].slice.call(document.querySelectorAll('.cc-shop-main ul.products li.product'));
  if(!cards.length)return;
  var grade=cards[0].parentNode, feito=false;
  function pronto(){if(feito)return;feito=true;grade.classList.add('cc-fotos-ok')}
  setTimeout(pronto,2500);
  if(!window.fetch){pronto();return}
  fetch('/wp-json/wp/v2/media?search=cc-card-&per_page=30&_fields=source_url',{credentials:'same-origin'})
    .then(function(r){return r.ok?r.json():[]})
    .then(function(lista){
      var mapa={};
      (lista||[]).forEach(function(m){
        var u=m.source_url||'', k=u.match(/cc-card-(lua|sol|saturno)-(33|100)(?:-\d+)?\.png$/i);
        if(k){var c=(k[1]+k[2]).toLowerCase(); if(!mapa[c]) mapa[c]=u;}
      });
      cards.forEach(function(li){
        var a=li.querySelector('a.woocommerce-LoopProduct-link')||li.querySelector('a');
        var h=(a&&a.getAttribute('href')||'').toLowerCase();
        var k=h.match(/\/(lua|sol|saturno)-\d+-(33|100)g\/?(?:[?#].*)?$/);
        if(!k){var t=(li.textContent||'').toLowerCase(), as=t.match(/\b(lua|sol|saturno)\b/), ps=t.match(/\b(33|100)\s?g\b/); if(as&&ps)k=[0,as[1],ps[1]];}
        var u=k&&mapa[k[1]+k[2]], img=li.querySelector('.cc-imgbox img, a img');
        if(!u||!img)return;
        img.removeAttribute('srcset'); img.removeAttribute('sizes'); img.removeAttribute('data-src');
        img.src=u; li.classList.add('cc-transp');
      });
    }).catch(function(){}).then(pronto);
})();
"""


# ---- PRODUTO ---------------------------------------------------------
PERKS_HTML = """<!-- [ REVISAR ] ajuste os selos para cada produto, se quiser -->
<ul class="cc-perks">
  <li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span><b data-i18n="t-perk1-h">Nibs to bar</b><span data-i18n="t-perk1-p">Feito à mão, em pequenos lotes.</span></span></li>
  <li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span><b data-i18n="t-perk2-h">Envio para todo o Brasil</b><span data-i18n="t-perk2-p">Frete calculado no carrinho pelo CEP.</span></span></li>
  <li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span><b data-i18n="t-perk3-h">Como guardar</b><span data-i18n="t-perk3-p">Local fresco e seco, entre 16 e 20 °C. Evite a geladeira.</span></span></li>
</ul>"""

RITUAL_HTML = """<section class="cc-ritual cc-band">
  <canvas class="stars" data-density="6000" aria-hidden="true"></canvas>
  <div class="cc-ritual__in">
    <div class="cc-ritual__head">
      <p class="label" style="font-family:var(--font-mono);font-size:clamp(9px,.66vw,11px);letter-spacing:.36em;text-transform:uppercase;font-weight:600;color:var(--cc-gold)" data-i18n="t-rit-label">Ritual de degustação</p>
      <h2 data-i18n="t-rit-h2">Devagar. É assim que se prova.</h2>
    </div>
    <ol class="cc-ritual__grid">
      <li><b>01</b><h3 data-i18n="t-rit1-h">Observe</h3><p data-i18n="t-rit1-p">O brilho, a cor, a superfície lisa. A temperagem aparece aqui.</p></li>
      <li><b>02</b><h3 data-i18n="t-rit2-h">Quebre</h3><p data-i18n="t-rit2-p">Ouça o estalo. Seco e limpo, como deve ser.</p></li>
      <li><b>03</b><h3 data-i18n="t-rit3-h">Respire</h3><p data-i18n="t-rit3-p">Aproxime do nariz. Fruta, terra, flor &mdash; o que chega primeiro?</p></li>
      <li><b>04</b><h3 data-i18n="t-rit4-h">Deixe derreter</h3><p data-i18n="t-rit4-p">Sem morder. Deixe a língua fazer o trabalho e o sabor se abrir.</p></li>
    </ol>
  </div>
</section>"""

PROD_JS = """/* ===== PRODUTO INDISPONÍVEL =============================== */
(function(){
  var en=document.documentElement.lang==='en';
  document.querySelectorAll('.cc-prod-info .stock.out-of-stock').forEach(function(s){
    s.textContent=en?'Unavailable for now':'Indisponível no momento';
    if(!document.querySelector('.cc-avise')) s.insertAdjacentHTML('afterend','<p class="cc-avise">'+(en?'This bar will be back soon. ':'Esta barra volta em breve. ')+
      '<a href="/contato/">'+(en?'Ask us to let you know':'Peça para avisarmos')+'</a>.</p>');
  });
})();
"""

PROD_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-perk1-h': 'Nibs to bar', 't-perk1-p': 'Handmade, in small batches.',
  't-perk2-h': 'Shipping across Brazil', 't-perk2-p': 'Shipping calculated in the cart by postcode.',
  't-perk3-h': 'How to store', 't-perk3-p': 'Cool and dry, between 16 and 20 °C. Avoid the fridge.',
  't-rit-label': 'Tasting ritual', 't-rit-h2': 'Slowly. That\\u2019s how you taste.',
  't-rit1-h': 'Look', 't-rit1-p': 'The shine, the colour, the smooth surface. Tempering shows here.',
  't-rit2-h': 'Snap', 't-rit2-p': 'Listen to the snap. Dry and clean, as it should be.',
  't-rit3-h': 'Breathe', 't-rit3-p': 'Bring it close. Fruit, earth, flower &mdash; what comes first?',
  't-rit4-h': 'Let it melt', 't-rit4-p': 'Don\\u2019t bite. Let your tongue do the work and the flavour open up.'
});
"""


# ---- PRODUTO EM 3D (céu e caixa de cada astro) -----------------------
SKY_HTML = '<div class="cc-sky__in"><canvas class="cc-sky__c" aria-hidden="true"></canvas></div>'

VIEWER_HTML = """<div class="cc3d" id="cc3d" aria-label="Caixa em 3D">
  <div class="cc3d__stage" id="cc3dStage">
    <div class="cc3d__shadow" aria-hidden="true"></div>
    <div class="cc3d__box" id="cc3dBox">
      <div class="cc3d__f fr" data-k="frente"></div><div class="cc3d__f bk" data-k="verso"></div>
      <div class="cc3d__f lf" data-k="esquerda"></div><div class="cc3d__f rt" data-k="direita"></div>
      <div class="cc3d__f tp" data-k="topo"></div><div class="cc3d__f bt" data-k="base"></div>
    </div>
  </div>
  <div class="cc3d__inside" id="cc3dInside" hidden role="img"></div>
  <p class="cc3d__hint" id="cc3dHint" data-i18n="t-3d-hint">Arraste a caixa para girar</p>
  <div class="cc3d__views" role="group" aria-label="Vistas da caixa">
    <button type="button" data-v="front" aria-pressed="true" data-i18n="t-3d-fr">Frente</button>
    <button type="button" data-v="left" data-i18n="t-3d-lf">Lateral</button>
    <button type="button" data-v="back" data-i18n="t-3d-bk">Verso</button>
    <button type="button" data-v="right" data-i18n="t-3d-rt">Outro lado</button>
    <button type="button" data-v="top" data-i18n="t-3d-tp">Topo</button>
    <button type="button" data-v="inside" data-i18n="t-3d-in">Por dentro</button>
  </div>
</div>"""

ESCOLHA_HTML = """<div class="cc-escolha" id="ccEscolha" hidden>
  <div class="cc-escolha__linha"><span data-i18n="t-3d-astro">Astro</span>
    <a data-a="lua" href="#">Lua</a><a data-a="sol" href="#">Sol</a><a data-a="saturno" href="#">Saturno</a></div>
  <div class="cc-escolha__linha"><span data-i18n="t-3d-peso">Tamanho</span>
    <a data-p="33" href="#">33 g</a><a data-p="100" href="#">100 g</a></div>
</div>"""

PROD_3D_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-3d-hint': 'Drag the box to rotate', 't-3d-fr': 'Front', 't-3d-lf': 'Side', 't-3d-bk': 'Back', 't-3d-rt': 'Other side',
  't-3d-tp': 'Top', 't-3d-in': 'Inside', 't-3d-astro': 'Star', 't-3d-peso': 'Size'
});
/* ===== PRODUTO NO CÉU DO ASTRO + CAIXA 3D ==================
   Astro e tamanho vêm do endereço (/produto/lua-100-33g/) ou do título.
   As faces da caixa são as imagens "cc3d-<astro>-<face>" da Biblioteca de Mídia. */
(function(){
  var prod=document.querySelector('.cc-prod'); if(!prod) return;
  var ASTROS={
    lua:{pct:'100',sky:'#030303',glow:'#1c1c22'},
    sol:{pct:'70',sky:'#3a2108',glow:'#7a4a14',esgotado:true},
    saturno:{pct:'47',sky:'#00062E',glow:'#0d1450'}
  };
  var m=location.pathname.match(/\\/(lua|sol|saturno)-\\d+-(33|100)g\\/?$/), astro, peso;
  if(m){ astro=m[1]; peso=m[2]; }
  else{
    var tt=((document.querySelector('.product_title')||{}).textContent||'').toLowerCase();
    astro=/\\blua\\b/.test(tt)?'lua':/\\bsol\\b/.test(tt)?'sol':/saturno/.test(tt)?'saturno':null;
    peso=/100\\s*g/.test(tt)?'100':'33';
  }
  if(!astro) return;
  var A=ASTROS[astro];
  document.body.classList.add('cc-astro-'+astro,'cc-p'+peso);

  /* céu estrelado */
  var c=prod.querySelector('.cc-sky__c');
  function ceu(){
    if(!c) return; var r=prod.getBoundingClientRect(), d=Math.min(devicePixelRatio||1,2), x=c.getContext('2d');
    c.width=r.width*d; c.height=r.height*d; x.setTransform(d,0,0,d,0,0); x.clearRect(0,0,r.width,r.height);
    var s=7, rnd=function(){ return (s=(s*16807)%2147483647)/2147483647; };
    for(var i=0;i<r.width*r.height/2600;i++){ x.globalAlpha=.25+rnd()*.65; x.fillStyle=rnd()>.85?'#C9A86A':'#EFE6D2';
      x.beginPath(); x.arc(rnd()*r.width,rnd()*r.height,rnd()*1.3+.3,0,7); x.fill(); }
    x.globalAlpha=1;
  }
  ceu(); addEventListener('load',ceu); var to; addEventListener('resize',function(){ clearTimeout(to); to=setTimeout(ceu,200); });

  /* trocar de astro e de tamanho */
  var esc=document.getElementById('ccEscolha');
  if(esc){
    var info=prod.querySelector('.cc-prod-info .product_title');
    var alvo=info&&info.closest('.elementor-element'); if(alvo) alvo.after(esc.closest('.elementor-element')||esc);
    esc.hidden=false;
    esc.querySelectorAll('[data-a]').forEach(function(a){ var k=a.dataset.a;
      a.href='/produto/'+k+'-'+ASTROS[k].pct+'-'+peso+'g/'; a.setAttribute('aria-current',String(k===astro)); if(ASTROS[k].esgotado) a.classList.add('esgotado'); });
    esc.querySelectorAll('[data-p]').forEach(function(a){ var p=a.dataset.p;
      a.href='/produto/'+astro+'-'+A.pct+'-'+p+'g/'; a.setAttribute('aria-current',String(p===peso)); });
  }

  /* caixa 3D */
  var viewer=document.getElementById('cc3d'); if(!viewer||!window.fetch) return;
  var box=document.getElementById('cc3dBox'), stage=document.getElementById('cc3dStage'),
      inside=document.getElementById('cc3dInside'), hint=document.getElementById('cc3dHint');
  var FACES=['frente','verso','esquerda','direita','topo','base','tampa'];
  fetch('/wp-json/wp/v2/media?search=cc3d-'+astro+'&per_page=30&_fields=source_url',{credentials:'same-origin'})
    .then(function(r){ return r.ok?r.json():[]; })
    .then(function(lista){
      var tex={};
      (lista||[]).forEach(function(m){
        var f=(m.source_url||'').split('/').pop().match(new RegExp('^cc3d-'+astro+'-(frente|verso|esquerda|direita|topo|base|tampa)(?:-\\\\d+)?\\\\.(jpe?g|png|webp)$','i'));
        if(f&&!tex[f[1]]) tex[f[1]]=m.source_url;
      });
      if(FACES.slice(0,6).some(function(k){ return !tex[k]; })) return;      /* faltam faces: fica a galeria */
      box.querySelectorAll('.cc3d__f').forEach(function(f){ f.style.backgroundImage='url('+tex[f.dataset.k]+')'; });
      if(tex.tampa) inside.style.backgroundImage='url('+tex.tampa+')';
      else viewer.querySelector('[data-v="inside"]').remove();
      viewer.classList.add('on');
    }).catch(function(){});

  var V={front:[-14,-28],left:[-10,62],back:[-14,152],right:[-10,-118],top:[-58,-24]}, rx=-14, ry=-28, drag=null;
  function aplica(){ box.style.transform='rotateX('+rx+'deg) rotateY('+ry+'deg)'; }
  function marca(v){ viewer.querySelectorAll('[data-v]').forEach(function(b){ b.setAttribute('aria-pressed',String(b.dataset.v===v)); }); }
  function vista(v){
    marca(v); var dentro=v==='inside'; inside.hidden=!dentro; stage.hidden=dentro; hint.hidden=dentro;
    if(!dentro){ rx=V[v][0]; ry=V[v][1]; aplica(); }
  }
  viewer.querySelectorAll('[data-v]').forEach(function(b){ b.addEventListener('click',function(){ vista(b.dataset.v); }); });
  stage.addEventListener('pointerdown',function(e){ drag={x:e.clientX,y:e.clientY,rx:rx,ry:ry}; box.classList.add('drag'); stage.setPointerCapture(e.pointerId); });
  stage.addEventListener('pointermove',function(e){ if(!drag) return; ry=drag.ry+(e.clientX-drag.x)*.5;
    rx=Math.max(-80,Math.min(80,drag.rx-(e.clientY-drag.y)*.4)); aplica(); });
  function solta(){ if(!drag) return; drag=null; box.classList.remove('drag'); marca(''); }
  stage.addEventListener('pointerup',solta); stage.addEventListener('pointercancel',solta);
  aplica();
})();
"""


def build_produto():
    t = Tpl("produto")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/produto.css") + "\n" + read("elementor/produto-3d.css")), "cc-header"),
        t.con("cc-prod", [
            t.html(SKY_HTML, "cc-sky"),
            t.con("cc-crumbs", [t.w("woocommerce-breadcrumb")]),
            t.w("woocommerce-notices"),
            t.con("cc-prod-grid", [
                t.con("cc-prod-gallery", [
                    t.html(VIEWER_HTML, "cc-3d"),
                    t.w("woocommerce-product-images", {"sale_flash": "yes"}),
                ]),
                t.con("cc-prod-info", [
                    t.dyn_heading("post-terms", {"taxonomy": "product_cat", "separator": " · ", "link": ""}, "p", "cc-label"),
                    t.w("woocommerce-product-title", {"header_size": "h1"}),
                    t.html(ESCOLHA_HTML, "cc-escolha-w"),
                    t.w("woocommerce-product-rating"),
                    t.w("woocommerce-product-price"),
                    t.w("woocommerce-product-short-description"),
                    t.w("woocommerce-product-stock"),
                    t.w("woocommerce-product-add-to-cart", {"layout": "", "show_quantity": "yes"}),
                    t.html(PERKS_HTML),
                    t.w("woocommerce-product-meta"),
                ]),
            ], direction="row"),
        ]),
        t.con("cc-tabs", [t.w("woocommerce-product-data-tabs")]),
        t.html(RITUAL_HTML),
        t.con("cc-related", [
            t.w("woocommerce-product-upsell", {"columns": "4", "orderby": "date", "order": "desc"}),
            t.w("woocommerce-product-related", {"posts_per_page": 4, "columns": "4", "orderby": "rand", "order": "desc"}),
        ]),
        t.html(tpl_footer(PROD_DICT + WOO_CARDS_JS + PROD_JS + PROD_3D_JS), "cc-footer"),
    ])]
    return export("produto-woocommerce", "Cacau Cosmo — Produto (Single Product)", "product", content)


# ---- CARRINHO --------------------------------------------------------
CART_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-cart-label': 'Cart', 't-cart-h1': 'Your small <span class="em">universe.</span>',
  't-cart-sub': 'Review your bars, apply a coupon and calculate shipping before checkout.',
  't-trust1-h': 'Small batches', 't-trust1-p': 'Nibs to bar, handmade. When a batch is gone, it\\u2019s gone.',
  't-trust2-h': 'Origin cacao', 't-trust2-p': 'Respecting people, territories and nature\\u2019s own time.',
  't-trust3-h': 'Careful shipping', 't-trust3-p': 'Packaging designed so the chocolate arrives whole, anywhere in Brazil.'
});
/* ===== CARRINHO =========================================== */
(function(){
  var root=document.querySelector('.cc-cart'); if(!root) return;
  var en=document.documentElement.lang==='en';
  var t=function(pt,e){return en?e:pt};
  var timer;

  function update(){
    var b=root.querySelector('button[name="update_cart"]'); if(!b) return;
    b.disabled=false; b.removeAttribute('aria-disabled');
    clearTimeout(timer); timer=setTimeout(function(){ b.click(); },650);
  }

  function enhance(){
    /* - e + na quantidade (só quando o carrinho permite editar) */
    root.querySelectorAll('.woocommerce-cart-form .quantity').forEach(function(q){
      var input=q.querySelector('input.qty');
      if(!input||input.type==='hidden'||q.querySelector('.cc-qbtn')) return;
      function btn(txt,label,delta){
        var b=document.createElement('button'); b.type='button'; b.className='cc-qbtn'; b.textContent=txt; b.setAttribute('aria-label',label);
        b.addEventListener('click',function(){
          var step=+input.step||1, min=input.min!==''?+input.min:0, max=input.max!==''?+input.max:Infinity;
          var v=Math.min(max,Math.max(min,(+input.value||0)+delta*step));
          if(v!==+input.value){ input.value=v; input.dispatchEvent(new Event('change',{bubbles:true})); update(); }
        });
        return b;
      }
      q.insertBefore(btn('\\u2212',t('Diminuir','Decrease'),-1),input);
      q.appendChild(btn('+',t('Aumentar','Increase'),1));
      input.addEventListener('change',update);
    });
    var cc=root.querySelector('#coupon_code'); if(cc) cc.placeholder=t('Cupom de desconto','Coupon code');

    /* selos abaixo do botão de finalizar */
    var go=root.querySelector('.wc-proceed-to-checkout');
    if(go&&!root.querySelector('.cc-cart-safe')){
      go.insertAdjacentHTML('afterend',
        '<p class="cc-cart-safe"><svg viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M6 10V7a6 6 0 0 1 12 0v3M5 10h14v11H5z"/></svg>'+
        t('Pagamento seguro','Secure checkout')+'</p>'+
        /* [ REVISAR ] confirme estas promessas */
        '<ul class="cc-cart-perks">'+
        '<li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span>'+t('Frete e prazo calculados pelo seu CEP.','Shipping cost and time calculated by postcode.')+'</span></li>'+
        '<li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span>'+t('Despacho em até 2 dias úteis, com embalagem que protege o chocolate.','Dispatched within 2 business days, in packaging that protects the chocolate.')+'</span></li>'+
        '<li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span>'+t('Dúvidas? <a href="/contato/" style="text-decoration:underline">Fale com a gente</a>.','Questions? <a href="/contato/" style="text-decoration:underline">Talk to us</a>.')+'</span></li>'+
        '</ul>');
    }
    var back=root.querySelector('p.return-to-shop .button'); if(back) back.textContent=t('Conhecer os chocolates','Discover the chocolates');
    root.classList.add('cc-autoupdate');
  }
  enhance();
  if(window.jQuery) jQuery(document.body).on('updated_wc_div updated_cart_totals updated_shipping_method',enhance);
})();
"""


def build_carrinho():
    t = Tpl("carrinho")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/carrinho.css")), "cc-header"),
        t.con("phero cc-cart-hero", [
            t.html(DECO, "cc-deco"),
            t.html('<p class="label" data-i18n="t-cart-label">Carrinho</p>', "cc-label"),
            t.html('<h1 class="display" style="font-size:clamp(34px,5vw,80px);line-height:1.08;color:var(--cc-cream);text-align:center;margin-top:14px" '
                   'data-i18n="t-cart-h1">Seu pequeno <span class="em">universo.</span></h1>'),
            t.html('<p class="cc-hero-sub" data-i18n="t-cart-sub">Revise suas barras, aplique um cupom e calcule o frete antes de finalizar.</p>'),
        ]),
        t.con("cc-cart", [
            t.w("shortcode", {"shortcode": "[woocommerce_cart]"}),
        ]),
        t.con("cc-cart-more", [
            t.w("heading", {"title": "Complete o ritual", "header_size": "h2"}, "cc-more-title"),
            t.w("shortcode", {"shortcode": '[products limit="12" columns="3" orderby="menu_order" order="ASC"]'}),
        ]),
        t.html(TRUST_HTML),
        t.html(tpl_footer(CART_JS + WOO_CARDS_JS), "cc-footer"),
    ])]
    return export("carrinho-woocommerce", "Cacau Cosmo — Carrinho", "page", content)


# ---- FINALIZAR COMPRA ------------------------------------------------
CHECKOUT_HERO = """<p class="label" data-i18n="t-co-label">Finalizar compra</p>
<h1 class="display js-co-h1" style="font-size:clamp(34px,5vw,80px);line-height:1.08;color:var(--cc-cream);text-align:center;margin-top:14px">Quase <span class="em">lá.</span></h1>
<p class="cc-hero-sub js-co-sub">Preencha seus dados e escolha a entrega. Seu chocolate já está separado.</p>
<nav class="cc-steps js-co-steps" aria-label="Etapas">
  <span class="ok"><i>✓</i><a href="/carrinho/"><em data-i18n="t-co-s1">Carrinho</em></a></span><b></b>
  <span class="on"><i>2</i><em data-i18n="t-co-s2">Dados e entrega</em></span><b></b>
  <span><i>3</i><em data-i18n="t-co-s3">Confirmação</em></span>
</nav>"""

CHECKOUT_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-co-label': 'Checkout', 't-co-s1': 'Cart', 't-co-s2': 'Details &amp; shipping', 't-co-s3': 'Confirmation'
});
/* ===== FINALIZAR COMPRA =================================== */
(function(){
  var root=document.querySelector('.cc-co'); if(!root) return;
  var en=document.documentElement.lang==='en';
  var t=function(pt,e){return en?e:pt};
  var h1=document.querySelector('.js-co-h1'), sub=document.querySelector('.js-co-sub');

  /* pedido recebido: muda o topo */
  if(root.querySelector('.woocommerce-order')||document.body.classList.contains('woocommerce-order-received')){
    var pix=root.querySelector('.mp-details-pix'), st=document.querySelectorAll('.js-co-steps span');
    if(pix){
      /* Pix ainda não pago: o foco é pagar */
      if(h1) h1.innerHTML=t('Pedido <span class="em">recebido.</span>','Order <span class="em">received.</span>');
      if(sub) sub.textContent=t('Falta só o Pix. Assim que o pagamento cair, você recebe a confirmação por e-mail.','Just the Pix payment left. Once it goes through, we email you the confirmation.');
      st.forEach(function(s,i){
        if(i<2){ s.className='ok'; s.querySelector('i').textContent='✓'; }
        else { s.className='on'; var em=s.querySelector('em'); if(em){ em.textContent=t('Pagamento','Payment'); em.removeAttribute('data-i18n'); } }
      });
      /* o bloco do Pix sobe para logo abaixo do topo */
      var ov=root.querySelector('ul.woocommerce-order-overview'), tit=root.querySelector('.mp-details-title');
      if(ov){ if(tit) ov.parentNode.insertBefore(tit,ov); ov.parentNode.insertBefore(pix,ov); }
      if(tit) tit.textContent=t('Pague com Pix para finalizar','Pay with Pix to finish');
      /* textos do plugin que vêm em inglês */
      root.querySelectorAll('.mp-details-pix-qr-subtitle').forEach(function(p){
        p.textContent=p.textContent.replace('minutes','minutos').replace('minute','minuto').replace('hours','horas').replace('hour','hora');
      });
      /* copiar o código com aviso */
      var bt=root.querySelector('.mp-details-pix-button'), inp=root.querySelector('#mp-qr-code');
      if(bt&&inp){
        var orig=bt.textContent;
        inp.readOnly=true;
        bt.addEventListener('click',function(){
          if(navigator.clipboard) navigator.clipboard.writeText(inp.value).catch(function(){});
          bt.classList.add('ok'); bt.textContent=t('Código copiado ✓','Code copied ✓');
          setTimeout(function(){ bt.classList.remove('ok'); bt.textContent=orig; },2500);
        });
      }
    } else {
      if(h1) h1.innerHTML=t('Pedido <span class="em">confirmado.</span>','Order <span class="em">confirmed.</span>');
      if(sub) sub.textContent=t('Obrigada! Enviamos os detalhes para o seu e-mail.','Thank you! We sent the details to your email.');
      st.forEach(function(s,i){ s.className='ok'; s.querySelector('i').textContent='✓'; });
    }
  } else if(sub){
    sub.textContent=t('Preencha seus dados e escolha a entrega. Seu chocolate já está separado.','Fill in your details and choose shipping. Your chocolate is already set aside.');
    if(h1&&en) h1.innerHTML='Almost <span class="em">there.</span>';
  }

  /* miniaturas no resumo do pedido (Store API do WooCommerce) */
  var thumbs=null;
  function addThumbs(){
    if(!thumbs) return;
    root.querySelectorAll('#order_review td.product-name').forEach(function(td){
      if(td.querySelector('.cc-thumb')) return;
      var nome=(td.childNodes[0]&&td.childNodes[0].textContent||td.textContent).trim().toLowerCase();
      for(var k in thumbs){ if(nome.indexOf(k)===0){
        var img=document.createElement('img'); img.className='cc-thumb'; img.alt=''; img.src=thumbs[k];
        td.insertBefore(img,td.firstChild); break; } }
    });
  }
  if(window.fetch&&root.querySelector('#order_review')){
    fetch('/wp-json/wc/store/v1/cart',{credentials:'same-origin'}).then(function(r){return r.ok?r.json():null}).then(function(d){
      if(!d||!d.items) return; thumbs={};
      d.items.forEach(function(it){
        var im=it.images&&it.images[0]; if(!im) return;
        var tmp=document.createElement('div'); tmp.innerHTML=it.name;
        thumbs[tmp.textContent.trim().toLowerCase()]=im.thumbnail||im.src;
      });
      addThumbs();
    }).catch(function(){});
  }

  /* selo de pagamento seguro + voltar ao carrinho */
  function extras(){
    addThumbs();
    var po=root.querySelector('#payment .place-order');
    if(po&&!po.querySelector('.cc-co-safe')){
      po.insertAdjacentHTML('beforeend','<p class="cc-co-safe"><svg viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M6 10V7a6 6 0 0 1 12 0v3M5 10h14v11H5z"/></svg>'+
        t('Pagamento seguro e criptografado','Secure, encrypted payment')+'</p><a class="cc-co-back" href="/carrinho/">← '+t('Voltar ao carrinho','Back to cart')+'</a>');
    }
  }
  extras();
  if(window.jQuery) jQuery(document.body).on('updated_checkout payment_method_selected',extras);
})();
"""


def build_checkout():
    t = Tpl("checkout")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/checkout.css")), "cc-header"),
        t.con("phero cc-co-hero", [
            t.html(DECO, "cc-deco"),
            t.html(CHECKOUT_HERO),
        ]),
        t.con("cc-co", [
            t.w("shortcode", {"shortcode": "[woocommerce_checkout]"}),
        ]),
        t.html(tpl_footer(CHECKOUT_JS), "cc-footer"),
    ])]
    return export("checkout-woocommerce", "Cacau Cosmo — Finalizar compra", "page", content)


# ---- MINHA CONTA -----------------------------------------------------
ACC_HERO = """<p class="label js-acc-label">Meu Universo</p>
<h1 class="display js-acc-h1" style="font-size:clamp(34px,5vw,80px);line-height:1.08;color:var(--cc-cream);text-align:center;margin-top:14px;max-width:16ch">Seu <span class="em">Círculo.</span></h1>
<p class="cc-hero-sub js-acc-sub"></p>"""

ACC_MORE = """<section class="cc-acc-more">
  <a class="cc-acc-more__journal" href="/journal/">
    <canvas class="stars" data-density="6000" aria-hidden="true"></canvas>
    <span class="label" data-i18n="t-acc-j-label">Journal</span>
    <h2 data-i18n="t-acc-j-h2">Notas do mundo entre o cacau e o cosmo.</h2>
    <p data-i18n="t-acc-j-p">Histórias de origem, rituais e ciência, para ler devagar.</p>
    <em data-i18n="t-acc-j-cta">Ler o Journal</em>
  </a>
  <a class="cc-acc-more__shop" href="/loja/">
    <svg class="art" viewBox="0 0 260 150" aria-hidden="true"><use href="#art-bar"></use></svg>
    <span class="label" data-i18n="t-acc-s-label">Coleção Cosmo</span>
    <h2 data-i18n="t-acc-s-h2">Sol, Lua e Saturno.</h2>
    <p data-i18n="t-acc-s-p">Nibs to bar em pequenos lotes. Quando acaba, acaba.</p>
    <em data-i18n="t-acc-s-cta">Ir para a loja</em>
  </a>
</section>"""

ACC_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-acc-j-label': 'Journal', 't-acc-j-h2': 'Notes from the world between cacao and cosmos.',
  't-acc-j-p': 'Stories of origin, ritual and science, to read slowly.', 't-acc-j-cta': 'Read the Journal',
  't-acc-s-label': 'Cosmos Collection', 't-acc-s-h2': 'Sun, Moon and Saturn.',
  't-acc-s-p': 'Nibs to bar in small batches. When it\\u2019s gone, it\\u2019s gone.', 't-acc-s-cta': 'Go to the shop'
});
/* ===== MINHA CONTA ======================================== */
(function(){
  var root=document.querySelector('.cc-acc'); if(!root) return;
  var en=document.documentElement.lang==='en';
  var t=function(pt,e){return en?e:pt};
  var label=document.querySelector('.js-acc-label'), h1=document.querySelector('.js-acc-h1'), sub=document.querySelector('.js-acc-sub');
  function hero(l,h,s){ if(label&&l)label.innerHTML=l; if(h1&&h)h1.innerHTML=h; if(sub)sub.innerHTML=s||''; }
  var nav=root.querySelector('.woocommerce-MyAccount-navigation');
  var content=root.querySelector('.woocommerce-MyAccount-content');

  if(!nav){
    /* ---- deslogado: entrar / criar conta / recuperar senha ---- */
    if(root.querySelector('form.lost_reset_password')){
      hero(t('Minha conta','My account'), t('Recuperar <span class="em">acesso.</span>','Recover <span class="em">access.</span>'),
        t('Informe seu e-mail e enviaremos um link para criar uma nova senha.','Enter your email and we\\u2019ll send a link to set a new password.'));
      var lr=root.querySelector('form.lost_reset_password'); if(lr) lr.parentNode.classList.add('cc-acc-solo');
      return;
    }
    hero(t('Minha conta','My account'), t('Entre no seu <span class="em">Círculo.</span>','Enter your <span class="em">Circle.</span>'),
      t('Acompanhe pedidos, salve endereços e saiba antes de todo mundo quando sai o próximo lote.','Track orders, save addresses and be the first to know when the next batch is out.'));
    var c1=root.querySelector('#customer_login .u-column1 > h2'), c2=root.querySelector('#customer_login .u-column2 > h2');
    if(c1){ c1.textContent=t('Já faço parte','I\\u2019m a member'); c1.insertAdjacentHTML('afterend','<p class="cc-acc-tag">'+t('Entre com seu e-mail e senha.','Sign in with your email and password.')+'</p>'); }
    if(c2){ c2.textContent=t('Quero entrar no Círculo','Join the Circle');
      c2.insertAdjacentHTML('afterend','<p class="cc-acc-tag">'+t('Crie sua conta em menos de um minuto.','Create your account in under a minute.')+'</p>');
      var rf=root.querySelector('form.woocommerce-form-register');
      if(rf) rf.insertAdjacentHTML('afterbegin','<ul class="cc-acc-perks"><li>'+t('Acompanhe seus pedidos','Track your orders')+'</li><li>'+
        t('Compre mais rápido com endereços salvos','Check out faster with saved addresses')+'</li><li>'+t('Novidades e lotes especiais primeiro','News and special batches first')+'</li></ul>');
    }
    if(!c2){ var lf=root.querySelector('form.woocommerce-form-login'); if(lf&&lf.parentNode) lf.parentNode.classList.add('cc-acc-solo'); }
    return;
  }

  /* ---- logado ---- */
  var NOMES={dashboard:t('Meu Universo','My Universe'),orders:t('Meus pedidos','My orders'),'edit-address':t('Endereços','Addresses'),
    'edit-account':t('Meus dados','My details'),'payment-methods':t('Pagamentos','Payments'),'customer-logout':t('Sair','Log out')};
  nav.querySelectorAll('li').forEach(function(li){
    var m=li.className.match(/navigation-link--([a-z-]+)/); if(!m) return;
    if(m[1]==='downloads'){ li.remove(); return; }
    var a=li.querySelector('a'); if(a&&NOMES[m[1]]) a.textContent=NOMES[m[1]];
  });
  var links={}; nav.querySelectorAll('li').forEach(function(li){
    var m=li.className.match(/navigation-link--([a-z-]+)/); if(m){ var a=li.querySelector('a'); links[m[1]]={href:a.getAttribute('href'),text:a.textContent.trim()}; }
  });
  var active=nav.querySelector('li.is-active'), isDash=active&&/--dashboard/.test(active.className);
  var nome=content&&content.querySelector('p strong'); nome=nome?nome.textContent.trim():'';

  if(isDash){
    hero('Cacaucosmo', t('Meu <span class="em">Universo.</span>','My <span class="em">Universe.</span>'),
      t('Seu lugar dentro do Círculo.','Your place inside the Circle.'));
    var ps=content.querySelectorAll(':scope > p');
    var sair=ps[0]? ps[0].querySelector('a') : null;
    ps.forEach(function(p){ p.style.display='none'; });
    var ic={
      caixa:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm0 0v18m8-13.5-8 4.5-8-4.5"/></svg>',
      pino:'<svg viewBox="0 0 24 24"><use href="#ic-pin"></use></svg>',
      brilho:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 3c.6 5.3 1.7 6.4 7 7-5.3.6-6.4 1.7-7 7-.6-5.3-1.7-6.4-7-7 5.3-.6 6.4-1.7 7-7Zm8 11c.3 2.2.8 2.7 3 3-2.2.3-2.7.8-3 3-.3-2.2-.8-2.7-3-3 2.2-.3 2.7-.8 3-3Z"/></svg>',
      pessoa:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.9-3.9 3.9-6 7.5-6s6.6 2.1 7.5 6"/></svg>',
      orbita:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="12" cy="12" r="3.2"/><ellipse cx="12" cy="12" rx="10" ry="4.2" transform="rotate(-28 12 12)"/></svg>'};
    function tile(href,icon,b){ return href?'<a class="cc-un-tile" href="'+href+'">'+icon+'<b>'+b+'</b></a>':''; }
    var html='<section class="cc-meu-un">'+
      '<div class="cc-meu-un__topo"><div><span class="cc-meu-un__tag">'+t('Membro do Círculo','Circle member')+'</span>'+
      '<h2>'+(nome?t('Bem-vinda ao seu universo, ','Welcome to your universe, ')+'<em>'+nome+'</em>.':t('Bem-vinda ao seu universo.','Welcome to your universe.'))+'</h2></div>'+
      '<span class="cc-meu-un__orbita" aria-hidden="true">'+ic.orbita+'</span></div>'+
      '<div class="cc-meu-un__grade">'+
        tile(links.orders&&links.orders.href,ic.caixa,t('Meus pedidos','My orders'))+
        tile(links['edit-address']&&links['edit-address'].href,ic.pino,t('Endereços','Addresses'))+
        tile('/universo/',ic.brilho,t('Meu Círculo','My Circle'))+
        tile(links['edit-account']&&links['edit-account'].href,ic.pessoa,t('Meus dados','My details'))+
      '</div></section>';
    if(sair) html+='<p class="cc-acc-small">'+t('Não é você? ','Not you? ')+'<a href="'+sair.getAttribute('href')+'">'+t('Sair','Log out')+'</a></p>';
    content.insertAdjacentHTML('afterbegin',html);
  } else {
    var titulo=active?active.textContent.trim():t('Meu Universo','My Universe');
    hero(t('Meu Universo','My Universe'), titulo+'<span class="em">.</span>', '');
  }

  /* selos de status nos pedidos */
  root.querySelectorAll('tr[class*="--status-"]').forEach(function(tr){
    var m=tr.className.match(/--status-([a-z-]+)/), td=tr.querySelector('.woocommerce-orders-table__cell-order-status');
    if(m&&td&&!td.querySelector('.cc-status')) td.innerHTML='<span class="cc-status cc-status--'+m[1]+'">'+td.textContent.trim()+'</span>';
  });
})();
"""


def build_conta():
    t = Tpl("conta")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/conta.css")), "cc-header"),
        t.con("phero cc-acc-hero", [
            t.html(DECO, "cc-deco"),
            t.html(ACC_HERO),
        ]),
        t.con("cc-acc", [
            t.w("shortcode", {"shortcode": "[woocommerce_my_account]"}),
        ]),
        t.html(ACC_MORE),
        t.html(tpl_footer(ACC_JS), "cc-footer"),
    ])]
    return export("minha-conta-woocommerce", "Cacau Cosmo — Minha conta", "page", content)


# ---- POST ------------------------------------------------------------
SHARE_HTML = """<div class="cc-share">
  <small data-i18n="t-share">Compartilhar</small>
  <div class="cc-share__links">
    <a class="js-share" data-net="wa" href="#" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><use href="#ic-wa"></use></svg></a>
    <a class="js-share" data-net="mail" href="#" aria-label="E-mail"><svg viewBox="0 0 24 24"><use href="#ic-mail"></use></svg></a>
    <button class="js-copy" type="button" aria-label="Copiar link"><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg></button>
  </div>
</div>"""

RELATED_HTML = """<h2 class="cc-mais__h" id="ccMaisH" data-i18n="t-related">Continue lendo</h2>
<div class="cc-mais__grade" id="ccMais" aria-live="polite"></div>"""

POST_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-share': 'Share', 't-related': 'Keep reading', 't-related-un': 'More from the Universe',
  't-circ-label': 'Journal', 't-circ-h2': 'Letters from the Cosmos.',
  't-circ-p': 'Rituals, origin and the time of cacao, written for those already in the Circle.', 't-circ-cta': 'Read the Journal'
});
/* ===== CONTINUE LENDO: Journal sugere Journal, Universo sugere Universo ===== */
(function(){
  var box=document.getElementById('ccMais'), h=document.getElementById('ccMaisH'); if(!box||!window.fetch)return;
  var m=document.body.className.match(/postid-(\\d+)/), id=m?+m[1]:0, en=document.documentElement.lang==='en';
  var API='/wp-json/wp/v2/';
  function j(u){return fetch(API+u,{credentials:'same-origin'}).then(function(r){return r.ok?r.json():[]})}
  function txt(x){var d=document.createElement('div');d.innerHTML=x||'';return (d.textContent||'').replace(/\\s+/g,' ').trim()}
  function esc(x){return String(x).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function troca(el,pt,ing,chave){if(!el)return;el.setAttribute('data-i18n',chave);el.dataset.pt=pt;el.innerHTML=en?ing:pt}
  Promise.all([j('categories?slug=universo&_fields=id'), id?j('posts/'+id+'?_fields=categories'):Promise.resolve({})]).then(function(r){
    var un=r[0]&&r[0][0]?r[0][0].id:0, cats=(r[1]&&r[1].categories)||[], ehUn=un&&cats.indexOf(un)>=0;
    if(ehUn){
      document.body.classList.add('cc-post-un');
      window.CC_DICT=Object.assign(window.CC_DICT||{},{'t-un-band-l':'Universe','t-un-band-h':'More stories from the Universe.',
        't-un-band-p':'Actions, gatherings and the people around cacao.','t-un-band-c':'See the Universe'});
      troca(h,'Mais do Universo','More from the Universe','t-related-un');
      var band=document.querySelector('.cc-circle');
      if(band){
        troca(band.querySelector('[data-i18n="t-circ-label"]'),'Universo','Universe','t-un-band-l');
        troca(band.querySelector('[data-i18n="t-circ-h2"]'),'Mais histórias do Universo.','More stories from the Universe.','t-un-band-h');
        troca(band.querySelector('[data-i18n="t-circ-p"]'),'Ações, encontros e as pessoas ao redor do cacau.','Actions, gatherings and the people around cacao.','t-un-band-p');
        var a=band.querySelector('a.btn'); if(a){a.href='/universo/'; troca(a,'Ver o Universo','See the Universe','t-un-band-c');}
      }
    }
    var q='posts?per_page=3&_embed=wp:featuredmedia&_fields=id,link,title,excerpt,date,_links,_embedded'+(id?'&exclude='+id:'')+
      (ehUn?'&categories='+un:(un?'&categories_exclude='+un:''));
    return j(q).then(function(ps){
      if(!ps.length){var sec=box.closest('.cc-post-related');if(sec)sec.style.display='none';return}
      box.innerHTML=ps.map(function(p){
        var md=p._embedded&&p._embedded['wp:featuredmedia']&&p._embedded['wp:featuredmedia'][0], sz=md&&md.media_details&&md.media_details.sizes,
            im=md?((sz&&sz.medium_large||{}).source_url||md.source_url):'', t=txt(p.title.rendered), d='';
        try{d=new Date(p.date).toLocaleDateString(en?'en-US':'pt-BR',{day:'2-digit',month:'short',year:'numeric'})}catch(e){}
        return '<article class="cc-mais__card"><a class="cc-mais__img" href="'+esc(p.link)+'" tabindex="-1" aria-hidden="true">'+(im?'<img src="'+esc(im)+'" alt="" loading="lazy">':'')+'</a>'+
          '<span class="cc-mais__data">'+esc(d)+'</span><h3><a href="'+esc(p.link)+'">'+esc(t)+'</a></h3>'+
          '<p>'+esc(txt(p.excerpt.rendered).slice(0,140))+'</p><a class="cc-mais__ler" href="'+esc(p.link)+'" aria-label="'+(en?'Read: ':'Ler: ')+esc(t)+'">'+(en?'Read':'Ler')+' &rarr;</a></article>';
      }).join('');
    });
  }).catch(function(){});
})();

/* ===== COMPARTILHAR ======================================= */
(function(){
  var u=encodeURIComponent(location.href.split('#')[0]), ti=encodeURIComponent(document.title);
  document.querySelectorAll('.js-share').forEach(function(a){
    var n=a.getAttribute('data-net');
    a.href = n==='wa' ? 'https://wa.me/?text='+ti+'%20'+u : 'mailto:?subject='+ti+'&body='+u;
  });
  document.querySelectorAll('.js-copy').forEach(function(b){
    b.addEventListener('click',function(){
      if(navigator.clipboard) navigator.clipboard.writeText(location.href.split('#')[0]).then(function(){
        b.style.background='var(--cc-gold)'; setTimeout(function(){b.style.background=''},1200);
      });
    });
  });
})();
"""


def build_post():
    t = Tpl("post")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/post.css")), "cc-header"),
        t.con("phero cc-post-hero", [
            t.html(DECO, "cc-deco"),
            t.dyn_heading("post-terms", {"taxonomy": "category", "separator": " · ", "link": ""}, "p", "cc-label"),
            t.w("theme-post-title", {"header_size": "h1", "align": "center"}),
            t.con("cc-post-meta", [
                t.dyn_heading("post-date", {"type": "post_published", "format": "default"}, "span"),
                t.dyn_heading("author-name", {"before": "Por "}, "span"),
            ], direction="row"),
        ]),
        t.con("cc-post-cover", [t.w("theme-post-featured-image", {"image_size": "full"})]),
        t.con("cc-post-body", [
            t.w("theme-post-content"),
            t.html(SHARE_HTML),
            t.dyn_heading("post-terms", {"taxonomy": "post_tag", "separator": "  ·  ", "before": "# "}, "p", "cc-tags"),
            t.w("author-box", {"source": "current", "show_avatar": "yes", "show_name": "yes",
                               "show_biography": "yes", "layout": "left"}),
        ]),
        t.con("cc-post-nav", [t.w("post-navigation", {
            "show_label": "yes", "prev_label": "Anterior", "next_label": "Próximo",
            "show_arrow": "yes", "arrow": "fa fa-angle-left", "show_title": "yes", "show_borders": "",
        })]),
        t.con("cc-post-comments", [t.w("post-comments", {"_skin": "theme_comments", "source_type": "current_post"})]),
        t.con("cc-post-related", [t.html(RELATED_HTML, "cc-mais")]),
        t.html(CIRCLE_HTML),
        t.html(tpl_footer(POST_JS), "cc-footer"),
    ])]
    return export("post-journal", "Cacau Cosmo — Post do Journal (Single Post)", "single-post", content)


# ---- JOURNAL (página) -------------------------------------------------
JOURNAL_HTML = """<div class="cc-jr">
  <header class="jr-ceu">
    <canvas class="stars" data-density="7000" aria-hidden="true"></canvas>
    <div class="jr-arco" aria-hidden="true"></div>
    <span class="jr-tag">Journal</span>
    <h1 data-i18n="t-jr-h1">Notas do mundo entre o cacau e o <em>cosmo.</em></h1>
    <svg class="jr-fagulha" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M9 0c.4 4.7 4.3 8.6 9 9-4.7.4-8.6 4.3-9 9-.4-4.7-4.3-8.6-9-9 4.7-.4 8.6-4.3 9-9z" fill="#DDB05A"/></svg>
    <p data-i18n="t-jr-p">Histórias, ciência, cadernos de campo e ideias para um planeta mais doce.</p>
  </header>

  <!-- 3 matérias em destaque: as marcadas como "Fixar no topo do blog"; se não houver, as 3 mais recentes -->
  <section class="jr-dest" id="jrDest" aria-label="Matérias em destaque" aria-roledescription="carrossel">
    <div class="jr-palco" id="jrPalco"><div class="jr-slide on"><div class="jr-slide__bg"></div></div></div>
    <aside class="jr-lado">
      <div class="jr-lado__topo">
        <span class="jr-tag" data-i18n="t-jr-dest">Em destaque</span>
        <span class="jr-cont" aria-hidden="true"><b id="jrN">01</b><i>/</i><b id="jrT">03</b></span>
      </div>
      <ol class="jr-lista" id="jrLista"></ol>
      <div class="jr-setas">
        <button type="button" id="jrAnt" aria-label="Matéria anterior"><svg viewBox="0 0 16 16" fill="none"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="1.3"/></svg></button>
        <button type="button" id="jrProx" aria-label="Próxima matéria"><svg viewBox="0 0 16 16" fill="none"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.3"/></svg></button>
      </div>
      <blockquote data-i18n="t-jr-quote">&ldquo;A transformação vive no que não podemos ver.&rdquo;</blockquote>
      <div class="jr-risco" aria-hidden="true"></div>
    </aside>
  </section>

  <section class="jr-todas" aria-labelledby="jrTodasH">
    <div class="jr-todas__in">
      <div class="jr-todas__topo">
        <div><span class="jr-tag" data-i18n="t-jr-todas">Todas as matérias</span><h2 id="jrTodasH" data-i18n="t-jr-todas-h">Do caderno do Cosmo.</h2></div>
        <small id="jrTotal"></small>
      </div>
      <div class="jr-grade" id="jrGrade"><div class="jr-skel"></div><div class="jr-skel"></div><div class="jr-skel"></div></div>
      <div class="jr-mais"><button type="button" id="jrMais" hidden data-i18n="t-jr-mais">Carregar mais</button></div>
    </div>
  </section>
</div>"""

JOURNAL_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-jr-h1': 'Notes from the world between cacao and the <em>cosmos.</em>',
  't-jr-p': 'Stories, science, field notes and ideas for a sweeter planet.',
  't-jr-dest': 'Featured', 't-jr-quote': '&ldquo;Transformation lives in what we cannot see.&rdquo;',
  't-jr-todas': 'All stories', 't-jr-todas-h': 'From the Cosmos notebook.', 't-jr-mais': 'Load more'
});
"""

JOURNAL_JS = r"""/* ===== JOURNAL: destaques + lista (REST do WordPress) ===== */
(function(){
  var en=document.documentElement.lang==='en', API='/wp-json/wp/v2/posts', POR=9;
  var CAMPOS='&_embed=wp:featuredmedia,wp:term&_fields=id,link,title,excerpt,date,sticky,_links,_embedded';
  function txt(h){var d=document.createElement('div');d.innerHTML=h||'';return (d.textContent||'').replace(/\s+/g,' ').trim()}
  function curto(s,n){return s.length>n?s.slice(0,n).replace(/\s+\S*$/,'')+'…':s}
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function img(p,tam){var m=p._embedded&&p._embedded['wp:featuredmedia']&&p._embedded['wp:featuredmedia'][0];if(!m||!m.source_url)return '';
    var s=m.media_details&&m.media_details.sizes;return (s&&s[tam]&&s[tam].source_url)||m.source_url}
  function cat(p){var t=p._embedded&&p._embedded['wp:term'];var c=t&&t[0]&&t[0][0];return c?txt(c.name):'Journal'}
  function data(p){try{return new Date(p.date).toLocaleDateString(en?'en-US':'pt-BR',{day:'2-digit',month:'short',year:'numeric'})}catch(e){return ''}}
  var FORA='';   /* categoria Universo fica fora do Journal */
  function get(q){return fetch(API+'?'+q+FORA+CAMPOS,{credentials:'same-origin'}).then(function(r){
    if(!r.ok)throw r; return r.json().then(function(j){return {itens:j,total:+r.headers.get('X-WP-Total')||j.length}})})}
  var seta='<svg viewBox="0 0 26 8" fill="none" aria-hidden="true"><path d="M0 4h24m0 0-4-3.2M24 4l-4 3.2" stroke="currentColor" stroke-width="1"/></svg>';

  /* ---- destaques ---- */
  var palco=document.getElementById('jrPalco'), lista=document.getElementById('jrLista'), dest=document.getElementById('jrDest');
  var vistos=[];
  function montarDestaques(ps){
    if(!ps.length){dest.hidden=true;return}
    vistos=ps.map(function(p){return p.id});
    document.getElementById('jrT').textContent=('0'+ps.length).slice(-2);
    palco.innerHTML=ps.map(function(p,i){
      var t=txt(p.title.rendered), u=t.lastIndexOf(' '), h=u>0?esc(t.slice(0,u))+' <span>'+esc(t.slice(u+1))+'</span>':esc(t), bg=img(p,'full');
      return '<article class="jr-slide'+(i?'':' on')+'" aria-roledescription="slide" aria-label="'+(i+1)+' / '+ps.length+'"'+(i?' aria-hidden="true"':'')+'>'+
        '<div class="jr-slide__bg"'+(bg?' style="background-image:url(\''+bg.replace(/'/g,'%27')+'\')"':'')+'></div>'+
        '<div class="jr-slide__txt"><span class="jr-tag">'+('0'+(i+1)).slice(-2)+' &mdash; '+esc(cat(p))+'</span>'+
        '<h2>'+h+'</h2><p>'+esc(curto(txt(p.excerpt.rendered),150))+'</p>'+
        '<a class="jr-pilula" href="'+esc(p.link)+'"'+(i?' tabindex="-1"':'')+'><span>'+(en?'Read story':'Ler matéria')+'</span>'+seta+'</a></div></article>';
    }).join('');
    lista.innerHTML=ps.map(function(p,i){var m=img(p,'thumbnail');
      return '<li><button type="button" data-i="'+i+'"'+(i?'':' aria-current="true"')+'>'+(m?'<img src="'+esc(m)+'" alt="" loading="lazy">':'<i class="jr-mini"></i>')+
        '<div><b>'+('0'+(i+1)).slice(-2)+'</b><span>'+esc(txt(p.title.rendered))+'</span></div></button></li>'}).join('');
    var slides=palco.querySelectorAll('.jr-slide'), bts=lista.querySelectorAll('button'), atual=0, timer=null;
    function ir(n){
      atual=(n+slides.length)%slides.length;
      slides.forEach(function(s,i){var on=i===atual;s.classList.toggle('on',on);s.setAttribute('aria-hidden',on?'false':'true');
        var a=s.querySelector('a');if(a)a.tabIndex=on?0:-1});
      bts.forEach(function(b,i){b.setAttribute('aria-current',i===atual?'true':'false')});
      document.getElementById('jrN').textContent=('0'+(atual+1)).slice(-2);
    }
    function play(){stop(); if(slides.length>1&&!matchMedia('(prefers-reduced-motion: reduce)').matches) timer=setInterval(function(){ir(atual+1)},7000)}
    function stop(){if(timer)clearInterval(timer);timer=null}
    bts.forEach(function(b){b.addEventListener('click',function(){ir(+b.dataset.i);play()})});
    document.getElementById('jrAnt').addEventListener('click',function(){ir(atual-1);play()});
    document.getElementById('jrProx').addEventListener('click',function(){ir(atual+1);play()});
    dest.addEventListener('mouseenter',stop); dest.addEventListener('mouseleave',play);
    dest.addEventListener('focusin',stop); dest.addEventListener('focusout',play);
    var x0=null; palco.addEventListener('touchstart',function(e){x0=e.touches[0].clientX},{passive:true});
    palco.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40){ir(atual+(dx<0?1:-1));play()}x0=null});
    play();
  }

  /* ---- lista ---- */
  var grade=document.getElementById('jrGrade'), mais=document.getElementById('jrMais'), pag=0, total=0;
  function card(p){var m=img(p,'medium_large'), t=esc(txt(p.title.rendered));
    return '<article class="jr-card"><a class="jr-card__img" href="'+esc(p.link)+'" tabindex="-1" aria-hidden="true">'+(m?'<img src="'+esc(m)+'" alt="" loading="lazy">':'')+'</a>'+
      '<span class="jr-tag">'+esc(cat(p))+' &middot; '+esc(data(p))+'</span><h3><a href="'+esc(p.link)+'">'+t+'</a></h3>'+
      '<p>'+esc(curto(txt(p.excerpt.rendered),180))+'</p><a class="jr-card__ler" href="'+esc(p.link)+'" aria-label="'+(en?'Read: ':'Ler: ')+t+'">'+(en?'Read':'Ler')+' &mdash;</a></article>'}
  function carregar(){
    pag++; mais.disabled=true;
    get('per_page='+POR+'&page='+pag+(vistos.length?'&exclude='+vistos.join(','):'')).then(function(r){
      if(pag===1){grade.innerHTML='';total=r.total}
      grade.insertAdjacentHTML('beforeend',r.itens.map(card).join(''));
      if(pag===1&&!r.itens.length) grade.innerHTML='<p class="jr-vazio">'+(en?'New stories soon.':'Novas matérias em breve.')+'</p>';
      var n=grade.querySelectorAll('.jr-card').length;
      mais.hidden=n>=total; mais.disabled=false;
      document.getElementById('jrTotal').textContent=total?(total+vistos.length)+(en?' stories':' matérias'):'';
    }).catch(function(){ if(pag===1) grade.innerHTML='<p class="jr-vazio">'+(en?'Could not load the stories.':'Não foi possível carregar as matérias.')+'</p>'; mais.hidden=true; });
  }
  mais.addEventListener('click',carregar);

  fetch('/wp-json/wp/v2/categories?slug=universo&_fields=id',{credentials:'same-origin'})
    .then(function(r){return r.ok?r.json():[]}).then(function(c){if(c&&c[0])FORA='&categories_exclude='+c[0].id}).catch(function(){})
    .then(function(){return get('sticky=true&per_page=3')}).then(function(r){
    var fixos=r.itens;
    if(fixos.length>=3) return fixos;
    return get('per_page='+(3-fixos.length)+(fixos.length?'&exclude='+fixos.map(function(p){return p.id}).join(','):'')).then(function(r2){return fixos.concat(r2.itens)});
  }).catch(function(){return []}).then(function(ps){montarDestaques(ps); carregar();});
})();
"""


def build_journal():
    t = Tpl("journal")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/journal.css")), "cc-header"),
        t.html(JOURNAL_HTML, "cc-journal"),
        t.html(tpl_footer(JOURNAL_DICT + JOURNAL_JS), "cc-footer"),
    ])]
    return export("pagina-journal", "Cacau Cosmo — Journal", "page", content)


# ---- UNIVERSO (página) ------------------------------------------------
# Ações, encontros e histórias: posts da categoria "Universo" (slug universo).
# As subcategorias de Universo viram os filtros do topo.
UNIVERSO_HTML = """<div class="cc-un">
  <header class="un-ceu">
    <canvas class="stars" data-density="5200" aria-hidden="true"></canvas>
    <div class="un-orbita" aria-hidden="true"></div>
    <span class="un-tag" data-i18n="t-un-tag">Para quem é do Círculo</span>
    <h1 data-i18n="t-un-h1">Universo<em>.</em></h1>
    <p data-i18n="t-un-p">Ações, encontros e histórias que acontecem ao redor do cacau e das pessoas que fazem parte dele.</p>
    <div class="un-filtros" id="unFiltros" role="group" aria-label="Filtrar" hidden></div>
  </header>

  <section class="un-lista" aria-label="Ações do Universo">
    <div class="un-lista__in" id="unLista"><div class="un-skel"></div><div class="un-skel"></div></div>
    <div class="un-mais"><button type="button" id="unMais" hidden data-i18n="t-un-mais">Carregar mais</button></div>
  </section>
</div>"""

UNIVERSO_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-un-tag': 'For Circle members', 't-un-h1': 'Universe<em>.</em>',
  't-un-p': 'Actions, gatherings and stories that happen around cacao and the people who are part of it.',
  't-un-mais': 'Load more'
});
"""

UNIVERSO_JS = r"""/* ===== UNIVERSO: ações da categoria Universo (REST do WordPress) ===== */
(function(){
  var en=document.documentElement.lang==='en', POR=6, API='/wp-json/wp/v2/';
  var lista=document.getElementById('unLista'), mais=document.getElementById('unMais'), filtros=document.getElementById('unFiltros');
  var raiz=0, cat=0, pag=0, total=0;
  function txt(h){var d=document.createElement('div');d.innerHTML=h||'';return (d.textContent||'').replace(/\s+/g,' ').trim()}
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
  function curto(s,n){return s.length>n?s.slice(0,n).replace(/\s+\S*$/,'')+'…':s}
  function j(u){return fetch(API+u,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw r;return r.json().then(function(d){return {d:d,total:+r.headers.get('X-WP-Total')||d.length}})})}
  function img(p){var m=p._embedded&&p._embedded['wp:featuredmedia']&&p._embedded['wp:featuredmedia'][0];if(!m||!m.source_url)return '';
    var s=m.media_details&&m.media_details.sizes;return (s&&(s.large||s.medium_large)||{}).source_url||m.source_url}
  function sub(p){var t=p._embedded&&p._embedded['wp:term']&&p._embedded['wp:term'][0]||[];
    var c=t.filter(function(x){return x.id!==raiz})[0];return c?txt(c.name):''}
  function quando(p){try{return new Date(p.date).toLocaleDateString(en?'en-US':'pt-BR',{month:'long',year:'numeric'})}catch(e){return ''}}
  var seta='<svg viewBox="0 0 24 8" fill="none" aria-hidden="true"><path d="M0 4h22m0 0-4-3.2M22 4l-4 3.2" stroke="currentColor" stroke-width="1"/></svg>';
  var vagem='<svg viewBox="0 0 200 320" aria-hidden="true"><use href="#art-pod"></use></svg>';

  function acao(p){
    /* "Outubro Rosa — Cuidado que se partilha" vira título + subtítulo */
    var t=txt(p.title.rendered), partes=t.split(/\s+[—–-]\s+/), titulo=partes.shift(), subt=partes.join(' — ');
    var m=img(p), c=sub(p), r=curto(txt(p.excerpt.rendered),200);
    function base(x){return x.toLowerCase().replace(/[.!…\s]+$/,'')}
    if(subt&&base(r)===base(subt)) r='';
    return '<article class="un-acao">'+
      '<a class="un-acao__foto" href="'+esc(p.link)+'" tabindex="-1" aria-hidden="true">'+(m?'<img src="'+esc(m)+'" alt="" loading="lazy">':'<span class="un-sem">'+vagem+'</span>')+'</a>'+
      '<div><div class="un-acao__meta"><span class="un-tag">'+esc(quando(p))+'</span>'+(c?'<span class="un-chip">'+esc(c)+'</span>':'')+'</div>'+
      '<h2><a href="'+esc(p.link)+'">'+esc(titulo)+'</a></h2>'+(subt?'<p class="un-acao__sub">'+esc(subt)+'</p>':'')+
      (r?'<p class="un-resumo">'+esc(r)+'</p>':'')+
      '<a class="un-ver" href="'+esc(p.link)+'" aria-label="'+(en?'See: ':'Ver: ')+esc(titulo)+'">'+(en?'See the story':'Ver a ação')+seta+'</a></div></article>';
  }
  function vazio(){lista.innerHTML='<div class="un-vazio"><b>'+(en?'The first stories are on the way.':'As primeiras histórias estão chegando.')+'</b><span>'+
    (en?'Come back soon.':'Volte em breve.')+'</span></div>'}
  function carregar(novo){
    if(novo){pag=0;lista.innerHTML='<div class="un-skel"></div>'}
    pag++; mais.disabled=true;
    j('posts?categories='+(cat||raiz)+'&per_page='+POR+'&page='+pag+'&_embed=wp:featuredmedia,wp:term&_fields=id,link,title,excerpt,date,_links,_embedded')
      .then(function(r){
        if(pag===1){lista.innerHTML='';total=r.total}
        lista.insertAdjacentHTML('beforeend',r.d.map(acao).join(''));
        if(pag===1&&!r.d.length) vazio();
        mais.hidden=lista.querySelectorAll('.un-acao').length>=total; mais.disabled=false;
      }).catch(function(){ if(pag===1) vazio(); mais.hidden=true; });
  }
  function montarFiltros(cats){
    if(!cats.length)return;
    var bts=[{id:0,name:en?'All':'Todos'}].concat(cats);
    filtros.innerHTML=bts.map(function(c,i){return '<button type="button" data-id="'+c.id+'" aria-pressed="'+(i?'false':'true')+'">'+esc(txt(c.name))+'</button>'}).join('');
    filtros.hidden=false;
    filtros.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;
      filtros.querySelectorAll('button').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false')});
      cat=+b.dataset.id; carregar(true);});
  }
  mais.addEventListener('click',function(){carregar(false)});
  j('categories?slug=universo&_fields=id').then(function(r){
    if(!r.d.length){vazio();return}
    raiz=r.d[0].id; carregar(true);
    j('categories?parent='+raiz+'&hide_empty=true&per_page=50&orderby=name&_fields=id,name').then(function(c){montarFiltros(c.d)}).catch(function(){});
  }).catch(vazio);
})();
"""


def build_universo():
    t = Tpl("universo")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/universo.css")), "cc-header"),
        t.html(UNIVERSO_HTML, "cc-universo"),
        t.html(tpl_footer(UNIVERSO_DICT + UNIVERSO_JS), "cc-footer"),
    ])]
    return export("pagina-universo", "Cacau Cosmo — Universo", "page", content)


# ---- PÁGINAS EM JSON (home, quem somos, contato) --------------------
def build_pagina_json(slug, titulo):
    t = Tpl(f"pg-{slug}")
    content = [t.con("cc-root cc-pagina", [t.html(read(ROOT / "paginas" / f"{slug}.html"))])]
    return export(f"pagina-{slug}", titulo, "page", content)


if __name__ == "__main__":
    for slug in ("quem-somos", "contato"):
        print("ok", build_page(slug).relative_to(ROOT.parent))
    for fn in (build_loja, build_produto, build_carrinho, build_checkout, build_conta, build_post, build_journal, build_universo):
        print("ok", fn().relative_to(ROOT.parent))
    for slug, titulo in (("home", "Cacau Cosmo — Home"), ("quem-somos", "Cacau Cosmo — Quem Somos"), ("contato", "Cacau Cosmo — Contato")):
        print("ok", build_pagina_json(slug, titulo).relative_to(ROOT.parent))
