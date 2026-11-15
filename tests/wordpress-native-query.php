<?php
/** Run with `wp eval-file`: differential checks against installed Elementor Pro. */
if ( ! class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Widgets\\Loop_Carousel' ) ) {
	throw new RuntimeException( 'These checks require Elementor Pro.' );
}

function eew_native_check( $condition, $message, $details = null ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message . ( null === $details ? '' : ': ' . wp_json_encode( $details ) ) );
	}
	WP_CLI::log( 'PASS: ' . $message );
}

function eew_native_widget( array $settings ) {
	return \Elementor\Plugin::$instance->elements_manager->create_element_instance( [
		// A selected template activates Pro's Number of slides control. These
		// checks execute queries only; no document is rendered by this helper.
		'id' => wp_unique_id( 'native-test-' ), 'elType' => 'widget', 'widgetType' => 'loop-carousel', 'settings' => array_merge( [ 'template_id' => 1 ], $settings ),
	] );
}

function eew_compare_posts( $label, array $settings, array $native_settings ) {
	$native = eew_native_widget( $native_settings );
	$native->query_posts();
	$expected = wp_list_pluck( $native->get_query()->posts, 'ID' );
	$widget = new \EEW\Carousel_Widget( [ 'id' => 'paritytest', 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => $settings ], [] );
	$actual = wp_list_pluck( \EEW\Carousel_Query::run( $settings, $widget )->posts, 'ID' );
	eew_native_check( $actual === $expected, $label . ': ' . wp_json_encode( [ $actual, $expected ] ) );
}

function eew_compare_terms( $label, array $settings, array $native_settings ) {
	$native = eew_native_widget( $native_settings );
	$native->get_settings_for_display();
	$native->get_current_skin()->set_parent( $native );
	$method = new ReflectionMethod( $native->get_current_skin(), 'get_terms' );
	$method->setAccessible( true );
	$expected = array_values( wp_list_pluck( $method->invoke( $native->get_current_skin() ), 'term_id' ) );
	$actual = wp_list_pluck( \EEW\Carousel_Query::run( $settings, null )->terms, 'term_id' );
	eew_native_check( $actual === $expected, $label . ': ' . wp_json_encode( [ $actual, $expected ] ) );
}

$created_posts = [];
$created_terms = [];
$context = \EEW\Loop_Template::capture_context();
$original_main = $GLOBALS['wp_the_query'];
register_post_type( 'eew_parity', [ 'public' => true, 'show_ui' => false, 'label' => 'Query parity fixtures' ] );
register_taxonomy( 'eew_parity_term', [ 'eew_parity', 'product' ], [ 'public' => true, 'show_ui' => false, 'hierarchical' => true ] );

try {
	foreach ( [ 'Alpha', 'Beta', 'Empty' ] as $name ) {
		$inserted = wp_insert_term( 'EEW parity ' . $name, 'eew_parity_term' );
		eew_native_check( ! is_wp_error( $inserted ), 'Create temporary term ' . $name );
		$created_terms[] = $inserted['term_id'];
	}
	$posts = [];
	for ( $i = 0; $i < 5; $i++ ) {
		$id = wp_insert_post( [ 'post_type' => 'eew_parity', 'post_status' => 4 === $i ? 'draft' : 'publish', 'post_title' => 'Parity ' . $i ], true );
		eew_native_check( ! is_wp_error( $id ), 'Create temporary post ' . $i );
		$created_posts[] = $id;
		$posts[] = $id;
		wp_set_object_terms( $id, [ $created_terms[ $i % 2 ] ], 'eew_parity_term' );
	}
	$base = [ 'query_post_type' => 'eew_parity', 'query_per_page' => 3, 'query_orderby' => 'title', 'query_order' => 'ASC' ];
	$native = [ '_skin' => 'post', 'posts_per_page' => 3, 'post_query_post_type' => 'eew_parity', 'post_query_orderby' => 'title', 'post_query_order' => 'ASC', 'post_query_ignore_sticky_posts' => 'yes' ];
	eew_compare_posts( 'Public custom posts and ordering', $base, $native );
	eew_compare_posts( 'Offset', array_merge( $base, [ 'query_offset' => 1 ] ), array_merge( $native, [ 'post_query_offset' => 1 ] ) );
	$term = get_term( $created_terms[0], 'eew_parity_term' );
	eew_compare_posts( 'Taxonomy filter', array_merge( $base, [ 'query_taxonomy' => 'eew_parity_term', 'query_term_ids' => (string) $term->term_id ] ), array_merge( $native, [ 'post_query_include' => [ 'terms' ], 'post_query_include_term_ids' => [ $term->term_taxonomy_id ] ] ) );
	eew_compare_posts( 'Manual selection and included order', array_merge( $base, [ 'query_source' => 'selection', 'query_include_ids' => "$posts[2],$posts[0]", 'query_orderby' => 'post__in' ] ), array_merge( $native, [ 'post_query_post_type' => 'by_id', 'post_query_posts_ids' => [ $posts[2], $posts[0] ], 'post_query_orderby' => 'post__in' ] ) );
	eew_compare_posts( 'Explicit post exclusion', array_merge( $base, [ 'query_exclude_ids' => (string) $posts[0] ] ), array_merge( $native, [ 'post_query_exclude' => [ 'manual_selection' ], 'post_query_exclude_ids' => [ $posts[0] ] ] ) );
	$GLOBALS['wp_query'] = new WP_Query( [ 'p' => $posts[0], 'post_type' => 'eew_parity' ] );
	$GLOBALS['post'] = get_post( $posts[0] );
	eew_compare_posts( 'Related posts', array_merge( $base, [ 'query_source' => 'related', 'query_taxonomy' => 'eew_parity_term' ] ), array_merge( $native, [ 'post_query_post_type' => 'related', 'post_query_include' => [ 'terms' ], 'post_query_related_taxonomies' => [ 'eew_parity_term' ], 'post_query_related_fallback' => 'fallback_none' ] ) );
	$GLOBALS['wp_query'] = new WP_Query( [ 'post_type' => 'eew_parity', 'posts_per_page' => 2, 'paged' => 2, 'orderby' => 'title', 'order' => 'ASC' ] );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	eew_compare_posts( 'Current query and pagination', [ 'query_source' => 'current' ], [ '_skin' => 'post', 'post_query_post_type' => 'current_query' ] );
	$GLOBALS['wp_the_query'] = $original_main;
	\EEW\Loop_Template::restore_context( $context );

	$terms_base = [ 'query_source' => 'terms', 'query_taxonomy' => 'eew_parity_term', 'query_per_page' => 6 ];
	$skin = class_exists( '\\WooCommerce' ) ? 'product_taxonomy' : 'post_taxonomy';
	$native_terms = [ '_skin' => $skin, 'posts_per_page' => 6, $skin . '_query_post_type' => 'eew_parity_term' ];
	eew_compare_terms( 'Default terms include empty terms', $terms_base, $native_terms );
	eew_compare_terms( 'Hide empty terms', array_merge( $terms_base, [ 'query_hide_empty' => 'yes' ] ), array_merge( $native_terms, [ $skin . '_hide_empty' => 'yes' ] ) );
	eew_compare_terms( 'Include terms', array_merge( $terms_base, [ 'query_term_ids' => (string) $created_terms[0] ] ), array_merge( $native_terms, [ $skin . '_query_filter_by' => 'manual_selection', $skin . '_posts_ids' => [ $created_terms[0] ] ] ) );
	eew_compare_terms( 'Exclude terms', array_merge( $terms_base, [ 'query_exclude_term_ids' => (string) $created_terms[0] ] ), array_merge( $native_terms, [ $skin . '_query_filter_by' => 'manual_selection', $skin . '_exclude_ids' => [ $created_terms[0] ] ] ) );
	eew_compare_terms( 'Term offset, limit and ordering', array_merge( $terms_base, [ 'query_offset' => 1, 'query_per_page' => 1, 'query_term_order' => 'ASC' ] ), array_merge( $native_terms, [ 'posts_per_page' => 1, $skin . '_offset' => 1, $skin . '_order' => 'ASC' ] ) );
	$before_filter = has_filter( 'elementor/loop_taxonomy/args' );
	$throw = static function () { throw new RuntimeException( 'Expected term-filter failure' ); };
	add_filter( 'eew/term_query_args', $throw );
	try {
		\EEW\Carousel_Query::run( $terms_base, null );
		throw new RuntimeException( 'Term filter did not throw' );
	} catch ( RuntimeException $error ) {
		eew_native_check( 'Expected term-filter failure' === $error->getMessage() && has_filter( 'elementor/loop_taxonomy/args' ) === $before_filter, 'Native term callback is removed after exceptions' );
	} finally {
		remove_filter( 'eew/term_query_args', $throw );
	}

	if ( class_exists( '\\WC_Product_Simple' ) ) {
		for ( $i = 0; $i < 3; $i++ ) {
			$product = new WC_Product_Simple();
			$product->set_name( 'EEW product parity ' . $i );
			$product->set_regular_price( ( $i + 1 ) * 100 );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 2 === $i ? 'hidden' : 'visible' );
			$created_posts[] = $product->save();
			wp_set_object_terms( $product->get_id(), [ $term->term_id ], 'eew_parity_term' );
		}
		$product_settings = [ 'query_post_type' => 'product', 'query_taxonomy' => 'eew_parity_term', 'query_term_ids' => (string) $term->term_id, 'query_per_page' => 10, 'query_orderby' => 'title', 'query_order' => 'ASC' ];
		$product_native = [ '_skin' => 'product', 'posts_per_page' => 10, 'product_query_post_type' => 'product', 'product_query_include' => [ 'terms' ], 'product_query_include_term_ids' => [ $term->term_taxonomy_id ], 'product_query_orderby' => 'title', 'product_query_order' => 'ASC' ];
		eew_compare_posts( 'WooCommerce catalog visibility and taxonomy filters', $product_settings, $product_native );
		eew_compare_posts( 'WooCommerce exclusions', array_merge( $product_settings, [ 'query_exclude_ids' => (string) $created_posts[5] ] ), array_merge( $product_native, [ 'product_query_exclude' => [ 'manual_selection' ], 'product_query_exclude_ids' => [ $created_posts[5] ] ] ) );
		$native_only = new WC_Product_Simple();
		$native_only->set_name( 'EEW native-only duplicate fixture' );
		$native_only->set_regular_price( 100 );
		$native_only->set_status( 'publish' );
		$native_only_id = $native_only->save();
		$created_posts[] = $native_only_id;
		$native_only_widget = eew_native_widget( [ '_skin' => 'product', 'product_query_post_type' => 'by_id', 'product_query_posts_ids' => [ $native_only_id ] ] );
		$native_only_widget->query_posts();
		eew_native_check( wp_list_pluck( $native_only_widget->get_query()->posts, 'ID' ) === [ $native_only_id ], 'Native carousel records its product result' );
		$avoid_native = \EEW\Carousel_Query::run( [ 'query_post_type' => 'product', 'query_include_ids' => (string) $native_only_id, 'query_avoid_duplicates' => 'yes' ], null );
		eew_native_check( ! $avoid_native->posts, 'Product duplicate avoidance shares the native carousel list' );

		// Category Image is a native Pro tag that also supplies brand thumbnails.
		// Exercise both image widgets and per-term dynamic background CSS.
		$image_ids = [];
		foreach ( array_slice( $created_terms, 0, 2 ) as $index => $term_id ) {
			$upload = wp_upload_bits( 'eew-parity-' . wp_generate_uuid4() . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) );
			eew_native_check( ! $upload['error'], 'Create temporary loop thumbnail' );
			$image_id = wp_insert_attachment( [ 'post_title' => 'Parity thumbnail', 'post_mime_type' => 'image/png' ], $upload['file'] );
			$created_posts[] = $image_id;
			wp_update_attachment_metadata( $image_id, [ 'width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path( $upload['file'] ) ] );
			update_term_meta( $term_id, 'thumbnail_id', $image_id );
			$image_ids[ $term_id ] = $image_id;
		}
		$loop_id = wp_insert_post( [ 'post_title' => 'Temporary parity loop', 'post_type' => 'elementor_library', 'post_status' => 'publish' ] );
		$created_posts[] = $loop_id;
		update_post_meta( $loop_id, '_elementor_template_type', 'loop-item' );
		update_post_meta( $loop_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $loop_id, '_elementor_version', ELEMENTOR_VERSION );
		update_post_meta( $loop_id, '_elementor_source', 'product_taxonomy' );
		$tag = '[elementor-tag id="parityimage" name="woocommerce-category-image-tag" settings="%7B%7D"]';
		$data = [ [ 'id' => 'paritybg', 'elType' => 'container', 'settings' => [ 'background_background' => 'classic', '__dynamic__' => [ 'background_image' => $tag ] ], 'elements' => [ [
			'id' => 'parityimg', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => [ 'image_size' => 'full', '__dynamic__' => [ 'image' => $tag ] ], 'elements' => [],
		] ] ] ];
		update_post_meta( $loop_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		$loop_settings = array_merge( $terms_base, [ 'content_source' => 'query', 'query_layout' => 'loop_item', 'loop_template_id' => $loop_id, 'query_term_ids' => implode( ',', array_keys( $image_ids ) ) ] );
		$loop_widget = new \EEW\Carousel_Widget( [ 'id' => 'parityloop', 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => $loop_settings ], [] );
		$before_render = \EEW\Loop_Template::capture_context();
		ob_start();
		try {
			$loop_widget->render_content();
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}
		eew_native_check( $before_render === \EEW\Loop_Template::capture_context(), 'Taxonomy loop restores the containing query context' );
		foreach ( $image_ids as $term_id => $image_id ) {
			$url = wp_get_attachment_image_url( $image_id, 'full' );
			eew_native_check( false !== strpos( $html, 'e-loop-item-' . $term_id ) && false !== strpos( $html, esc_url( $url ) ), 'Native taxonomy image and wrapper for term ' . $term_id );
			preg_match( '/<style id="eew-loop-dynamic-' . $loop_id . '-' . $term_id . '">(.*?)<\/style>/s', $html, $style );
			eew_native_check( isset( $style[1] ) && false !== strpos( $style[1], $url ), 'Dynamic background CSS for term ' . $term_id, [ 'expected' => $url, 'style' => $style[1] ?? null ] );
		}
	}
	WP_CLI::success( 'Native Loop Carousel query parity checks passed.' );
} finally {
	$GLOBALS['wp_the_query'] = $original_main;
	\EEW\Loop_Template::restore_context( $context );
	foreach ( array_reverse( $created_posts ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $created_terms as $term_id ) {
		wp_delete_term( $term_id, 'eew_parity_term' );
	}
	unregister_taxonomy( 'eew_parity_term' );
	unregister_post_type( 'eew_parity' );
}
