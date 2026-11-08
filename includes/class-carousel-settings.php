<?php
namespace EEW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One configuration path for both script dependencies and the rendered widget. */
final class Carousel_Settings {
	private static function number( $settings, $key, $default, $min, $max ) {
		$value = isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ? (float) $settings[ $key ] : $default;
		return max( $min, min( $max, $value ) );
	}

	private static function choice( $settings, $key, $choices, $default ) {
		return isset( $settings[ $key ] ) && in_array( $settings[ $key ], $choices, true ) ? $settings[ $key ] : $default;
	}

	private static function enabled( $settings, $key, $default = false ) {
		return array_key_exists( $key, $settings ) ? 'yes' === $settings[ $key ] : $default;
	}

	public static function build( array $settings ) {
		$axis      = self::choice( $settings, 'axis', [ 'x', 'y' ], 'x' );
		$direction = self::choice( $settings, 'direction', [ 'auto', 'ltr', 'rtl' ], 'auto' );
		$fade      = 'fade' === ( $settings['effect'] ?? 'scroll' );
		$motion    = self::choice( $settings, 'motion', [ 'none', 'autoplay', 'auto_scroll' ], 'none' );
		if ( $fade && 'auto_scroll' === $motion ) {
			$motion = 'none';
		}
		$options = [
			'axis'            => $axis,
			'direction'       => 'auto' === $direction ? ( is_rtl() ? 'rtl' : 'ltr' ) : $direction,
			'align'           => $fade ? 'center' : self::choice( $settings, 'align', [ 'start', 'center', 'end' ], 'start' ),
			'loop'            => self::enabled( $settings, 'loop' ),
			'containScroll'   => $fade || 'none' === ( $settings['contain_scroll'] ?? '' ) ? false : self::choice( $settings, 'contain_scroll', [ 'trimSnaps', 'keepSnaps' ], 'trimSnaps' ),
			'dragFree'        => ! $fade && self::enabled( $settings, 'drag_free' ),
			'skipSnaps'       => self::enabled( $settings, 'skip_snaps' ),
			'watchDrag'       => self::enabled( $settings, 'draggable', true ),
			'watchFocus'      => true,
			'watchResize'     => true,
			'watchSlides'     => true,
			'dragThreshold'   => (int) self::number( $settings, 'drag_threshold', 10, 0, 100 ),
			'duration'        => (int) self::number( $settings, 'duration', 25, 0, 100 ),
			'startIndex'      => (int) self::number( $settings, 'start_slide', 1, 1, 10000 ) - 1,
			'inViewThreshold' => self::number( $settings, 'in_view_threshold', 0, 0, 1 ),
			'slidesToScroll'   => $fade ? 1 : ( 'auto' === ( $settings['group_mode'] ?? '' ) ? 'auto' : (int) self::number( $settings, 'slides_to_scroll', 1, 1, 20 ) ),
		];
		$plugins = [];
		$motion_options = [
			'playOnInit'        => true,
			'stopOnInteraction' => self::enabled( $settings, 'stop_on_interaction', true ),
			'stopOnMouseEnter'  => self::enabled( $settings, 'pause_on_hover', true ),
			'stopOnFocusIn'     => true,
		];
		if ( 'autoplay' === $motion ) {
			$plugins['autoplay'] = array_merge( $motion_options, [
				'delay'          => (int) self::number( $settings, 'autoplay_delay', 4000, 500, 120000 ),
				'jump'           => self::enabled( $settings, 'autoplay_jump' ),
				'stopOnLastSnap' => self::enabled( $settings, 'stop_on_last_snap' ),
			] );
		}
		if ( 'auto_scroll' === $motion ) {
			$plugins['autoScroll'] = array_merge( $motion_options, [
				'speed'      => self::number( $settings, 'auto_scroll_speed', 2, 0.1, 20 ),
				'startDelay' => (int) self::number( $settings, 'auto_scroll_delay', 1000, 0, 120000 ),
				'direction'  => self::choice( $settings, 'auto_scroll_direction', [ 'forward', 'backward' ], 'forward' ),
			] );
		}
		if ( self::enabled( $settings, 'auto_height' ) && 'x' === $axis ) {
			$plugins['autoHeight'] = (object) [];
		}
		if ( $fade ) {
			$plugins['fade'] = (object) [];
		}
		if ( self::enabled( $settings, 'class_names' ) ) {
			$plugins['classNames'] = (object) [];
		}
		if ( self::enabled( $settings, 'wheel_gestures' ) && $options['watchDrag'] ) {
			$wheel_axis = self::choice( $settings, 'wheel_axis', [ 'auto', 'x', 'y' ], 'auto' );
			$plugins['wheelGestures'] = 'auto' === $wheel_axis ? (object) [] : [ 'forceWheelAxis' => $wheel_axis ];
		}
		return [
			'options'              => $options,
			'plugins'              => (object) $plugins,
			'keyboard'             => self::enabled( $settings, 'keyboard', true ),
			'respectReducedMotion' => self::enabled( $settings, 'reduced_motion', true ),
			'labels'               => [
				'goTo'     => __( 'Go to slide group %d', 'sobelz-embla-carousel-widget' ),
				'status'   => __( 'Slide group %1$d of %2$d', 'sobelz-embla-carousel-widget' ),
				'play'     => __( 'Start automatic rotation', 'sobelz-embla-carousel-widget' ),
				'pause'    => __( 'Pause automatic rotation', 'sobelz-embla-carousel-widget' ),
				'error'    => __( 'The carousel could not start. Please reload the page.', 'sobelz-embla-carousel-widget' ),
			],
		];
	}
}
