<?php
/**
 * Plugin Name: Telemetry Tracker
 * Description: Tracks active installs for your plugins via WordPress REST API and provides analytics.
 * Version: 1.0.0
 * Author: Robert DeVore
 * Author URI: https://robertdevore.com/
 * License: GPL-2.0+
 * Text Domain: telemetry-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use TelemetryTracker\TelemetryTracker;

$plugin_slug    = 'telemetry-tracker';
$plugin_version = '1.0.0';

$telemetry_tracker = new TelemetryTracker( $plugin_slug, $plugin_version );

register_activation_hook( __FILE__, [ 'TelemetryTracker\\TelemetryTracker', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'TelemetryTracker\\TelemetryTracker', 'deactivate' ] );
