<?php
namespace EEW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Render Loop Item documents through their own Pro rendering/CSS methods. */
final class Loop_Template {
	private static $stack = [];

	public static function document( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'elementor_library' !== $post->post_type || 'publish' !== $post->post_status || post_password_required( $post ) || 'loop-item' !== get_post_meta( $id, '_elementor_template_type', true ) || in_array( $id, self::$stack, true ) || count( self::$stack ) >= 3 ) {
			return null;
		}
		$context = self::capture_context();
		try {
			$document = \Elementor\Plugin::$instance->documents->get( $id );
			// With Pro inactive Elementor falls back to a regular document type.
			return $document && 'loop-item' === $document->get_name() && is_callable( [ $document, 'print_content' ] ) ? $document : null;
		} finally {
			self::restore_context( $context );
		}
	}

	public static function render( $document ) {
		$id = $document->get_main_id();
		if ( in_array( $id, self::$stack, true ) || count( self::$stack ) >= 3 ) {
			return;
		}
		self::$stack[] = $id;
		$elementor = \Elementor\Plugin::$instance;
		$editing = $elementor->editor->is_edit_mode();
		$elementor->editor->set_edit_mode( false );
		$elementor->documents->switch_to_document( $document );
		try {
			// Loop CSS contains per-post dynamic values (e.g. backgrounds) and
			// selectors. The generic saved-template renderer does not emit it.
			$css_class = '\\ElementorPro\\Modules\\LoopBuilder\\Files\\Css\\Loop';
			if ( class_exists( $css_class ) ) {
				$css = $css_class::create( $id );
				if ( is_callable( [ $css, 'print_all_css' ] ) ) {
					$css->print_all_css( get_the_ID() );
				}
			}
			$document->print_content();
		} finally {
			$elementor->documents->restore_document();
			$elementor->editor->set_edit_mode( $editing );
			array_pop( self::$stack );
		}
	}

	/** Preserve the containing loop, including WooCommerce and nested queries. */
	public static function capture_context() {
		$context = [];
		foreach ( [ 'post', 'wp_query', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'product' ] as $key ) {
			$context[ $key ] = [ array_key_exists( $key, $GLOBALS ), $GLOBALS[ $key ] ?? null ];
		}
		return $context;
	}

	public static function restore_context( array $context ) {
		foreach ( $context as $key => $state ) {
			if ( $state[0] ) {
				$GLOBALS[ $key ] = $state[1];
			} else {
				unset( $GLOBALS[ $key ] );
			}
		}
	}
}
