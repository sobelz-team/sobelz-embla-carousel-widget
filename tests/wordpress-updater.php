<?php
/** Exercise the installed updater through real WordPress hooks, without HTTP. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

function eew_updater_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

$plugin_file = plugin_basename( EEW_FILE );
$headers = get_plugin_data( EEW_FILE, false, false );
$update_uri = 'https://plugins.sobelz.ir/sobelz-embla-carousel-widget';
$feed = $update_uri . '/update.json';
$update_hook = 'update_plugins_plugins.sobelz.ir';
$manifest = json_decode( file_get_contents( '/wordpress/wp-content/eew-release-manifest.json' ), true );
eew_updater_assert( $headers['UpdateURI'] === $update_uri, 'Update URI is registered in the actual plugin header' );
eew_updater_assert( $headers['Version'] === $manifest['version'], 'Release manifest matches the installed plugin version' );
eew_updater_assert( has_filter( $update_hook ), 'Bootstrap registers the update provider' );

// Include a second copy as if another Sobelz plugin were active. The V1 class
// guard must allow both plugins to use this shared implementation.
include EEW_PATH . 'includes/updater/class-sobelz-plugin-updater.php';

$GLOBALS['eew_updater_test_data'] = $manifest;
$GLOBALS['eew_updater_test_data']['version'] = '99.0.0';
$GLOBALS['eew_updater_test_data']['sections']['description'] = '<p>Update details</p><script>alert(1)</script>';
$GLOBALS['eew_updater_test_mode'] = 'valid';
$GLOBALS['eew_updater_test_requests'] = 0;
$GLOBALS['eew_updater_companion_requests'] = 0;
$http = static function ( $pre, $args, $url ) use ( $feed ) {
	if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check/' ) ) {
		return [ 'headers' => [], 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [ 'plugins' => (object) [], 'no_update' => (object) [], 'translations' => [] ] ) ];
	}
	if ( 'https://plugins.sobelz.ir/eew-updater-companion/update.json' === $url ) {
		$GLOBALS['eew_updater_companion_requests']++;
		return [ 'headers' => [], 'response' => [ 'code' => 200 ], 'body' => wp_json_encode( [ 'slug' => 'eew-updater-companion', 'version' => '2.0.0', 'download_url' => 'https://plugins.sobelz.ir/eew-updater-companion/plugin.zip' ] ) ];
	}
	if ( $url !== $feed ) {
		return $pre;
	}
	$GLOBALS['eew_updater_test_requests']++;
	eew_updater_assert( true === $args['sslverify'] && 10 === $args['timeout'], 'Manifest transport keeps upstream HTTPS and timeout settings' );
	if ( 'transport_error' === $GLOBALS['eew_updater_test_mode'] ) {
		return new WP_Error( 'eew_test_offline', 'Test transport failure' );
	}
	return [
		'headers' => [],
		'response' => [ 'code' => 'http_error' === $GLOBALS['eew_updater_test_mode'] ? 503 : 200 ],
		'body' => 'invalid_json' === $GLOBALS['eew_updater_test_mode'] ? 'invalid-json' : wp_json_encode( $GLOBALS['eew_updater_test_data'] ),
	];
};

$check = static function () use ( $headers, $plugin_file, $update_hook ) {
	return apply_filters( $update_hook, false, $headers, $plugin_file, [ 'fa_IR' ] );
};
$clear = static function () { delete_site_transient( 'update_plugins' ); };
$companion = null;
add_filter( 'pre_http_request', $http, 10, 3 );

try {
	$clear();
	$update = $check();
	eew_updater_assert( '99.0.0' === $update['version'] && '99.0.0' === $update['new_version'], 'Update metadata maps its remote version' );
	eew_updater_assert( $manifest['download_url'] === $update['package'], 'Update metadata points to the stable ZIP' );
	eew_updater_assert( $plugin_file === $update['plugin'] && $update_uri === $update['id'], 'Update belongs to the correct installed plugin' );
	eew_updater_assert( '7.4' === $update['requires_php'], 'PHP requirement maps to WordPress' );
	eew_updater_assert( 1 === $GLOBALS['eew_updater_test_requests'], 'One request fetches the feed' );

	$information = plugins_api( 'plugin_information', [ 'slug' => $manifest['slug'] ] );
	eew_updater_assert( is_object( $information ) && '99.0.0' === $information->version, 'Native plugin-details API uses the custom feed' );
	eew_updater_assert( false === strpos( $information->sections['description'], '<script' ), 'Plugin details strip unsafe markup' );
	eew_updater_assert( 1 === $GLOBALS['eew_updater_test_requests'], 'Details reuse the request cache' );
	eew_updater_assert( 'untouched' === apply_filters( $update_hook, 'untouched', [], 'other-plugin/plugin.php', [] ), 'Other plugins keep their update response' );

	$fresh = \Sobelz\PluginUpdater\V1\Updater::register( [ 'plugin_file' => EEW_FILE, 'slug' => $manifest['slug'], 'update_uri' => $update_uri ] );
	$fresh->filter_update( false, $headers, $plugin_file, [] );
	eew_updater_assert( 1 === $GLOBALS['eew_updater_test_requests'], 'A fresh updater instance reuses persistent site cache' );

	$companion_file = WP_PLUGIN_DIR . '/eew-updater-companion/plugin.php';
	$companion = \Sobelz\PluginUpdater\V1\Updater::register( [ 'plugin_file' => $companion_file, 'slug' => 'eew-updater-companion', 'update_uri' => 'https://plugins.sobelz.ir/eew-updater-companion' ] );
	$other = apply_filters( $update_hook, false, [], plugin_basename( $companion_file ), [] );
	eew_updater_assert( 'eew-updater-companion' === $other['slug'] && '2.0.0' === $other['version'], 'Two Sobelz plugins can share the class and host' );
	eew_updater_assert( 1 === $GLOBALS['eew_updater_companion_requests'] && 1 === $GLOBALS['eew_updater_test_requests'], 'Feeds and caches are independent per plugin' );

	do_action( 'upgrader_process_complete', null, [ 'type' => 'plugin', 'action' => 'update', 'plugins' => [ plugin_basename( $companion_file ) ] ] );
	$check();
	eew_updater_assert( 1 === $GLOBALS['eew_updater_test_requests'], 'An unrelated upgrade preserves this plugin cache' );
	do_action( 'upgrader_process_complete', null, [ 'type' => 'plugin', 'action' => 'update', 'plugins' => [ $plugin_file ] ] );
	$check();
	eew_updater_assert( 2 === $GLOBALS['eew_updater_test_requests'], 'Completing this plugin upgrade invalidates its cache' );
	$clear();
	$check();
	eew_updater_assert( 3 === $GLOBALS['eew_updater_test_requests'], 'Forced WordPress update checks clear persistent and request caches' );

	foreach ( [ 'transport_error', 'http_error', 'invalid_json', 'wrong_slug', 'insecure_zip', 'missing_version' ] as $failure ) {
		$GLOBALS['eew_updater_test_data'] = $manifest;
		$GLOBALS['eew_updater_test_mode'] = $failure;
		if ( 'wrong_slug' === $failure ) $GLOBALS['eew_updater_test_data']['slug'] = 'another-plugin';
		if ( 'insecure_zip' === $failure ) $GLOBALS['eew_updater_test_data']['download_url'] = 'http://example.com/plugin.zip';
		if ( 'missing_version' === $failure ) unset( $GLOBALS['eew_updater_test_data']['version'] );
		$clear();
		eew_updater_assert( false === $check(), 'Bad update metadata fails without changing the update response: ' . $failure );
	}

	$GLOBALS['eew_updater_test_mode'] = 'valid';
	$GLOBALS['eew_updater_test_data'] = $manifest;
	$GLOBALS['eew_updater_test_data']['version'] = '99.0.0';
	$clear();
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	eew_updater_assert( isset( $updates->response[ $plugin_file ] ) && '99.0.0' === $updates->response[ $plugin_file ]->new_version, 'WordPress core offers a newer release through the normal updates screen' );
	$GLOBALS['eew_updater_test_data']['version'] = EEW_VERSION;
	$clear();
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	eew_updater_assert( ! isset( $updates->response[ $plugin_file ] ) && isset( $updates->no_update[ $plugin_file ] ), 'WordPress core does not offer the already-installed version' );
	$GLOBALS['eew_updater_test_data']['version'] = '1.0.0';
	$clear();
	wp_update_plugins();
	$updates = get_site_transient( 'update_plugins' );
	eew_updater_assert( ! isset( $updates->response[ $plugin_file ] ), 'WordPress core does not offer a downgrade' );

	echo wp_json_encode( [ 'updater' => 'passed', 'http_requests' => $GLOBALS['eew_updater_test_requests'], 'live_update_server_contacted' => false ] );
} finally {
	$clear();
	if ( $companion ) $companion->clear_cache();
	remove_filter( 'pre_http_request', $http, 10 );
}
