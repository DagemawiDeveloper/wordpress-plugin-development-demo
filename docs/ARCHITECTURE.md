# Architecture

```mermaid
flowchart LR
    A[WordPress / WooCommerce / Custom Plugin] -->|wpitk_send_event| B[Webhook Service]
    B --> C[HMAC Signature]
    B --> D[(Webhook Log Table)]
    B -->|HTTPS POST| E[External API]
    E -->|HTTP response| B

    F[External System] -->|Signed JSON| G[WP REST API]
    G --> H{Valid HMAC?}
    H -->|No| I[401 + Rejected Log]
    H -->|Yes| J[Accepted Log]
    J --> K[wpitk_inbound_webhook_received]
    K --> L[Custom Business Logic]
```

## Design choices

### Service separation
Delivery, logging, encryption, REST routing and admin concerns are separated into focused classes. This keeps integration logic testable and prevents the main plugin bootstrap from becoming a monolith.

### HMAC authentication
Inbound and outbound webhook payloads use HMAC-SHA256 signatures so the receiver can validate message authenticity without exposing the shared secret over the wire.

### Secret-at-rest protection
The shared secret is encrypted before storage using keys derived from WordPress authentication salts. It is never rendered back into the settings page.

### Observability
Each inbound and outbound attempt is persisted with status, HTTP response code, payload metadata and retry count. Failed outbound events can be retried from wp-admin.

### Extensibility
`wpitk_send_event` and `wpitk_inbound_webhook_received` let other plugins integrate without modifying this plugin's source.
