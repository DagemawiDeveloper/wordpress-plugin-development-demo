<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Webhook_Service {
    private $logger;
    private $crypto;

    public function __construct( WPITK_Logger $logger, WPITK_Crypto $crypto ) {
        $this->logger = $logger;
        $this->crypto = $crypto;
    }

    public function send( $event_name, array $payload, $existing_log_id = 0 ) {
        $settings = get_option( 'wpitk_settings', array() );
        $endpoint = isset( $settings['outbound_url'] ) ? esc_url_raw( $settings['outbound_url'] ) : '';
        $secret   = isset( $settings['webhook_secret'] ) ? $this->crypto->decrypt( $settings['webhook_secret'] ) : '';
        $timeout  = isset( $settings['request_timeout'] ) ? max( 1, min( 30, absint( $settings['request_timeout'] ) ) ) : 10;

        if ( '' === $endpoint ) {
            return new WP_Error( 'wpitk_missing_endpoint', __( 'Outbound webhook URL is not configured.', 'wp-integration-toolkit' ) );
        }

        $body = wp_json_encode(
            array(
                'event'     => sanitize_key( $event_name ),
                'timestamp' => gmdate( 'c' ),
                'site'      => home_url( '/' ),
                'data'      => $payload,
            )
        );

        $signature = '' !== $secret ? hash_hmac( 'sha256', $body, $secret ) : '';

        $log_id = absint( $existing_log_id );
        if ( ! $log_id ) {
            $log_id = $this->logger->create(
                array(
                    'direction'    => 'outbound',
                    'event_name'   => sanitize_key( $event_name ),
                    'endpoint'     => $endpoint,
                    'request_body' => $body,
                    'status'       => 'sending',
                )
            );
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => $timeout,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'User-Agent'        => 'WP-Integration-Toolkit/' . WPITK_VERSION,
                    'X-WPITK-Event'     => sanitize_key( $event_name ),
                    'X-WPITK-Signature' => $signature,
                ),
                'body'    => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->update(
                $log_id,
                array(
                    'status'        => 'failed',
                    'response_code' => 0,
                    'response_body' => $response->get_error_message(),
                    'attempts'      => $this->attempt_count( $log_id ),
                )
            );

            return $response;
        }

        $code          = (int) wp_remote_retrieve_response_code( $response );
        $response_body = (string) wp_remote_retrieve_body( $response );
        $status        = $code >= 200 && $code < 300 ? 'delivered' : 'failed';

        $this->logger->update(
            $log_id,
            array(
                'status'        => $status,
                'response_code' => $code,
                'response_body' => wp_html_excerpt( $response_body, 5000, '…' ),
                'attempts'      => $this->attempt_count( $log_id ),
            )
        );

        if ( 'failed' === $status ) {
            return new WP_Error(
                'wpitk_remote_failure',
                sprintf( __( 'Remote endpoint returned HTTP %d.', 'wp-integration-toolkit' ), $code ),
                array( 'status' => $code )
            );
        }

        return array(
            'log_id'        => $log_id,
            'response_code' => $code,
            'response_body' => $response_body,
        );
    }

    public function retry( $log_id ) {
        $log = $this->logger->get( $log_id );

        if ( ! $log || 'outbound' !== $log->direction ) {
            return new WP_Error( 'wpitk_log_not_found', __( 'Outbound webhook log not found.', 'wp-integration-toolkit' ) );
        }

        $decoded = json_decode( $log->request_body, true );
        $data    = is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : array();

        $this->logger->update(
            $log_id,
            array(
                'status'   => 'retrying',
                'attempts' => (int) $log->attempts + 1,
            )
        );

        return $this->send( $log->event_name, $data, $log_id );
    }

    public function verify_signature( $raw_body, $signature ) {
        $settings = get_option( 'wpitk_settings', array() );
        $secret   = isset( $settings['webhook_secret'] ) ? $this->crypto->decrypt( $settings['webhook_secret'] ) : '';

        if ( '' === $secret || '' === (string) $signature ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', (string) $raw_body, $secret );

        return hash_equals( $expected, (string) $signature );
    }

    private function attempt_count( $log_id ) {
        $log = $this->logger->get( $log_id );
        return $log ? max( 1, (int) $log->attempts ) : 1;
    }
}
