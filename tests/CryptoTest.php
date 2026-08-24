<?php

use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase {
    public function test_secret_round_trip_uses_versioned_authenticated_encryption(): void {
        $crypto    = new WPITK_Crypto();
        $encrypted = $crypto->encrypt( 'correct horse battery staple' );

        self::assertStringStartsWith( WPITK_Crypto::PREFIX_V2, $encrypted );
        self::assertSame( 'correct horse battery staple', $crypto->decrypt( $encrypted ) );
    }

    public function test_tampered_ciphertext_is_rejected(): void {
        $crypto    = new WPITK_Crypto();
        $encrypted = $crypto->encrypt( 'top secret' );
        $last      = substr( $encrypted, -1 );
        $tampered  = substr( $encrypted, 0, -1 ) . ( 'A' === $last ? 'B' : 'A' );

        self::assertSame( '', $crypto->decrypt( $tampered ) );
    }

    public function test_plaintext_and_base64_values_are_not_accepted_as_secrets(): void {
        $crypto = new WPITK_Crypto();

        self::assertSame( '', $crypto->decrypt( 'plaintext-secret' ) );
        self::assertSame( '', $crypto->decrypt( base64_encode( 'plaintext-secret' ) ) );
    }

    public function test_empty_secret_is_stable(): void {
        $crypto = new WPITK_Crypto();

        self::assertSame( '', $crypto->encrypt( '' ) );
        self::assertSame( '', $crypto->decrypt( '' ) );
    }
}
