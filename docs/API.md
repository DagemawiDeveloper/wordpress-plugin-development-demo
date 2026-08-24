# API Reference

## Health endpoint

```http
GET /wp-json/wp-integration-toolkit/v1/health
```

Example response:

```json
{
  "status": "ok",
  "version": "1.1.0",
  "time": "2026-08-24T00:00:00Z"
}
```

## Inbound webhook

```http
POST /wp-json/wp-integration-toolkit/v1/webhooks/inbound
Content-Type: application/json
X-WPITK-Event: customer.updated
X-WPITK-Delivery: delivery-0123456789abcdef
X-WPITK-Timestamp: 1770000000
X-WPITK-Signature: <hex hmac sha256>
```

All four authentication headers are required. `X-WPITK-Event` must be a lowercase event token, `X-WPITK-Delivery` must be a stable identifier between 8 and 128 allowed characters, and `X-WPITK-Timestamp` must fall inside the configured clock tolerance.

### Canonical signature

The signature is the lowercase hexadecimal HMAC-SHA256 digest of:

```text
<timestamp>\n<delivery-id>\n<event>\n<exact-raw-body>
```

The raw body must not be decoded and re-encoded before verification.

Example PHP generation:

```php
$timestamp  = (string) time();
$deliveryId = 'delivery-' . bin2hex(random_bytes(16));
$event      = 'customer.updated';
$body       = json_encode($payload, JSON_THROW_ON_ERROR);

$canonical = implode("\n", [$timestamp, $deliveryId, $event, $body]);
$signature = hash_hmac('sha256', $canonical, $secret);
```

### Responses

| Status | Meaning |
|---|---|
| `202` | Authenticated, new delivery accepted |
| `400` | Body is not valid JSON |
| `401` | Signature or timestamp is invalid/expired |
| `409` | Delivery ID has already been accepted |
| `413` | Body exceeds 1 MB |
| `503` | Secure webhook authentication is not configured |

Accepted requests trigger:

```php
do_action('wpitk_inbound_webhook_received', $payload, $event, $logId);
```

## Outbound events from WordPress

Other code can dispatch a webhook without coupling itself to the delivery implementation:

```php
do_action(
    'wpitk_send_event',
    'order.created',
    array('order_id' => 123)
);
```

Outbound requests use the same four authentication headers and canonical signing format. Retries preserve the original delivery ID and exact encrypted body, but use a fresh timestamp and signature.

Outbound delivery requires:

- a public HTTPS URL;
- a configured signing secret of at least 32 characters;
- OpenSSL and WordPress authentication salts;
- a receiver that returns HTTP `2xx` on success.
