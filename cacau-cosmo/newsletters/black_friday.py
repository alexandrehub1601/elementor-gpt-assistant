#!/usr/bin/env python3
"""Black Friday Cacau Cosmo (até 29/11, 23h59): 4 e-mails no mesmo layout das newsletters.
Uso: python3 cacau-cosmo/newsletters/black_friday.py  ->  bf-1 … bf-4 .html e black-friday.zip"""
import zipfile
from gerar import OUT, SITE, UP, NAVY, CREAM, GOLD, WINE, SANS, p, h2, botao, citacao, passos, produto, email

URL = SITE + "/produto/saturno-47-100g/"
CARD = UP + "/2026/10/cc-card-saturno-100.png"
PRECO = ('<span style="text-decoration:line-through;opacity:.6">R$ 430,00</span> &nbsp;por&nbsp; '
         '<strong style="font-size:22px">R$ 397,00</strong>')


def selo(texto):
    return (f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr>'
            f'<td style="background:{NAVY};border-radius:4px;padding:16px 20px;text-align:center;font-family:{SANS};font-size:12px;'
            f'letter-spacing:3px;text-transform:uppercase;font-weight:bold;color:{GOLD}">{texto}</td></tr></table>')


def oferta(cta="Garantir o meu Saturno"):
    return produto(CARD, "Saturno 47% · 100 g", "Black Friday", PRECO + "<br>Suave, cremoso, lento. Quatro ingredientes, vegano.",
                   URL, cta, "Válido até 29/11, às 23h59, ou enquanto durar o lote.")


BF = [
    ("bf-1-comecou", email(
        "Black Friday no Círculo: o Saturno com preço especial",
        "De R$ 430 por R$ 397, só até 29/11.",
        "Black Friday", "Black Friday, no tempo do cacau.",
        p("A gente não faz liquidação de pressa. Mas nesta Black Friday quisemos deixar um presente para quem faz parte do Círculo.")
        + selo("Até 29/11, às 23h59")
        + oferta()
        + p("Saturno é o planeta do tempo, e esta barra pede exatamente isso: tempo. Adoçada com açúcar de coco, "
            "com aveia e shiitake na receita, é suave e cremosa, feita para derreter devagar.")
        + citacao("&ldquo;Que minhas raízes cresçam profundas.&rdquo;"),
        imagem=(UP + "/2026/10/cacau-cosmo-saturno-tampa.jpg", "Tampa da caixa do Saturno com a intenção do ritual", NAVY))),
    ("bf-2-ritual", email(
        "Um ritual para desacelerar na Black Friday",
        "Enquanto todo mundo corre, o Saturno pede calma. E está com preço especial.",
        "Black Friday", "Enquanto o mundo corre, um pedaço de calma.",
        p("Black Friday costuma ser sinônimo de pressa. A gente propõe o contrário: um pedaço de chocolate e alguns minutos só seus.")
        + h2("O ritual do Saturno")
        + passos(["Quebre um pedaço pequeno.", "Respire o aroma antes de provar.",
                  "Deixe derreter na boca, devagar, sem morder.", "Repare no que muda do primeiro ao último instante."])
        + oferta("Quero o meu Saturno")
        + p("O preço especial vale até sábado, 29/11, às 23h59."),
        imagem=(UP + "/2026/11/cc-home-cacau.jpg", "Frutos de cacau vermelhos, de perto"))),
    ("bf-3-ultimos-dias", email(
        "Últimos dias: Saturno por R$ 397",
        "A Black Friday do Círculo termina sábado, 29/11, às 23h59.",
        "Últimos dias", "Últimos dias de Black Friday.",
        p("Passando para lembrar: o Saturno 100 g está de R$ 430 por R$ 397 só até sábado.")
        + selo("Termina sábado, 29/11, às 23h59")
        + oferta()
        + p("Nossos lotes são pequenos e feitos à mão. Quando acaba, acaba, e o próximo leva o tempo que o chocolate pede.")
        + p("Também é um bom presente de Natal adiantado: cada caixa vem com uma intenção na tampa."),
        imagem=(UP + "/2026/11/cc-home-barra.jpg", "Barra de chocolate Cacau Cosmo com relevo do logo", "#2a1a3a"))),
    ("bf-4-ultimo-dia", email(
        "ÚLTIMO DIA: a Black Friday termina hoje às 23h59",
        "Hoje é a última chance de levar o Saturno por R$ 397.",
        "Último dia", "É hoje. Último dia.",
        selo("Termina hoje, 29/11, às 23h59")
        + p("Este é o último lembrete: à meia-noite o Saturno 100 g volta ao preço normal.")
        + oferta("Garantir agora")
        + p("Obrigada por fazer parte do Círculo e por escolher um chocolate que respeita o tempo de quem planta, de quem faz e de quem prova.")
        + citacao("O tempo é ingrediente."),
        imagem=(UP + "/2026/10/cacau-cosmo-saturno-tampa.jpg", "Tampa da caixa do Saturno com a intenção do ritual", NAVY))),
]

if __name__ == "__main__":
    with zipfile.ZipFile(OUT / "black-friday.zip", "w", zipfile.ZIP_DEFLATED) as z:
        for nome, html in BF:
            f = OUT / f"{nome}.html"
            f.write_text(html, encoding="utf-8"); z.write(f, f.name); print("ok", f.name)
