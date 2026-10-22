#!/usr/bin/env python3
"""Gera o CSV de importação do WooCommerce e as imagens provisórias
da Coleção Cosmo (Sol, Lua, Saturno).

Uso:  python3 cacau-cosmo/woocommerce/produtos.py [pasta-das-fontes]
A pasta das fontes deve ter Recoleta-Bold.ttf, Recoleta-Light.ttf e PINOPOLIS.TTF
(as mesmas do site). Sem ela, as imagens usam DejaVu.
"""
import csv
import math
import random
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

OUT = Path(__file__).resolve().parent
FONTS = Path(sys.argv[1]) if len(sys.argv) > 1 else None

NAVY, CREAM, GOLD, PINK, BROWN, BLUSH = "#00062E", "#F1E8D0", "#C9A84C", "#D293A8", "#4A2C1D", "#E9D9D2"

# Envio e conservação: entra no fim da descrição de todos os produtos.
ENTREGA = """
<h3>Entrega</h3>
<ul>
<li>Enviamos para todo o Brasil. Frete e prazo são calculados no carrinho pelo seu CEP.</li>
<li>Os pedidos são despachados em até 2 dias úteis após a confirmação do pagamento.</li>
<li>Embalagem térmica nos meses mais quentes. Em ondas de calor, podemos segurar o envio por alguns dias para proteger o chocolate &mdash; avisamos por e-mail.</li>
</ul>
<h3>Como guardar</h3>
<p>Em local fresco, seco e longe da luz, idealmente entre 16&nbsp;°C e 20&nbsp;°C. Evite a geladeira: umidade e odores alteram textura e sabor. Se a barra ficar esbranquiçada, é só a manteiga de cacau aflorando &mdash; continua boa para comer.</p>
"""

PRODUTOS = [
    {
        "sku": "CC-SOL-55",
        "nome": "Sol — Chocolate ao Leite 55%",
        "slug": "sol",
        "preco": "42.00",
        "categorias": "Barras > Ao leite, Coleção Cosmo",
        "tags": "ao leite, bean-to-bar, caramelo, presente",
        "curta": "<p>A barra mais calorosa do nosso céu. Ao leite com 55% de cacau, macia e luminosa, com notas de caramelo, mel e castanhas tostadas.</p>"
                 "<p><strong>80 g · bean-to-bar · pequenos lotes</strong></p>",
        "descricao": """<p><em>Tudo começa com luz.</em></p>
<p>Sol é o centro da Coleção Cosmo: um chocolate ao leite intenso, com 55% de cacau, que derrete devagar e aquece. Torramos o cacau de forma suave para preservar o lado doce e tostado do grão, e refinamos por longas horas até a textura ficar sedosa.</p>
<h3>Notas de degustação</h3>
<p>Caramelo, mel, castanhas tostadas e um final longo de leite cozido.</p>
<h3>Para acompanhar</h3>
<p>Café coado, chá preto, vinho do Porto tawny ou simplesmente uma tarde sem pressa.</p>
<h3>Ingredientes</h3>
<p>Cacau, açúcar, leite em pó integral e manteiga de cacau.</p>
<p><strong>Alérgicos:</strong> contém leite. Pode conter castanhas e amendoim.</p>
""",
        "atributos": [("Cacau", "55%"), ("Tipo", "Ao leite"), ("Peso", "80 g"),
                      ("Notas", "Caramelo | Mel | Castanhas tostadas"), ("Origem do cacau", "Brasil")],
        "arte": "sol",
        "subtitulo": "Chocolate ao leite 55%",
    },
    {
        "sku": "CC-LUA-35",
        "nome": "Lua — Chocolate Branco 35%",
        "slug": "lua",
        "preco": "42.00",
        "categorias": "Barras > Branco, Coleção Cosmo",
        "tags": "chocolate branco, manteiga de cacau, baunilha, presente",
        "curta": "<p>A face clara do cacau. Chocolate branco com 35% de manteiga de cacau prensada por nós, delicado, cremoso e pouco doce, com notas de baunilha e flor de laranjeira.</p>"
                 "<p><strong>80 g · bean-to-bar · pequenos lotes</strong></p>",
        "descricao": """<p><em>O que a noite revela.</em></p>
<p>Lua é a nossa leitura do chocolate branco: menos açúcar, mais cacau. Usamos manteiga de cacau com aroma, que guarda a memória do grão, e o resultado é uma barra cremosa, clara e surpreendentemente complexa.</p>
<h3>Notas de degustação</h3>
<p>Baunilha, leite fresco, flor de laranjeira e um fundo suave de cacau.</p>
<h3>Para acompanhar</h3>
<p>Chá verde, espumante brut, frutas vermelhas ou um café espresso curto.</p>
<h3>Ingredientes</h3>
<p>Manteiga de cacau, açúcar, leite em pó integral e baunilha.</p>
<p><strong>Alérgicos:</strong> contém leite. Pode conter castanhas e amendoim.</p>
""",
        "atributos": [("Cacau", "35% (manteiga de cacau)"), ("Tipo", "Branco"), ("Peso", "80 g"),
                      ("Notas", "Baunilha | Leite fresco | Flor de laranjeira"), ("Origem do cacau", "Brasil")],
        "arte": "lua",
        "subtitulo": "Chocolate branco 35%",
    },
    {
        "sku": "CC-SAT-80",
        "nome": "Saturno — Chocolate Amargo 80%",
        "slug": "saturno",
        "preco": "46.00",
        "categorias": "Barras > Intenso, Coleção Cosmo",
        "tags": "amargo, 80%, bean-to-bar, sem leite",
        "curta": "<p>Denso, profundo e cheio de camadas, como os anéis de Saturno. Chocolate amargo com 80% de cacau e só dois ingredientes, com notas de frutas escuras, café e terra molhada.</p>"
                 "<p><strong>80 g · bean-to-bar · pequenos lotes</strong></p>",
        "descricao": """<p><em>O tempo em camadas.</em></p>
<p>Saturno é a barra para quem gosta de ir fundo. Com 80% de cacau e apenas cacau e açúcar, ela mostra o grão sem filtros: fermentação, terra e fruta. Cada pedaço se abre em camadas, como os anéis do planeta que lhe dá nome.</p>
<h3>Notas de degustação</h3>
<p>Frutas escuras, café torrado, especiarias e terra molhada, com um final longo e seco.</p>
<h3>Para acompanhar</h3>
<p>Vinho tinto encorpado, whisky, café filtrado ou queijos curados.</p>
<h3>Ingredientes</h3>
<p>Cacau e açúcar.</p>
<p><strong>Alérgicos:</strong> pode conter leite, castanhas e amendoim (produzido no mesmo ambiente).</p>
""",
        "atributos": [("Cacau", "80%"), ("Tipo", "Amargo"), ("Peso", "80 g"),
                      ("Notas", "Frutas escuras | Café | Terra molhada"), ("Origem do cacau", "Brasil")],
        "arte": "saturno",
        "subtitulo": "Chocolate amargo 80%",
    },
]


# ---------------------------------------------------------------- CSV
def gerar_csv(urls_imagens=None):
    max_attr = max(len(p["atributos"]) for p in PRODUTOS)
    cab = ["Type", "SKU", "Name", "Slug", "Published", "Is featured?", "Visibility in catalog",
           "Short description", "Description", "Tax status", "In stock?", "Backorders allowed?",
           "Sold individually?", "Weight (kg)", "Length (cm)", "Width (cm)", "Height (cm)",
           "Allow customer reviews?", "Regular price", "Categories", "Tags", "Shipping class", "Images"]
    for i in range(1, max_attr + 1):
        cab += [f"Attribute {i} name", f"Attribute {i} value(s)", f"Attribute {i} visible", f"Attribute {i} global"]

    path = OUT / "produtos-cacau-cosmo.csv"
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(cab)
        for p in PRODUTOS:
            linha = ["simple", p["sku"], p["nome"], p["slug"], 1, 1, "visible",
                     p["curta"], p["descricao"].strip() + "\n" + ENTREGA.strip(), "taxable", 1, 0,
                     0, "0.12", "17", "8", "1.5",
                     1, p["preco"], p["categorias"], p["tags"], "Chocolate",
                     (urls_imagens or {}).get(p["slug"], "")]
            for nome, valor in p["atributos"]:
                linha += [nome, valor, 1, 0]
            linha += [""] * (len(cab) - len(linha))
            w.writerow(linha)
    return path


# ---------------------------------------------------------------- imagens
def fonte(nome, tam, reserva="DejaVuSerif-Bold.ttf"):
    if FONTS and (FONTS / nome).exists():
        return ImageFont.truetype(str(FONTS / nome), tam)
    return ImageFont.truetype(f"/usr/share/fonts/truetype/dejavu/{reserva}", tam)


def hexrgb(h, a=255):
    h = h.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4)) + (a,)


def gradiente(w, h, c1, c2):
    img = Image.new("RGBA", (w, h))
    d = ImageDraw.Draw(img)
    a, b = hexrgb(c1), hexrgb(c2)
    for y in range(h):
        t = y / h
        d.line([(0, y), (w, y)], fill=tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(4)))
    return img


def esfera(diam, luz, meio, sombra):
    """Planeta com luz vinda do canto superior esquerdo."""
    s = Image.new("RGBA", (diam, diam), (0, 0, 0, 0))
    px = s.load()
    cl, cm, cs = hexrgb(luz), hexrgb(meio), hexrgb(sombra)
    r = diam / 2
    lx, ly = r * 0.62, r * 0.55
    for y in range(diam):
        for x in range(diam):
            dx, dy = x - r, y - r
            if dx * dx + dy * dy > r * r:
                continue
            t = min(1, math.hypot(x - lx, y - ly) / (diam * 0.95))
            if t < .5:
                k = t / .5
                c = tuple(int(cl[i] + (cm[i] - cl[i]) * k) for i in range(3))
            else:
                k = (t - .5) / .5
                c = tuple(int(cm[i] + (cs[i] - cm[i]) * k) for i in range(3))
            px[x, y] = c + (255,)
    return s


def estrelas(img, n, seed, cor=(241, 232, 208)):
    rnd = random.Random(seed)
    d = ImageDraw.Draw(img)
    w, h = img.size
    for _ in range(n):
        x, y, m = rnd.random() * w, rnd.random() * h, rnd.random()
        rad = .6 + m ** 3 * 2.6
        c = (201, 168, 76, 230) if m > .95 else cor + (int(50 + m * 120),)
        d.ellipse([x - rad, y - rad, x + rad, y + rad], fill=c)


def brilho(diam, cor, alpha):
    g = Image.new("RGBA", (diam, diam), (0, 0, 0, 0))
    ImageDraw.Draw(g).ellipse([diam * .2, diam * .2, diam * .8, diam * .8], fill=hexrgb(cor, alpha))
    return g.filter(ImageFilter.GaussianBlur(diam * .12))


def estrela4(d, cx, cy, r, cor):
    pts = []
    for i in range(16):
        ang = i * math.pi / 8 - math.pi / 2
        rr = r if i % 4 == 0 else (r * .16 if i % 2 else r * .3)
        pts.append((cx + rr * math.cos(ang), cy + rr * math.sin(ang)))
    d.polygon(pts, fill=cor)


def arte(p):
    W, H = 1200, 1500
    tipo = p["arte"]
    if tipo == "sol":
        img = gradiente(W, H, "#1B1008", "#3A2216")
        estrelas(img, 260, 1)
        img.alpha_composite(brilho(1100, "#E9A54C", 120), (50, 120))
        img.alpha_composite(esfera(560, "#F6D58A", "#D9973F", "#7A3E17"), (320, 390))
        texto, sub_cor = CREAM, GOLD
    elif tipo == "lua":
        img = gradiente(W, H, "#0A1240", "#00062E")
        estrelas(img, 320, 2)
        img.alpha_composite(brilho(1000, PINK, 70), (100, 160))
        lua = esfera(540, "#F3ECE6", "#C4B4B6", "#4A4460")
        crat = Image.new("RGBA", lua.size, (0, 0, 0, 0))
        d = ImageDraw.Draw(crat)
        rnd = random.Random(7)
        for _ in range(9):  # crateras
            x, y, r = rnd.randint(130, 400), rnd.randint(130, 400), rnd.randint(14, 44)
            d.ellipse([x - r, y - r, x + r, y + r], fill=(110, 100, 120, 40))
        crat = crat.filter(ImageFilter.GaussianBlur(3))
        crat.putalpha(Image.composite(crat.getchannel("A"), Image.new("L", lua.size, 0), lua.getchannel("A")))
        lua.alpha_composite(crat)
        img.alpha_composite(lua, (330, 400))
        texto, sub_cor = CREAM, PINK
    else:
        img = gradiente(W, H, "#00062E", "#0A1240")
        estrelas(img, 360, 3)
        img.alpha_composite(brilho(1000, "#8E8296", 60), (100, 160))
        cx, cy, R = 600, 680, 250
        aneis = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        da = ImageDraw.Draw(aneis)
        for k, (rx, ry, cor, lw) in enumerate([(470, 120, GOLD, 5), (420, 104, CREAM, 3), (380, 92, "#C4A57A", 7)]):
            da.ellipse([cx - rx, cy - ry, cx + rx, cy + ry], outline=hexrgb(cor, 200 - k * 30), width=lw)
        aneis = aneis.rotate(-14, center=(cx, cy), resample=Image.BICUBIC)
        traseiro = aneis.crop((0, 0, W, cy))
        img.alpha_composite(traseiro, (0, 0))
        img.alpha_composite(esfera(2 * R, "#E8CFA0", "#B3825A", "#3A2216"), (cx - R, cy - R))
        frente = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        frente.paste(aneis.crop((0, cy, W, H)), (0, cy))
        img.alpha_composite(frente)
        texto, sub_cor = CREAM, GOLD

    d = ImageDraw.Draw(img)
    marca = fonte("PINOPOLIS.TTF", 30, "DejaVuSerif.ttf")
    d.text((W / 2, 120), "C A C A U   C O S M O", font=marca, fill=hexrgb(texto, 220), anchor="mm")
    estrela4(d, W / 2, 190, 16, hexrgb(GOLD))

    d.text((W / 2, 1180), p["nome"].split(" — ")[0], font=fonte("Recoleta-Bold.ttf", 150), fill=hexrgb(texto), anchor="mm")
    d.line([(W / 2 - 60, 1285), (W / 2 + 60, 1285)], fill=hexrgb(sub_cor, 200), width=2)
    d.text((W / 2, 1340), p["subtitulo"].upper(), font=fonte("PINOPOLIS.TTF", 34, "DejaVuSerif.ttf"),
           fill=hexrgb(sub_cor), anchor="mm")
    d.text((W / 2, 1400), "80 g  ·  bean-to-bar  ·  Brasil", font=fonte("PINOPOLIS.TTF", 28, "DejaVuSerif.ttf"),
           fill=hexrgb(texto, 170), anchor="mm")

    path = OUT / "imagens" / f"{p['slug']}.jpg"
    path.parent.mkdir(exist_ok=True)
    img.convert("RGB").save(path, quality=90, optimize=True, progressive=True)
    return path


if __name__ == "__main__":
    for p in PRODUTOS:
        print("ok", arte(p).relative_to(OUT.parent.parent))
    print("ok", gerar_csv().relative_to(OUT.parent.parent))
