# Gutenberg Integration Status Block

Version 1.2.0 adds a custom dynamic Gutenberg block named `wp-integration-toolkit/integration-status`.

The block exists for a real plugin use case: editors can place a public integration-status component in a page without exposing the configured outbound URL, signing secret, or webhook payload data.

## Architecture

```text
block.json
   |
   +--> React-based editor UI (index.js)
   |       - InspectorControls
   |       - TextControl / ToggleControl
   |       - ServerSideRender preview
   |
   +--> PHP registration (WPITK_Blocks)
           - registers editor script/style
           - registers block metadata
           - supplies render_callback
           - reads current plugin configuration
           - escapes public output
```

## Editor behavior

The editor code uses WordPress's React abstraction through `wp.element` together with the Block Editor component APIs. Editors can change:

- the block heading;
- the label shown when the integration is configured;
- the label shown when configuration is incomplete;
- whether the explanatory status text is displayed.

The editor preview uses `wp.serverSideRender`, so the preview comes from the same PHP render callback used on the frontend. This avoids maintaining two separate implementations of the configuration-state logic.

## Dynamic rendering

The saved block contains attributes rather than trusted frontend HTML. PHP evaluates the current `wpitk_settings` option on each render and considers the integration ready only when both the outbound endpoint and encrypted webhook secret are present.

The rendered output intentionally exposes only a boolean ready/not-ready state. It never renders:

- the outbound endpoint;
- the signing secret;
- encrypted secret material;
- retry payloads;
- inbound webhook data.

Custom editor labels are sanitized and escaped before output.

## Why no Node build step here?

This repository is an installable reference plugin with a small editor surface. The block uses WordPress-provided `wp.*` packages directly and `wp.element.createElement()` rather than adding a generated JavaScript bundle and a large Node dependency tree for one component.

That is a deliberate scope trade-off, not a limitation of the architecture. On a larger block suite I would normally introduce `@wordpress/scripts`, JSX/TypeScript, package locking, JavaScript unit tests, and an end-to-end layer such as Playwright. For this plugin, keeping the shipped source directly inspectable and avoiding a build dependency makes the example easier to install and audit.

## Automated verification

The GitHub Actions matrix runs on PHP 7.4, 8.2, and 8.3 and now verifies:

- Composer metadata;
- PHP syntax;
- `block.json` identity and API version;
- Gutenberg editor JavaScript syntax with `node --check`;
- block registration wiring;
- required React/Block Editor dependencies;
- configured and unconfigured frontend rendering;
- output escaping;
- non-disclosure of endpoint and secret values;
- the existing webhook-authentication and cryptography regression suite.

## Files

```text
blocks/integration-status/
├── block.json
├── index.js
└── style.css

includes/class-wpitk-blocks.php
tests/BlocksTest.php
```
