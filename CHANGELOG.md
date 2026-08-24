# Changelog

All notable changes to this project are documented here.

## [Unreleased]

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
