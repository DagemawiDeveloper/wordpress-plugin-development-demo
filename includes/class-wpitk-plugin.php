<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Plugin {
    public function run() {
        $crypto   = new WPITK_Crypto();
        $auth     = new WPITK_Webhook_Auth();
        $logger   = new WPITK_Logger();
        $webhooks = new WPITK_Webhook_Service( $logger, $crypto, $auth );
        $rest     = new WPITK_REST_Controller( $logger, $webhooks );
        $admin    = new WPITK_Admin( $logger, $webhooks, $crypto );
        $blocks   = new WPITK_Blocks();

        add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
        add_action( 'init', array( $blocks, 'register' ) );
        $admin->register();

        add_shortcode( 'wpitk_integration_status', array( $this, 'status_shortcode' ) );

        add_action(
            'wpitk_send_event',
            function ( $event_name, $payload = array() ) use ( $webhooks ) {
                $webhooks->send( sanitize_key( $event_name ), is_array( $payload ) ? $payload : array() );
            },
            10,
            2
        );
    }

    public function status_shortcode() {
        $ready = WPITK_Blocks::is_configured();

        return sprintf(
            '<span class="wpitk-public-status" data-ready="%1$s">%2$s</span>',
            esc_attr( $ready ? '1' : '0' ),
            esc_html( $ready ? __( 'Integration configured', 'wp-integration-toolkit' ) : __( 'Integration not configured', 'wp-integration-toolkit' ) )
        );
    }
}
