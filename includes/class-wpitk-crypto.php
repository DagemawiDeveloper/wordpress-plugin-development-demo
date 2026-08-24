<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Encrypts plugin secrets with key material derived from WordPress salts.
 *
 * New values use AES-256-GCM. Version-1 AES-CBC values remain readable so an
 * existing installation can upgrade without exposing or resetting its secret.
 * Unknown/plaintext values are never treated as valid encrypted secrets.
 */
class WPITK_Crypto {
    const PREFIX_V1 = 'wpitk:v1:';
    const PREFIX_V2 = 'wpitk:v2:';

    public function is_available() {
        return function_exists( 'openssl_encrypt' )
            && function_exists( 'openssl_decrypt' )
            && function_exists( 'openssl_cipher_iv_length' )
            && defined( 'AUTH_KEY' )
            && defined( 'SECURE_AUTH_SALT' )
            && strlen( (string) AUTH_KEY ) >= 32
            && strlen( (string) SECURE_AUTH_SALT ) >= 32;
    }

    public function encrypt( $value ) {
        $value = (string) $value;

        if ( '' === $value ) {
            return '';
        }

        if ( ! $this->is_available() ) {
            throw new RuntimeException( 'Secure secret storage is unavailable. OpenSSL and WordPress authentication salts are required.' );
        }

        $cipher = 'aes-256-gcm';
        $iv_len = openssl_cipher_iv_length( $cipher );
        $iv     = random_bytes( $iv_len );
        $tag    = '';

        $ciphertext = openssl_encrypt(
            $value,
            $cipher,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
            throw new RuntimeException( 'The webhook secret could not be encrypted.' );
        }

        return self::PREFIX_V2 . base64_encode( $iv . $tag . $ciphertext );
    }

    public function decrypt( $value ) {
        $value = (string) $value;

        if ( '' === $value || ! $this->is_available() ) {
            return '';
        }

        if ( 0 === strpos( $value, self::PREFIX_V2 ) ) {
            return $this->decrypt_v2( substr( $value, strlen( self::PREFIX_V2 ) ) );
        }

        if ( 0 === strpos( $value, self::PREFIX_V1 ) ) {
            return $this->decrypt_v1( substr( $value, strlen( self::PREFIX_V1 ) ) );
        }

        // Do not silently accept legacy plaintext/Base64 values as secrets.
        return '';
    }

    private function decrypt_v2( $encoded ) {
        $payload = base64_decode( (string) $encoded, true );
        $cipher  = 'aes-256-gcm';
        $iv_len  = openssl_cipher_iv_length( $cipher );

        if ( false === $payload || strlen( $payload ) <= $iv_len + 16 ) {
            return '';
        }

        $iv         = substr( $payload, 0, $iv_len );
        $tag        = substr( $payload, $iv_len, 16 );
        $ciphertext = substr( $payload, $iv_len + 16 );

        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $this->key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return false === $plaintext ? '' : $plaintext;
    }

    private function decrypt_v1( $encoded ) {
        $payload = base64_decode( (string) $encoded, true );

        if ( false === $payload || strlen( $payload ) < 49 ) {
            return '';
        }

        $iv         = substr( $payload, 0, 16 );
        $mac        = substr( $payload, 16, 32 );
        $ciphertext = substr( $payload, 48 );
        $key        = $this->key();
        $expected   = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

        if ( ! hash_equals( $expected, $mac ) ) {
            return '';
        }

        $plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

        return false === $plaintext ? '' : $plaintext;
    }

    private function key() {
        return hash( 'sha256', (string) AUTH_KEY . (string) SECURE_AUTH_SALT, true );
    }
}
