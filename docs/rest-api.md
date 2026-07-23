# Administrative REST API

Namespace: `garion-projetos-technical-seo-toolkit/v1`.

Every route declares a permission callback. All routes require `manage_options`; content-specific routes additionally require `edit_post` for the requested content ID. IDs, pagination and filters are sanitized and bounded.

- `POST /audits`: start a full asynchronous audit.
- `GET /audits/history`: retrieve bounded site or content score history.
- `GET /audits/{id}`: execution state, progress, score, metrics, severities, categories and worst content.
- `GET /audits/{id}/issues`: paginated immutable findings from an audit, including historical runs.
- `POST /audits/{id}/cancel`: cancel pending or running work.
- `POST /contents/{id}/audit`: start an asynchronous audit for one content item.
- `GET /contents/{id}/issues`: retrieve paginated current issues for one content item.
- `GET /issues`: search, filter, order and paginate current issues.
- `GET /issues/{id}`: retrieve the complete issue, evidence and remediation action.
- `POST /issues/{id}/ignore`: ignore an issue with an optional reason.
- `POST /issues/{id}/reopen`: return an ignored issue to the open lifecycle.
- `GET /broken-links/status`: retrieve scanner state.
- `POST /broken-links/scan`: trigger the asynchronous scanner.

Responses use REST normalization or `WP_Error`; mutations are protected by the WordPress REST nonce when called from wp-admin.