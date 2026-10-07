# MVF Voting

[English](README.md)

Plugin WordPress de votação de clipes feito para o [Music Video Festival](https://www.musicvideofestival.com.br/) (MVF).

A versão original deste plugin rodou as votações do festival em 2022, 2023 e 2024. Este repositório reconstrói esse projeto para o meu portfólio, com as práticas atuais do WordPress. É um projeto legado; hoje o site usa outro sistema de votação.

## Meu papel

Desenvolvi o plugin original sozinho, de ponta a ponta: levantamento de requisitos, modelagem de dados, back-end em PHP, API REST, telas do admin, página pública de votação, segurança e testes.

## O problema

O site do MVF tem cerca de 30 mil acessos por mês e chega a 90 mil nos períodos de votação. O festival precisava de duas votações separadas. O público vota de forma anônima, sem cadastro e sem limite de votos, então o sistema precisa barrar robôs. Os jurados entram com login e ordenam os clipes das categorias que receberam, e cada posição vale pontos.

A organização também precisava cadastrar edições anuais, categorias com número máximo de clipes, clipes do YouTube e os jurados de cada categoria. Os rankings aparecem só no painel.

## Decisões técnicas

### Picos de acesso

- O cache de página pode guardar a página de votação inteira. O voto vai para a API REST por um `POST` em `/wp-json/mvf/v1/vote`, que o cache ignora, e o PHP só roda para registrar o voto.
- Os votos ficam numa tabela própria, uma linha por voto, com índice por categoria, clipe e IP. O ranking sai de um único `GROUP BY`, sem varrer metadados de post.
- Cada card mostra a thumbnail e só carrega o iframe do YouTube no clique, o que deixa leve uma página com dezenas de clipes.
- Temas de bloco renderizam o shortcode antes do `wp_enqueue_scripts`, então o plugin registra os scripts dentro do próprio shortcode.

### Votação anônima e fraude

- O Cloudflare Turnstile confere cada voto no servidor, com um token novo a cada vez. A maioria dos visitantes nem vê o desafio.
- Cada IP espera 5 segundos entre um voto e outro (configurável). A pessoa continua votando quantas vezes quiser; a espera atrasa os scripts.
- O plugin só lê o cabeçalho `CF-Connecting-IP` quando o admin confirma que o site está atrás do proxy do Cloudflare. Sem o proxy, qualquer um conseguiria forjar esse cabeçalho.
- Uma tela de auditoria lista os IPs com mais votos e os votos por hora, para a organização achar picos suspeitos. Ela pode invalidar e revalidar votos em massa, e o plugin nunca apaga nenhum.

### Privacidade (LGPD)

- O plugin guarda IP e navegador só como hash HMAC-SHA256, com o salt do próprio site, e nunca em texto puro.
- Um cookie first-party guarda um identificador aleatório para a auditoria.
- A organização escreve o aviso de privacidade nas configurações, e o plugin mostra o texto abaixo da votação.

### Regras do júri

- A cédula guarda a posição de cada clipe, e o plugin calcula os pontos na hora da leitura. Mudar a tabela de pontos recalcula todos os resultados.
- No empate, vence o clipe com mais 1º lugares; depois, o com mais 2º lugares, e assim por diante. Clipes empatados em todas as posições dividem a colocação.
- O servidor só aceita a cédula de um jurado designado para a categoria, dentro do período do júri, e só se ela trouxer cada clipe da categoria exatamente uma vez.
- Se um clipe entra ou sai da categoria depois do envio, o painel de progresso marca a cédula e o jurado recebe um aviso para revisar.

### Integridade do cadastro

- O servidor aplica o limite de clipes por categoria. Mesmo forçando o checkbox pelo navegador, o clipe não entra numa categoria cheia.
- O WordPress recusa termos com nome repetido, então o plugin monta o slug da categoria como `nome-edição`. O mesmo nome pode existir em duas edições, mas não duas vezes na mesma.
- Uma edição nova pode copiar as categorias do ano anterior.

### Segurança no admin

- Duas capabilities próprias: `mvf_manage` para a organização e `mvf_vote_jury` para os jurados. Dentro do plugin, o jurado só chega à própria cédula.
- Todo formulário do admin e a API do júri conferem um nonce, as queries passam por `$wpdb->prepare` e os templates escapam a saída HTML.

## Testes

O plugin rodou num WordPress local (WordPress Playground) em PHP 7.4 e PHP 8.0, com `WP_DEBUG` ligado, e nenhuma tela do admin nem a página pública mostrou warning. O teste manual cobriu:

- Voto do público: o voto com token válido do Turnstile passa. Token inválido, segundo voto dentro do intervalo por IP (HTTP 429), clipe fora da categoria e votação fechada são recusados.
- Júri: ordenação por arrastar e por setas, rascunho e envio, bloqueio de categoria não atribuída e um empate montado de propósito para conferir os pontos e o desempate.
- Admin: limite de categoria forçado pelo navegador, cópia de categorias entre edições, invalidação em massa aparecendo no ranking e os três CSVs abrindo com acentos corretos.
- Celular: layout em 375px sem rolagem horizontal.

A correção para temas de bloco descrita acima saiu desses testes. O projeto não tem testes automatizados.

## Stack

- PHP 7.4+ e WordPress 6.4+ (custom post type, taxonomias, REST API, Settings API)
- MySQL com tabelas próprias criadas via `dbDelta`
- JavaScript sem build, mais o jQuery UI Sortable (que já vem no WordPress) na cédula do júri
- CSS puro com variáveis que o tema pode sobrescrever
- Cloudflare Turnstile

## Estrutura do código

```
mvf-voting.php              bootstrap e constantes
includes/
  class-public-vote.php     endpoint do voto do público
  class-jury.php            cédula do júri, atribuição de jurados, endpoint
  class-results.php         cálculo dos rankings e desempate
  class-admin-results.php   telas de resultados, progresso e auditoria
  class-export.php          exportação CSV
  class-clip-admin.php      cadastro de clipes e limite por categoria
  class-taxonomy-admin.php  edições (períodos) e categorias
  class-turnstile.php       verificação do Turnstile
  class-request.php         IP, hashes e cookie de auditoria
templates/                  HTML da página de votação (sobrescrevível pelo tema)
assets/                     CSS e JS do site e do admin
```

## Como rodar

1. Copie a pasta para `wp-content/plugins/` e ative o plugin. A ativação cria as tabelas, a função Jurado MVF e o menu MVF Votação.
2. Em MVF Votação → Configurações, informe as chaves do Turnstile. As chaves de teste da Cloudflare (`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`) servem para rodar localmente.
3. Crie uma edição, as categorias e os clipes, e atribua os jurados (usuários com a função Jurado MVF).
4. Coloque o shortcode numa página:

```
[mvf_votacao]                         edição ativa, todas as categorias
[mvf_votacao categoria="slug"]        só uma categoria
[mvf_votacao edicao="2026"]           outra edição (slug ou ID)
```

Se o site usar cache de página (WP Rocket, LiteSpeed, regras do Cloudflare), exclua `/wp-json/mvf/*`. Sem cache de objetos persistente, o limite por IP grava uma linha temporária em `wp_options` a cada voto; nos períodos de votação, vale ativar Redis ou Memcached.
