=== Auto Tweet de Posts ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Publica automaticamente no X (Twitter) cada post publicado no WordPress, com a imagem destacada, o título e o link.

== Instalação ==

1. Em Plugins › Adicionar novo › Enviar plugin, envie o arquivo auto-tweet-posts.zip e ative.
2. No X, acesse https://developer.x.com/en/portal/dashboard e crie um app (Project + App).
3. No app, em "User authentication settings", ative OAuth 1.0a com permissão "Read and write"
   (Callback URL e Website podem ser o endereço do seu site).
4. Em "Keys and tokens", gere:
   - API Key e API Key Secret
   - Access Token e Access Token Secret (gere DEPOIS de colocar "Read and write";
     se gerou antes, clique em "Regenerate").
5. No WordPress, vá em Configurações › Auto Tweet (X), cole as 4 chaves, salve
   e clique em "Testar conexão com o X".

== Como funciona ==

* Ao publicar um post (inclusive agendado), ele é enviado ao X alguns segundos depois.
* O tweet leva a imagem destacada como foto, o título e o link (modelo padrão: "{title}" + link).
* No editor do post há a caixa "Publicar no X (Twitter)":
  - marcar/desmarcar o envio daquele post;
  - escrever um texto próprio para o tweet (em branco = modelo padrão);
  - depois de publicado: link para o tweet, erros e botão "Enviar agora / Enviar de novo".
* Variáveis aceitas no texto: {title} {url} {excerpt} {tags} {categories} {author} {site}.
  Se o texto não tiver {url} nem um link, o link do post é colocado no final.
* Cada post é enviado só uma vez automaticamente (editar depois não reenvia).
* O histórico dos últimos envios fica em Configurações › Auto Tweet (X).

== Observações ==

* O envio usa o WP-Cron. Se o site tiver DISABLE_WP_CRON, é preciso ter um cron real
  chamando wp-cron.php (a maioria das hospedagens já faz isso).
* A API do X é paga por uso/plano; confira se sua conta de desenvolvedor permite publicar posts
  e enviar mídia. Erros da API aparecem na caixa do post e no histórico.
