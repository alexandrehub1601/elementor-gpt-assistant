# Agenda Conexão Pop (plugin do WordPress)

Agenda de shows e festivais para o conexaopop.com.br, com cadastro pelo painel,
envio de shows por artistas e produtoras (com aprovação da redação), alertas de fãs
("Me avisa") e importação de shows da Ticketmaster.

## Instalar

1. No painel do WordPress: **Plugins → Adicionar novo → Enviar plugin**, escolha
   `agenda-conexao-pop.zip` e clique em **Ativar**.
2. Aparece o menu **Agenda de shows** na lateral.

## Montar as páginas

1. Na página **Agenda** (conexaopop.com.br/agenda), apague o HTML do protótipo e cole
   `[agenda_conexao]` num widget de **Shortcode** do Elementor. Deixe em largura total.
   Faça isso depois de importar a agenda inicial, para a página não ficar vazia.
2. Crie a página **Cadastrar meu show** e cole `[agenda_conexao_cadastro]`.
3. Em **Agenda de shows → Configurações**, escolha a página de cadastro e o
   e-mail que recebe o aviso de show novo para aprovar.

O cabeçalho e o rodapé são os do próprio tema do site.

## Usar no dia a dia

- **Adicionar show:** Agenda de shows → Adicionar show. Preencha o nome, a capa
  (imagem destacada), as datas (uma linha por apresentação), a ticketeira e os
  gêneros. Marque "É um festival" para ele ir para a aba Festivais.
- **Link de ingressos:** só preencha quando o card deve levar para a compra, como
  nas parcerias (ex.: Hilary Duff com UTM da Ticketmaster). Sem link, o card mostra
  só o nome da ticketeira.
- **Esgotado e data extra:** na coluna "Situação" de cada data.
- **Aprovar envios:** o que chega pelo formulário fica como **Pendente** (o menu
  mostra quantos). Na lista, use **Aprovar e publicar**. O link de ingressos que a
  produtora mandou fica guardado no campo `_cpa_link_enviado`, mas não aparece no
  card até a redação colar no campo "Link de ingressos".
- **Banner de destaque:** em Configurações, cole a URL da imagem (1200 × 250) e o
  link. Ele aparece acima da lista.
- **Alertas de fãs:** ficam em Agenda de shows → Alertas de fãs (e-mail, show e o
  que a pessoa quer saber). O envio automático dos e-mails de aviso é a próxima etapa.

## Importar shows

- **Agenda inicial:** em Configurações, "Importar agenda inicial" cria os 125 shows e
  festivais que estão hoje em conexaopop.com.br/agenda, já **publicados** e com capa.
  Depois é só editar ou mandar para a lixeira. Clicar de novo não duplica nada.
  Em festivais onde a fonte só dava o local, a cidade foi preenchida à mão.
- **Ticketmaster:** crie uma chave grátis em developer.ticketmaster.com (My Apps →
  Consumer Key), cole em Configurações e clique em "Importar da Ticketmaster agora".
  Os shows de música no Brasil chegam como Pendentes, já com a capa oficial.
  Marcando "Importação diária", isso roda uma vez por dia.
- **Eventim, Ticket360 e Ingresse:** não têm API aberta e bloqueiam leitura
  automática. Os shows delas entram pelo painel ou pelo formulário até existir um
  acesso de parceiro.

## Arquivos

- `agenda-conexao-pop.php`: tipos de conteúdo, painel, formulário, alertas e importações
- `templates/`: marcação da agenda e do formulário
- `assets/`: CSS, JavaScript e a foto da plateia (Unsplash)
- `data/agenda-inicial.json`: shows da importação inicial
