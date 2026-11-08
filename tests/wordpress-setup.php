<?php
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );

function eew_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new Exception( $message );
	}
}

$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'eew-carousel' );
eew_check( $widget instanceof \EEW\Carousel_Widget, 'Widget was registered' );
eew_check( $widget->get_script_depends() === [ 'eew-embla', 'eew-carousel' ], 'Type-level dependencies are safe without element settings' );
$default_instance = new \EEW\Carousel_Widget( [ 'id' => 'defaulttest', 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => [] ], [] );
$defaults = $default_instance->get_settings_for_display();
eew_check( count( $defaults['slides'] ) === 3, 'Default repeater slides' );
eew_check( count( $widget->get_controls() ) > 50, 'Widget controls loaded' );
$config = \EEW\Carousel_Settings::build( [ 'effect' => 'fade', 'motion' => 'auto_scroll', 'axis' => 'y', 'auto_height' => 'yes', 'autoplay_delay' => -10 ] );
eew_check( ! isset( $config['plugins']->autoScroll ), 'Incompatible fade / auto scroll disabled' );
eew_check( ! isset( $config['plugins']->autoHeight ), 'Vertical auto height disabled' );
eew_check( $config['options']['slidesToScroll'] === 1, 'Fade groups one slide' );
$config = \EEW\Carousel_Settings::build( [ 'motion' => 'autoplay', 'autoplay_delay' => -10 ] );
eew_check( $config['plugins']->autoplay['delay'] === 500, 'Delay safely clamped' );
$instance = new \EEW\Carousel_Widget( [ 'id' => 'depstest', 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => [ 'motion' => 'autoplay' ] ], [] );
eew_check( in_array( 'eew-autoplay', $instance->get_script_depends(), true ), 'Autoplay dependency enabled' );
eew_check( ! in_array( 'eew-fade', $instance->get_script_depends(), true ), 'Unused dependency excluded' );

// Syntax-check the actual shipped PHP files in this runtime.
foreach ( array_merge( glob( EEW_PATH . 'includes/*.php' ), glob( EEW_PATH . 'includes/updater/*.php' ), [ EEW_PATH . 'sobelz-embla-carousel-widget.php' ] ) as $file ) {
	token_get_all( file_get_contents( $file ), TOKEN_PARSE );
}

$slides = [];
for ( $i = 1; $i <= 6; $i++ ) {
	$slides[] = [ '_id' => 'slide' . $i, 'content_type' => 'card', 'title' => 'Slide ' . $i, 'description' => 'Content for slide ' . $i, 'button_text' => 'Read more', 'link' => [ 'url' => '#slide-' . $i ] ];
}

function eew_page( $title, $settings = null, $type = 'page' ) {
	$id = wp_insert_post( [ 'post_title' => $title, 'post_type' => $type, 'post_status' => 'publish', 'post_content' => 'Test page' ] );
	if ( null !== $settings ) {
		$data = [ [ 'id' => 'container' . $id, 'elType' => 'container', 'settings' => [], 'elements' => [ [
			'id' => 'widget' . $id, 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => $settings, 'elements' => [],
		] ] ] ];
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $id, '_elementor_template_type', 'page' === $type ? 'wp-page' : 'section' );
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
	}
	return $id;
}

$pages = [];
$pages['plain'] = eew_page( 'Plain page' );
$base = [ 'slides' => $slides, 'slides_per_view' => 2, 'duration' => 0, 'pause_on_hover' => '', 'stop_on_interaction' => '' ];
$pages['basic'] = eew_page( 'Basic carousel', $base );
$pages['autoplay'] = eew_page( 'Autoplay carousel', array_merge( $base, [ 'motion' => 'autoplay', 'autoplay_delay' => 10000 ] ) );
$pages['fade'] = eew_page( 'Fade carousel', array_merge( $base, [ 'effect' => 'fade', 'motion' => 'autoplay', 'autoplay_delay' => 10000, 'auto_height' => 'yes', 'class_names' => 'yes', 'wheel_gestures' => 'yes' ] ) );
$pages['vertical'] = eew_page( 'Vertical carousel', array_merge( $base, [ 'axis' => 'y', 'direction' => 'rtl', 'motion' => 'auto_scroll', 'loop' => 'yes' ] ) );
$template = wp_insert_post( [ 'post_title' => 'Test saved template', 'post_type' => 'elementor_library', 'post_status' => 'publish' ] );
update_post_meta( $template, '_elementor_edit_mode', 'builder' );
update_post_meta( $template, '_elementor_template_type', 'section' );
update_post_meta( $template, '_elementor_data', wp_slash( wp_json_encode( [ [ 'id' => 'heading1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Saved template content' ], 'elements' => [] ] ] ) ) );
$pages['template'] = eew_page( 'Template carousel', [ 'slides' => [ [ '_id' => 'template1', 'content_type' => 'template', 'title' => 'Template slide', 'template_id' => $template ] ] ] );

require '/wordpress/wp-content/eew-query-tests.php';

// Verify the Persian binary catalog without relying on a remote language pack.
load_textdomain( 'sobelz-embla-carousel-widget', EEW_PATH . 'languages/sobelz-embla-carousel-widget-fa_IR.mo' );
eew_check( __( 'Slides', 'sobelz-embla-carousel-widget' ) === 'اسلایدها', 'Persian translations load' );
unload_textdomain( 'sobelz-embla-carousel-widget' );

echo wp_json_encode( [ 'ok' => true, 'pages' => $pages, 'queries' => $query_test_result, 'wordpress' => $GLOBALS['wp_version'], 'elementor' => ELEMENTOR_VERSION, 'php' => PHP_VERSION ] );
