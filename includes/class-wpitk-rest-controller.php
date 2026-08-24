<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_REST_Controller {
    const MAX_BODY_BYTES = 1048576;

    private $logger;
    private $webhooks;

    public function __construct( WPITK_Logger $logger, WPITK_Webhook_Service $webhooks ) {
        $this->logger   = $logger;
        $this->webhooks = $webhooks;
    }

    public function register_routes() {
        register_rest_route(
            'wp-integration-toolkit/v1',
            '/webhooks/inbound',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'receive_webhook' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'wp-integration-toolkit/v1',
            '/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'health' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function receive_webhook( WP_REST_Request $request ) {
        $raw_body    = (string) $request->get_body();
        $signature   = (string) $request->get_header( 'x-wpitk-signature' );
        $timestamp   = (string) $request->get_header( 'x-wpitk-timestamp' );
        $delivery_id = (string) $request->get_header( 'x-wpitk-delivery' );
        $event       = sanitize_key( $request->get_header( 'x-wpitk-event' ) ?: 'inbound' );

        if ( strlen( $raw_body ) > self::MAX_BODY_BYTES ) {
            return new WP_Error(
                'wpitk_payload_too_large',
                __( 'Webhook payload exceeds the 1 MB limit.', 'wp-integration-toolkit' ),
                array( 'status' => 413 )
            );
        }

        $validation = $this->webhooks->validate_inbound(
            $raw_body,
            $timestamp,
            $delivery_id,
            $event,
            $signature
        );

        if ( is_wp_error( $validation ) ) {
            $error_data = $validation->get_error_data();
            $status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 401;

            $this->logger->create(
                array(
                    'direction'     => 'inbound',
                    'event_name'    => $event,
                    'endpoint'      => rest_url( 'wp-integration-toolkit/v1/webhooks/inbound' ),
                    'request_body'  => $this->request_metadata( $raw_body ),
                    'status'        => 'rejected',
                    'response_code' => $status,
                )
            );

            return $validation;
        }

        $payload = json_decode( $raw_body, true );

        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
            return new WP_Error(
                'wpitk_invalid_json',
                __( 'Request body must be a JSON object or array.', 'wp-integration-toolkit' ),
                array( 'status' => 400 )
            );
        }

        $log_id = $this->logger->create(
            array(
                'delivery_id'   => $delivery_id,
                'direction'     => 'inbound',
                'event_name'    => $event,
                'endpoint'      => rest_url( 'wp-integration-toolkit/v1/webhooks/inbound' ),
                'request_body'  => $this->request_metadata( $raw_body ),
                'response_code' => 202,
                'response_body' => wp_json_encode( array( 'accepted' => true ) ),
                'status'        => 'accepted',
            )
        );

        if ( ! $log_id ) {
            return new WP_Error(
                'wpitk_replayed_delivery',
                __( 'This webhook delivery has already been processed.', 'wp-integration-toolkit' ),
                array( 'status' => 409 )
            );
        }

        do_action( 'wpitk_inbound_webhook_received', $payload, $event, $log_id );

        return new WP_REST_Response(
            array(
                'accepted'    => true,
                'delivery_id' => $delivery_id,
                'log_id'      => $log_id,
            ),
            202
        );
    }

    public function health() {
        return rest_ensure_response(
            array(
                'status'  => 'ok',
                'version' => WPITK_VERSION,
                'time'    => gmdate( 'c' ),
            )
        );
    }

    private function request_metadata( $body ) {
        $body = (string) $body;

        return wp_json_encode(
            array(
                'bytes'  => strlen( $body ),
                'sha256' => hash( 'sha256', $body ),
            )
        );
    }
}
