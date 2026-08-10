# Security Notes

This showcase intentionally demonstrates common WordPress integration security controls:

- `manage_options` capability checks for administrator actions.
- WordPress nonces for AJAX requests.
- HMAC-SHA256 signatures for webhook authenticity.
- `hash_equals` for timing-safe signature comparison.
- Sanitization and escaping at WordPress input/output boundaries.
- Encrypted shared-secret storage using WordPress authentication salt material.
- No secret is returned to the browser after it has been saved.
- REST payloads are rejected when signatures fail.

For a production deployment, rotate webhook secrets periodically and enforce HTTPS at the application and reverse-proxy layers.
