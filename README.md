# Garion Projetos — Technical SEO Toolkit

Plugin WordPress próprio para SEO técnico, indexação e rastreabilidade.

## Funcionalidades

- Gerenciamento de redirecionamentos (com import/export CSV)
- Monitor de 404: registra "página não encontrada" reais e permite criar redirecionamento com um clique
- Sitemap XML (index + sitemaps paginados por tipo de conteúdo), linkado automaticamente no robots.txt
- Detecção de links quebrados (scanner assíncrono via WP-Cron)
- Inserção de dados estruturados (Schema.org)
- Controle de canonical
- Configuração de robots.txt / meta robots
- Open Graph / Twitter Card, com overrides por post e preview ao vivo no editor
- Auditoria de páginas, com busca e paginação
- Integração com REST API do WordPress
- Tela administrativa no wp-admin

## Requisitos

- WordPress 6.x+
- PHP 8.0+

## Estrutura

```
garion-projetos-technical-seo-toolkit/
├── garion-projetos-technical-seo-toolkit.php
├── includes/
│   ├── class-gpseo-redirects.php
│   ├── class-gpseo-404-monitor.php
│   ├── class-gpseo-broken-links.php
│   ├── class-gpseo-sitemap.php
│   ├── class-gpseo-structured-data.php
│   ├── class-gpseo-canonical.php
│   ├── class-gpseo-robots.php
│   ├── class-gpseo-social-meta.php
│   ├── class-gpseo-audit.php
│   └── class-gpseo-rest-controller.php
├── admin/
│   ├── class-gpseo-admin-page.php
│   └── class-gpseo-metabox.php
└── assets/
    ├── css/admin.css
    └── js/admin.js, metabox-social.js
```

## Status

✅ Funcional.
