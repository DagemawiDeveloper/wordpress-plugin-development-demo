<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Activator {
    public static function maybe_upgrade() {
        if ( WPITK_VERSION !== get_option( 'wpitk_version' ) ) {
            self::activate();
        }
    }

    public static function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'wpitk_webhook_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            delivery_id VARCHAR(128) NULL,
            direction VARCHAR(20) NOT NULL,
            event_name VARCHAR(190) NOT NULL,
            endpoint TEXT NULL,
            request_body LONGTEXT NULL,
            response_code INT NULL,
            response_body LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY delivery_id (delivery_id),
            KEY direction (direction),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        if ( false === get_option( 'wpitk_settings', false ) ) {
            add_option(
                'wpitk_settings',
                array(
                    'outbound_url'             => '',
                    'webhook_secret'           => '',
                    'request_timeout'          => 10,
                    'signature_tolerance'      => WPITK_Webhook_Auth::DEFAULT_TOLERANCE,
                    'remove_data_on_uninstall' => 0,
                )
            );
        }

        update_option( 'wpitk_version', WPITK_VERSION );
    }
}
