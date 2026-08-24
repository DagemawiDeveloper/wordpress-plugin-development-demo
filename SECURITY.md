# Security Policy and Design Notes

## Supported version

Security fixes are applied to the latest release line of this reference plugin.

## Reporting a vulnerability

Do not publish exploitable details in a public issue. Contact the repository owner privately through the contact method on the GitHub profile and include:

- affected version and file;
- reproduction steps;
- expected and observed behavior;
- realistic impact;
- any suggested mitigation.

## Trust boundaries

The plugin assumes:

- WordPress core and the database are trusted;
- administrators with `manage_options` are trusted to configure endpoints and secrets;
- outbound remote services are untrusted;
- inbound callers are untrusted until canonical HMAC verification succeeds;
- webhook payloads may contain sensitive business data.

## Implemented controls

- Administrator capability checks for settings, tests, and retries.
- WordPress nonces for AJAX requests.
- Canonical HMAC-SHA256 signatures covering timestamp, delivery ID, event, and exact raw body.
- `hash_equals()` for timing-safe comparison.
- Configurable timestamp tolerance to reject stale requests.
- Unique accepted delivery IDs to prevent replay.
- A 1 MB inbound body limit.
- AES-256-GCM authenticated encryption for new secrets and outbound retry payloads.
- Backward-compatible decryption of the prior authenticated AES-CBC format.
- Fail-closed behavior when OpenSSL or WordPress authentication salts are unavailable.
- No plaintext/Base64 crypto downgrade.
- Signing secrets are never returned to the browser after saving.
- Public HTTPS-only outbound endpoints.
- `wp_safe_remote_post()` with redirects disabled and bounded timeout.
- Inbound payloads are not stored in logs; only size and SHA-256 metadata are retained.
- Remote response bodies are represented by size and SHA-256 metadata instead of persisted verbatim.
- Optional settings/log cleanup on uninstall.

## Operational requirements

- Generate a random secret of at least 32 characters.
- Keep WordPress authentication salts private and rotate them only with an understood secret-migration plan.
- Use HTTPS end to end.
- Keep system clocks synchronized because timestamp verification depends on them.
- Rotate webhook secrets periodically and after any suspected disclosure.
- Apply rate limiting at the web server, CDN, WAF, or reverse proxy.
- Set database retention policies appropriate for delivery metadata.
- Monitor repeated signature failures, replay responses, and terminal outbound failures.

## Explicit limitations

The repository does not provide a distributed replay ledger across multiple independent WordPress databases, automated secret rotation, edge rate limiting, centralized alerting, or workload-specific data-loss-prevention rules. Those controls depend on deployment architecture and should be added for production use.
