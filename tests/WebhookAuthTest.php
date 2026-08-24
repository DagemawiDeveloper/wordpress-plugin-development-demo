<?php

use PHPUnit\Framework\TestCase;

final class WebhookAuthTest extends TestCase {
    private $auth;

    protected function setUp(): void {
        $this->auth = new WPITK_Webhook_Auth();
    }

    public function test_valid_signature_is_accepted(): void {
        $body      = '{"customer_id":42}';
        $timestamp = '1770000000';
        $delivery  = 'delivery-12345678';
        $event     = 'customer.updated';
        $secret    = str_repeat( 's', 32 );
        $signature = $this->auth->sign( $body, $timestamp, $delivery, $event, $secret );

        self::assertTrue(
            $this->auth->verify( $body, $timestamp, $delivery, $event, $signature, $secret, 1770000000 )
        );
    }

    public function test_tampered_body_is_rejected(): void {
        $timestamp = '1770000000';
        $delivery  = 'delivery-12345678';
        $event     = 'customer.updated';
        $secret    = str_repeat( 's', 32 );
        $signature = $this->auth->sign( '{"customer_id":42}', $timestamp, $delivery, $event, $secret );

        self::assertFalse(
            $this->auth->verify( '{"customer_id":43}', $timestamp, $delivery, $event, $signature, $secret, 1770000000 )
        );
    }

    public function test_changed_event_or_delivery_is_rejected(): void {
        $body      = '{"customer_id":42}';
        $timestamp = '1770000000';
        $delivery  = 'delivery-12345678';
        $event     = 'customer.updated';
        $secret    = str_repeat( 's', 32 );
        $signature = $this->auth->sign( $body, $timestamp, $delivery, $event, $secret );

        self::assertFalse(
            $this->auth->verify( $body, $timestamp, $delivery, 'customer.deleted', $signature, $secret, 1770000000 )
        );
        self::assertFalse(
            $this->auth->verify( $body, $timestamp, 'delivery-87654321', $event, $signature, $secret, 1770000000 )
        );
    }

    public function test_expired_timestamp_is_rejected(): void {
        $body      = '{}';
        $timestamp = '1770000000';
        $delivery  = 'delivery-12345678';
        $event     = 'integration.test';
        $secret    = str_repeat( 's', 32 );
        $signature = $this->auth->sign( $body, $timestamp, $delivery, $event, $secret );

        self::assertFalse(
            $this->auth->verify( $body, $timestamp, $delivery, $event, $signature, $secret, 1770000301, 300 )
        );
    }

    public function test_malformed_metadata_is_rejected(): void {
        $secret = str_repeat( 's', 32 );

        self::assertFalse( $this->auth->verify( '{}', 'not-a-time', 'short', 'Invalid Event', str_repeat( 'a', 64 ), $secret, 1770000000 ) );
        self::assertFalse( $this->auth->verify( '{}', '1770000000', 'delivery-12345678', 'valid.event', 'bad-signature', $secret, 1770000000 ) );
    }
}
