=== WP Integration Toolkit ===
Contributors: dagemawideveloper
Tags: webhook, rest-api, integration, security, developer-tools
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An installable WordPress reference plugin for authenticated webhooks, encrypted retry payloads, delivery logging, REST APIs, and AJAX administration.

== Description ==

WP Integration Toolkit demonstrates maintainable WordPress integration patterns including:

* Canonical HMAC-SHA256 request authentication
* Timestamp expiry and replay protection
* Stable delivery identifiers across retries
* AES-256-GCM secret and retry-payload encryption
* Fail-closed cryptography
* Public HTTPS endpoint validation
* REST webhook endpoints
* Custom database delivery logging
* AJAX test and retry actions
* Capability and nonce checks
* PHPUnit and GitHub Actions quality checks

This repository is an engineering showcase and reference implementation. Review SECURITY.md before adapting it for production.

== Changelog ==

= 1.1.0 =
* Harden webhook authentication with timestamp, event, delivery ID, and raw-body signing.
* Reject expired and replayed inbound deliveries.
* Replace insecure crypto fallbacks with authenticated encryption that fails closed.
* Encrypt outbound retry payloads and avoid storing inbound payloads in plaintext.
* Require public HTTPS outbound endpoints and use the safe WordPress HTTP API.
* Add security-focused PHPUnit tests and multi-version CI.

= 1.0.0 =
* Initial public reference implementation.
