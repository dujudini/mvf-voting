# MVF Voting

🇺🇸 [Read in English](README.md)

Plugin WordPress de votação de clipes feito para o [Music Video Festival](https://www.musicvideofestival.com.br/) (MVF).

A versão original deste plugin rodou as votações do festival em **2022, 2023 e 2024**. Este repositório é uma reconstrução desse projeto para o portfólio, reescrita com práticas atuais do WordPress. É um **projeto legado**: não é o sistema de votação usado hoje no site.

## Meu papel

Desenvolvi o plugin original sozinho, de ponta a ponta: levantamento de requisitos, modelagem de dados, back-end em PHP, API REST, telas do admin, página pública de votação, segurança e testes.

## O problema

O site do MVF tem cerca de 30 mil acessos por mês, com picos de até 90 mil nos períodos de votação. O festival precisava de duas votações independentes:

- **Público:** anônima, sem cadastro e sem limite de votos, mas resistente a robôs.
- **Júri:** jurados com login ordenam os clipes das categorias atribuídas a eles, e cada posição vale pontos.

A organização também precisava cadastrar edições anuais, categorias com número máximo de clipes, clipes do YouTube e jurados por categoria. Os rankings ficam só no painel, nunca no site.

## Decisões técnicas

### Aguentar os picos de acesso

- **A página de votação pode ficar inteira em cache.** O HTML é estático e o voto vai por um `POST` à API REST (`/wp-json/mvf/v1/vote`), que nunca passa pelo cache. Só o voto chega ao PHP.
- **Tabela própria para os votos.** Cada voto é uma linha numa tabela indexada por categoria, clipe e IP, em vez de metadados de post. A contagem do ranking é um `GROUP BY` simples.
- **Player do YouTube só no clique.** A página mostra a thumbnail e troca pelo iframe quando a pessoa clica, então uma página com dezenas de clipes continua leve.
- **Compatível com temas de bloco.** Nos testes apareceu um bug: em temas de bloco o shortcode roda antes do `wp_enqueue_scripts`, e a configuração do JavaScript não era impressa. Os assets passaram a ser registrados na hora em que o shortcode roda.

### Votação anônima sem fraude fácil

- **Cloudflare Turnstile verificado no servidor**, com um token novo a cada voto. Na maioria dos casos o desafio é invisível para o visitante.
- **Intervalo mínimo entre votos por IP** (padrão 5s, configurável). O público pode votar à vontade; o limite só segura scripts.
- **IP real atrás do Cloudflare só quando habilitado.** O cabeçalho `CF-Connecting-IP` é ignorado se o site não estiver atrás do proxy, porque nesse caso qualquer um pode forjá-lo.
- **Auditoria em vez de bloqueio cego.** Uma tela mostra os IPs com mais votos e os votos por hora, para achar picos suspeitos. Votos podem ser invalidados e revalidados em massa, e nunca são apagados.

### Privacidade (LGPD)

- IP e navegador são gravados apenas como hash HMAC-SHA256, com o salt do próprio site.
- Um cookie first-party com um identificador aleatório serve só para auditoria.
- O texto do aviso de privacidade é configurável e aparece abaixo da votação.

### Regras do júri

- **Pontos calculados na leitura, não gravados.** Cada cédula guarda só a posição de cada clipe. Mudar a tabela de pontos recalcula todos os resultados.
- **Desempate por colocações:** vence quem tem mais 1º lugares; persistindo, mais 2º lugares, e assim por diante. Empate em tudo divide a posição.
- **Validação no servidor:** o jurado só grava em categorias atribuídas a ele, dentro do período do júri, e a lista enviada precisa ter exatamente os clipes da categoria, sem repetição.
- **Cédulas desatualizadas são sinalizadas.** Se um clipe entra ou sai da categoria depois do envio, o painel de progresso avisa e o jurado vê um alerta para revisar.

### Integridade do cadastro

- **Limite de clipes por categoria validado no servidor**, não só na interface. Mesmo forçando o checkbox pelo navegador, o clipe não entra numa categoria cheia.
- **O mesmo nome de categoria em edições diferentes.** O WordPress não aceita termos com nome repetido, então o slug é gerado como `nome-edição`. Nome repetido na mesma edição continua bloqueado.
- **Edições com cópia de categorias**, para montar o ano seguinte sem refazer tudo.

### Segurança no admin

- Capabilities próprias: `mvf_manage` para a organização e `mvf_vote_jury` para jurados, que do plugin só acessam a própria cédula.
- Nonce em todos os formulários do admin e na API do júri, queries com `$wpdb->prepare` e escape na saída HTML.

## Como foi validado

O plugin foi testado num WordPress local (WordPress Playground) em **PHP 7.4 e PHP 8.0**, com `WP_DEBUG` ligado e sem nenhum warning nas telas do admin nem no site:

- **Voto do público:** voto aceito com Turnstile válido, recusado com token inválido, bloqueado pelo intervalo por IP (HTTP 429) e recusado para clipe fora da categoria ou com a votação fechada.
- **Júri:** ordenação por arrastar e por setas, rascunho e envio, bloqueio de categoria não atribuída, e um empate montado de propósito para conferir pontos e desempate.
- **Admin:** limite de categoria forçado pelo navegador, cópia de categorias entre edições, invalidação em massa na auditoria refletindo no ranking, e os três CSVs abertos com acentos corretos.
- **Celular:** layout em 375px sem rolagem horizontal.

Não há testes automatizados.

## Stack

- PHP 7.4+ e WordPress 6.4+ (custom post type, taxonomias, REST API, Settings API)
- MySQL com tabelas próprias criadas via `dbDelta`
- JavaScript sem build; jQuery UI Sortable, que já vem no WordPress, para a cédula do júri
- CSS puro com variáveis para o tema sobrescrever
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

1. Copie a pasta para `wp-content/plugins/` e ative o plugin. A ativação cria as tabelas, a função **Jurado MVF** e o menu **MVF Votação**.
2. Em **MVF Votação → Configurações**, informe as chaves do Turnstile. As chaves de teste da Cloudflare (`1x00000000000000000000AA` / `1x0000000000000000000000000000000AA`) servem para rodar localmente.
3. Crie uma edição, as categorias e os clipes, e atribua jurados (usuários com a função *Jurado MVF*).
4. Coloque o shortcode numa página:

```
[mvf_votacao]                         edição ativa, todas as categorias
[mvf_votacao categoria="slug"]        só uma categoria
[mvf_votacao edicao="2026"]           outra edição (slug ou ID)
```

Se o site usar cache de página (WP Rocket, LiteSpeed, regras do Cloudflare), exclua `/wp-json/mvf/*`. Sem cache de objetos persistente, o limite por IP grava uma linha temporária em `wp_options` a cada voto, então vale ativar Redis ou Memcached nos períodos de votação.
