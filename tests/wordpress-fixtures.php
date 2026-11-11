<?php
/** Test-only fixture plugin. Never included in the installable archive. */
add_action( 'init', static function () {
	register_post_type( 'eew_event', [ 'public' => true, 'label' => 'Events', 'supports' => [ 'title', 'editor', 'excerpt', 'thumbnail' ], 'taxonomies' => [ 'category' ] ] );
	register_taxonomy( 'product_cat', [ 'eew_event' ], [ 'public' => true, 'hierarchical' => true, 'label' => 'Product categories' ] );
	register_taxonomy( 'product_brand', [ 'eew_event' ], [ 'public' => true, 'label' => 'Brands' ] );
	register_taxonomy( 'eew_secret', [ 'eew_event' ], [ 'public' => false ] );
	register_taxonomy( 'eew_hidden', [ 'eew_event' ], [ 'public' => true, 'publicly_queryable' => false ] );
} );

add_action( 'elementor/query/eew_featured', static function ( $query ) {
	$query->set( 'meta_query', [ [ 'key' => '_eew_featured', 'value' => 'yes' ] ] );
} );

add_action( 'elementor/documents/register', static function ( $manager ) {
	if ( class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Documents\\Loop' ) ) {
		return;
	}
	// Exercise the public Loop Item document contract in Elementor Free when
	// a user-supplied Pro installation is unavailable. This is not Pro code.
	class EEW_Test_Loop_Document extends \Elementor\Modules\Library\Documents\Section {
		public static function get_type() { return 'loop-item'; }
		public function print_content() {
			echo '<div class="e-loop-item e-loop-item-' . esc_attr( get_the_ID() ) . '">';
			echo $this->get_content( true );
			echo '</div>';
		}
	}
	$manager->register_document_type( 'loop-item', EEW_Test_Loop_Document::class );
} );

add_action( 'elementor/widgets/register', static function ( $manager ) {
	class EEW_Test_Post_Context_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'eew-test-context'; }
		public function get_title() { return 'Post context probe'; }
		protected function is_dynamic_content(): bool { return true; }
		protected function render() {
			echo '<a class="eew-test-post" data-item-id="' . esc_attr( get_the_ID() ) . '" href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . ':' . esc_html( get_post_meta( get_the_ID(), '_eew_featured', true ) ) . '</a>';
		}
	}
	$manager->register( new EEW_Test_Post_Context_Widget() );
	class EEW_Test_Term_Context_Widget extends \Elementor\Widget_Base {
		public function get_name() { return 'eew-test-term-context'; }
		public function get_title() { return 'Term context probe'; }
		protected function is_dynamic_content(): bool { return true; }
		protected function render() {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term ) {
				throw new \RuntimeException( 'Expected a term archive context' );
			}
			if ( 'yes' === get_term_meta( $term->term_id, '_eew_throw', true ) ) {
				throw new \RuntimeException( 'Expected term render exception' );
			}
			if ( 'category' === $term->taxonomy ? ! is_category( $term->term_id ) : ( 'post_tag' === $term->taxonomy ? ! is_tag( $term->term_id ) : ! is_tax( $term->taxonomy, $term->term_id ) ) ) {
				throw new \RuntimeException( 'Incorrect term archive flags' );
			}
			echo '<a class="eew-test-term" data-item-id="' . esc_attr( get_queried_object_id() ) . '" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( single_term_title( '', false ) ) . ':' . esc_html( wp_strip_all_tags( get_the_archive_description() ) ) . '</a>';
		}
	}
	$manager->register( new EEW_Test_Term_Context_Widget() );
}, 20 );
