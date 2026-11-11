<?php
/** Term queries and rendering in the real WordPress/Elementor test site. */

function eew_term_ids( $settings, $widget ) {
	$query = \EEW\Carousel_Query::run( $settings, $widget );
	eew_check( $query instanceof WP_Term_Query, 'Terms use WP_Term_Query' );
	return wp_list_pluck( $query->terms, 'term_id' );
}

$alpha = wp_insert_term( 'Alpha products', 'product_cat', [ 'description' => 'Alpha category description' ] )['term_id'];
$child = wp_insert_term( 'Alpha child', 'product_cat', [ 'parent' => $alpha ] )['term_id'];
$beta = wp_insert_term( 'Beta products', 'product_cat', [ 'description' => 'Beta category description' ] )['term_id'];
$empty_term = wp_insert_term( 'Empty products', 'product_cat' )['term_id'];
$brand = wp_insert_term( 'Carousel brand', 'product_brand', [ 'description' => 'Brand description' ] )['term_id'];
wp_set_object_terms( $events[0], [ $alpha ], 'product_cat' );
wp_set_object_terms( $events[1], [ $child ], 'product_cat' );
wp_set_object_terms( $events[2], [ $beta ], 'product_cat' );
wp_set_object_terms( $events[3], [ $beta ], 'product_cat' );
wp_set_object_terms( $events[0], [ $brand ], 'product_brand' );

$term_settings = [ 'content_source' => 'query', 'query_source' => 'terms', 'query_taxonomy' => 'product_cat', 'query_per_page' => 20 ];
$term_widget = eew_test_widget( $term_settings );
$controls = $term_widget->get_controls();
// Elementor strips editor-only labels/options from controls on the frontend.
eew_check( isset( $controls['query_term_orderby'], $controls['query_hide_empty'], $controls['query_term_parent'] ), 'Term controls registered' );
$available_taxonomies = \EEW\Carousel_Query::taxonomies();
foreach ( [ 'category', 'product_cat', 'product_brand' ] as $taxonomy_name ) {
	eew_check( isset( $available_taxonomies[ $taxonomy_name ] ), 'Public taxonomy discovered: ' . $taxonomy_name );
}
eew_check( ! isset( $available_taxonomies['eew_secret'] ) && ! isset( $available_taxonomies['eew_hidden'] ), 'Internal taxonomies are unavailable' );
eew_check( eew_term_ids( $term_settings, $term_widget ) === [ $child, $alpha, $beta ], 'Queries terms themselves by name and hides empty terms' );
$all_terms = array_merge( $term_settings, [ 'query_hide_empty' => '' ] );
eew_check( eew_term_ids( $all_terms, $term_widget ) === [ $child, $alpha, $beta, $empty_term ], 'Empty terms can be included' );
eew_check( eew_term_ids( array_merge( $all_terms, [ 'query_term_order' => 'DESC' ] ), $term_widget ) === [ $empty_term, $beta, $alpha, $child ], 'Descending term order' );
eew_check( eew_term_ids( array_merge( $all_terms, [ 'query_offset' => 1, 'query_per_page' => 2 ] ), $term_widget ) === [ $alpha, $beta ], 'Term limit and offset' );
$term_selection = array_merge( $all_terms, [ 'query_term_ids' => "$beta,$alpha,$child", 'query_exclude_term_ids' => (string) $alpha, 'query_term_orderby' => 'include' ] );
eew_check( eew_term_ids( $term_selection, $term_widget ) === [ $beta, $child ], 'Term ID order and exclusions both apply' );
eew_check( ! eew_term_ids( array_merge( $term_selection, [ 'query_exclude_term_ids' => "$beta,$alpha,$child" ] ), $term_widget ), 'Excluded selection never falls back to all terms' );
eew_check( eew_term_ids( array_merge( $all_terms, [ 'query_exclude_term_ids' => (string) $alpha ] ), $term_widget ) === [ $child, $beta, $empty_term ], 'Term exclusion without inclusion' );
eew_check( eew_term_ids( array_merge( $all_terms, [ 'query_term_parent' => 0 ] ), $term_widget ) === [ $alpha, $beta, $empty_term ], 'Zero selects top-level terms' );
eew_check( eew_term_ids( array_merge( $all_terms, [ 'query_term_parent' => $alpha ] ), $term_widget ) === [ $child ], 'Parent selects direct children' );
eew_check( eew_term_ids( array_merge( $term_settings, [ 'query_term_orderby' => 'count', 'query_term_order' => 'DESC', 'query_per_page' => 1 ] ), $term_widget ) === [ $beta ], 'Terms sort by post count' );
eew_check( eew_term_ids( array_merge( $term_settings, [ 'query_taxonomy' => 'product_brand' ] ), $term_widget ) === [ $brand ], 'Brand taxonomy queried independently' );
eew_check( eew_term_ids( array_merge( $term_settings, [ 'query_taxonomy' => 'category', 'query_term_ids' => "$term_a,$term_b" ] ), $term_widget ) === [ $term_a, $term_b ], 'Blog categories queried independently' );
foreach ( [ '', 'missing_taxonomy', 'eew_secret', 'eew_hidden' ] as $invalid_taxonomy ) {
	eew_check( ! eew_term_ids( array_merge( $all_terms, [ 'query_taxonomy' => $invalid_taxonomy ] ), $term_widget ), 'Invalid or internal taxonomy produces no results' );
}
$bounded_args = \EEW\Carousel_Query::term_args( [ 'query_per_page' => 1000, 'query_offset' => -50, 'query_term_orderby' => 'invalid' ] );
eew_check( 100 === $bounded_args['number'] && 0 === $bounded_args['offset'] && 'name' === $bounded_args['orderby'], 'Term settings are bounded and validated' );

$term_filter = static function ( $args, $owner, $settings ) use ( $term_widget, $beta ) {
	eew_check( $owner === $term_widget && 'terms' === $settings['query_source'], 'Term args filter gets widget and resolved settings' );
	$args['include'] = [ $beta ];
	$args['fields'] = 'ids';
	$args['number'] = 0;
	return $args;
};
add_filter( 'eew/term_query_args', $term_filter, 10, 3 );
try {
	$filtered_terms = \EEW\Carousel_Query::run( $term_settings, $term_widget );
	eew_check( wp_list_pluck( $filtered_terms->terms, 'term_id' ) === [ $beta ], 'Term args filter customizes results while retaining objects' );
	eew_check( 1 === $filtered_terms->query_vars['number'], 'Term args filter cannot request an unbounded query' );
} finally {
	remove_filter( 'eew/term_query_args', $term_filter );
}

$image_id = wp_insert_attachment( [ 'post_title' => 'Category image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ] );
update_post_meta( $image_id, '_wp_attached_file', 'category-image.png' );
wp_update_attachment_metadata( $image_id, [ 'width' => 1, 'height' => 1, 'file' => 'category-image.png' ] );
update_term_meta( $alpha, 'thumbnail_id', $image_id );
$term_card = eew_test_render( eew_test_widget( $term_settings ) );
eew_check( false !== strpos( $term_card, 'Alpha products' ) && false !== strpos( $term_card, 'Alpha category description' ), 'Term cards render names and descriptions' );
eew_check( false !== strpos( $term_card, esc_url( get_term_link( $alpha, 'product_cat' ) ) ), 'Term cards link to their archives' );
$image_url = wp_get_attachment_image_url( $image_id, 'large' );
eew_check( $image_url && false !== strpos( $term_card, esc_url( $image_url ) ), 'Term cards use taxonomy thumbnail metadata' );
eew_check( false === strpos( $term_card, 'data-post-id=' ) && false !== strpos( $term_card, 'data-term-id="' . $alpha . '"' ), 'Term slides expose term IDs instead of post IDs' );
$empty_card = eew_test_render( eew_test_widget( array_merge( $term_settings, [ 'query_taxonomy' => '', 'query_empty_text' => 'No taxonomy terms' ] ) ) );
eew_check( false !== strpos( $empty_card, 'No taxonomy terms' ) && false === strpos( $empty_card, 'data-eew-config' ), 'Empty term queries show the configured message' );

$term_template = wp_insert_post( [ 'post_title' => 'Term Loop Item context test', 'post_type' => 'elementor_library', 'post_status' => 'publish' ] );
update_post_meta( $term_template, '_elementor_edit_mode', 'builder' );
update_post_meta( $term_template, '_elementor_template_type', 'loop-item' );
update_post_meta( $term_template, '_elementor_data', wp_slash( wp_json_encode( [ [ 'id' => 'termcontainer', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'termprobe', 'elType' => 'widget', 'widgetType' => 'eew-test-term-context', 'settings' => [], 'elements' => [] ] ] ] ] ) ) );
$term_loop_settings = array_merge( $term_settings, [ 'query_layout' => 'loop_item', 'loop_template_id' => $term_template ] );
$term_context = \EEW\Loop_Template::capture_context();
$main_query_vars = $GLOBALS['wp_the_query']->query_vars;
try {
	$term_loop = eew_test_render( eew_test_widget( $term_loop_settings ) );
	foreach ( [ $child, $alpha, $beta ] as $term_id ) {
		$term_object = get_term( $term_id );
		eew_check( false !== strpos( $term_loop, 'data-item-id="' . $term_id . '"' ) && false !== strpos( $term_loop, esc_html( $term_object->name ) ), 'Loop Item resolves each term archive ID and title' );
		eew_check( false !== strpos( $term_loop, esc_url( get_term_link( $term_object ) ) ), 'Loop Item resolves each term archive link' );
	}
	eew_check( false !== strpos( $term_loop, 'Alpha category description' ), 'Loop Item resolves the term archive description' );
	eew_check( $term_context === \EEW\Loop_Template::capture_context(), 'Outer context restored after term rendering' );
	eew_check( $main_query_vars === $GLOBALS['wp_the_query']->query_vars, 'Term rendering leaves the main query untouched' );
	foreach ( [ 'category', 'post_tag', 'product_brand' ] as $taxonomy_name ) {
		$probe_settings = array_merge( $term_loop_settings, [ 'query_taxonomy' => $taxonomy_name, 'query_term_ids' => '' ] );
		if ( 'post_tag' === $taxonomy_name ) {
			$tag_id = wp_insert_term( 'Carousel tag', 'post_tag' )['term_id'];
			$tagged_post = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Tagged article' ] );
			wp_set_post_terms( $tagged_post, [ $tag_id ], 'post_tag' );
		}
		eew_check( false !== strpos( eew_test_render( eew_test_widget( $probe_settings ) ), 'eew-test-term' ), 'Loop archive flags for ' . $taxonomy_name );
	}
	update_term_meta( $child, '_eew_throw', 'yes' );
	try {
		eew_test_render( eew_test_widget( $term_loop_settings ) );
		throw new Exception( 'Expected term render exception was not thrown' );
	} catch ( RuntimeException $exception ) {
		eew_check( 'Expected term render exception' === $exception->getMessage(), 'Expected template exception' );
		eew_check( $term_context === \EEW\Loop_Template::capture_context(), 'Outer context restored after a term template throws' );
	} finally {
		delete_term_meta( $child, '_eew_throw' );
	}
} finally {
	\EEW\Loop_Template::restore_context( $term_context );
}

$pages['terms'] = eew_page( 'Product category carousel', array_merge( $term_settings, [ 'slides_per_view' => 1, 'duration' => 0 ] ) );
$pages['term_loop'] = eew_page( 'Taxonomy Loop Item carousel', array_merge( $term_loop_settings, [ 'slides_per_view' => 1, 'duration' => 0 ] ) );
$query_test_result['terms'] = [ $child, $alpha, $beta ];
