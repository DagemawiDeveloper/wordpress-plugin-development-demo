<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Webhook_Service {
    private $logger;
    private $crypto;
    private $auth;

    public function __construct( WPITK_Logger $logger, WPITK_Crypto $crypto, WPITK_Webhook_Auth $auth ) {
        $this->logger = $logger;
        $this->crypto = $crypto;
        $this->auth   = $auth;
    }

    public function send( $event_name, array $payload, $existing_log_id = 0, $existing_delivery_id = '', $existing_body = '' ) {
        $settings = get_option( 'wpitk_settings', array() );
        $endpoint = isset( $settings['outbound_url'] ) ? esc_url_raw( $settings['outbound_url'] ) : '';
        $secret   = isset( $settings['webhook_secret'] ) ? $this->crypto->decrypt( $settings['webhook_secret'] ) : '';
        $timeout  = isset( $settings['request_timeout'] ) ? max( 1, min( 30, absint( $settings['request_timeout'] ) ) ) : 10;
        $event    = sanitize_key( $event_name );

        if ( '' === $endpoint ) {
            return new WP_Error( 'wpitk_missing_endpoint', __( 'Outbound webhook URL is not configured.', 'wp-integration-toolkit' ) );
        }

        if ( ! $this->is_allowed_endpoint( $endpoint ) ) {
            return new WP_Error( 'wpitk_insecure_endpoint', __( 'Outbound webhook URLs must be valid public HTTPS URLs.', 'wp-integration-toolkit' ) );
        }

        if ( '' === $secret ) {
            return new WP_Error( 'wpitk_missing_secret', __( 'A secure webhook signing secret is required before delivery.', 'wp-integration-toolkit' ) );
        }

        $delivery_id = '' !== (string) $existing_delivery_id ? (string) $existing_delivery_id : wp_generate_uuid4();
        $body        = (string) $existing_body;

        if ( '' === $body ) {
            $body = wp_json_encode(
                array(
                    'delivery_id' => $delivery_id,
                    'event'       => $event,
                    'site'        => home_url( '/' ),
                    'data'        => $payload,
                )
            );
        }

        if ( false === $body || '' === $body ) {
            return new WP_Error( 'wpitk_json_failure', __( 'The webhook payload could not be encoded.', 'wp-integration-toolkit' ) );
        }

        $log_id = absint( $existing_log_id );

        if ( ! $log_id ) {
            try {
                $encrypted_body = $this->crypto->encrypt( $body );
            } catch ( RuntimeException $exception ) {
                return new WP_Error( 'wpitk_crypto_unavailable', $exception->getMessage() );
            }

            $log_id = $this->logger->create(
                array(
                    'delivery_id'  => $delivery_id,
                    'direction'    => 'outbound',
                    'event_name'   => $event,
                    'endpoint'     => $endpoint,
                    'request_body' => $encrypted_body,
                    'status'       => 'sending',
                    'attempts'     => 1,
                )
            );

            if ( ! $log_id ) {
                return new WP_Error( 'wpitk_log_failure', __( 'The webhook delivery could not be recorded.', 'wp-integration-toolkit' ) );
            }
        }

        $timestamp = (string) time();
        $signature = $this->auth->sign( $body, $timestamp, $delivery_id, $event, $secret );

        $response = wp_safe_remote_post(
            $endpoint,
            array(
                'timeout' => $timeout,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'User-Agent'        => 'WP-Integration-Toolkit/' . WPITK_VERSION,
                    'X-WPITK-Event'     => $event,
                    'X-WPITK-Delivery'  => $delivery_id,
                    'X-WPITK-Timestamp' => $timestamp,
                    'X-WPITK-Signature' => $signature,
                ),
                'body'        => $body,
                'data_format' => 'body',
                'redirection' => 0,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->update(
                $log_id,
                array(
                    'status'        => 'failed',
                    'response_code' => 0,
                    'response_body' => wp_json_encode(
                        array(
                            'error_code' => $response->get_error_code(),
                            'message'    => substr( sanitize_text_field( $response->get_error_message() ), 0, 500 ),
                        )
                    ),
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
                'response_body' => $this->response_metadata( $response_body ),
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
            'delivery_id'   => $delivery_id,
            'response_code' => $code,
        );
    }

    public function retry( $log_id ) {
        $log = $this->logger->get( $log_id );

        if ( ! $log || 'outbound' !== $log->direction ) {
            return new WP_Error( 'wpitk_log_not_found', __( 'Outbound webhook log not found.', 'wp-integration-toolkit' ) );
        }

        $body = $this->crypto->decrypt( $log->request_body );

        if ( '' === $body ) {
            return new WP_Error( 'wpitk_retry_payload_unavailable', __( 'The encrypted retry payload could not be recovered.', 'wp-integration-toolkit' ) );
        }

        $decoded = json_decode( $body, true );

        if ( ! is_array( $decoded ) || ! array_key_exists( 'data', $decoded ) || ! is_array( $decoded['data'] ) ) {
            return new WP_Error( 'wpitk_retry_payload_invalid', __( 'The stored retry payload is invalid.', 'wp-integration-toolkit' ) );
        }

        $this->logger->update(
            $log_id,
            array(
                'status'   => 'retrying',
                'attempts' => (int) $log->attempts + 1,
            )
        );

        return $this->send(
            $log->event_name,
            $decoded['data'],
            $log_id,
            (string) $log->delivery_id,
            $body
        );
    }

    public function validate_inbound( $raw_body, $timestamp, $delivery_id, $event, $signature ) {
        $settings  = get_option( 'wpitk_settings', array() );
        $secret    = isset( $settings['webhook_secret'] ) ? $this->crypto->decrypt( $settings['webhook_secret'] ) : '';
        $tolerance = isset( $settings['signature_tolerance'] )
            ? max( 60, min( 900, absint( $settings['signature_tolerance'] ) ) )
            : WPITK_Webhook_Auth::DEFAULT_TOLERANCE;

        if ( '' === $secret ) {
            return new WP_Error(
                'wpitk_secret_unavailable',
                __( 'Webhook authentication is not configured securely.', 'wp-integration-toolkit' ),
                array( 'status' => 503 )
            );
        }

        if ( ! $this->auth->verify( $raw_body, $timestamp, $delivery_id, $event, $signature, $secret, time(), $tolerance ) ) {
            return new WP_Error(
                'wpitk_invalid_signature',
                __( 'Invalid or expired webhook signature.', 'wp-integration-toolkit' ),
                array( 'status' => 401 )
            );
        }

        return true;
    }

    private function is_allowed_endpoint( $endpoint ) {
        $scheme = strtolower( (string) wp_parse_url( $endpoint, PHP_URL_SCHEME ) );

        return 'https' === $scheme && false !== wp_http_validate_url( $endpoint );
    }

    private function attempt_count( $log_id ) {
        $log = $this->logger->get( $log_id );
        return $log ? max( 1, (int) $log->attempts ) : 1;
    }

    private function response_metadata( $body ) {
        $body = (string) $body;

        return wp_json_encode(
            array(
                'bytes'  => strlen( $body ),
                'sha256' => hash( 'sha256', $body ),
            )
        );
    }
}
