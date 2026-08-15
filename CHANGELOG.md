# Changelog

## 1.1.0 - 2026-08-15

- Add native dependent Drupal Form API tracking controls and nested
  `options.tracking` request mapping with strict value normalization.
- Wire the permission-protected route to an injectable native Form API
  submission form that loads and sends the selected content entity.
- Bind the State API token and Drupal HTTP client into the production gateway,
  and enforce supported entity type plus update access before sending.
- Document per-network attribution and exact `utm_source`/`utm_term`
  precedence in English and Russian.
- Initialize the independent Drupal plugin repository boundary.
- Add distribution, contribution, and security documents.
