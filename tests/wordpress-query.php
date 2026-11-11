<?php
/** Included by wordpress-setup.php in a real temporary WordPress installation. */

function eew_test_widget( array $settings ) {
	static $counter = 0;
	return new \EEW\Carousel_Widget( [ 'id' => 'querytest' . ++$counter, 'elType' => 'widget', 'widgetType' => 'eew-carousel', 'settings' => $settings ], [] );
}

function eew_test_render( $widget ) {
	$buffer_level = ob_get_level();
	ob_start();
	try {
		$widget->render_content();
		return ob_get_contents();
	} finally {
		// Elementor can leave an inner output buffer open when a widget throws.
		while ( ob_get_level() > $buffer_level ) {
			ob_end_clean();
		}
	}
}

function eew_result_ids( $query ) { return wp_list_pluck( $query->posts, 'ID' ); }

$term_a = wp_insert_term( 'Carousel alpha', 'category' )['term_id'];
$term_b = wp_insert_term( 'Carousel beta', 'category' )['term_id'];
$events = [];
for ( $i = 1; $i <= 6; $i++ ) {
	$id = wp_insert_post( [ 'post_type' => 'eew_event', 'post_status' => 'publish', 'post_title' => 'Event ' . $i, 'post_content' => 'Event content ' . $i, 'post_excerpt' => 'Excerpt ' . $i ] );
	wp_set_post_terms( $id, [ $i % 2 ? $term_a : $term_b ], 'category' );
	update_post_meta( $id, '_eew_featured', $i % 2 ? 'yes' : 'no' );
	$events[] = $id;
}
$draft = wp_insert_post( [ 'post_type' => 'eew_event', 'post_status' => 'draft', 'post_title' => 'Hidden draft' ] );
$private = wp_insert_post( [ 'post_type' => 'eew_event', 'post_status' => 'private', 'post_title' => 'Hidden private' ] );
$protected = wp_insert_post( [ 'post_type' => 'eew_event', 'post_status' => 'publish', 'post_title' => 'Protected event', 'post_password' => 'secret' ] );

$query_settings = [ 'content_source' => 'query', 'query_post_type' => 'eew_event', 'query_per_page' => 3, 'query_orderby' => 'title', 'query_order' => 'ASC' ];
$widget = eew_test_widget( $query_settings );
eew_check( eew_result_ids( \EEW\Carousel_Query::run( $query_settings, $widget ) ) === array_slice( $events, 0, 3 ), 'Published custom post type results and ordering' );
$selection = array_merge( $query_settings, [ 'query_source' => 'selection', 'query_include_ids' => implode( ',', [ $events[4], $events[0], $events[2] ] ), 'query_exclude_ids' => (string) $events[0], 'query_orderby' => 'post__in' ] );
eew_check( eew_result_ids( \EEW\Carousel_Query::run( $selection, $widget ) ) === [ $events[4], $events[2] ], 'Manual ID order and exclusions both apply' );
eew_check( ! \EEW\Carousel_Query::run( array_merge( $query_settings, [ 'query_source' => 'selection' ] ), $widget )->posts, 'Empty ID selection returns no posts' );
$terms = array_merge( $query_settings, [ 'query_taxonomy' => 'category', 'query_term_ids' => (string) $term_a ] );
eew_check( eew_result_ids( \EEW\Carousel_Query::run( $terms, $widget ) ) === [ $events[0], $events[2], $events[4] ], 'Taxonomy filtering' );
$offset = array_merge( $query_settings, [ 'query_offset' => 2, 'query_per_page' => 2 ] );
eew_check( eew_result_ids( \EEW\Carousel_Query::run( $offset, $widget ) ) === [ $events[2], $events[3] ], 'Query offset' );

$context = \EEW\Loop_Template::capture_context();
$main = $GLOBALS['wp_the_query'];
try {
	$GLOBALS['post'] = get_post( $events[0] );
	$related = array_merge( $query_settings, [ 'query_source' => 'related', 'query_taxonomy' => 'category' ] );
	eew_check( eew_result_ids( \EEW\Carousel_Query::run( $related, $widget ) ) === [ $events[2], $events[4] ], 'Related posts use current terms and exclude current post' );
	$related['query_taxonomy'] = '';
	eew_check( ! \EEW\Carousel_Query::run( $related, $widget )->posts, 'No related terms never falls back to all posts' );
	$main_query = new WP_Query( [ 'post_type' => 'eew_event', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 2, 'paged' => 2, 'orderby' => 'title', 'order' => 'ASC' ] );
	$GLOBALS['wp_the_query'] = $main_query;
	$before_vars = $main_query->query_vars;
	eew_check( eew_result_ids( \EEW\Carousel_Query::run( [ 'query_source' => 'current' ], $widget ) ) === [ $events[2], $events[3] ], 'Main query filters and pagination inherited' );
	eew_check( $before_vars === $main_query->query_vars, 'Main query is not mutated' );
} finally {
	$GLOBALS['wp_the_query'] = $main;
	\EEW\Loop_Template::restore_context( $context );
}

$calls = 0;
$before_hook = has_filter( 'pre_get_posts' );
$callback = static function ( $query, $owner ) use ( &$calls, $widget, $events ) {
	$calls++;
	eew_check( $owner === $widget, 'Query ID receives the widget instance' );
	$query->set( 'post__in', [ $events[1], $events[3] ] );
	new WP_Query( [ 'post_type' => 'page', 'posts_per_page' => 1 ] );
};
add_action( 'elementor/query/eew_nested_query', $callback, 10, 2 );
try {
	$hooked = array_merge( $query_settings, [ 'query_id' => 'eew_nested_query' ] );
	eew_check( eew_result_ids( \EEW\Carousel_Query::run( $hooked, $widget ) ) === [ $events[1], $events[3] ], 'Query ID modifies the executed query' );
	eew_check( 1 === $calls, 'Nested unrelated queries do not invoke this Query ID' );
} finally {
	remove_action( 'elementor/query/eew_nested_query', $callback, 10 );
}
eew_check( has_filter( 'pre_get_posts' ) === $before_hook, 'Temporary query hook removed' );

$exception_hook = static function () { throw new RuntimeException( 'Expected test exception' ); };
add_action( 'elementor/query/eew_throw', $exception_hook );
try {
	\EEW\Carousel_Query::run( array_merge( $query_settings, [ 'query_id' => 'eew_throw' ] ), $widget );
	throw new Exception( 'Expected callback exception was not thrown' );
} catch ( RuntimeException $exception ) {
	eew_check( has_filter( 'pre_get_posts' ) === $before_hook, 'Query hook removed when a callback throws' );
} finally {
	remove_action( 'elementor/query/eew_throw', $exception_hook );
}

$status_hook = static function ( $query ) {
	$query->set( 'post_status', [ 'publish', 'private', 'draft' ] );
	$query->set( 'has_password', null );
	$query->set( 'posts_per_page', 100 );
};
add_action( 'elementor/query/eew_status', $status_hook );
try {
	$public_ids = eew_result_ids( \EEW\Carousel_Query::run( array_merge( $query_settings, [ 'query_id' => 'eew_status' ] ), $widget ) );
	eew_check( ! array_intersect( [ $draft, $private, $protected ], $public_ids ), 'Non-public and password-protected posts are not rendered' );
} finally {
	remove_action( 'elementor/query/eew_status', $status_hook );
}

$loop_template = wp_insert_post( [ 'post_title' => 'Loop Item context test', 'post_type' => 'elementor_library', 'post_status' => 'publish' ] );
update_post_meta( $loop_template, '_elementor_edit_mode', 'builder' );
update_post_meta( $loop_template, '_elementor_template_type', 'loop-item' );
update_post_meta( $loop_template, '_elementor_data', wp_slash( wp_json_encode( [ [ 'id' => 'loopcontainer', 'elType' => 'container', 'settings' => [], 'elements' => [ [ 'id' => 'contextprobe', 'elType' => 'widget', 'widgetType' => 'eew-test-context', 'settings' => [], 'elements' => [] ] ] ] ] ) ) );
eew_check( \EEW\Loop_Template::document( $loop_template ) !== null, 'Loop Item document resolves' );
eew_check( \EEW\Loop_Template::document( $template ) === null, 'Regular saved templates cannot be mistaken for Loop Items' );

$loop_settings = array_merge( $query_settings, [ 'query_layout' => 'loop_item', 'loop_template_id' => $loop_template ] );
$outer = \EEW\Loop_Template::capture_context();
$GLOBALS['product'] = (object) [ 'test' => 'outer-product' ];
$GLOBALS['pages'] = [ 'Outer page content' ];
$GLOBALS['post'] = get_post( $events[5] );
$expected_context = \EEW\Loop_Template::capture_context();
try {
	$content = eew_test_render( eew_test_widget( $loop_settings ) );
	foreach ( array_slice( $events, 0, 3 ) as $index => $result_post_id ) {
		eew_check( false !== strpos( $content, 'data-item-id="' . $result_post_id . '"' ), 'Loop Item gets each result post ID' );
		eew_check( false !== strpos( $content, 'Event ' . ( $index + 1 ) . ':' ), 'Loop Item gets each result title' );
		eew_check( false !== strpos( $content, esc_url( get_permalink( $result_post_id ) ) ), 'Loop Item gets each result permalink' );
	}
	$actual_context = \EEW\Loop_Template::capture_context();
	$changed = [];
	foreach ( $expected_context as $key => $value ) {
		if ( $value !== $actual_context[ $key ] ) {
			$changed[] = $key;
		}
	}
	eew_check( ! $changed, 'Nested post/query/product/page context fully restored; changed: ' . implode( ', ', $changed ) );
} finally {
	\EEW\Loop_Template::restore_context( $outer );
}

$empty = eew_test_render( eew_test_widget( array_merge( $query_settings, [ 'query_source' => 'selection', 'query_empty_text' => 'Empty query result' ] ) ) );
eew_check( false !== strpos( $empty, 'Empty query result' ) && false === strpos( $empty, 'data-eew-config' ), 'Empty queries have a message and no empty carousel' );

$pages['query'] = eew_page( 'Dynamic post carousel', array_merge( $query_settings, [ 'loop' => 'yes', 'slides_per_view' => 1, 'duration' => 0 ] ) );
$pages['query_hook'] = eew_page( 'Dynamic Query ID carousel', array_merge( $query_settings, [ 'query_id' => 'eew_featured' ] ) );
$pages['loop_item'] = eew_page( 'Loop Item carousel', array_merge( $loop_settings, [ 'loop' => 'yes', 'slides_per_view' => 1, 'duration' => 0 ] ) );
$query_test_result = [ 'events' => array_slice( $events, 0, 3 ), 'featured' => [ $events[0], $events[2], $events[4] ], 'pro' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null ];
require '/wordpress/wp-content/eew-term-tests.php';
