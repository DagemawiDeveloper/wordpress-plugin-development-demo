<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Blocks {
    const BLOCK_NAME = 'wp-integration-toolkit/integration-status';

    public function register() {
        wp_register_script(
            'wpitk-integration-status-editor',
            WPITK_URL . 'blocks/integration-status/index.js',
            array(
                'wp-blocks',
                'wp-block-editor',
                'wp-components',
                'wp-element',
                'wp-i18n',
                'wp-server-side-render',
            ),
            WPITK_VERSION,
            true
        );

        wp_register_style(
            'wpitk-integration-status',
            WPITK_URL . 'blocks/integration-status/style.css',
            array(),
            WPITK_VERSION
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations(
                'wpitk-integration-status-editor',
                'wp-integration-toolkit'
            );
        }

        register_block_type(
            WPITK_PATH . 'blocks/integration-status',
            array(
                'editor_script'   => 'wpitk-integration-status-editor',
                'style'           => 'wpitk-integration-status',
                'editor_style'    => 'wpitk-integration-status',
                'render_callback' => array( $this, 'render_integration_status' ),
            )
        );
    }

    public static function is_configured() {
        $settings = get_option( 'wpitk_settings', array() );

        return ! empty( $settings['outbound_url'] ) && ! empty( $settings['webhook_secret'] );
    }

    public function render_integration_status( $attributes = array() ) {
        $ready = self::is_configured();

        $heading = $this->text_attribute(
            $attributes,
            'heading',
            __( 'Integration status', 'wp-integration-toolkit' )
        );

        $configured_label = $this->text_attribute(
            $attributes,
            'configuredLabel',
            __( 'Integration configured', 'wp-integration-toolkit' )
        );

        $not_configured_label = $this->text_attribute(
            $attributes,
            'notConfiguredLabel',
            __( 'Integration not configured', 'wp-integration-toolkit' )
        );

        $show_description = ! isset( $attributes['showDescription'] ) || (bool) $attributes['showDescription'];
        $status_label     = $ready ? $configured_label : $not_configured_label;
        $state_class      = $ready ? 'is-ready' : 'needs-setup';

        $wrapper_attributes = get_block_wrapper_attributes(
            array(
                'class'       => 'wpitk-integration-status ' . $state_class,
                'data-ready'  => $ready ? '1' : '0',
                'role'        => 'status',
                'aria-label'  => $status_label,
                'aria-live'   => 'polite',
            )
        );

        $description = '';

        if ( $show_description ) {
            $description_text = $ready
                ? __( 'Signed outbound webhook delivery is configured and ready.', 'wp-integration-toolkit' )
                : __( 'Complete the Integration Toolkit settings before signed outbound events can be sent.', 'wp-integration-toolkit' );

            $description = sprintf(
                '<p class="wpitk-integration-status__description">%s</p>',
                esc_html( $description_text )
            );
        }

        return sprintf(
            '<section %1$s><div class="wpitk-integration-status__indicator" aria-hidden="true"></div><div class="wpitk-integration-status__content"><p class="wpitk-integration-status__eyebrow">%2$s</p><h3 class="wpitk-integration-status__heading">%3$s</h3><p class="wpitk-integration-status__label">%4$s</p>%5$s</div></section>',
            $wrapper_attributes,
            esc_html__( 'WP Integration Toolkit', 'wp-integration-toolkit' ),
            esc_html( $heading ),
            esc_html( $status_label ),
            $description
        );
    }

    private function text_attribute( $attributes, $key, $fallback ) {
        if ( ! isset( $attributes[ $key ] ) || ! is_string( $attributes[ $key ] ) ) {
            return $fallback;
        }

        $value = sanitize_text_field( $attributes[ $key ] );

        return '' === $value ? $fallback : $value;
    }
}
