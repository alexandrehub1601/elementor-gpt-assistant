#!/usr/bin/env python3
"""Monta as fotos de produto a partir das faces reais da embalagem
(fotos-reais/<produto>/frente.jpg, lateral-2.jpg, topo.jpg, tampa.jpg).

Fundo azul Cacau Cosmo; a embalagem fica com a cor original (Lua preta,
Sol ocre, Saturno azul). A caixa de 100 g é a mesma arte 1,32× maior e
um pouco mais funda, como na página 3D da coleção.

Uso:  python3 cacau-cosmo/woocommerce/fotos_reais.py [lua sol saturno]
Saída: imagens/<produto>-33g.jpg, <produto>-100g.jpg (principais),
       <produto>-frente.jpg e <produto>-tampa.jpg (galeria)
"""
import random
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

OUT = Path(__file__).resolve().parent
W, H = 1200, 1500

# proporções da caixa na página 3D: altura = 1,4375 × largura; fundo = 0,2667 × largura
ALTURA, FUNDO = 1.4375, 0.2667
TAMANHOS = {"33g": (560, 1.0), "100g": (740, 1.12)}   # largura da frente (px), fundo extra


def fundo(seed):
    """Azul Cacau Cosmo com brilho central e estrelas."""
    bg = Image.new("RGB", (W, H), (0, 6, 46))
    glow = Image.new("RGB", (W, H), (0, 0, 0))
    ImageDraw.Draw(glow).ellipse([W * .12, H * .16, W * .88, H * .78], fill=(22, 30, 92))
    bg = Image.blend(bg, glow.filter(ImageFilter.GaussianBlur(170)), .55)
    bg = Image.composite(bg, Image.new("RGB", (W, H), (0, 6, 46)), Image.new("L", (W, H), 255))
    d = ImageDraw.Draw(bg, "RGBA")
    rnd = random.Random(seed)
    for _ in range(420):
        x, y, m = rnd.random() * W, rnd.random() * H, rnd.random()
        r = .6 + m ** 3 * 2.4
        cor = (201, 168, 106, 220) if m > .94 else (239, 230, 210, int(40 + m * 130))
        d.ellipse([x - r, y - r, x + r, y + r], fill=cor)
    return bg.convert("RGBA")


def coeffs(dst, src):
    import numpy as np
    m = []
    for (x, y), (u, v) in zip(dst, src):
        m.append([x, y, 1, 0, 0, 0, -u * x, -u * y])
        m.append([0, 0, 0, x, y, 1, -v * x, -v * y])
    return np.linalg.solve(np.array(m, dtype=float), np.array([p for uv in src for p in uv], dtype=float)).tolist()


def warp(img, quad):
    w, h = img.size
    return img.convert("RGBA").transform((W, H), Image.PERSPECTIVE, coeffs(quad, [(0, 0), (w, 0), (w, h), (0, h)]), Image.BICUBIC)


def escurecer(img, f):
    return Image.eval(img.convert("RGB"), lambda v: int(v * f))


def caixa(slug, tamanho):
    pasta = OUT / "fotos-reais" / slug
    frente = Image.open(pasta / "frente.jpg")
    lado = Image.open(pasta / ("lateral-2.jpg" if (pasta / "lateral-2.jpg").exists() else "lateral-1.jpg"))
    topo = Image.open(pasta / "topo.jpg")

    fw, extra = TAMANHOS[tamanho]
    fh = int(fw * ALTURA)
    dw = int(fw * FUNDO * extra)
    dx, dy = int(dw * .62), int(dw * .42)
    x0 = (W - fw - dx) // 2
    y0 = (H - fh + dy) // 2 + 30

    bg = fundo(slug + tamanho)
    # brilho atrás da caixa e sombra no chão
    halo = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(halo).ellipse([x0 - 90, y0 - 60, x0 + fw + dx + 90, y0 + fh + 40], fill=(120, 130, 210, 48))
    bg.alpha_composite(halo.filter(ImageFilter.GaussianBlur(70)))
    sh = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh).ellipse([x0 - 20, y0 + fh - 30, x0 + fw + dx + 60, y0 + fh + 46], fill=(0, 0, 12, 200))
    bg.alpha_composite(sh.filter(ImageFilter.GaussianBlur(26)))

    bg.alpha_composite(warp(escurecer(lado, .72), [(x0 + fw, y0), (x0 + fw + dx, y0 - dy),
                                                    (x0 + fw + dx, y0 + fh - dy), (x0 + fw, y0 + fh)]))
    bg.alpha_composite(warp(escurecer(topo, 1.08), [(x0 + dx, y0 - dy), (x0 + fw + dx, y0 - dy),
                                                     (x0 + fw, y0), (x0, y0)]))
    bg.alpha_composite(frente.convert("RGBA").resize((fw, fh), Image.LANCZOS), (x0, y0))
    # brilho suave na frente
    luz = Image.new("RGBA", (fw, fh), (0, 0, 0, 0))
    dl = ImageDraw.Draw(luz)
    for x in range(fw):
        a = int(22 * max(0, 1 - abs(x / fw - .18) / .22))
        dl.line([(x, 0), (x, fh)], fill=(255, 255, 255, a))
    bg.alpha_composite(luz, (x0, y0))
    # aresta clara entre frente e lateral
    ImageDraw.Draw(bg).line([(x0 + fw, y0), (x0 + fw, y0 + fh)], fill=(255, 255, 255, 40), width=2)
    return bg.convert("RGB")


def plana(slug, face, alvo_w):
    img = Image.open(OUT / "fotos-reais" / slug / f"{face}.jpg").convert("RGBA")
    w = alvo_w
    h = int(w * img.height / img.width)
    if h > 1240:
        h = 1240
        w = int(h * img.width / img.height)
    img = img.resize((w, h), Image.LANCZOS)
    bg = fundo(slug + face)
    x, y = (W - w) // 2, (H - h) // 2
    sh = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh).rectangle([x + 16, y + 26, x + w + 16, y + h + 22], fill=(0, 0, 12, 170))
    bg.alpha_composite(sh.filter(ImageFilter.GaussianBlur(26)))
    bg.alpha_composite(img, (x, y))
    ImageDraw.Draw(bg).rectangle([x, y, x + w - 1, y + h - 1], outline=(239, 230, 210, 46), width=1)
    return bg.convert("RGB")


def salvar(img, nome):
    img.save(OUT / "imagens" / nome, quality=92, optimize=True, progressive=True)
    print("ok", f"imagens/{nome}")


if __name__ == "__main__":
    (OUT / "imagens").mkdir(exist_ok=True)
    for slug in sys.argv[1:] or ["saturno", "lua", "sol"]:
        for tam in TAMANHOS:
            salvar(caixa(slug, tam), f"{slug}-{tam}.jpg")
        salvar(plana(slug, "frente", 720), f"{slug}-frente.jpg")
        salvar(plana(slug, "tampa", 960), f"{slug}-tampa.jpg")
