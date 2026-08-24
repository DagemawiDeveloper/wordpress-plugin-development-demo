# WP Integration Toolkit

[![PHP quality](https://github.com/DagemawiDeveloper/wordpress-plugin-development-demo/actions/workflows/php-lint.yml/badge.svg)](https://github.com/DagemawiDeveloper/wordpress-plugin-development-demo/actions/workflows/php-lint.yml)

**An installable WordPress reference plugin for authenticated inbound and outbound webhooks, REST APIs, AJAX administration, encrypted retry payloads, delivery logging, and maintainable PHP architecture.**

The project is deliberately focused on the failure-prone parts of integrations: authenticating callbacks, preventing replay, preserving a stable delivery ID across retries, keeping secrets out of the browser, avoiding unsafe outbound destinations, and retaining enough metadata to debug failures without storing inbound payloads in plaintext.

## What this project demonstrates

- Installable WordPress plugin structure and lifecycle
- Signed inbound and outbound webhooks
- Canonical HMAC-SHA256 authentication covering timestamp, delivery ID, event, and raw body
- Timestamp expiry and database-enforced replay protection
- AES-256-GCM secret and outbound retry-payload encryption
- Backward-compatible reading of the earlier authenticated AES-CBC format
- Fail-closed behavior when secure cryptography is unavailable
- Public HTTPS endpoint validation and `wp_safe_remote_post()`
- Custom delivery audit table with stable delivery identifiers
- REST endpoints and WordPress action hooks
- Capability checks, nonces, sanitization, and escaped admin output
- GitHub Actions linting and security-focused PHPUnit tests on PHP 7.4, 8.2, and 8.3

## Architecture

```mermaid
flowchart LR
    WP[WordPress / WooCommerce] -->|Action hook| WS[Webhook Service]
    WS --> ID[Stable delivery ID]
    WS --> ENC[Encrypt retry payload]
    WS --> LOG[(Delivery log)]
    WS --> SIGN[Canonical HMAC signature]
    SIGN -->|Safe HTTPS POST| API[External API]

    EXT[External system] -->|Signed JSON + metadata| REST[WP REST API]
    REST --> VERIFY{Signature + timestamp valid?}
    VERIFY -->|No| REJECT[401 + metadata-only audit]
    VERIFY -->|Yes| UNIQUE{New delivery ID?}
    UNIQUE -->|No| REPLAY[409 replay]
    UNIQUE -->|Yes| EVENT[WordPress action]
    EVENT --> LOG
```

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for design rationale and [`docs/API.md`](docs/API.md) for the wire protocol.

## Admin workflow

The plugin adds **Integration Toolkit** to `wp-admin`, where an administrator can:

1. Configure a public HTTPS outbound endpoint.
2. Save a randomly generated signing secret of at least 32 characters.
3. Choose request timeout and signature clock tolerance.
4. Send a real test webhook.
5. Inspect delivery status, response codes, and attempt counts.
6. Retry a failed outbound delivery using its original encrypted payload and stable delivery ID.
7. Copy the inbound REST endpoint.

Secrets are never rendered back into the browser after saving.

## Webhook authentication protocol

Every authenticated request uses four values:

```text
X-WPITK-Timestamp: Unix timestamp
X-WPITK-Delivery: stable unique delivery ID
X-WPITK-Event: lowercase event name
X-WPITK-Signature: hex HMAC-SHA256
```

The canonical signing payload is:

```text
<timestamp>\n<delivery-id>\n<event>\n<exact-raw-body>
```

Example PHP signing code:

```php
$timestamp  = (string) time();
$deliveryId = 'delivery-' . bin2hex(random_bytes(16));
$event      = 'customer.updated';
$body       = json_encode(['customer_id' => 42], JSON_THROW_ON_ERROR);

$canonical = implode("\n", [$timestamp, $deliveryId, $event, $body]);
$signature = hash_hmac('sha256', $canonical, $secret);
```

Inbound requests are rejected when the signature is wrong, required metadata is malformed, the timestamp is outside the configured tolerance, the body exceeds 1 MB, or the delivery ID has already been accepted.

## REST API

### Health

```bash
curl https://example.com/wp-json/wp-integration-toolkit/v1/health
```

### Inbound webhook

```bash
curl -X POST \
  https://example.com/wp-json/wp-integration-toolkit/v1/webhooks/inbound \
  -H 'Content-Type: application/json' \
  -H 'X-WPITK-Event: customer.updated' \
  -H 'X-WPITK-Delivery: delivery-0123456789abcdef' \
  -H 'X-WPITK-Timestamp: 1770000000' \
  -H 'X-WPITK-Signature: <canonical-hmac-sha256>' \
  -d '{"customer_id":42,"status":"active"}'
```

Full details: [`docs/API.md`](docs/API.md).

## Dispatch an outbound webhook

Other plugins can dispatch an event without knowing how HTTP delivery, signing, encryption, logging, or retries are implemented:

```php
do_action(
    'wpitk_send_event',
    'order.created',
    array(
        'order_id' => 123,
        'total'    => 149.99,
    )
);
```

## Security model

- Administrator capability checks.
- AJAX nonces.
- Authenticated canonical request signatures.
- Timing-safe comparison.
- Timestamp expiry.
- Replay prevention through a unique accepted delivery ID.
- AES-256-GCM encryption for secrets and retry payloads.
- No Base64/plaintext crypto fallback.
- Public HTTPS-only outbound endpoints.
- Redirects disabled for outbound webhook delivery.
- Inbound logs retain only size and SHA-256 metadata, not raw payloads.
- Outbound payloads are encrypted at rest for controlled retry behavior.
- Optional data cleanup on uninstall.

See [`SECURITY.md`](SECURITY.md) for trust boundaries and remaining production considerations.

## Installation

1. Copy this repository into `wp-content/plugins/wp-integration-toolkit`.
2. Activate **WP Integration Toolkit** in WordPress.
3. Open **Integration Toolkit** in `wp-admin`.
4. Configure a public HTTPS endpoint and a random signing secret of at least 32 characters.
5. Use **Send Test Webhook** to verify connectivity.

### Requirements

- WordPress 6.0+
- PHP 7.4+
- OpenSSL
- Correctly configured WordPress `AUTH_KEY` and `SECURE_AUTH_SALT`
- HTTPS

## Development and tests

```bash
composer install
composer lint
composer test
```

The current automated suite focuses on the framework-independent security core:

- Valid canonical signatures.
- Tampered body, event, and delivery metadata.
- Expired timestamps.
- Malformed headers.
- Authenticated encryption round trips.
- Ciphertext tampering.
- Rejection of plaintext and Base64 secret values.

WordPress integration behavior should additionally be exercised in a real test site before release.

## Project structure

```text
wordpress-plugin-development-demo/
├── wp-integration-toolkit.php
├── uninstall.php
├── includes/
│   ├── class-wpitk-activator.php
│   ├── class-wpitk-admin.php
│   ├── class-wpitk-crypto.php
│   ├── class-wpitk-logger.php
│   ├── class-wpitk-plugin.php
│   ├── class-wpitk-rest-controller.php
│   ├── class-wpitk-webhook-auth.php
│   └── class-wpitk-webhook-service.php
├── tests/
│   ├── bootstrap.php
│   ├── CryptoTest.php
│   └── WebhookAuthTest.php
├── assets/
├── docs/
├── .github/workflows/php-lint.yml
├── phpunit.xml.dist
├── SECURITY.md
└── LICENSE
```

## Scope

This is a public engineering reference implementation, not a hosted webhook platform. Production deployments should add environment-specific monitoring, retention policies, secret rotation procedures, rate limiting at the edge, and operational alerting.

## Author

**Dagemawi Alemayehu**  
PHP / Laravel / WordPress / API Integration / SaaS Development
