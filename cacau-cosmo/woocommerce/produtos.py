#!/usr/bin/env python3
"""Gera o CSV de importação do WooCommerce da Coleção Cacau Cosmo:
Saturno 47%, Lua 100% e Sol 70%, cada um em 33 g e 100 g (6 produtos).

Fonte dos dados: página "Coleção Cacau Cosmo 3D" (nome, percentual, ritual,
perfil, intenção da tampa, ingredientes e situação de cada barra) e o verso
da caixa do Saturno (tabela nutricional).

Uso:   python3 cacau-cosmo/woocommerce/produtos.py
Saída: cacau-cosmo/woocommerce/produtos-cacau-cosmo.csv
Fotos: python3 cacau-cosmo/woocommerce/fotos_reais.py
"""
import csv
from pathlib import Path

OUT = Path(__file__).resolve().parent

# Envio e conservação: entra no fim da descrição de todos os produtos.
# [ REVISAR ] prazo de despacho e embalagem térmica.
ENTREGA = """
<h3>Entrega</h3>
<ul>
<li>Enviamos para todo o Brasil. Frete e prazo são calculados no carrinho pelo seu CEP.</li>
<li>Os pedidos são despachados em até 2 dias úteis após a confirmação do pagamento.</li>
<li>Embalagem térmica nos meses mais quentes. Em ondas de calor, podemos segurar o envio por alguns dias para proteger o chocolate &mdash; avisamos por e-mail.</li>
</ul>
<h3>Como guardar</h3>
<p>Armazenar em local seco e fresco, longe da luz &mdash; idealmente entre 16&nbsp;°C e 20&nbsp;°C. Evite a geladeira: umidade e odores alteram textura e sabor.</p>
"""

# Tabela do verso da caixa do Saturno (por 100 g e por porção de 5,5 g).
NUTRI_SATURNO = [
    ("Valor energético (kcal)", "419", "23", "1%"),
    ("Carboidratos totais (g)", "43", "2,4", "1%"),
    ("Açúcares totais (g)", "41", "2,3", ""),
    ("Açúcares adicionados (g)", "40", "2,2", "4%"),
    ("Proteínas (g)", "5,4", "0", "0%"),
    ("Gorduras totais (g)", "25", "1,4", "2%"),
    ("Gorduras saturadas (g)", "15", "0,8", "4%"),
    ("Gorduras trans (g)", "0", "0", "0%"),
    ("Fibras alimentares (g)", "2,9", "0", "0%"),
    ("Sódio (mg)", "79", "0", "0%"),
]

# Peso e medidas para o frete. [ CONFIRMAR ] com as caixas reais.
EMBALAGEM = {
    "33":  {"peso_kg": "0.06", "dim": ("12", "8", "3"), "porcoes": "6 porções"},
    "100": {"peso_kg": "0.15", "dim": ("16", "11", "4"), "porcoes": "aproximadamente 18 porções"},
}

BARRAS = [
    {
        "slug": "saturno", "nome": "Saturno", "cacau": "47%",
        "ritual": "Ritual das Raízes", "perfil": "Suave, cremoso, lento",
        "intencao": "Que minhas raízes cresçam profundas.",
        "ingredientes": ["Cacau", "açúcar de coco", "aveia", "shiitake"],
        # aveia é cereal com glúten na rotulagem de alergênicos: [ CONFIRMAR ] a frase de glúten
        "alergicos": "Produto vegano e artesanal. Contém aveia. Pode conter leite, amêndoa e avelã.",
        "nutri": NUTRI_SATURNO,
        "disponivel": True, "posicao": 1,
        "texto": "Saturno é o planeta do tempo, e esta barra pede exatamente isso: tempo. "
                 "Com 47% de cacau, adoçada com açúcar de coco e com aveia e shiitake na receita, "
                 "é suave e cremosa, feita para derreter devagar.",
        "tags": "47%, vegano, açúcar de coco, aveia, shiitake",
    },
    {
        "slug": "lua", "nome": "Lua", "cacau": "100%",
        "ritual": "Ritual dos Ciclos", "perfil": "Intensa, profunda, intuitiva",
        "intencao": "Sou inteira em todas as minhas fases.",
        "ingredientes": ["Cacau"],
        "alergicos": "Produto vegano e artesanal. Pode conter leite, amêndoa e avelã. Não contém glúten.",
        "nutri": None,                       # [ PREENCHER ] o verso da caixa repete o rótulo do Saturno
        "disponivel": True, "posicao": 3,
        "texto": "A Lua não tem pressa de se mostrar inteira. Chocolate 100% cacau, com um único ingrediente: "
                 "o próprio cacau, sem açúcar. Intensa e profunda, para quem gosta de sentir o grão sem filtros.",
        "tags": "100%, sem açúcar, vegano, 1 ingrediente",
    },
    {
        "slug": "sol", "nome": "Sol", "cacau": "70%",
        "ritual": "Ritual da Luz", "perfil": "Vibrante, forte, radiante",
        "intencao": "Que minha luz esteja a serviço da vida.",
        "ingredientes": ["Cacau", "açúcar de coco", "cogumelo do sol"],
        "alergicos": "Produto vegano e artesanal. Pode conter leite, amêndoa e avelã. Não contém glúten.",
        "nutri": None,                       # [ PREENCHER ] o verso da caixa repete o rótulo do Saturno
        "disponivel": False, "posicao": 5,   # entra no site como indisponível
        "texto": "O Sol é a barra mais luminosa do nosso céu. Chocolate 70% cacau, adoçado com açúcar de coco "
                 "e com cogumelo do sol na receita. Vibrante e forte, com presença do começo ao fim.",
        "tags": "70%, vegano, açúcar de coco, cogumelo do sol",
    },
]


def tabela(nutri, peso):
    linhas = "\n".join(f"<tr><td>{n}</td><td>{a}</td><td>{b}</td><td>{c}</td></tr>" for n, a, b, c in nutri)
    return (f"<p>Porções por embalagem: {EMBALAGEM[peso]['porcoes']}. Porção: 5,5 g (1 pedaço).</p>\n"
            '<table class="cc-nutri">\n<thead><tr><th></th><th>100 g</th><th>5,5 g</th><th>%VD*</th></tr></thead>\n'
            f"<tbody>\n{linhas}\n</tbody>\n</table>\n"
            "<p><small>*Percentual de valores diários fornecidos pela porção.</small></p>")


def produto(b, peso):
    ing = ", ".join([b["ingredientes"][0]] + [i for i in b["ingredientes"][1:]])
    n_ing = len(b["ingredientes"])
    nome = f"{b['nome']} {b['cacau']} · {peso} g"
    curta = (f"<p><strong>{b['perfil']}.</strong> Chocolate {b['cacau']} cacau do {b['ritual']}, "
             f"feito com {'um único ingrediente' if n_ing == 1 else f'{n_ing} ingredientes'}.</p>"
             f"<p>Nibs to bar · vegano · {peso} g</p>")
    desc = (f"<p><em>{b['intencao']}</em></p>\n<p>{b['texto']}</p>\n"
            f"<h3>O ritual</h3>\n<p>{b['nome']} faz parte do <strong>{b['ritual']}</strong>. "
            f"Ao abrir a caixa, a tampa guarda uma intenção para acompanhar o momento: "
            f"<em>&ldquo;{b['intencao']}&rdquo;</em> Quebre um pedaço, respire o aroma e deixe derreter devagar, sem morder.</p>\n"
            f"<h3>Ingredientes</h3>\n<p>{ing}.</p>\n"
            f"<p><strong>Alérgicos:</strong> {b['alergicos']}</p>\n")
    if b["nutri"]:
        desc += "<h3>Informação nutricional</h3>\n" + tabela(b["nutri"], peso) + "\n"
    emb = EMBALAGEM[peso]
    return {
        "sku": f"CC-{b['slug'].upper()}-{peso}",
        "nome": nome,
        "slug": f"{b['slug']}-{b['cacau'].rstrip('%')}-{peso}g",
        "curta": curta,
        "descricao": desc.strip() + "\n" + ENTREGA.strip(),
        "disponivel": b["disponivel"],
        "peso_kg": emb["peso_kg"], "dim": emb["dim"],
        "categorias": f"Barras, {b['ritual']}",
        "tags": b["tags"] + f", {peso} g",
        "posicao": b["posicao"] + (0 if peso == "33" else 1),
        "imagem": f"{b['slug']}-{peso}g.jpg",
        "galeria": [f"{b['slug']}-frente.jpg", f"{b['slug']}-tampa.jpg"],
        "atributos": [("Cacau", b["cacau"]), ("Peso", f"{peso} g"), ("Ritual", b["ritual"]),
                      ("Perfil", b["perfil"].replace(", ", " | ")), ("Ingredientes", ing.replace(", ", " | ")),
                      ("Vegano", "Sim")],
    }


PRODUTOS = [produto(b, peso) for b in BARRAS for peso in ("33", "100")]


def gerar_csv(nome="produtos-cacau-cosmo.csv"):
    max_attr = max(len(p["atributos"]) for p in PRODUTOS)
    cab = ["Type", "SKU", "Name", "Slug", "Published", "Is featured?", "Visibility in catalog",
           "Short description", "Description", "Tax status", "In stock?", "Backorders allowed?",
           "Sold individually?", "Weight (kg)", "Length (cm)", "Width (cm)", "Height (cm)",
           "Allow customer reviews?", "Regular price", "Categories", "Tags", "Shipping class", "Images", "Position"]
    for i in range(1, max_attr + 1):
        cab += [f"Attribute {i} name", f"Attribute {i} value(s)", f"Attribute {i} visible", f"Attribute {i} global"]
    path = OUT / nome
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(cab)
        for p in sorted(PRODUTOS, key=lambda x: x["posicao"]):
            comp, larg, alt = p["dim"]
            linha = ["simple", p["sku"], p["nome"], p["slug"], 1, 1, "visible",
                     p["curta"], p["descricao"], "taxable", 1 if p["disponivel"] else 0, 0,
                     0, p["peso_kg"], comp, larg, alt,
                     1, "", p["categorias"], p["tags"], "Chocolate", "", p["posicao"]]
            for nome_a, valor in p["atributos"]:
                linha += [nome_a, valor, 1, 0]
            linha += [""] * (len(cab) - len(linha))
            w.writerow(linha)
    return path


if __name__ == "__main__":
    print("ok", gerar_csv().relative_to(OUT.parent.parent))
    for p in sorted(PRODUTOS, key=lambda x: x["posicao"]):
        print(f"   {p['posicao']}. {p['sku']:<16} {p['nome']:<22} {'à venda' if p['disponivel'] else 'INDISPONÍVEL':<13} "
              f"foto: {p['imagem']}  galeria: {', '.join(p['galeria'])}")
