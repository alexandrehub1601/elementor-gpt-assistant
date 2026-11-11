=== Auto Tweet de Posts ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Compartilha os posts no X (Twitter) com o título, "🔗 Saiba tudo em: [link]" e a thumb.

Dois modos (Configurações › Auto Tweet (X)):
* Grátis – 1 clique (padrão): botão "Compartilhar no X" na caixa do post e na lista de posts.
  Abre o X com o texto pronto; é só clicar em Postar. A thumb aparece como prévia grande do link
  (vem das tags og:image/twitter:card do Yoast/Rank Math). Não precisa de chave nem de API.
* Automático – API do X (paga por uso, ~US$ 0,20 por post com link): publica sozinho com a thumb
  anexada como foto. Siga a instalação abaixo a partir do passo 2.

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
