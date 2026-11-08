<?php
namespace EEW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	const MIN_ELEMENTOR_VERSION = '3.20.0';
	const EMBLA_VERSION         = '8.6.0';

	public static function boot() {
		add_action( 'init', static function () {
			load_plugin_textdomain( 'sobelz-embla-carousel-widget', false, dirname( plugin_basename( EEW_FILE ) ) . '/languages' );
		} );

		if ( ! did_action( 'elementor/loaded' ) || ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
			return;
		}

		require_once EEW_PATH . 'includes/class-carousel-settings.php';
		require_once EEW_PATH . 'includes/class-carousel-query.php';
		require_once EEW_PATH . 'includes/class-loop-template.php';
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register_widget' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ], 5 );
		add_action( 'elementor/frontend/after_register_scripts', [ __CLASS__, 'register_assets' ] );
		add_action( 'elementor/frontend/after_register_styles', [ __CLASS__, 'register_assets' ] );
	}

	public static function dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html( sprintf(
			/* translators: %s: minimum Elementor version. */
			__( 'sobelz-embla-carousel-widget requires Elementor %s or newer. Install or update Elementor to use the widget.', 'sobelz-embla-carousel-widget' ),
			self::MIN_ELEMENTOR_VERSION
		) );
		echo '</p></div>';
	}

	public static function register_widget( $widgets_manager ) {
		require_once EEW_PATH . 'includes/class-carousel-widget.php';
		$widgets_manager->register( new Carousel_Widget() );
	}

	public static function plugin_assets() {
		return [
			'autoplay'      => [ 'embla-carousel-autoplay', self::EMBLA_VERSION ],
			'autoScroll'    => [ 'embla-carousel-auto-scroll', self::EMBLA_VERSION ],
			'autoHeight'    => [ 'embla-carousel-auto-height', self::EMBLA_VERSION ],
			'fade'          => [ 'embla-carousel-fade', self::EMBLA_VERSION ],
			'classNames'    => [ 'embla-carousel-class-names', self::EMBLA_VERSION ],
			'wheelGestures' => [ 'embla-carousel-wheel-gestures', '8.0.2' ],
		];
	}

	public static function register_assets() {
		if ( wp_script_is( 'eew-carousel', 'registered' ) ) {
			return;
		}
		wp_register_script( 'eew-embla', EEW_URL . 'assets/vendor/embla-carousel/embla-carousel.umd.js', [], self::EMBLA_VERSION, true );
		$sources = [];
		foreach ( self::plugin_assets() as $key => $asset ) {
			$url = EEW_URL . 'assets/vendor/' . $asset[0] . '/' . $asset[0] . '.umd.js';
			wp_register_script( 'eew-' . $key, $url, [ 'eew-embla' ], $asset[1], true );
			$sources[ $key ] = add_query_arg( 'ver', $asset[1], $url );
		}
		wp_register_script( 'eew-carousel', EEW_URL . 'assets/js/carousel.js', [ 'eew-embla', 'elementor-frontend' ], EEW_VERSION, true );
		wp_localize_script( 'eew-carousel', 'EEWAssets', [ 'plugins' => $sources ] );
		wp_register_style( 'eew-carousel', EEW_URL . 'assets/css/carousel.css', [], EEW_VERSION );
	}
}
