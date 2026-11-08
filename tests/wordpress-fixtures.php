<?php
/** Test-only fixture plugin. Never included in the installable archive. */
add_action( 'init', static function () {
	register_post_type( 'eew_event', [ 'public' => true, 'label' => 'Events', 'supports' => [ 'title', 'editor', 'excerpt', 'thumbnail' ], 'taxonomies' => [ 'category' ] ] );
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
}, 20 );
