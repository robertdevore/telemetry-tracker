<?php
namespace TelemetryTracker;

class TelemetryTracker {

    private $api_url = 'https://plugins.robertdevore.com/wp-json/telemetry-tracker/v1/track/';
    private $plugin_slug;
    private $plugin_version;

    public function __construct( $plugin_slug, $plugin_version ) {
        $this->plugin_slug = $plugin_slug;
        $this->plugin_version = $plugin_version;

        add_action( 'init', [ $this, 'schedule_weekly_ping' ] );
    }

    /**
     * Send telemetry data.
     */
    public function send_ping( $event ) {
        $plugin_data = [
            'site_hash'   => md5( home_url() ),
            'plugin'      => $this->plugin_slug,
            'version'     => $this->plugin_version,
            'wp_version'  => get_bloginfo( 'version' ),
            'php_version' => phpversion(),
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'event'       => $event,
        ];

        wp_remote_post( $this->api_url, [
            'body'    => json_encode( $plugin_data ),
            'headers' => [ 'Content-Type' => 'application/json' ],
            'timeout' => 5,
        ] );
    }

    /**
     * Register plugin activation hook.
     */
    public static function activate( $plugin_slug, $plugin_version ) {
        $tracker = new self( $plugin_slug, $plugin_version );
        $tracker->send_ping( 'activated' );
    }

    /**
     * Register plugin deactivation hook.
     */
    public static function deactivate( $plugin_slug, $plugin_version ) {
        $tracker = new self( $plugin_slug, $plugin_version );
        $tracker->send_ping( 'deactivated' );
    }

    /**
     * Schedule weekly telemetry pings.
     */
    public function schedule_weekly_ping() {
        if ( ! wp_next_scheduled( 'telemetry_tracker_weekly_ping' ) ) {
            wp_schedule_event( time(), 'weekly', 'telemetry_tracker_weekly_ping' );
        }
        add_action( 'telemetry_tracker_weekly_ping', [ $this, 'send_weekly_ping' ] );
    }

    /**
     * Send a weekly ping to verify active installs.
     */
    public function send_weekly_ping() {
        $this->send_ping( 'weekly_ping' );
    }
}
