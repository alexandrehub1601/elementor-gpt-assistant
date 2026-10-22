#!/usr/bin/env python3
"""Baixa os posts da categoria Blog de theamazonapothecary.com e gera um
arquivo WXR (Ferramentas → Importar → WordPress) para o Journal da Cacau Cosmo.

Uso:  python3 cacau-cosmo/journal/importar_posts.py
Saída: cacau-cosmo/journal/journal-posts.xml

O arquivo traz os posts, categorias, tags, imagens destacadas e as imagens de
dentro do texto como anexos. Marque "Baixar e importar anexos" na importação
para as imagens virem para a sua Biblioteca de Mídia.
"""
import html
import json
import re
import unicodedata
import urllib.parse
import urllib.request
from email.utils import format_datetime
from datetime import datetime, timezone
from pathlib import Path

ORIGEM = "https://theamazonapothecary.com"
CATEGORIA_ORIGEM = "blog"
DESTINO = "https://cacaucosmo.com.br"
CATEGORIA_DESTINO = ("blog", "Blog")   # slug e nome da categoria no Journal
STATUS = "publish"                      # "draft" para revisar antes
OUT = Path(__file__).resolve().parent / "journal-posts.xml"

UA = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36",
      "Accept": "application/json"}


def get(url):
    with urllib.request.urlopen(urllib.request.Request(url, headers=UA), timeout=60) as r:
        return json.load(r)


def cdata(s):
    return "<![CDATA[" + (s or "").replace("]]>", "]]]]><![CDATA[>") + "]]>"


def slugify(texto):
    texto = html.unescape(texto)
    texto = unicodedata.normalize("NFKD", texto).encode("ascii", "ignore").decode()
    texto = re.sub(r"[^a-zA-Z0-9]+", "-", texto).strip("-").lower()
    return texto[:80].strip("-")


def limpar_slug(post):
    slug = urllib.parse.unquote(post["slug"])
    slug = slugify(slug)
    if not slug or re.fullmatch(r"\d+(-\d+)?", slug):   # slugs tipo "6640-2"
        slug = slugify(post["title"]["rendered"])
    return slug


def url_original(src):
    """Troca a miniatura (foto-225x300.jpg) pelo arquivo original (foto.jpg)."""
    return re.sub(r"-\d+x\d+(?=\.(?:jpe?g|png|webp|gif)$)", "", src, flags=re.I)


def limpar_conteudo(c):
    """Aponta as imagens para o arquivo original e remove srcset, para o
    importador conseguir trocar as URLs pelas da sua Biblioteca de Mídia."""
    imagens = []

    def img(m):
        tag = m.group(0)
        src = re.search(r'src="([^"]+)"', tag)
        if not src:
            return tag
        full = url_original(html.unescape(src.group(1)))
        imagens.append(full)
        tag = re.sub(r'\s(srcset|sizes|fetchpriority|decoding|width|height)="[^"]*"', "", tag)
        tag = re.sub(r'src="[^"]+"', f'src="{full}"', tag)
        tag = re.sub(r'class="[^"]*"', 'class="aligncenter size-full"', tag)
        return tag

    c = re.sub(r"<img\b[^>]*>", img, c)
    c = re.sub(r"<p>(&nbsp;|\s)*</p>\s*", "", c)
    return c.strip(), imagens


def termos(post, taxonomia):
    out = []
    for grupo in post.get("_embedded", {}).get("wp:term", []):
        for t in grupo:
            if t.get("taxonomy") == taxonomia:
                out.append((t["slug"], html.unescape(t["name"])))
    return out


def main():
    cat = get(f"{ORIGEM}/wp-json/wp/v2/categories?slug={CATEGORIA_ORIGEM}")[0]
    posts = get(f"{ORIGEM}/wp-json/wp/v2/posts?categories={cat['id']}&per_page=100&_embed=1")

    itens, tags_todas = [], {}
    proximo_id = 900000          # IDs só dentro do arquivo; o WordPress cria novos
    for p in posts:
        conteudo, imagens = limpar_conteudo(p["content"]["rendered"])
        titulo = html.unescape(re.sub(r"<[^>]+>", "", p["title"]["rendered"])).strip()
        resumo = html.unescape(re.sub(r"<[^>]+>", "", p["excerpt"]["rendered"])).strip()
        resumo = re.sub(r"\s*\[(…|&hellip;|\.\.\.)\]\s*$", "…", resumo)
        tags = [t for t in termos(p, "post_tag")]
        for s, n in tags:
            tags_todas[s] = n
        autor = (p.get("_embedded", {}).get("author") or [{}])[0].get("name", "admin")

        destaque = (p.get("_embedded", {}).get("wp:featuredmedia") or [{}])[0]
        anexos = []
        if destaque.get("source_url"):
            anexos.append((destaque["source_url"], html.unescape(destaque.get("alt_text") or titulo), True))
        for u in imagens:
            if all(u != a[0] for a in anexos):
                anexos.append((u, titulo, False))

        post_id = p["id"]
        thumb_id = None
        anexos_xml = []
        for url, alt, eh_destaque in anexos:
            proximo_id += 1
            if eh_destaque:
                thumb_id = proximo_id
            nome = Path(urllib.parse.urlparse(url).path).stem
            anexos_xml.append(f"""
	<item>
		<title>{cdata(nome)}</title>
		<link>{DESTINO}/?attachment_id={proximo_id}</link>
		<pubDate>{format_datetime(datetime.fromisoformat(p['date_gmt']).replace(tzinfo=timezone.utc))}</pubDate>
		<dc:creator>{cdata(autor)}</dc:creator>
		<guid isPermaLink="false">{html.escape(url)}</guid>
		<description></description>
		<content:encoded>{cdata('')}</content:encoded>
		<excerpt:encoded>{cdata('')}</excerpt:encoded>
		<wp:post_id>{proximo_id}</wp:post_id>
		<wp:post_date>{cdata(p['date'].replace('T', ' '))}</wp:post_date>
		<wp:post_date_gmt>{cdata(p['date_gmt'].replace('T', ' '))}</wp:post_date_gmt>
		<wp:comment_status>{cdata('closed')}</wp:comment_status>
		<wp:ping_status>{cdata('closed')}</wp:ping_status>
		<wp:post_name>{cdata(slugify(nome))}</wp:post_name>
		<wp:status>{cdata('inherit')}</wp:status>
		<wp:post_parent>{post_id}</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type>{cdata('attachment')}</wp:post_type>
		<wp:post_password>{cdata('')}</wp:post_password>
		<wp:is_sticky>0</wp:is_sticky>
		<wp:attachment_url>{cdata(url)}</wp:attachment_url>
		<wp:postmeta>
			<wp:meta_key>{cdata('_wp_attachment_image_alt')}</wp:meta_key>
			<wp:meta_value>{cdata(alt)}</wp:meta_value>
		</wp:postmeta>
	</item>""")

        cats_xml = f'\n\t\t<category domain="category" nicename="{CATEGORIA_DESTINO[0]}">{cdata(CATEGORIA_DESTINO[1])}</category>'
        cats_xml += "".join(f'\n\t\t<category domain="post_tag" nicename="{s}">{cdata(n)}</category>' for s, n in tags)
        meta = ""
        if thumb_id:
            meta = f"""
		<wp:postmeta>
			<wp:meta_key>{cdata('_thumbnail_id')}</wp:meta_key>
			<wp:meta_value>{cdata(str(thumb_id))}</wp:meta_value>
		</wp:postmeta>"""

        slug = limpar_slug(p)
        itens.append(f"""
	<item>
		<title>{cdata(titulo)}</title>
		<link>{DESTINO}/{slug}/</link>
		<pubDate>{format_datetime(datetime.fromisoformat(p['date_gmt']).replace(tzinfo=timezone.utc))}</pubDate>
		<dc:creator>{cdata(autor)}</dc:creator>
		<guid isPermaLink="false">{DESTINO}/?p={post_id}</guid>
		<description></description>
		<content:encoded>{cdata(conteudo)}</content:encoded>
		<excerpt:encoded>{cdata(resumo)}</excerpt:encoded>
		<wp:post_id>{post_id}</wp:post_id>
		<wp:post_date>{cdata(p['date'].replace('T', ' '))}</wp:post_date>
		<wp:post_date_gmt>{cdata(p['date_gmt'].replace('T', ' '))}</wp:post_date_gmt>
		<wp:comment_status>{cdata('open')}</wp:comment_status>
		<wp:ping_status>{cdata('closed')}</wp:ping_status>
		<wp:post_name>{cdata(slug)}</wp:post_name>
		<wp:status>{cdata(STATUS)}</wp:status>
		<wp:post_parent>0</wp:post_parent>
		<wp:menu_order>0</wp:menu_order>
		<wp:post_type>{cdata('post')}</wp:post_type>
		<wp:post_password>{cdata('')}</wp:post_password>
		<wp:is_sticky>0</wp:is_sticky>{cats_xml}{meta}
	</item>""" + "".join(anexos_xml))
        print(f"  {p['date'][:10]}  {titulo[:60]:<60}  imagens: {len(anexos)}")

    autores = sorted({(p.get("_embedded", {}).get("author") or [{}])[0].get("name", "admin") for p in posts})
    autores_xml = "".join(f"""
	<wp:author><wp:author_login>{cdata(slugify(a) or 'admin')}</wp:author_login><wp:author_display_name>{cdata(a)}</wp:author_display_name></wp:author>"""
                          for a in autores)
    tags_xml = "".join(f"""
	<wp:tag><wp:tag_slug>{cdata(s)}</wp:tag_slug><wp:tag_name>{cdata(n)}</wp:tag_name></wp:tag>"""
                       for s, n in sorted(tags_todas.items()))

    xml = f"""<?xml version="1.0" encoding="UTF-8" ?>
<!-- Posts do Journal — Cacau Cosmo. Importe em Ferramentas → Importar → WordPress. -->
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
	<title>Cacau Cosmo</title>
	<link>{DESTINO}</link>
	<description>Journal</description>
	<language>pt-BR</language>
	<wp:wxr_version>1.2</wp:wxr_version>
	<wp:base_site_url>{DESTINO}</wp:base_site_url>
	<wp:base_blog_url>{DESTINO}</wp:base_blog_url>
{autores_xml}
	<wp:category><wp:term_id>1</wp:term_id><wp:category_nicename>{cdata(CATEGORIA_DESTINO[0])}</wp:category_nicename><wp:category_parent>{cdata('')}</wp:category_parent><wp:cat_name>{cdata(CATEGORIA_DESTINO[1])}</wp:cat_name></wp:category>
{tags_xml}
{''.join(itens)}
</channel>
</rss>
"""
    OUT.write_text(xml, encoding="utf-8")
    print(f"ok {OUT.name}: {len(posts)} posts")


if __name__ == "__main__":
    main()
