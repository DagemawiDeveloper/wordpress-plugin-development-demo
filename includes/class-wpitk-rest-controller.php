<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_REST_Controller {
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
        $raw_body  = $request->get_body();
        $signature = $request->get_header( 'x-wpitk-signature' );
        $event     = sanitize_key( $request->get_header( 'x-wpitk-event' ) ?: 'inbound' );

        if ( ! $this->webhooks->verify_signature( $raw_body, $signature ) ) {
            $this->logger->create(
                array(
                    'direction'    => 'inbound',
                    'event_name'   => $event,
                    'endpoint'     => rest_url( 'wp-integration-toolkit/v1/webhooks/inbound' ),
                    'request_body' => wp_html_excerpt( $raw_body, 5000, '…' ),
                    'status'       => 'rejected',
                    'response_code'=> 401,
                )
            );

            return new WP_Error(
                'wpitk_invalid_signature',
                __( 'Invalid webhook signature.', 'wp-integration-toolkit' ),
                array( 'status' => 401 )
            );
        }

        $payload = json_decode( $raw_body, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'wpitk_invalid_json',
                __( 'Request body must be valid JSON.', 'wp-integration-toolkit' ),
                array( 'status' => 400 )
            );
        }

        $log_id = $this->logger->create(
            array(
                'direction'     => 'inbound',
                'event_name'    => $event,
                'endpoint'      => rest_url( 'wp-integration-toolkit/v1/webhooks/inbound' ),
                'request_body'  => wp_json_encode( $payload ),
                'response_code' => 202,
                'response_body' => wp_json_encode( array( 'accepted' => true ) ),
                'status'        => 'accepted',
            )
        );

        do_action( 'wpitk_inbound_webhook_received', $payload, $event, $log_id );

        return new WP_REST_Response(
            array(
                'accepted' => true,
                'log_id'   => $log_id,
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
}
