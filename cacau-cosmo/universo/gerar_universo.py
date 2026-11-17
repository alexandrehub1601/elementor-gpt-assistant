#!/usr/bin/env python3
"""Gera universo-inicial.xml (Ferramentas → Importar → WordPress):
a categoria Universo, as subcategorias que viram filtros na página /universo/
e os primeiros posts como RASCUNHO, para a Nicole completar com texto e fotos.

Uso:  python3 cacau-cosmo/universo/gerar_universo.py
"""
from pathlib import Path

DESTINO = "https://cacaucosmo.com.br"
OUT = Path(__file__).resolve().parent / "universo-inicial.xml"

RAIZ = ("universo", "Universo")
SUBS = [("processos", "Processos"), ("encontros", "Encontros"), ("viagens", "Viagens"),
        ("colaboracoes", "Colaborações"), ("pessoas", "Pessoas"), ("ingredientes", "Ingredientes"),
        ("territorio", "Território"), ("receitas", "Receitas")]

# título "Nome — subtítulo": na página o nome vira título e o resto vira subtítulo
POSTS = [
    (910001, "outubro-rosa", "Outubro Rosa — Cuidado que se partilha", "2026-10-31 18:00:00", ""),
    (910002, "cosmo-cafe", "Cosmo Café — onde o universo começa a ganhar sabor", "2026-11-17 10:00:00", ""),
]
TEXTO = ("<!-- wp:paragraph --><p>[Conte aqui como foi: o que aconteceu, onde, quem esteve junto e o que ficou.]</p><!-- /wp:paragraph -->\n"
         "<!-- wp:paragraph --><p>[Coloque as fotos da ação no meio do texto e uma delas como Imagem destacada.]</p><!-- /wp:paragraph -->")


def cdata(s):
    return "<![CDATA[" + (s or "").replace("]]>", "]]]]><![CDATA[>") + "]]>"


def main():
    cats = [f"\t<wp:category><wp:term_id>900{1}</wp:term_id><wp:category_nicename>{cdata(RAIZ[0])}</wp:category_nicename>"
            f"<wp:category_parent>{cdata('')}</wp:category_parent><wp:cat_name>{cdata(RAIZ[1])}</wp:cat_name></wp:category>"]
    for i, (slug, nome) in enumerate(SUBS, 2):
        cats.append(f"\t<wp:category><wp:term_id>900{i}</wp:term_id><wp:category_nicename>{cdata(slug)}</wp:category_nicename>"
                    f"<wp:category_parent>{cdata(RAIZ[0])}</wp:category_parent><wp:cat_name>{cdata(nome)}</wp:cat_name></wp:category>")
    itens = []
    for pid, slug, titulo, data, resumo in POSTS:
        itens.append(f"""
	<item>
		<title>{cdata(titulo)}</title>
		<link>{DESTINO}/{slug}/</link>
		<dc:creator>{cdata('admin')}</dc:creator>
		<guid isPermaLink="false">{DESTINO}/?p={pid}</guid>
		<description></description>
		<content:encoded>{cdata(TEXTO)}</content:encoded>
		<excerpt:encoded>{cdata(resumo)}</excerpt:encoded>
		<wp:post_id>{pid}</wp:post_id>
		<wp:post_date>{cdata(data)}</wp:post_date>
		<wp:post_date_gmt>{cdata('0000-00-00 00:00:00')}</wp:post_date_gmt>
		<wp:comment_status>{cdata('closed')}</wp:comment_status>
		<wp:ping_status>{cdata('closed')}</wp:ping_status>
		<wp:post_name>{cdata(slug)}</wp:post_name>
		<wp:status>{cdata('draft')}</wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type>{cdata('post')}</wp:post_type>
		<wp:post_password>{cdata('')}</wp:post_password>
		<wp:is_sticky>0</wp:is_sticky>
		<category domain="category" nicename="{RAIZ[0]}">{cdata(RAIZ[1])}</category>
	</item>""")
    xml = f"""<?xml version="1.0" encoding="UTF-8" ?>
<!-- Universo — Cacau Cosmo. Importe em Ferramentas → Importar → WordPress. Os posts entram como rascunho. -->
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<title>Cacau Cosmo</title>
	<link>{DESTINO}</link>
	<description>Universo</description>
	<language>pt-BR</language>
	<wp:wxr_version>1.2</wp:wxr_version>
	<wp:base_site_url>{DESTINO}</wp:base_site_url>
	<wp:base_blog_url>{DESTINO}</wp:base_blog_url>
	<wp:author><wp:author_login>{cdata('admin')}</wp:author_login><wp:author_display_name>{cdata('Cacau Cosmo')}</wp:author_display_name></wp:author>
{chr(10).join(cats)}
{''.join(itens)}
</channel>
</rss>
"""
    OUT.write_text(xml, encoding="utf-8")
    print("ok", OUT.name)


if __name__ == "__main__":
    main()
