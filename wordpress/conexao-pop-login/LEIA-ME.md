# Login Conexão Pop (plugin do WordPress)

Troca o endereço do login, bloqueia robôs que tentam adivinhar senhas e deixa a tela
de login com a cara do Conexão Pop.

## Antes de instalar

1. Faça um backup do site (a hospedagem normalmente tem um botão para isso).
2. Se já existir outro plugin que muda o endereço do login (WPS Hide Login, iThemes,
   o recurso de "login personalizado" do Wordfence etc.), desative-o antes.
   Dois plugins mexendo no login ao mesmo tempo podem trancar todo mundo do lado de fora.

## Instalar

1. Plugins → Adicionar novo → Enviar plugin → `conexao-pop-login.zip` → Ativar.
2. Aparece um aviso amarelo com o endereço novo: **https://conexaopop.com.br/acesso-redacao/**.
   Salve nos favoritos e passe para a equipe.
3. Para trocar o endereço: Configurações → Login do Conexão Pop. Prefira algo que
   ninguém de fora adivinhe.

## O que muda

- `/wp-login.php` e `/wp-admin` (para quem não está logado) passam a mostrar
  "página não encontrada". Os robôs que atacam esses endereços não acham mais o login.
- 5 senhas erradas seguidas bloqueiam aquele endereço de internet por 15 minutos.
- O erro diz só "usuário ou senha incorretos", sem revelar se o usuário existe.
- O `xmlrpc.php` fica bloqueado (desmarque nas configurações se vocês usam o app do
  WordPress no celular ou o Jetpack).
- A lista de usuários (`?author=1` e `/wp-json/wp/v2/users`) some para quem não está logado.
- Formulários do site que usam `admin-post.php` e `admin-ajax.php` (como o
  "Cadastrar meu show" da agenda) continuam funcionando.

## Se alguém ficar sem acesso

No wp-config.php, acima da linha "That's all, stop editing!", adicione:

    define('CP_LOGIN_PADRAO', true);

O login volta a ser `/wp-login.php`. Entre, confira o endereço em Configurações e
apague a linha. Outra saída: renomear a pasta `wp-content/plugins/conexao-pop-login`
pelo gerenciador de arquivos da hospedagem.

## Isso basta?

Trocar o endereço tira o site do caminho dos robôs, mas não substitui o básico:
senhas fortes e diferentes para cada pessoa, verificação em duas etapas
(o plugin "Two Factor", gratuito, resolve), WordPress, tema e plugins sempre
atualizados, e remover usuários que não trabalham mais com vocês. Se houve vírus,
vale uma varredura (Wordfence ou a própria hospedagem) e trocar todas as senhas,
inclusive as do banco de dados e do FTP.
