# API Reference

## Health endpoint

```http
GET /wp-json/wp-integration-toolkit/v1/health
```

Example response:

```json
{
  "status": "ok",
  "version": "1.0.0",
  "time": "2026-08-11T00:00:00Z"
}
```

## Inbound webhook

```http
POST /wp-json/wp-integration-toolkit/v1/webhooks/inbound
Content-Type: application/json
X-WPITK-Event: customer.updated
X-WPITK-Signature: <hex hmac sha256>
```

The signature is the hex-encoded HMAC-SHA256 digest of the **raw request body**, using the secret configured in WordPress.

Example PHP signature generation:

```php
$body = json_encode($payload);
$signature = hash_hmac('sha256', $body, $secret);
```

Accepted requests trigger:

```php
do_action('wpitk_inbound_webhook_received', $payload, $event, $log_id);
```

## Outbound events from WordPress

Other code can dispatch a signed webhook without coupling itself to the delivery implementation:

```php
do_action(
    'wpitk_send_event',
    'order.created',
    array('order_id' => 123)
);
```
