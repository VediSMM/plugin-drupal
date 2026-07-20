# VediSMM for Drupal

VediSMM for Drupal connects Drupal editorial content to the VediSMM API at `https://vedismm.ru/api/v1`.
The plugin will normalize CMS content into the shared `DraftInput` contract, create VediSMM drafts, and let an editor explicitly schedule or publish them.

Status: `0.1.0-dev`.

## Planned Scope

- Store the VediSMM API token only in server-side Drupal configuration.
- Map a Drupal content entity to `title`, `content`, `link`, `account_ids`, `group_ids`, and `media_ids`.
- Create drafts with a stable CMS-scoped idempotency key.
- Offer explicit draft, schedule, and publish actions. No automatic publish on content save.
- Redact credentials and API tokens from logs, UI, test output, and screenshots.

## Local Development

This repository is intentionally independent from the main VediSMM application repository.
Use the shared CMS plugin contract fixtures from `docs/cms-plugin-contract.json` in the main repository until a packaged test fixture is released here.

## Distribution

The free public listing target is Drupal.org Projects: https://www.drupal.org/project/project_module
Russian documentation is available in [README.ru.md](README.ru.md).

Support: GitHub Issues.
Security reports: use this repository's GitHub Security policy, not public issues.
