<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPITK_Admin {
    private $logger;
    private $webhooks;
    private $crypto;

    public function __construct( WPITK_Logger $logger, WPITK_Webhook_Service $webhooks, WPITK_Crypto $crypto ) {
        $this->logger   = $logger;
        $this->webhooks = $webhooks;
        $this->crypto   = $crypto;
    }

    public function register() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'wp_ajax_wpitk_send_test', array( $this, 'ajax_send_test' ) );
        add_action( 'wp_ajax_wpitk_retry', array( $this, 'ajax_retry' ) );
    }

    public function menu() {
        add_menu_page(
            __( 'Integration Toolkit', 'wp-integration-toolkit' ),
            __( 'Integration Toolkit', 'wp-integration-toolkit' ),
            'manage_options',
            'wpitk',
            array( $this, 'render' ),
            'dashicons-rest-api',
            81
        );
    }

    public function settings() {
        register_setting(
            'wpitk_settings_group',
            'wpitk_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    public function sanitize_settings( $input ) {
        $current = get_option( 'wpitk_settings', array() );
        $output  = array();

        $output['outbound_url'] = isset( $input['outbound_url'] ) ? esc_url_raw( $input['outbound_url'] ) : '';
        $output['request_timeout'] = isset( $input['request_timeout'] ) ? max( 1, min( 30, absint( $input['request_timeout'] ) ) ) : 10;
        $output['remove_data_on_uninstall'] = ! empty( $input['remove_data_on_uninstall'] ) ? 1 : 0;

        $submitted_secret = isset( $input['webhook_secret'] ) ? trim( (string) $input['webhook_secret'] ) : '';
        if ( '' !== $submitted_secret ) {
            $output['webhook_secret'] = $this->crypto->encrypt( sanitize_text_field( $submitted_secret ) );
        } else {
            $output['webhook_secret'] = isset( $current['webhook_secret'] ) ? $current['webhook_secret'] : '';
        }

        return $output;
    }

    public function assets( $hook ) {
        if ( 'toplevel_page_wpitk' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wpitk-admin', WPITK_URL . 'assets/css/admin.css', array(), WPITK_VERSION );
        wp_enqueue_script( 'wpitk-admin', WPITK_URL . 'assets/js/admin.js', array( 'jquery' ), WPITK_VERSION, true );
        wp_localize_script(
            'wpitk-admin',
            'wpitkAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wpitk_admin' ),
                'testing' => __( 'Sending…', 'wp-integration-toolkit' ),
                'retrying'=> __( 'Retrying…', 'wp-integration-toolkit' ),
            )
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = get_option( 'wpitk_settings', array() );
        $logs     = $this->logger->recent( 30 );
        ?>
        <div class="wrap wpitk-wrap">
            <h1><?php esc_html_e( 'WP Integration Toolkit', 'wp-integration-toolkit' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Configure signed webhooks, test integrations, and inspect delivery history from one place.', 'wp-integration-toolkit' ); ?></p>

            <div class="wpitk-grid">
                <section class="wpitk-card">
                    <h2><?php esc_html_e( 'Integration Settings', 'wp-integration-toolkit' ); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'wpitk_settings_group' ); ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="wpitk-outbound-url"><?php esc_html_e( 'Outbound webhook URL', 'wp-integration-toolkit' ); ?></label></th>
                                <td><input class="regular-text" type="url" id="wpitk-outbound-url" name="wpitk_settings[outbound_url]" value="<?php echo esc_attr( isset( $settings['outbound_url'] ) ? $settings['outbound_url'] : '' ); ?>" placeholder="https://example.com/webhooks/wordpress"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpitk-secret"><?php esc_html_e( 'Signing secret', 'wp-integration-toolkit' ); ?></label></th>
                                <td>
                                    <input class="regular-text" type="password" id="wpitk-secret" name="wpitk_settings[webhook_secret]" value="" autocomplete="new-password" placeholder="••••••••••••">
                                    <p class="description"><?php esc_html_e( 'Leave blank to keep the existing encrypted secret.', 'wp-integration-toolkit' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="wpitk-timeout"><?php esc_html_e( 'Request timeout', 'wp-integration-toolkit' ); ?></label></th>
                                <td><input type="number" min="1" max="30" id="wpitk-timeout" name="wpitk_settings[request_timeout]" value="<?php echo esc_attr( isset( $settings['request_timeout'] ) ? $settings['request_timeout'] : 10 ); ?>"> <?php esc_html_e( 'seconds', 'wp-integration-toolkit' ); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Uninstall cleanup', 'wp-integration-toolkit' ); ?></th>
                                <td><label><input type="checkbox" name="wpitk_settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $settings['remove_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete plugin settings and logs when uninstalled', 'wp-integration-toolkit' ); ?></label></td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                        <button type="button" class="button button-secondary" id="wpitk-send-test"><?php esc_html_e( 'Send Test Webhook', 'wp-integration-toolkit' ); ?></button>
                        <span id="wpitk-test-result" aria-live="polite"></span>
                    </form>
                </section>

                <section class="wpitk-card">
                    <h2><?php esc_html_e( 'Inbound Endpoint', 'wp-integration-toolkit' ); ?></h2>
                    <code><?php echo esc_html( rest_url( 'wp-integration-toolkit/v1/webhooks/inbound' ) ); ?></code>
                    <p><?php esc_html_e( 'POST JSON and include X-WPITK-Signature (HMAC-SHA256 of the raw request body) and optionally X-WPITK-Event.', 'wp-integration-toolkit' ); ?></p>
                    <h3><?php esc_html_e( 'Health Check', 'wp-integration-toolkit' ); ?></h3>
                    <code><?php echo esc_html( rest_url( 'wp-integration-toolkit/v1/health' ) ); ?></code>
                </section>
            </div>

            <section class="wpitk-card wpitk-logs-card">
                <h2><?php esc_html_e( 'Recent Webhook Activity', 'wp-integration-toolkit' ); ?></h2>
                <div class="wpitk-table-scroll">
                    <table class="widefat striped">
                        <thead><tr><th>ID</th><th><?php esc_html_e( 'Direction', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'Event', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'Status', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'HTTP', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'Attempts', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'Time', 'wp-integration-toolkit' ); ?></th><th><?php esc_html_e( 'Action', 'wp-integration-toolkit' ); ?></th></tr></thead>
                        <tbody>
                            <?php if ( empty( $logs ) ) : ?>
                                <tr><td colspan="8"><?php esc_html_e( 'No webhook activity yet.', 'wp-integration-toolkit' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $logs as $log ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $log->id ); ?></td>
                                        <td><?php echo esc_html( ucfirst( $log->direction ) ); ?></td>
                                        <td><code><?php echo esc_html( $log->event_name ); ?></code></td>
                                        <td><span class="wpitk-status wpitk-status-<?php echo esc_attr( sanitize_html_class( $log->status ) ); ?>"><?php echo esc_html( ucfirst( $log->status ) ); ?></span></td>
                                        <td><?php echo esc_html( null === $log->response_code ? '—' : $log->response_code ); ?></td>
                                        <td><?php echo esc_html( $log->attempts ); ?></td>
                                        <td><?php echo esc_html( $log->created_at ); ?></td>
                                        <td>
                                            <?php if ( 'outbound' === $log->direction && 'delivered' !== $log->status ) : ?>
                                                <button class="button button-small wpitk-retry" data-log-id="<?php echo esc_attr( $log->id ); ?>"><?php esc_html_e( 'Retry', 'wp-integration-toolkit' ); ?></button>
                                            <?php else : ?>—<?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <?php
    }

    public function ajax_send_test() {
        $this->guard_ajax();

        $result = $this->webhooks->send(
            'integration.test',
            array(
                'message' => 'Test webhook from WP Integration Toolkit',
                'user_id' => get_current_user_id(),
            )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        wp_send_json_success( array( 'message' => __( 'Webhook delivered successfully.', 'wp-integration-toolkit' ), 'result' => $result ) );
    }

    public function ajax_retry() {
        $this->guard_ajax();
        $log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;
        $result = $this->webhooks->retry( $log_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
        }

        wp_send_json_success( array( 'message' => __( 'Webhook retry delivered successfully.', 'wp-integration-toolkit' ) ) );
    }

    private function guard_ajax() {
        check_ajax_referer( 'wpitk_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'You are not allowed to perform this action.', 'wp-integration-toolkit' ) ), 403 );
        }
    }
}
