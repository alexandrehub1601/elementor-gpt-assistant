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

Nenhum bloco repete matéria de outro na mesma página. "Em alta" usa o contador de visitas
que o site já tem (coluna "Views"); sem contador, usa as mais comentadas.
