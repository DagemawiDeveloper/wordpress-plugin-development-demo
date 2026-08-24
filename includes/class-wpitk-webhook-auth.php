<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates and verifies the canonical signatures used by WP Integration Toolkit.
 *
 * The signature authenticates the request timestamp, stable delivery ID, event
 * name and exact raw body. Including request metadata prevents attackers from
 * reusing a valid body under a different event or delivery identifier.
 */
class WPITK_Webhook_Auth {
    const DEFAULT_TOLERANCE = 300;

    public function sign( $raw_body, $timestamp, $delivery_id, $event, $secret ) {
        return hash_hmac(
            'sha256',
            $this->canonical_payload( $raw_body, $timestamp, $delivery_id, $event ),
            (string) $secret
        );
    }

    public function verify( $raw_body, $timestamp, $delivery_id, $event, $signature, $secret, $now = null, $tolerance = self::DEFAULT_TOLERANCE ) {
        $timestamp   = (string) $timestamp;
        $delivery_id = (string) $delivery_id;
        $event       = (string) $event;
        $signature   = strtolower( trim( (string) $signature ) );
        $secret      = (string) $secret;
        $tolerance   = max( 1, (int) $tolerance );
        $now         = null === $now ? time() : (int) $now;

        if (
            '' === $secret
            || ! ctype_digit( $timestamp )
            || ! preg_match( '/^[a-zA-Z0-9._:-]{8,128}$/', $delivery_id )
            || ! preg_match( '/^[a-z0-9._:-]{1,190}$/', $event )
            || ! preg_match( '/^[a-f0-9]{64}$/', $signature )
        ) {
            return false;
        }

        if ( abs( $now - (int) $timestamp ) > $tolerance ) {
            return false;
        }

        $expected = $this->sign( $raw_body, $timestamp, $delivery_id, $event, $secret );

        return hash_equals( $expected, $signature );
    }

    private function canonical_payload( $raw_body, $timestamp, $delivery_id, $event ) {
        return implode(
            "\n",
            array(
                (string) $timestamp,
                strtolower( trim( (string) $delivery_id ) ),
                strtolower( trim( (string) $event ) ),
                (string) $raw_body,
            )
        );
    }
}
