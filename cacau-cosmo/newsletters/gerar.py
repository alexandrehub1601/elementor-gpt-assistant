#!/usr/bin/env python3
"""Gera as newsletters do Círculo em HTML de e-mail (tabelas e estilos inline),
prontas para o Mailchimp: Campanha → E-mail → "Code your own" → "Paste in code".

Uso:  python3 cacau-cosmo/newsletters/gerar.py
Saída: newsletters/01-os-cacaus.html … 06-ano-novo.html e newsletters.zip

Tags do Mailchimp usadas: *|MC_PREVIEW_TEXT|* (texto de pré-visualização),
*|FNAME|* (nome), *|UNSUB|* (descadastro) e *|LIST:ADDRESSLINE|* (endereço obrigatório).
Trechos entre [colchetes] são para completar antes de enviar.
"""
import zipfile
from html import escape
from pathlib import Path

OUT = Path(__file__).resolve().parent
SITE = "https://cacaucosmo.com.br"
UP = SITE + "/wp-content/uploads"
LOGO = UP + "/2026/08/mark-bege-transparente.png"

NAVY, CREAM, PAPER, GOLD, BROWN, PINK, WINE = "#00062E", "#F1E8D0", "#EFE8DC", "#C9A84C", "#4A2C1D", "#D293A8", "#8E3B5A"
SERIF = "Georgia,'Times New Roman',serif"
SANS = "Helvetica,Arial,sans-serif"


# ---- blocos -------------------------------------------------------------
def p(texto, **kw):
    cor = kw.get("cor", NAVY)
    return (f'<p style="margin:0 0 18px;font-family:{SERIF};font-size:17px;line-height:1.7;color:{cor}">{texto}</p>')


def label(texto, cor=GOLD):
    return (f'<p style="margin:0 0 12px;font-family:{SANS};font-size:11px;letter-spacing:4px;text-transform:uppercase;'
            f'font-weight:bold;color:{cor}">{texto}</p>')


def h1(texto, cor=NAVY):
    return (f'<h1 style="margin:0 0 20px;font-family:{SERIF};font-size:34px;line-height:1.15;font-weight:bold;color:{cor}">{texto}</h1>')


def h2(texto):
    return (f'<h2 style="margin:30px 0 14px;font-family:{SERIF};font-size:24px;line-height:1.2;font-weight:bold;color:{NAVY}">{texto}</h2>')


def botao(texto, url, fundo=NAVY, cor=CREAM):
    return (f'<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 8px"><tr>'
            f'<td style="background:{fundo};border-radius:3px">'
            f'<a href="{url}" target="_blank" style="display:inline-block;padding:16px 24px;font-family:{SANS};font-size:11px;white-space:nowrap;'
            f'letter-spacing:2px;text-transform:uppercase;font-weight:bold;color:{cor};text-decoration:none">{texto}</a></td></tr></table>')


def foto(src, alt, fundo=BROWN):
    return (f'<tr><td style="padding:0;background:{fundo}"><img src="{src}" width="600" alt="{escape(alt)}" '
            f'style="display:block;width:100%;max-width:600px;height:auto;border:0"></td></tr>')


def citacao(texto):
    return (f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 26px"><tr>'
            f'<td style="border-top:1px solid {GOLD};border-bottom:1px solid {GOLD};padding:20px 6px;text-align:center;'
            f'font-family:{SERIF};font-style:italic;font-size:21px;line-height:1.45;color:{BROWN}">{texto}</td></tr></table>')


def passos(itens):
    linhas = "".join(
        f'<tr><td width="40" valign="top" style="padding:0 0 14px"><div style="width:28px;height:28px;line-height:28px;border-radius:14px;'
        f'background:{NAVY};color:{CREAM};text-align:center;font-family:{SANS};font-size:12px;font-weight:bold">{i}</div></td>'
        f'<td valign="top" style="padding:3px 0 14px;font-family:{SERIF};font-size:16px;line-height:1.55;color:{NAVY}">{t}</td></tr>'
        for i, t in enumerate(itens, 1))
    return f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 10px">{linhas}</table>'


def produto(png, nome, linha, texto, url, cta, nota=""):
    """Caixa sem fundo sobre o papel, com nome, perfil e botão."""
    return (f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 24px;background:{PAPER};border-radius:6px">'
            f'<tr><td class="cc-col" width="220" valign="middle" style="padding:20px 10px 20px 20px;text-align:center">'
            f'<img src="{png}" width="190" alt="Caixa do {escape(nome)}" style="display:block;width:190px;max-width:100%;height:auto;margin:0 auto;border:0"></td>'
            f'<td class="cc-col" valign="middle" style="padding:24px 24px 24px 10px">'
            f'<p style="margin:0 0 6px;font-family:{SANS};font-size:10px;letter-spacing:3px;text-transform:uppercase;font-weight:bold;color:{BROWN}">{linha}</p>'
            f'<p style="margin:0 0 10px;font-family:{SERIF};font-size:26px;font-weight:bold;line-height:1.1;color:{NAVY}">{nome}</p>'
            f'<p style="margin:0;font-family:{SERIF};font-size:15px;line-height:1.6;color:{NAVY}">{texto}</p>'
            + (f'<p style="margin:10px 0 0;font-family:{SANS};font-size:11px;letter-spacing:1px;color:{WINE}">{nota}</p>' if nota else "")
            + botao(cta, url) + '</td></tr></table>')


def assinatura():
    return (p("Com carinho,<br><strong>Nicole e todo o Cosmo</strong>")
            + f'<p style="margin:0;font-family:{SANS};font-size:11px;letter-spacing:3px;text-transform:uppercase;color:{GOLD}">O tempo é ingrediente.</p>')


def email(assunto, preview, topo_label, titulo, corpo, imagem=None, fundo_topo=NAVY):
    hero = foto(*imagem) if imagem else ""
    return f"""<!doctype html>
<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>{escape(assunto)}</title>
<style>
  body{{margin:0;padding:0;background:{PAPER}}}
  a{{color:{BROWN}}}
  @media (max-width:620px){{
    .cc-wrap{{width:100%!important}}
    .cc-pad{{padding-left:24px!important;padding-right:24px!important}}
    .cc-col{{display:block!important;width:100%!important;padding:20px 22px!important;box-sizing:border-box}}
    h1{{font-size:28px!important}}
  }}
</style>
</head>
<body style="margin:0;padding:0;background:{PAPER}">
<!-- Assunto sugerido: {escape(assunto)} -->
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:{PAPER}">*|MC_PREVIEW_TEXT|*{escape(preview)}&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{PAPER}">
<tr><td align="center" style="padding:28px 12px">
<table role="presentation" class="cc-wrap" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:{CREAM}">
  <tr><td align="center" style="background:{fundo_topo};padding:34px 24px 28px">
    <a href="{SITE}" target="_blank" style="text-decoration:none"><img src="{LOGO}" width="54" alt="Cacau Cosmo" style="display:block;margin:0 auto 10px;border:0;width:54px;height:auto"></a>
    <p style="margin:0;font-family:{SANS};font-size:11px;letter-spacing:6px;color:{CREAM}">CACAU COSMO</p>
  </td></tr>
  {hero}
  <tr><td class="cc-pad" style="padding:44px 48px 18px">
    {label(topo_label)}
    {h1(titulo)}
    {p('*|IF:FNAME|*Olá, *|FNAME|*.*|ELSE:|*Olá.*|END:IF|*')}
    {corpo}
    {assinatura()}
  </td></tr>
  <tr><td class="cc-pad" style="padding:30px 48px 34px;border-top:1px solid rgba(0,6,46,.12)">
    <p style="margin:0 0 10px;font-family:{SANS};font-size:12px;line-height:1.7;color:{NAVY};opacity:.75">
      Você recebe este e-mail porque faz parte do Círculo da Cacau Cosmo.<br>
      <a href="{SITE}" style="color:{NAVY}">cacaucosmo.com.br</a> &nbsp;·&nbsp;
      <a href="https://instagram.com/cacaucosmo" style="color:{NAVY}">@cacaucosmo</a> &nbsp;·&nbsp;
      <a href="mailto:hello@cacaucosmo.com" style="color:{NAVY}">hello@cacaucosmo.com</a></p>
    <p style="margin:0;font-family:{SANS};font-size:11px;line-height:1.6;color:{NAVY};opacity:.55">
      *|LIST:ADDRESSLINE|*<br><a href="*|UNSUB|*" style="color:{NAVY}">Não quero mais receber</a></p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
"""


# ---- as 6 newsletters ---------------------------------------------------
CARD = UP + "/2026/10/cc-card-{}-100.png"
PROD = SITE + "/produto/{}/"

NEWS = []

NEWS.append(("01-os-cacaus", email(
    "De onde vem o seu chocolate",
    "Antes da barra, existe uma árvore, um fruto e muito tempo.",
    "Do fruto", "Antes do chocolate, existe a floresta.",
    p("Toda barra da Cacau Cosmo começa muito antes da cozinha. Começa numa árvore que dá frutos direto no tronco, "
      "à sombra de outras árvores, no tempo da floresta.")
    + h2("O que acontece até virar chocolate")
    + passos([
        "<strong>O fruto.</strong> Dentro de cada fruto de cacau há dezenas de sementes envoltas numa polpa branca e adocicada.",
        "<strong>A fermentação.</strong> As sementes descansam com a polpa por alguns dias. É aqui que nascem os aromas: "
        "sem fermentar, o cacau não teria gosto de chocolate.",
        "<strong>A secagem.</strong> Depois, as sementes secam devagar, até guardar o sabor sem perder nada pelo caminho.",
        "<strong>Os nibs.</strong> Torradas e quebradas, as sementes viram nibs, pedacinhos de cacau puro. "
        "É deles que a gente parte: <em>nibs to bar</em>.",
        "<strong>A barra.</strong> Na cozinha, em pequenos lotes, os nibs viram chocolate. Sem pressa, no tempo que ele pede.",
    ])
    + citacao("O tempo é ingrediente. Cada barra carrega o tempo que levou para existir.")
    + p("Nas próximas cartas, vamos apresentar cada astro do nosso céu: Saturno, Lua e Sol. "
        "Cada um com seu cacau, seu ritual e sua intenção.")
    + botao("Ler mais no Journal", SITE + "/journal/"),
    imagem=(UP + "/2026/11/cc-home-cacau.jpg", "Frutos de cacau vermelhos, de perto"))))

NEWS.append(("02-saturno", email(
    "Saturno: o chocolate que pede tempo",
    "47% cacau, suave e cremoso. O Ritual das Raízes.",
    "Ritual das Raízes", "Saturno, o chocolate que pede tempo.",
    p("Saturno é o planeta do tempo, e esta barra pede exatamente isso: tempo. Com 47% de cacau, adoçada com açúcar de coco "
      "e com aveia e shiitake na receita, é suave e cremosa, feita para derreter devagar.")
    + produto(CARD.format("saturno"), "Saturno 47%", "Suave · cremoso · lento",
              "Quatro ingredientes: cacau, açúcar de coco, aveia e shiitake. Vegano, em caixas de 33 g e 100 g.",
              PROD.format("saturno-47-100g"), "Conhecer o Saturno", "Contém aveia.")
    + h2("O ritual")
    + p("Ao abrir a caixa, a tampa guarda uma intenção para acompanhar o momento:")
    + citacao("&ldquo;Que minhas raízes cresçam profundas.&rdquo;")
    + passos(["Quebre um pedaço pequeno.", "Respire o aroma antes de provar.",
              "Deixe derreter na boca, devagar, sem morder.", "Repare no que muda do primeiro ao último instante."])
    + p("Para quem está chegando ao chocolate de verdade, o Saturno é a porta de entrada: acolhedor, redondo, sem pressa."),
    imagem=(UP + "/2026/10/cacau-cosmo-saturno-tampa.jpg", "Tampa da caixa do Saturno com a intenção do ritual", NAVY))))

NEWS.append(("03-lua", email(
    "Lua: inteira em todas as fases",
    "100% cacau, um único ingrediente. O Ritual dos Ciclos.",
    "Ritual dos Ciclos", "Lua, inteira em todas as fases.",
    p("A Lua não tem pressa de se mostrar inteira. Chocolate 100% cacau, com um único ingrediente: o próprio cacau, sem açúcar. "
      "Intensa e profunda, para quem gosta de sentir o grão sem filtros.")
    + produto(CARD.format("lua"), "Lua 100%", "Intensa · profunda · intuitiva",
              "Um ingrediente só: cacau. Sem açúcar, vegano, em caixas de 33 g e 100 g.",
              PROD.format("lua-100-100g"), "Conhecer a Lua")
    + h2("Primeira vez com 100%?")
    + passos(["Comece com um pedaço bem pequeno.",
              "Deixe derreter sem mastigar: o amargor chega primeiro e depois abre espaço para as outras camadas do cacau.",
              "Um gole de água entre um pedaço e outro ajuda a perceber as camadas.",
              "Volte no dia seguinte. A Lua muda a cada vez que a gente prova."])
    + p("A tampa da caixa guarda a intenção do Ritual dos Ciclos:")
    + citacao("&ldquo;Sou inteira em todas as minhas fases.&rdquo;"),
    imagem=(UP + "/2026/10/cacau-cosmo-lua-tampa.jpg", "Tampa da caixa da Lua com a intenção do ritual", "#030303"),
    fundo_topo="#030303")))

NEWS.append(("04-sol", email(
    "O Sol está a caminho",
    "70% cacau, vibrante e forte. Quer ser a primeira a saber?",
    "Ritual da Luz", "O Sol está a caminho.",
    p("O Sol é a barra mais luminosa do nosso céu. Chocolate 70% cacau, adoçado com açúcar de coco e com cogumelo do sol na receita. "
      "Vibrante e forte, com presença do começo ao fim.")
    + produto(CARD.format("sol"), "Sol 70%", "Vibrante · forte · radiante",
              "Cacau, açúcar de coco e cogumelo do sol. Vegano, em caixas de 33 g e 100 g.",
              "mailto:hello@cacaucosmo.com?subject=Quero%20saber%20quando%20o%20Sol%20chegar", "Quero ser avisada",
              "Ainda não disponível: o próximo lote está sendo preparado.")
    + p("Como tudo aqui, o Sol chega no tempo dele. Quando o lote estiver pronto, quem pediu para ser avisada fica sabendo antes.")
    + p("A tampa da caixa guarda a intenção do Ritual da Luz:")
    + citacao("&ldquo;Que minha luz esteja a serviço da vida.&rdquo;")
    + p("Enquanto isso, Saturno e Lua estão na loja esperando por você.")
    + botao("Ir para a loja", SITE + "/loja/"),
    imagem=(UP + "/2026/10/cacau-cosmo-sol-tampa.jpg", "Tampa da caixa do Sol com a intenção do ritual", "#3a2108"),
    fundo_topo="#3a2108")))

NEWS.append(("05-natal", email(
    "Um Natal com tempo",
    "Presentes feitos à mão, em pequenos lotes, para quem você ama.",
    "Natal", "Um Natal com tempo.",
    p("Tem presente que se compra correndo. E tem presente que carrega o tempo de quem fez e o cuidado de quem escolheu. "
      "Neste Natal, a gente quer estar no segundo grupo.")
    + h2("Para cada pessoa, um astro")
    + produto(CARD.format("saturno"), "Saturno 47%", "Para quem gosta de aconchego",
              "Suave e cremoso. Um bom primeiro chocolate de verdade para quem ainda não conhece.",
              PROD.format("saturno-47-100g"), "Presentear com Saturno")
    + produto(CARD.format("lua"), "Lua 100%", "Para quem gosta de intensidade",
              "Só cacau, sem açúcar. Para quem aprecia sabores profundos e sem filtros.",
              PROD.format("lua-100-100g"), "Presentear com Lua")
    + p("Cada caixa vem com uma intenção na tampa. Um presente que também é um pequeno ritual para abrir com calma.")
    + citacao("Para chegar antes do Natal, faça seu pedido até <strong>[dia de dezembro]</strong>.")
    + p("Que a sua noite de Natal seja doce, lenta e cheia de presença."),
    imagem=(UP + "/2026/11/cc-home-bombons.jpg", "Bombons da Cacau Cosmo em caixinhas, prontos para presentear"))))

NEWS.append(("06-ano-novo", email(
    "Que o novo ano tenha o seu tempo",
    "Um pequeno ritual de intenção para começar o ano.",
    "Feliz Ano Novo", "Que o novo ano tenha o seu tempo.",
    p("Este foi o ano em que o Círculo nasceu. Obrigada por estar aqui, por provar com calma e por fazer parte deste pequeno universo "
      "entre o cacau e o cosmo.")
    + h2("Um ritual para a virada")
    + passos(["Separe um pedaço de chocolate e um momento só seu, mesmo que curto.",
              "Pense numa palavra para o ano que chega.",
              "Deixe o chocolate derreter devagar enquanto essa palavra se assenta.",
              "Escreva a sua intenção num papel e guarde onde você possa ver."])
    + p("Se precisar de inspiração, empreste uma das nossas:")
    + citacao("&ldquo;Que minhas raízes cresçam profundas.&rdquo;<br>&ldquo;Sou inteira em todas as minhas fases.&rdquo;<br>"
              "&ldquo;Que minha luz esteja a serviço da vida.&rdquo;")
    + p("No ano que chega, tem lote novo, encontros e mais histórias do Universo. A gente se vê por lá.")
    + botao("Ver o Universo", SITE + "/universo/")
    + p("Feliz Ano Novo."),
    imagem=(UP + "/2026/11/cc-home-nicole.jpg", "Nicole Buffara, fundadora da Cacau Cosmo, rindo"))))


if __name__ == "__main__":
    with zipfile.ZipFile(OUT / "newsletters.zip", "w", zipfile.ZIP_DEFLATED) as z:
        for nome, html in NEWS:
            f = OUT / f"{nome}.html"
            f.write_text(html, encoding="utf-8")
            z.write(f, f.name)
            print("ok", f.name)
