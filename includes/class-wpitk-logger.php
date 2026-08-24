<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Logger {
    public function create( array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wpitk_webhook_logs';
        $now   = current_time( 'mysql' );

        $row = wp_parse_args(
            $data,
            array(
                'delivery_id'   => null,
                'direction'     => 'outbound',
                'event_name'    => 'unknown',
                'endpoint'      => '',
                'request_body'  => '',
                'response_code' => null,
                'response_body' => '',
                'status'        => 'pending',
                'attempts'      => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            )
        );

        if ( empty( $row['delivery_id'] ) ) {
            $row['delivery_id'] = null;
        } else {
            $row['delivery_id'] = substr( sanitize_text_field( (string) $row['delivery_id'] ), 0, 128 );
        }

        $inserted = $wpdb->insert( $table, $row );

        return false === $inserted ? 0 : (int) $wpdb->insert_id;
    }

    public function update( $id, array $data ) {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        return $wpdb->update( $wpdb->prefix . 'wpitk_webhook_logs', $data, array( 'id' => absint( $id ) ) );
    }

    public function get( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpitk_webhook_logs';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
    }

    public function recent( $limit = 25 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpitk_webhook_logs';
        $limit = max( 1, min( 100, absint( $limit ) ) );
        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
    }
}
