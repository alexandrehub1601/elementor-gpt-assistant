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
      <div><h3 data-i18n="t-trust1-h">Pequenos lotes</h3><p data-i18n="t-trust1-p">Bean-to-bar, feito à mão. Quando um lote acaba, acaba.</p></div>
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
  <p class="label" style="font-family:var(--font-mono);font-size:clamp(9px,.66vw,11px);letter-spacing:.36em;text-transform:uppercase;font-weight:600;color:var(--cc-gold)" data-i18n="t-circ-label">O Círculo</p>
  <h2 data-i18n="t-circ-h2">O próximo ritual começa aqui.</h2>
  <p data-i18n="t-circ-p">Pequenos lotes, cartas do Cosmo e convites para o que estamos criando.</p>
  <a class="btn btn--pink" href="/#circulo" data-i18n="t-btncircle">Entrar no Círculo</a>
</section>"""

FEATURE_HTML = """<section class="cc-feature">
  <div class="cc-feature__media">
    <!-- [ TROCAR FOTO ] <img src="https://cacaucosmo.com.br/wp-content/uploads/2026/08/barra.jpg" alt="Barras de chocolate quebradas" loading="lazy"> -->
    <svg viewBox="0 0 260 150"><use href="#art-bar"></use></svg>
  </div>
  <div class="cc-feature__body">
    <p class="label" style="color:var(--cc-brown)" data-i18n="t-feat-label">Do grão à barra</p>
    <h2 data-i18n="t-feat-h2">O tempo é ingrediente.</h2>
    <p data-i18n="t-feat-p">Fermentação, torra, refino e temperagem acontecem aqui, em pequenos lotes. Cada barra carrega o tempo que levou para existir.</p>
    <a class="cta" href="/quem-somos/#processo" data-i18n="t-feat-cta">Conheça o processo &mdash;</a>
  </div>
</section>"""

SHOP_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-shop-label': 'Shop', 't-shop-sub': 'Bean-to-bar chocolate in small batches. When it\\u2019s gone, it\\u2019s gone.',
  't-trust1-h': 'Small batches', 't-trust1-p': 'Bean-to-bar, handmade. When a batch is gone, it\\u2019s gone.',
  't-trust2-h': 'Origin cacao', 't-trust2-p': 'Respecting people, territories and nature\\u2019s own time.',
  't-trust3-h': 'Careful shipping', 't-trust3-p': 'Packaging designed so the chocolate arrives whole, anywhere in Brazil.',
  't-feat-label': 'Bean to bar', 't-feat-h2': 'Time is an ingredient.',
  't-feat-p': 'Fermentation, roasting, refining and tempering happen here, in small batches. Every bar carries the time it took to exist.',
  't-feat-cta': 'See the process &mdash;',
  't-circ-label': 'The Circle', 't-circ-h2': 'The next ritual begins here.',
  't-circ-p': 'Small batches, letters from the Cosmos and invitations to what we\\u2019re creating.'
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
  });
})();
"""


def build_loja():
    t = Tpl("loja")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/loja.css")), "cc-header"),
        t.con("phero cc-shop-hero", [
            t.html(DECO, "cc-deco"),
            t.html('<p class="label" data-i18n="t-shop-label">Loja</p>', "cc-label"),
            t.w("theme-archive-title", {"header_size": "h1", "align": "center"}),
            t.html('<p class="cc-hero-sub" data-i18n="t-shop-sub">Chocolate bean-to-bar em pequenos lotes. Quando acaba, acaba.</p>'),
        ]),
        t.con("cc-shop-main", [
            t.w("woocommerce-notices"),
            # vitrine fixa: só a Coleção Cosmo, na ordem Sol, Lua, Saturno
            t.w("shortcode", {"shortcode": '[products skus="CC-SOL-55,CC-LUA-35,CC-SAT-80" columns="3" orderby="date" order="ASC"]'}),
        ]),
        t.html(TRUST_HTML),
        t.html(FEATURE_HTML),
        t.html(CIRCLE_HTML),
        t.html(tpl_footer(SHOP_DICT + WOO_CARDS_JS), "cc-footer"),
    ])]
    # versão "página": entra por qualquer importador e depois é inserida no
    # template de Arquivo de produtos pela biblioteca (Meus modelos → Inserir)
    export("loja-woocommerce-alternativo", "Cacau Cosmo — Loja (inserir no Arquivo de produtos)", "page", content)
    return export("loja-woocommerce", "Cacau Cosmo — Loja (Arquivo de produtos)", "product-archive", content)


# ---- PRODUTO ---------------------------------------------------------
PERKS_HTML = """<!-- [ REVISAR ] ajuste os selos para cada produto, se quiser -->
<ul class="cc-perks">
  <li><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg><span><b data-i18n="t-perk1-h">Bean-to-bar</b><span data-i18n="t-perk1-p">Do grão à barra, em pequenos lotes.</span></span></li>
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

PROD_DICT = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-perk1-h': 'Bean-to-bar', 't-perk1-p': 'From bean to bar, in small batches.',
  't-perk2-h': 'Shipping across Brazil', 't-perk2-p': 'Shipping calculated in the cart by postcode.',
  't-perk3-h': 'How to store', 't-perk3-p': 'Cool and dry, between 16 and 20 °C. Avoid the fridge.',
  't-rit-label': 'Tasting ritual', 't-rit-h2': 'Slowly. That\\u2019s how you taste.',
  't-rit1-h': 'Look', 't-rit1-p': 'The shine, the colour, the smooth surface. Tempering shows here.',
  't-rit2-h': 'Snap', 't-rit2-p': 'Listen to the snap. Dry and clean, as it should be.',
  't-rit3-h': 'Breathe', 't-rit3-p': 'Bring it close. Fruit, earth, flower &mdash; what comes first?',
  't-rit4-h': 'Let it melt', 't-rit4-p': 'Don\\u2019t bite. Let your tongue do the work and the flavour open up.'
});
"""


def build_produto():
    t = Tpl("produto")
    content = [t.con("cc cc-root", [
        t.html(tpl_header(read("elementor/produto.css")), "cc-header"),
        t.con("cc-prod", [
            t.con("cc-crumbs", [t.w("woocommerce-breadcrumb")]),
            t.w("woocommerce-notices"),
            t.con("cc-prod-grid", [
                t.con("cc-prod-gallery", [
                    t.w("woocommerce-product-images", {"sale_flash": "yes"}),
                ]),
                t.con("cc-prod-info", [
                    t.dyn_heading("post-terms", {"taxonomy": "product_cat", "separator": " · ", "link": ""}, "p", "cc-label"),
                    t.w("woocommerce-product-title", {"header_size": "h1"}),
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
        t.html(tpl_footer(PROD_DICT + WOO_CARDS_JS), "cc-footer"),
    ])]
    return export("produto-woocommerce", "Cacau Cosmo — Produto (Single Product)", "product", content)


# ---- CARRINHO --------------------------------------------------------
CART_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-cart-label': 'Cart', 't-cart-h1': 'Your small <span class="em">universe.</span>',
  't-cart-sub': 'Review your bars, apply a coupon and calculate shipping before checkout.',
  't-trust1-h': 'Small batches', 't-trust1-p': 'Bean-to-bar, handmade. When a batch is gone, it\\u2019s gone.',
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
            t.w("shortcode", {"shortcode": '[products skus="CC-SOL-55,CC-LUA-35,CC-SAT-80" columns="3" orderby="date" order="ASC"]'}),
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
    if(h1) h1.innerHTML=t('Pedido <span class="em">confirmado.</span>','Order <span class="em">confirmed.</span>');
    if(sub) sub.textContent=t('Obrigada! Enviamos os detalhes para o seu e-mail.','Thank you! We sent the details to your email.');
    var st=document.querySelectorAll('.js-co-steps span');
    st.forEach(function(s,i){ s.className='ok'; s.querySelector('i').textContent='✓'; });
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


# ---- POST ------------------------------------------------------------
SHARE_HTML = """<div class="cc-share">
  <small data-i18n="t-share">Compartilhar</small>
  <div class="cc-share__links">
    <a class="js-share" data-net="wa" href="#" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24"><use href="#ic-wa"></use></svg></a>
    <a class="js-share" data-net="mail" href="#" aria-label="E-mail"><svg viewBox="0 0 24 24"><use href="#ic-mail"></use></svg></a>
    <button class="js-copy" type="button" aria-label="Copiar link"><svg viewBox="0 0 24 24"><use href="#ic-star"></use></svg></button>
  </div>
</div>"""

POST_JS = """window.CC_DICT = Object.assign(window.CC_DICT||{}, {
  't-share': 'Share', 't-related': 'Keep reading',
  't-circ-label': 'The Circle', 't-circ-h2': 'The next ritual begins here.',
  't-circ-p': 'Small batches, letters from the Cosmos and invitations to what we\\u2019re creating.'
});
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
        t.con("cc-post-related", [
            t.w("heading", {"title": "Continue lendo", "header_size": "h2"}, "cc-related-title"),
            t.w("posts", {
                "_skin": "classic",
                "classic_columns": "3", "classic_columns_tablet": "2", "classic_columns_mobile": "1",
                "classic_posts_per_page": 3,
                "classic_thumbnail": "top", "classic_item_ratio": {"unit": "px", "size": 0.75},
                "classic_title_tag": "h3",
                "classic_show_excerpt": "yes", "classic_excerpt_length": 18,
                "classic_meta_data": ["date"],
                "classic_show_read_more": "yes", "classic_read_more_text": "Ler →",
                "posts_post_type": "post", "posts_exclude": ["current_post"],
                "orderby": "date", "order": "desc",
            }),
        ]),
        t.html(CIRCLE_HTML),
        t.html(tpl_footer(POST_JS), "cc-footer"),
    ])]
    return export("post-journal", "Cacau Cosmo — Post do Journal (Single Post)", "single-post", content)


if __name__ == "__main__":
    for slug in ("quem-somos", "contato"):
        print("ok", build_page(slug).relative_to(ROOT.parent))
    for fn in (build_loja, build_produto, build_carrinho, build_checkout, build_post):
        print("ok", fn().relative_to(ROOT.parent))
