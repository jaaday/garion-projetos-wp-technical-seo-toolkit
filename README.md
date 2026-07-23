# Garion Projetos Technical SEO Toolkit

Plugin WordPress independente para SEO técnico, auditoria e manutenção, com interoperabilidade opcional com outros plugins de SEO.

## Autoria e independência

O código deste plugin é uma implementação original da Garion Projetos. O Rank Math foi analisado apenas como referência de boas práticas de produto, segurança, desempenho e convivência com o ecossistema WordPress.

Nenhum arquivo, classe, função, algoritmo, texto, interface, asset ou trecho de código-fonte do Rank Math foi copiado, adaptado ou redistribuído. Quando o Rank Math está instalado, a comunicação ocorre exclusivamente por hooks públicos e pelas APIs do WordPress. O toolkit não depende do Rank Math e funciona normalmente sem ele.

## Gestão de resultados 0.5.1

- Aba Problemas com busca, filtros, ordenação, paginação e ações de ciclo de vida.
- Detalhes de conteúdo, problema e auditoria com evidência, origem do dado e orientação de correção.
- Cálculo explicável com penalidade bruta, penalidade aplicada e limite por categoria persistidos.
- Comparação, exportação JSON/CSV e consulta de auditorias históricas.
- Endpoints REST protegidos para auditorias, conteúdos e problemas.
- Tabelas operacionais ampliadas com último acesso, URL final, rechecagem e itens ignorados.
## Fundação de auditoria 0.5.0

- Motor extensível com verificações independentes, contexto e resultados normalizados.
- Pontuação ponderada por severidade, com limite de penalidade por categoria.
- Auditorias completas ou individuais em lotes retomáveis pelo WP-Cron.
- Bloqueio contra execuções concorrentes, heartbeat, cancelamento e recuperação após timeout.
- Histórico persistente de pontuação e ciclo de vida dos problemas.
- Providers centralizados para Toolkit, WordPress, Rank Math e Yoast SEO.
- Visão geral administrativa, histórico de execuções e progresso via REST.
- Retenção configurável e remoção de dados somente mediante consentimento explícito.

Documentação técnica:

- [Arquitetura da Fase 1](docs/phase-1-architecture.md)
- [Tabelas](docs/database.md)
- [API REST](docs/rest-api.md)
- [Hooks públicos](docs/hooks.md)
- [Testes e diagnóstico](docs/testing.md)
- [Riscos e roteiro das próximas fases](docs/implementation-roadmap.md)
## Principais recursos

- Redirecionamentos 301, 302, 307 e 308, com prevenção de loops e importação/exportação CSV.
- Monitor de erros 404 com retenção automática de 90 dias.
- Scanner assíncrono de links quebrados, executado em lotes e iniciado diariamente pelo WP-Cron.
- Auditoria de páginas e posts com análise de títulos, descrição, imagem destacada, indexação e links quebrados.
- Sitemap XML próprio quando o módulo de sitemap do Rank Math não está ativo.
- Canonical, meta description, robots, Open Graph e Twitter Cards com overrides por conteúdo.
- Schema.org para sites que não utilizam o módulo Schema do Rank Math.
- Regras adicionais de `robots.txt`.
- Endpoints REST protegidos por capacidade administrativa.

## Interoperabilidade opcional com Rank Math

Desde a versão 0.4.0, o toolkit detecta o Rank Math e evita saídas duplicadas:

| Recurso | Com Rank Math ativo |
| --- | --- |
| Canonical e meta description | Overrides do toolkit são enviados aos filtros oficiais do Rank Math. |
| Meta robots | `noindex` e `nofollow` do toolkit são mesclados com as diretivas do Rank Math. |
| Open Graph e Twitter | Overrides sociais são aplicados às tags geradas pelo Rank Math. |
| Schema | A saída própria é desativada quando o módulo Schema do Rank Math está ativo. |
| Sitemap | O sitemap próprio é desativado quando o módulo Sitemap do Rank Math está ativo. |
| `robots.txt` | Regras extras são preservadas sem repetir a linha do sitemap. |
| Monitor 404 | O registro próprio pausa quando o monitor do Rank Math está ativo, evitando gravação duplicada. |
| Redirecionamentos | Continuam disponíveis; regras do toolkit têm prioridade para os caminhos cadastrados nele. |
| Links quebrados e auditoria | Permanecem ativos como recursos complementares. |

Os campos do metabox do toolkit continuam editáveis. Quando o Rank Math está ativo, eles funcionam como overrides de frontend e não imprimem um segundo conjunto de tags.

## Segurança e desempenho

- O scanner usa `wp_safe_remote_head()` e `wp_safe_remote_get()` para bloquear requisições a destinos inseguros ou redes privadas.
- URLs relativas são normalizadas antes da verificação.
- Cada conteúdo verifica no máximo 100 links por ciclo.
- Resultados antigos são substituídos a cada nova leitura, evitando falsos positivos persistentes.
- A varredura automática completa inicia uma vez por dia e processa pequenos lotes em segundo plano.
- Redirecionamentos recusam protocolos não HTTP(S) e loops para a própria URL.
- Atualizações de versão executam migrações de tabelas automaticamente.

## Requisitos

- WordPress 6.0 ou superior.
- PHP 8.1 ou superior.
- Rank Math é opcional.

## Instalação

1. Envie a pasta `garion-projetos-technical-seo-toolkit` para `/wp-content/plugins/`.
2. Ative o plugin em **Plugins**.
3. Abra **Technical SEO** no painel.
4. Quando usar Rank Math, mantenha os módulos desejados ativos; o toolkit detectará a configuração automaticamente.

## Estrutura

```text
garion-projetos-technical-seo-toolkit/
├── admin/
├── assets/
├── docs/
├── includes/
│   ├── audit/
│   │   └── checks/
│   └── providers/
├── tests/
├── garion-projetos-technical-seo-toolkit.php
├── readme.txt
└── uninstall.php
```

## Versão 0.5.0

- Motor modular com checks registráveis por filtro público.
- Pontuação ponderada e normalizada por categoria.
- Quatro novas tabelas para execuções, resultados, problemas e histórico.
- Auditoria completa e individual assíncrona, retomável e cancelável.
- Cursor por ID, heartbeat e lock expirável contra lotes duplicados.
- Providers centralizados para metadados do toolkit, WordPress, Rank Math e Yoast.
- Novos endpoints REST administrativos e telas de visão geral/histórico.
- Retenção configurável e desinstalação com remoção de dados opt-in.
- Interoperabilidade opcional por meio dos filtros públicos de frontend, Open Graph e sitemap do Rank Math.
- Supressão automática de tags e sitemaps duplicados.
- Monitor 404 compatível com o módulo equivalente do Rank Math.
- Scanner de links protegido contra SSRF e resultados obsoletos.
- Cron diário em vez de varredura contínua a cada dez minutos.
- Migrações automáticas de banco após atualizações.
- Redirecionamentos 307/308, validação de protocolos, prevenção de loops e contador atômico.
- Schema independente mais completo e válido.
- Auditoria compatível com `rank_math_robots` e contagem multibyte de títulos.
