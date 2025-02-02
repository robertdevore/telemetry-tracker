<?php
namespace TelemetryTracker;

use WP_REST_Request;
use WP_REST_Response;
use wpdb;

/**
 * Class TelemetryTracker
 * Handles telemetry tracking and analytics.
 */
class TelemetryTracker {

    /** @var wpdb $wpdb WordPress database instance */
    private $wpdb;

    /** @var string $table_name Table name for telemetry data */
    private $table_name;

    /** @var array $plugin_names Mapping of plugin slugs to names */
    private $plugin_names = [
        'subscribers-for-wordpress' => 'Subscribers for WordPress',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'telemetry_data';
        
        add_action( 'rest_api_init', [ $this, 'register_endpoint' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'telemetry_tracker_weekly_event', [ $this, 'send_weekly_ping' ] );
    }

    /**
     * Register the REST API endpoint.
     */
    public function register_endpoint() {
        register_rest_route( 'telemetry-tracker/v1', '/track/', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle incoming telemetry data.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response The response object.
     */
    public function handle_request( WP_REST_Request $request ) {
        $data = $request->get_json_params();
        
        if ( empty( $data['site_hash'] ) || empty( $data['plugin'] ) || empty( $data['version'] ) || empty( $data['wp_version'] ) || empty( $data['php_version'] ) ) {
            return new WP_REST_Response( [ 'error' => __( 'Missing required parameters', 'telemetry-tracker' ) ], 400 );
        }
        
        $site_hash   = sanitize_text_field( $data['site_hash'] );
        $plugin_slug = sanitize_text_field( $data['plugin'] );
        $version     = sanitize_text_field( $data['version'] );
        $wp_version  = sanitize_text_field( $data['wp_version'] );
        $php_version = sanitize_text_field( $data['php_version'] );
        $mysql_version = $this->wpdb->db_version();

        // Insert or update telemetry data securely
        $this->wpdb->query( $this->wpdb->prepare(
            "INSERT INTO $this->table_name (site_hash, plugin_slug, version, wp_version, php_version, mysql_version, last_ping)
            VALUES (%s, %s, %s, %s, %s, %s, NOW())
            ON DUPLICATE KEY UPDATE
                version = VALUES(version),
                wp_version = VALUES(wp_version),
                php_version = VALUES(php_version),
                mysql_version = VALUES(mysql_version),
                last_ping = NOW()",
            $site_hash, $plugin_slug, $version, $wp_version, $php_version, $mysql_version
        ));

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * Schedule weekly telemetry pings.
     */
    public static function activate() {
        if ( ! wp_next_scheduled( 'telemetry_tracker_weekly_event' ) ) {
            wp_schedule_event( time(), 'weekly', 'telemetry_tracker_weekly_event' );
        }
    }

    /**
     * Remove scheduled telemetry pings on deactivation.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'telemetry_tracker_weekly_event' );
    }

    /**
     * Send weekly telemetry ping.
     */
    public function send_weekly_ping() {
        $plugin_data = [
            'site_hash'   => md5( home_url() ),
            'plugin'      => 'telemetry-tracker',
            'version'     => '1.1.1',
            'wp_version'  => get_bloginfo( 'version' ),
            'php_version' => phpversion(),
            'mysql_version' => $this->wpdb->db_version(),
        ];

        wp_remote_post( 'https://plugins.robertdevore.com/wp-json/telemetry-tracker/v1/track/', [
            'body'    => json_encode( $plugin_data ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 5,
        ] );
    }

    /**
     * Get the display name of a plugin from its slug.
     *
     * @param string $slug The plugin slug.
     * @return string The plugin name.
     */
    private function get_plugin_name( $slug ) {
        return $this->plugin_names[ $slug ] ?? ucfirst( str_replace( '-', ' ', $slug ) );
    }
}
