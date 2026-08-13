# VediSMM for Drupal Guide

VediSMM for Drupal adds a permission-protected content submission workflow for Drupal 11.

The module stores the VediSMM token in State API, not exported configuration. The default action creates a VediSMM draft; publish is an explicit action protected by Drupal permission and CSRF checks.

Administrators configure the API URL and token at **Configuration → Web services
→ VediSMM**. The saved token is never rendered back into the form: leave the
password field blank to preserve it, or use the separate removal checkbox to
clear it explicitly. Requests fail closed while no token is saved.

## Tracking Links

The submission form uses native Drupal Form API checkboxes for **Shorten
links** and **Add network source**. Both default to off. Form API disables the
source control while shortening is off, and the server mapping always forces
source attribution off in that state. Drupal's native Form API handles CSRF.
Values are sent only under `options.tracking`; the module neither rewrites
entity URLs nor stores generated-link state.

VediSMM creates a separate short link for each target network. With source
attribution enabled, if a non-empty `utm_source` is absent, VediSMM adds
`utm_source=<network>` and preserves an existing `utm_term`. If `utm_source`
exists, VediSMM preserves it and replaces every existing `utm_term` (or adds
one) with exactly one `utm_term=<network>`. Other query parameters, encoded
values, their order, and the fragment remain unchanged.
