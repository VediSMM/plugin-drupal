# VediSMM for Drupal

VediSMM for Drupal connects Drupal editorial content to the VediSMM API at `https://vedismm.ru/api/v1`.
The plugin will normalize CMS content into the shared `DraftInput` contract, create VediSMM drafts, and let an editor explicitly schedule or publish them.

Status: `0.1.0-dev`.

## Planned Scope

- Store the VediSMM API token only in server-side Drupal configuration.
- Map a Drupal content entity to `title`, `content`, `link`, `account_ids`, `group_ids`, and `media_ids`.
- Create drafts with a stable CMS-scoped idempotency key.
- Offer explicit draft, schedule, and publish actions. No automatic publish on content save.
- Offer native, dependent per-submission link-shortening and network-source controls, both off by default.
- Redact credentials and API tokens from logs, UI, test output, and screenshots.

Tracking choices are sent only as `options.tracking.shorten_links` and
`options.tracking.add_source`. Source attribution is available only while link
shortening is enabled; disabling shortening forces source attribution off. The
module sends original content and URLs unchanged and stores no generated-link
state. Drupal Form API supplies the form token/CSRF protection; no custom token
field is introduced.

When source attribution is enabled, VediSMM uses each target network key. If a
non-empty `utm_source` is absent, it adds `utm_source=<network>` and preserves
any existing `utm_term`. If `utm_source` exists, it is preserved and all
existing `utm_term` values are replaced (or one is added) with exactly one
`utm_term=<network>`. Other query parameters, encoded values, their order, and
the fragment remain unchanged.

## Local Development

This repository is intentionally independent from the main VediSMM application repository.
Use the shared CMS plugin contract fixtures from `docs/cms-plugin-contract.json` in the main repository until a packaged test fixture is released here.

## Distribution

The free public listing target is Drupal.org Projects: https://www.drupal.org/project/project_module
Russian documentation is available in [README.ru.md](README.ru.md).

Support: GitHub Issues.
Security reports: use this repository's GitHub Security policy, not public issues.
