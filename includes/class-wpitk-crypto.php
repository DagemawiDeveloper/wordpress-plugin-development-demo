<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Crypto {
    const PREFIX = 'wpitk:v1:';

    public function encrypt( $value ) {
        if ( '' === (string) $value ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( (string) $value );
        }

        $key = $this->key();
        $iv  = random_bytes( 16 );
        $ciphertext = openssl_encrypt( (string) $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $ciphertext ) {
            return '';
        }

        $mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
        return self::PREFIX . base64_encode( $iv . $mac . $ciphertext );
    }

    public function decrypt( $value ) {
        $value = (string) $value;
        if ( '' === $value ) {
            return '';
        }

        if ( 0 !== strpos( $value, self::PREFIX ) ) {
            $decoded = base64_decode( $value, true );
            return false === $decoded ? $value : $decoded;
        }

        $encoded = substr( $value, strlen( self::PREFIX ) );
        $payload = base64_decode( $encoded, true );
        if ( false === $payload || strlen( $payload ) < 49 || ! function_exists( 'openssl_decrypt' ) ) {
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

        $plaintext = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return false === $plaintext ? '' : $plaintext;
    }

    private function key() {
        $material = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpitk-fallback-auth-key';
        $material .= defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'wpitk-fallback-salt';
        return hash( 'sha256', $material, true );
    }
}
