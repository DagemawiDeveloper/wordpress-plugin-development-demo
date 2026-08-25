# Changelog

All notable changes to this project are documented here.

## [1.2.0] - 2026-08-25

### Added

- Custom dynamic `Integration Status` Gutenberg block registered from `block.json` metadata.
- React-based editor UI using WordPress `wp.element`, `InspectorControls`, `TextControl`, `ToggleControl`, and `ServerSideRender`.
- PHP render callback that evaluates current integration readiness without exposing endpoint or secret values.
- Frontend/editor block styles and configurable editor labels.
- PHPUnit coverage for block registration, React/Block Editor dependencies, configured/unconfigured rendering, output escaping, and secret non-disclosure.
- GitHub Actions checks for block metadata and editor JavaScript syntax.
- Gutenberg implementation and build-trade-off documentation.

## [1.1.0] - 2026-08-24

### Security

- Authenticate timestamp, delivery ID, event name, and exact raw webhook body with canonical HMAC-SHA256 signatures.
- Reject stale signatures and replayed inbound delivery IDs.
- Replace insecure encryption fallbacks with fail-closed AES-256-GCM secret and retry-payload encryption.
- Require public HTTPS endpoints and use WordPress safe HTTP handling for outbound delivery.
- Avoid retaining inbound payloads and arbitrary remote response bodies in plaintext logs.

### Added

- Versioned database schema upgrades and stable delivery identifiers.
- Security-focused PHPUnit tests and multi-version PHP CI.
- Expanded API, architecture, security, and contribution documentation.

## [1.0.0]

- Initial public reference implementation.
