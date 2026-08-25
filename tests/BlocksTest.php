<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if ( ! defined( 'WPITK_VERSION' ) ) {
    define( 'WPITK_VERSION', '1.2.0' );
}

if ( ! defined( 'WPITK_PATH' ) ) {
    define( 'WPITK_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WPITK_URL' ) ) {
    define( 'WPITK_URL', 'https://example.test/wp-content/plugins/wp-integration-toolkit/' );
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        return esc_html( $text );
    }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) {
        return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return trim( strip_tags( (string) $text ) );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return array_key_exists( $name, $GLOBALS['wpitk_test_options'] )
            ? $GLOBALS['wpitk_test_options'][ $name ]
            : $default;
    }
}

if ( ! function_exists( 'get_block_wrapper_attributes' ) ) {
    function get_block_wrapper_attributes( $attributes = array() ) {
        $pairs = array();

        foreach ( $attributes as $name => $value ) {
            $pairs[] = sprintf( '%s="%s"', $name, esc_attr( $value ) );
        }

        return implode( ' ', $pairs );
    }
}

if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps = array(), $version = false, $in_footer = false ) {
        $GLOBALS['wpitk_registered_scripts'][ $handle ] = array(
            'src'       => $src,
            'deps'      => $deps,
            'version'   => $version,
            'in_footer' => $in_footer,
        );

        return true;
    }
}

if ( ! function_exists( 'wp_register_style' ) ) {
    function wp_register_style( $handle, $src, $deps = array(), $version = false ) {
        $GLOBALS['wpitk_registered_styles'][ $handle ] = array(
            'src'     => $src,
            'deps'    => $deps,
            'version' => $version,
        );

        return true;
    }
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
    function wp_set_script_translations( $handle, $domain ) {
        $GLOBALS['wpitk_script_translations'][ $handle ] = $domain;

        return true;
    }
}

if ( ! function_exists( 'register_block_type' ) ) {
    function register_block_type( $path, $args = array() ) {
        $GLOBALS['wpitk_registered_block'] = array(
            'path' => $path,
            'args' => $args,
        );

        return (object) array( 'name' => WPITK_Blocks::BLOCK_NAME );
    }
}

final class BlocksTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['wpitk_test_options']        = array();
        $GLOBALS['wpitk_registered_scripts']  = array();
        $GLOBALS['wpitk_registered_styles']   = array();
        $GLOBALS['wpitk_script_translations'] = array();
        $GLOBALS['wpitk_registered_block']    = null;
    }

    public function test_registers_react_editor_assets_and_dynamic_block(): void {
        $blocks = new WPITK_Blocks();
        $blocks->register();

        self::assertArrayHasKey( 'wpitk-integration-status-editor', $GLOBALS['wpitk_registered_scripts'] );
        self::assertContains( 'wp-element', $GLOBALS['wpitk_registered_scripts']['wpitk-integration-status-editor']['deps'] );
        self::assertContains( 'wp-block-editor', $GLOBALS['wpitk_registered_scripts']['wpitk-integration-status-editor']['deps'] );
        self::assertContains( 'wp-server-side-render', $GLOBALS['wpitk_registered_scripts']['wpitk-integration-status-editor']['deps'] );
        self::assertArrayHasKey( 'wpitk-integration-status', $GLOBALS['wpitk_registered_styles'] );
        self::assertSame( WPITK_PATH . 'blocks/integration-status', $GLOBALS['wpitk_registered_block']['path'] );
        self::assertIsCallable( $GLOBALS['wpitk_registered_block']['args']['render_callback'] );
    }

    public function test_rendered_ready_status_does_not_expose_endpoint_or_secret(): void {
        $GLOBALS['wpitk_test_options']['wpitk_settings'] = array(
            'outbound_url'  => 'https://partner.example.test/webhook',
            'webhook_secret' => 'super-secret-value',
        );

        $blocks = new WPITK_Blocks();
        $html   = $blocks->render_integration_status(
            array(
                'heading'           => 'Partner API',
                'configuredLabel'   => 'Ready for signed events',
                'showDescription'   => false,
            )
        );

        self::assertStringContainsString( 'data-ready="1"', $html );
        self::assertStringContainsString( 'Partner API', $html );
        self::assertStringContainsString( 'Ready for signed events', $html );
        self::assertStringNotContainsString( 'partner.example.test', $html );
        self::assertStringNotContainsString( 'super-secret-value', $html );
        self::assertStringNotContainsString( 'Signed outbound webhook delivery is configured and ready.', $html );
    }

    public function test_unconfigured_status_escapes_custom_editor_content(): void {
        $blocks = new WPITK_Blocks();
        $html   = $blocks->render_integration_status(
            array(
                'heading'            => '<strong>Status</strong>',
                'notConfiguredLabel' => '<script>alert(1)</script>Needs setup',
            )
        );

        self::assertStringContainsString( 'data-ready="0"', $html );
        self::assertStringContainsString( '>Status<', $html );
        self::assertStringContainsString( 'Needs setup', $html );
        self::assertStringNotContainsString( '<strong>', $html );
        self::assertStringNotContainsString( '<script>', $html );
    }

    public function test_configuration_requires_both_endpoint_and_secret(): void {
        $GLOBALS['wpitk_test_options']['wpitk_settings'] = array(
            'outbound_url' => 'https://partner.example.test/webhook',
        );
        self::assertFalse( WPITK_Blocks::is_configured() );

        $GLOBALS['wpitk_test_options']['wpitk_settings']['webhook_secret'] = 'secret';
        self::assertTrue( WPITK_Blocks::is_configured() );
    }
}
