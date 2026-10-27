#!/usr/bin/env python3
"""Monta as fotos de produto a partir das faces reais da embalagem
(fotos-reais/<produto>/frente.jpg, verso.jpg, lateral-2.jpg, topo.jpg, tampa.jpg).

Uso:  python3 cacau-cosmo/woocommerce/fotos_reais.py saturno [sol lua]
Saída: imagens/<produto>.jpg (principal), <produto>-frente.jpg, -verso.jpg, -tampa.jpg
"""
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

OUT = Path(__file__).resolve().parent
W, H = 1200, 1500


def fundo():
    bg = Image.new("RGB", (W, H))
    d = ImageDraw.Draw(bg)
    a, b = (244, 237, 225), (230, 218, 203)
    for y in range(H):
        t = y / H
        d.line([(0, y), (W, y)], fill=tuple(int(a[i] + (b[i] - a[i]) * t) for i in range(3)))
    grao = Image.effect_noise((W, H), 18).convert("L").point(lambda v: 128 + (v - 128) // 3)
    return Image.blend(bg, Image.merge("RGB", (grao, grao, grao)), 0.05).convert("RGBA")


def coeffs(dst, src):
    """Coeficientes de perspectiva que levam os 4 pontos dst aos 4 pontos src."""
    import numpy as np
    m = []
    for (x, y), (u, v) in zip(dst, src):
        m.append([x, y, 1, 0, 0, 0, -u * x, -u * y])
        m.append([0, 0, 0, x, y, 1, -v * x, -v * y])
    a = np.array(m, dtype=float)
    b = np.array([p for uv in src for p in uv], dtype=float)
    return np.linalg.solve(a, b).tolist()


def warp(img, quad):
    """Cola img (retangular) no quadrilátero quad = [tl, tr, br, bl] do quadro W×H."""
    w, h = img.size
    src = [(0, 0), (w, 0), (w, h), (0, h)]
    return img.convert("RGBA").transform((W, H), Image.PERSPECTIVE, coeffs(quad, src), Image.BICUBIC)


def escurecer(img, f):
    return Image.eval(img.convert("RGB"), lambda v: int(v * f))


def caixa(slug):
    pasta = OUT / "fotos-reais" / slug
    frente = Image.open(pasta / "frente.jpg")
    lado = Image.open(pasta / ("lateral-2.jpg" if (pasta / "lateral-2.jpg").exists() else "lateral-1.jpg"))
    topo = Image.open(pasta / "topo.jpg")

    fw = 560
    fh = int(fw * frente.height / frente.width)       # proporção real da caixa
    dw = int(fw * lado.width / frente.width)           # profundidade real
    dx, dy = int(dw * .62), int(dw * .42)              # recuo da perspectiva
    x0 = (W - fw - dx) // 2
    y0 = (H - fh + dy) // 2 + 10

    bg = fundo()
    # sombra
    sh = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh).polygon([(x0 + 40, y0 + fh - 10), (x0 + fw + dx + 60, y0 + fh - dy - 10),
                                (x0 + fw + dx + 120, y0 + fh + 40), (x0 + 70, y0 + fh + 60)], fill=(60, 36, 20, 140))
    bg.alpha_composite(sh.filter(ImageFilter.GaussianBlur(34)))
    sh2 = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh2).rectangle([x0 + 10, y0 + fh - 6, x0 + fw + dx, y0 + fh + 8], fill=(40, 24, 14, 120))
    bg.alpha_composite(sh2.filter(ImageFilter.GaussianBlur(8)))

    # lateral (direita) e topo, em perspectiva e um pouco mais escuros
    bg.alpha_composite(warp(escurecer(lado, .72), [(x0 + fw, y0), (x0 + fw + dx, y0 - dy),
                                                    (x0 + fw + dx, y0 + fh - dy), (x0 + fw, y0 + fh)]))
    bg.alpha_composite(warp(escurecer(topo, .9), [(x0 + dx, y0 - dy), (x0 + fw + dx, y0 - dy),
                                                   (x0 + fw, y0), (x0, y0)]))
    # frente
    f = frente.convert("RGBA").resize((fw, fh), Image.LANCZOS)
    bg.alpha_composite(f, (x0, y0))
    # brilho suave na frente
    luz = Image.new("RGBA", (fw, fh), (0, 0, 0, 0))
    dl = ImageDraw.Draw(luz)
    for x in range(fw):
        a = int(26 * max(0, 1 - abs(x / fw - .18) / .22))
        dl.line([(x, 0), (x, fh)], fill=(255, 255, 255, a))
    bg.alpha_composite(luz, (x0, y0))
    return bg.convert("RGB")


def plana(img_path, alvo_w=760):
    """Face da embalagem sozinha, centralizada no fundo creme, com sombra."""
    img = Image.open(img_path).convert("RGBA")
    w = alvo_w
    h = int(w * img.height / img.width)
    if h > 1260:
        h = 1260
        w = int(h * img.width / img.height)
    img = img.resize((w, h), Image.LANCZOS)
    bg = fundo()
    x, y = (W - w) // 2, (H - h) // 2
    sh = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    ImageDraw.Draw(sh).rectangle([x + 20, y + 30, x + w + 20, y + h + 26], fill=(60, 36, 20, 110))
    bg.alpha_composite(sh.filter(ImageFilter.GaussianBlur(30)))
    bg.alpha_composite(img, (x, y))
    return bg.convert("RGB")


if __name__ == "__main__":
    (OUT / "imagens").mkdir(exist_ok=True)
    for slug in sys.argv[1:] or ["saturno"]:
        pasta = OUT / "fotos-reais" / slug
        caixa(slug).save(OUT / "imagens" / f"{slug}.jpg", quality=92, optimize=True, progressive=True)
        print("ok", f"imagens/{slug}.jpg")
        for face in ("frente", "verso", "tampa"):
            if (pasta / f"{face}.jpg").exists():
                plana(pasta / f"{face}.jpg", 900 if face == "tampa" else 760).save(
                    OUT / "imagens" / f"{slug}-{face}.jpg", quality=92, optimize=True, progressive=True)
                print("ok", f"imagens/{slug}-{face}.jpg")
