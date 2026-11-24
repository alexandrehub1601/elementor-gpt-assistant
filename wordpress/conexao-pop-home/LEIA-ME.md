# Home Conexão Pop (plugin do WordPress)

Blocos de home com a identidade 2026, inspirados no formato de revista do Escutai.
Puxam as matérias de verdade do site; nada muda no jeito de publicar.

## Instalar e montar
1. Plugins > Adicionar novo > Enviar plugin > `conexao-pop-home.zip` > Ativar.
2. Na home (Elementor), adicione um widget **Shortcode** com `[cp_home]`, ou monte do seu
   jeito com um bloco por widget (lista completa em Configurações > Home Conexão Pop).
3. Use o layout **"Largura total do Elementor"** na página.

## Blocos
| Shortcode | O que mostra |
|---|---|
| `[cp_faixa]` | Faixa rosa com as últimas manchetes passando |
| `[cp_destaques]` | 2 manchetes grandes da categoria "destaque" |
| `[cp_agora]` | Destaque + "Acabou de sair" (5 mais recentes) |
| `[cp_em_alta]` | Ranking 01 a 06 com as mais lidas dos últimos 14 dias |
| `[cp_editoria categoria="musica" titulo="Música"]` | 4 matérias de uma editoria |
| `[cp_slides categoria="entrevistas" titulo="Entrevistas" cor="limao"]` | Carrossel (cor: limao, rosa ou azul) |
| `[cp_proximos_shows]` | Próximos shows da agenda (precisa do plugin da agenda) |
| `[cp_newsletter]` | Newsletter (cole o formulário em Configurações) |
| `[cp_capa categoria="coberturas" rotulo="Cobertura"]` | Uma matéria em tela cheia, título enorme e a skyline rosa |
| `[cp_mosaico categoria="festival" titulo="Festivais"]` | Uma grande e quatro menores |
| `[cp_manchetes categoria="marcas" titulo="Marcas"]` | Seis manchetes só em texto, estilo revista |
| `[cp_sanfona categoria="entrevistas" titulo="Entrevistas"]` | Colunas com título na vertical que abrem ao passar o mouse |
| `[cp_divisor padrao="3" cor="rosa"]` | Divisória de pixels da identidade (padrão 1 a 4; cor rosa, preto, azul ou limao; `virar="sim"`) |

Nenhum bloco repete matéria de outro na mesma página. "Em alta" usa o contador de visitas
que o site já tem (coluna "Views"); sem contador, usa as mais comentadas.

## Matérias, categorias, busca e 404
Em Configurações > Home Conexão Pop, marque onde usar o layout novo: matérias, categorias,
resultado da busca e página não encontrada (404). Usa o cabeçalho e o rodapé do tema.
Com Elementor Pro, dá para deixar desmarcado e usar `[cp_materia]`, `[cp_categoria]`,
`[cp_busca]` e `[cp_404]` nos modelos do Construtor de temas.
