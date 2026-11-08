<?php
namespace EEW;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Carousel_Widget extends Widget_Base {
	private static $template_stack = [];

	public function get_name() { return 'eew-carousel'; }
	public function get_title() { return __( 'Sobelz Embla Carousel', 'sobelz-embla-carousel-widget' ); }
	public function get_icon() { return 'eicon-slider-push'; }
	public function get_categories() { return [ 'general' ]; }
	public function get_keywords() { return [ 'sobelz', 'embla', 'carousel', 'slider', 'autoplay', 'اسلایدر', 'کروسل' ]; }
	public function get_style_depends() { return [ 'eew-carousel' ]; }
	protected function is_dynamic_content(): bool { return true; }

	public function get_script_depends() {
		// Elementor also asks the registered widget type for its dependencies,
		// before there is an element instance with settings (e.g. editor config).
		if ( $this->is_type_instance() ) {
			return [ 'eew-embla', 'eew-carousel' ];
		}
		$config  = Carousel_Settings::build( $this->get_settings_for_display() );
		$depends = [ 'eew-embla' ];
		foreach ( array_keys( (array) $config['plugins'] ) as $plugin ) {
			$depends[] = 'eew-' . $plugin;
		}
		$depends[] = 'eew-carousel';
		return $depends;
	}

	private function toggle( $name, $label, $default = false, $extra = [] ) {
		$this->add_control( $name, array_merge( [
			'label' => $label, 'type' => Controls_Manager::SWITCHER,
			'return_value' => 'yes', 'default' => $default ? 'yes' : '',
		], $extra ) );
	}

	private function number_control( $name, $label, $default, $min, $max, $extra = [] ) {
		$this->add_control( $name, array_merge( [
			'label' => $label, 'type' => Controls_Manager::NUMBER,
			'default' => $default, 'min' => $min, 'max' => $max,
		], $extra ) );
	}

	private function select_control( $name, $label, $options, $default, $extra = [] ) {
		$this->add_control( $name, array_merge( [
			'label' => $label, 'type' => Controls_Manager::SELECT,
			'options' => $options, 'default' => $default,
		], $extra ) );
	}

	protected function register_controls() {
		$this->slides_controls();
		$this->layout_controls();
		$this->behavior_controls();
		$this->plugin_controls();
		$this->navigation_controls();
		$this->style_controls();
	}

	private function slides_controls() {
		$this->start_controls_section( 'section_slides', [ 'label' => __( 'Slides', 'sobelz-embla-carousel-widget' ) ] );
		$this->select_control( 'content_source', __( 'Content source', 'sobelz-embla-carousel-widget' ), [
			'manual' => __( 'Manual slides', 'sobelz-embla-carousel-widget' ),
			'query' => __( 'Dynamic query', 'sobelz-embla-carousel-widget' ),
		], 'manual' );
		$this->select_control( 'query_layout', __( 'Query item layout', 'sobelz-embla-carousel-widget' ), [
			'card' => __( 'Built-in post card', 'sobelz-embla-carousel-widget' ),
			'loop_item' => __( 'Elementor Pro Loop Item', 'sobelz-embla-carousel-widget' ),
		], 'card', [ 'condition' => [ 'content_source' => 'query' ] ] );
		$loop_templates = [];
		foreach ( get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_elementor_template_type', 'meta_value' => 'loop-item' ] ) as $template ) {
			$loop_templates[ $template->ID ] = $template->post_title . ' (#' . $template->ID . ')';
		}
		$this->add_control( 'loop_template_id', [
			'label' => __( 'Loop Item template', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SELECT2,
			'options' => $loop_templates, 'label_block' => true,
			'condition' => [ 'content_source' => 'query', 'query_layout' => 'loop_item' ],
			'description' => __( 'Choose a published Loop Item from Templates → Theme Builder → Loop Item. Its dynamic tags use each query result as the current post. Requires Elementor Pro.', 'sobelz-embla-carousel-widget' ),
		] );
		$repeater = new Repeater();
		$repeater->add_control( 'content_type', [
			'label' => __( 'Content type', 'sobelz-embla-carousel-widget' ),
			'type' => Controls_Manager::SELECT, 'default' => 'card',
			'options' => [ 'card' => __( 'Image and text', 'sobelz-embla-carousel-widget' ), 'template' => __( 'Saved Elementor template', 'sobelz-embla-carousel-widget' ) ],
		] );
		$repeater->add_control( 'title', [
			'label' => __( 'Title', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT,
			'default' => __( 'Slide title', 'sobelz-embla-carousel-widget' ), 'dynamic' => [ 'active' => true ],
		] );
		$repeater->add_control( 'image', [
			'label' => __( 'Image', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::MEDIA,
			'dynamic' => [ 'active' => true ], 'condition' => [ 'content_type' => 'card' ],
		] );
		$repeater->add_control( 'description', [
			'label' => __( 'Description', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXTAREA,
			'default' => __( 'Add your slide content here.', 'sobelz-embla-carousel-widget' ),
			'dynamic' => [ 'active' => true ], 'condition' => [ 'content_type' => 'card' ],
		] );
		$repeater->add_control( 'button_text', [
			'label' => __( 'Button text', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT,
			'dynamic' => [ 'active' => true ], 'condition' => [ 'content_type' => 'card' ],
		] );
		$repeater->add_control( 'link', [
			'label' => __( 'Button link', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::URL,
			'dynamic' => [ 'active' => true ], 'condition' => [ 'content_type' => 'card' ],
		] );
		$templates = [];
		foreach ( get_posts( [ 'post_type' => 'elementor_library', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ] ) as $template ) {
			$templates[ $template->ID ] = $template->post_title . ' (#' . $template->ID . ')';
		}
		$repeater->add_control( 'template_id', [
			'label' => __( 'Template', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SELECT2,
			'options' => $templates, 'label_block' => true, 'condition' => [ 'content_type' => 'template' ],
			'description' => __( 'Select a published template from Elementor → Templates. The slide title is used as its accessible label.', 'sobelz-embla-carousel-widget' ),
		] );
		$this->add_control( 'slides', [
			'label' => __( 'Slides', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::REPEATER,
			'condition' => [ 'content_source' => 'manual' ],
			'fields' => $repeater->get_controls(), 'title_field' => '{{{ title }}}',
			'default' => [
				[ 'content_type' => 'card', 'title' => __( 'Slide one', 'sobelz-embla-carousel-widget' ), 'description' => __( 'Add your slide content here.', 'sobelz-embla-carousel-widget' ) ],
				[ 'content_type' => 'card', 'title' => __( 'Slide two', 'sobelz-embla-carousel-widget' ), 'description' => __( 'Add your slide content here.', 'sobelz-embla-carousel-widget' ) ],
				[ 'content_type' => 'card', 'title' => __( 'Slide three', 'sobelz-embla-carousel-widget' ), 'description' => __( 'Add your slide content here.', 'sobelz-embla-carousel-widget' ) ],
			],
		] );
		$this->select_control( 'image_size', __( 'Image resolution', 'sobelz-embla-carousel-widget' ), [
			'thumbnail' => __( 'Thumbnail', 'sobelz-embla-carousel-widget' ), 'medium' => __( 'Medium', 'sobelz-embla-carousel-widget' ),
			'medium_large' => __( 'Medium large', 'sobelz-embla-carousel-widget' ), 'large' => __( 'Large', 'sobelz-embla-carousel-widget' ), 'full' => __( 'Full', 'sobelz-embla-carousel-widget' ),
		], 'large' );
		$this->select_control( 'title_tag', __( 'Title HTML tag', 'sobelz-embla-carousel-widget' ), array_combine( [ 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ], [ 'H2', 'H3', 'H4', 'H5', 'H6', 'div' ] ), 'h3' );
		$this->end_controls_section();
		$this->query_controls();
	}

	private function query_controls() {
		$this->start_controls_section( 'section_query', [ 'label' => __( 'Query', 'sobelz-embla-carousel-widget' ), 'condition' => [ 'content_source' => 'query' ] ] );
		$this->select_control( 'query_source', __( 'Query source', 'sobelz-embla-carousel-widget' ), [
			'posts' => __( 'Posts / custom post type', 'sobelz-embla-carousel-widget' ),
			'current' => __( 'Current archive / main query', 'sobelz-embla-carousel-widget' ),
			'related' => __( 'Related posts', 'sobelz-embla-carousel-widget' ),
			'selection' => __( 'Select posts by ID', 'sobelz-embla-carousel-widget' ),
		], 'posts', [ 'description' => __( 'Current query follows the main page or archive, including its filters, order and pagination. The other sources use the settings below.', 'sobelz-embla-carousel-widget' ) ] );
		$types = [];
		foreach ( Carousel_Query::post_types() as $name => $type ) {
			$types[ $name ] = $type->labels->name . ' (' . $name . ')';
		}
		$this->select_control( 'query_post_type', __( 'Post type', 'sobelz-embla-carousel-widget' ), $types, 'post', [ 'condition' => [ 'query_source!' => 'current' ] ] );
		$this->number_control( 'query_per_page', __( 'Items to load', 'sobelz-embla-carousel-widget' ), 6, 1, Carousel_Query::MAX_ITEMS, [ 'condition' => [ 'query_source!' => 'current' ], 'dynamic' => [ 'active' => true ] ] );
		$this->number_control( 'query_offset', __( 'Offset', 'sobelz-embla-carousel-widget' ), 0, 0, 10000, [ 'condition' => [ 'query_source!' => 'current' ] ] );
		$this->select_control( 'query_orderby', __( 'Order by', 'sobelz-embla-carousel-widget' ), [
			'date' => __( 'Publish date', 'sobelz-embla-carousel-widget' ), 'modified' => __( 'Last modified', 'sobelz-embla-carousel-widget' ),
			'title' => __( 'Title', 'sobelz-embla-carousel-widget' ), 'menu_order' => __( 'Menu order', 'sobelz-embla-carousel-widget' ),
			'comment_count' => __( 'Comment count', 'sobelz-embla-carousel-widget' ), 'rand' => __( 'Random', 'sobelz-embla-carousel-widget' ),
			'post__in' => __( 'Included ID order', 'sobelz-embla-carousel-widget' ),
		], 'date', [ 'condition' => [ 'query_source!' => 'current' ] ] );
		$this->select_control( 'query_order', __( 'Order', 'sobelz-embla-carousel-widget' ), [ 'DESC' => __( 'Descending', 'sobelz-embla-carousel-widget' ), 'ASC' => __( 'Ascending', 'sobelz-embla-carousel-widget' ) ], 'DESC', [ 'condition' => [ 'query_source!' => 'current' ] ] );
		$taxonomies = [ '' => __( 'None', 'sobelz-embla-carousel-widget' ) ];
		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $name => $taxonomy ) {
			$taxonomies[ $name ] = $taxonomy->labels->name . ' (' . $name . ')';
		}
		$this->select_control( 'query_taxonomy', __( 'Taxonomy', 'sobelz-embla-carousel-widget' ), $taxonomies, 'category', [
			'condition' => [ 'query_source!' => 'current' ],
			'description' => __( 'Used for term filtering, or to find items sharing terms with the current post in Related mode.', 'sobelz-embla-carousel-widget' ),
		] );
		foreach ( [
			'query_term_ids' => [ __( 'Include term IDs', 'sobelz-embla-carousel-widget' ), [ 'query_source' => [ 'posts', 'selection' ], 'query_taxonomy!' => '' ] ],
			'query_include_ids' => [ __( 'Include post IDs', 'sobelz-embla-carousel-widget' ), [ 'query_source!' => 'current' ] ],
			'query_exclude_ids' => [ __( 'Exclude post IDs', 'sobelz-embla-carousel-widget' ), [ 'query_source!' => 'current' ] ],
			'query_authors' => [ __( 'Author IDs', 'sobelz-embla-carousel-widget' ), [ 'query_source!' => 'current' ] ],
		] as $key => $control ) {
			$this->add_control( $key, [ 'label' => $control[0], 'type' => Controls_Manager::TEXT, 'dynamic' => [ 'active' => true ], 'condition' => $control[1], 'description' => __( 'Comma-separated IDs, for example: 12, 34, 56.', 'sobelz-embla-carousel-widget' ) ] );
		}
		$this->toggle( 'query_exclude_current', __( 'Exclude current post', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'query_source' => [ 'posts', 'selection' ] ] ] );
		$this->add_control( 'query_id', [
			'label' => __( 'Query ID', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT,
			'placeholder' => 'my_embla_query', 'description' => __( 'Optional. Uses the elementor/query/QUERY_ID hook to customize WP_Query, including meta queries and relationship fields.', 'sobelz-embla-carousel-widget' ),
		] );
		$this->number_control( 'query_excerpt_length', __( 'Excerpt length (words)', 'sobelz-embla-carousel-widget' ), 24, 0, 100, [ 'condition' => [ 'query_layout' => 'card' ] ] );
		$this->add_control( 'query_button_text', [ 'label' => __( 'Button text', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Read more', 'sobelz-embla-carousel-widget' ), 'condition' => [ 'query_layout' => 'card' ] ] );
		$this->add_control( 'query_empty_text', [ 'label' => __( 'No results message', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'No items found.', 'sobelz-embla-carousel-widget' ) ] );
		$this->end_controls_section();
	}

	private function layout_controls() {
		$this->start_controls_section( 'section_layout', [ 'label' => __( 'Layout', 'sobelz-embla-carousel-widget' ) ] );
		$this->select_control( 'axis', __( 'Axis', 'sobelz-embla-carousel-widget' ), [ 'x' => __( 'Horizontal', 'sobelz-embla-carousel-widget' ), 'y' => __( 'Vertical', 'sobelz-embla-carousel-widget' ) ], 'x' );
		$this->select_control( 'direction', __( 'Text direction', 'sobelz-embla-carousel-widget' ), [ 'auto' => __( 'Site default', 'sobelz-embla-carousel-widget' ), 'ltr' => 'LTR', 'rtl' => 'RTL' ], 'auto' );
		$this->select_control( 'effect', __( 'Transition effect', 'sobelz-embla-carousel-widget' ), [ 'scroll' => __( 'Scroll', 'sobelz-embla-carousel-widget' ), 'fade' => __( 'Fade', 'sobelz-embla-carousel-widget' ) ], 'scroll', [
			'description' => __( 'Fade shows one slide at a time and cannot be combined with continuous Auto Scroll.', 'sobelz-embla-carousel-widget' ),
		] );
		$this->add_responsive_control( 'slides_per_view', [
			'label' => __( 'Slides per view', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::NUMBER,
			'min' => 1, 'max' => 12, 'step' => 0.1, 'default' => 3, 'tablet_default' => 2, 'mobile_default' => 1,
			'condition' => [ 'effect' => 'scroll' ],
			'selectors' => [ '{{WRAPPER}} .eew' => '--eew-per-view: {{VALUE}};' ],
		] );
		$this->add_responsive_control( 'gap', [
			'label' => __( 'Slide gap', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'default' => [ 'size' => 20, 'unit' => 'px' ],
			'condition' => [ 'effect' => 'scroll' ], 'selectors' => [ '{{WRAPPER}} .eew' => '--eew-gap: {{SIZE}}{{UNIT}};' ],
		] );
		$this->add_responsive_control( 'loop_gap', [
			'label' => __( 'Last-to-first loop gap', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 200 ] ],
			'condition' => [ 'effect' => 'scroll', 'loop' => 'yes' ],
			'description' => __( 'Leave empty to use the slide gap. Set a value to customize the seam between the last and first slides, including in RTL and vertical layouts.', 'sobelz-embla-carousel-widget' ),
			'selectors' => [ '{{WRAPPER}} .eew' => '--eew-loop-gap: {{SIZE}}{{UNIT}};' ],
		] );
		$this->add_responsive_control( 'vertical_height', [
			'label' => __( 'Vertical viewport height', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER,
			'size_units' => [ 'px', 'vh' ], 'range' => [ 'px' => [ 'min' => 100, 'max' => 1600 ], 'vh' => [ 'min' => 10, 'max' => 100 ] ],
			'default' => [ 'size' => 600, 'unit' => 'px' ], 'condition' => [ 'axis' => 'y' ],
			'selectors' => [ '{{WRAPPER}} .eew' => '--eew-height: {{SIZE}}{{UNIT}};' ],
		] );
		$this->select_control( 'align', __( 'Snap alignment', 'sobelz-embla-carousel-widget' ), [ 'start' => __( 'Start', 'sobelz-embla-carousel-widget' ), 'center' => __( 'Center', 'sobelz-embla-carousel-widget' ), 'end' => __( 'End', 'sobelz-embla-carousel-widget' ) ], 'start', [ 'condition' => [ 'effect' => 'scroll' ] ] );
		$this->select_control( 'contain_scroll', __( 'Scroll containment', 'sobelz-embla-carousel-widget' ), [ 'trimSnaps' => 'trimSnaps', 'keepSnaps' => 'keepSnaps', 'none' => __( 'None', 'sobelz-embla-carousel-widget' ) ], 'trimSnaps', [ 'condition' => [ 'effect' => 'scroll' ] ] );
		$this->end_controls_section();
	}

	private function behavior_controls() {
		$this->start_controls_section( 'section_behavior', [ 'label' => __( 'Carousel behavior', 'sobelz-embla-carousel-widget' ) ] );
		$this->toggle( 'loop', __( 'Infinite loop', 'sobelz-embla-carousel-widget' ), false, [ 'description' => __( 'Embla disables looping automatically when there are too few slides to loop without gaps.', 'sobelz-embla-carousel-widget' ) ] );
		$this->toggle( 'draggable', __( 'Mouse and touch dragging', 'sobelz-embla-carousel-widget' ), true );
		$this->toggle( 'drag_free', __( 'Free dragging', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'effect' => 'scroll', 'draggable' => 'yes' ] ] );
		$this->toggle( 'skip_snaps', __( 'Skip snaps on vigorous dragging', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'draggable' => 'yes', 'drag_free!' => 'yes' ] ] );
		$this->number_control( 'drag_threshold', __( 'Mouse drag threshold (px)', 'sobelz-embla-carousel-widget' ), 10, 0, 100, [ 'condition' => [ 'draggable' => 'yes' ] ] );
		$this->select_control( 'group_mode', __( 'Slides per scroll', 'sobelz-embla-carousel-widget' ), [ 'number' => __( 'Fixed number', 'sobelz-embla-carousel-widget' ), 'auto' => __( 'Auto (visible group)', 'sobelz-embla-carousel-widget' ) ], 'number', [ 'condition' => [ 'effect' => 'scroll' ] ] );
		$this->number_control( 'slides_to_scroll', __( 'Scroll group size', 'sobelz-embla-carousel-widget' ), 1, 1, 20, [ 'condition' => [ 'group_mode' => 'number', 'effect' => 'scroll' ] ] );
		$this->number_control( 'start_slide', __( 'Initial slide (starts at 1)', 'sobelz-embla-carousel-widget' ), 1, 1, 10000 );
		$this->number_control( 'duration', __( 'Transition duration', 'sobelz-embla-carousel-widget' ), 25, 0, 100, [ 'description' => __( 'Embla physics duration, not milliseconds. Recommended: 20–60. Zero makes transitions instant.', 'sobelz-embla-carousel-widget' ) ] );
		$this->number_control( 'in_view_threshold', __( 'In-view threshold', 'sobelz-embla-carousel-widget' ), 0, 0, 1, [ 'step' => 0.05, 'condition' => [ 'effect' => 'scroll' ] ] );
		$this->toggle( 'reduced_motion', __( 'Respect reduced motion', 'sobelz-embla-carousel-widget' ), true, [ 'description' => __( 'Disable automatic motion and use instant navigation when the visitor requests reduced motion.', 'sobelz-embla-carousel-widget' ) ] );
		$this->end_controls_section();
	}

	private function plugin_controls() {
		$this->start_controls_section( 'section_plugins', [ 'label' => __( 'Embla plugins', 'sobelz-embla-carousel-widget' ) ] );
		$this->add_control( 'local_assets_note', [ 'type' => Controls_Manager::RAW_HTML, 'raw' => esc_html__( 'All scripts are bundled locally. Only enabled plugins are loaded and registered with Embla.', 'sobelz-embla-carousel-widget' ), 'content_classes' => 'elementor-panel-alert elementor-panel-alert-info' ] );
		$this->select_control( 'motion', __( 'Automatic movement', 'sobelz-embla-carousel-widget' ), [
			'none' => __( 'None', 'sobelz-embla-carousel-widget' ), 'autoplay' => __( 'Autoplay (slide by slide)', 'sobelz-embla-carousel-widget' ), 'auto_scroll' => __( 'Auto Scroll (continuous)', 'sobelz-embla-carousel-widget' ),
		], 'none' );
		$this->number_control( 'autoplay_delay', __( 'Autoplay delay (ms)', 'sobelz-embla-carousel-widget' ), 4000, 500, 120000, [ 'step' => 100, 'condition' => [ 'motion' => 'autoplay' ] ] );
		$this->toggle( 'autoplay_jump', __( 'Instant autoplay transitions', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'motion' => 'autoplay' ] ] );
		$this->toggle( 'stop_on_last_snap', __( 'Stop on last slide group', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'motion' => 'autoplay' ] ] );
		$this->number_control( 'auto_scroll_speed', __( 'Auto Scroll speed (px/frame)', 'sobelz-embla-carousel-widget' ), 2, 0.1, 20, [ 'step' => 0.1, 'condition' => [ 'motion' => 'auto_scroll', 'effect' => 'scroll' ] ] );
		$this->number_control( 'auto_scroll_delay', __( 'Auto Scroll start delay (ms)', 'sobelz-embla-carousel-widget' ), 1000, 0, 120000, [ 'condition' => [ 'motion' => 'auto_scroll', 'effect' => 'scroll' ] ] );
		$this->select_control( 'auto_scroll_direction', __( 'Auto Scroll direction', 'sobelz-embla-carousel-widget' ), [ 'forward' => __( 'Forward', 'sobelz-embla-carousel-widget' ), 'backward' => __( 'Backward', 'sobelz-embla-carousel-widget' ) ], 'forward', [ 'condition' => [ 'motion' => 'auto_scroll', 'effect' => 'scroll' ] ] );
		$this->toggle( 'pause_on_hover', __( 'Pause on hover', 'sobelz-embla-carousel-widget' ), true, [ 'condition' => [ 'motion!' => 'none' ] ] );
		$this->toggle( 'stop_on_interaction', __( 'Stop after interaction', 'sobelz-embla-carousel-widget' ), true, [ 'condition' => [ 'motion!' => 'none' ], 'description' => __( 'When disabled, movement resumes after dragging, hovering or leaving keyboard focus.', 'sobelz-embla-carousel-widget' ) ] );
		$this->toggle( 'auto_height', __( 'Auto Height', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'axis' => 'x' ] ] );
		$this->toggle( 'class_names', __( 'Class Names', 'sobelz-embla-carousel-widget' ), false, [ 'description' => __( 'Adds Embla state classes such as is-snapped, is-in-view and is-dragging for custom styling.', 'sobelz-embla-carousel-widget' ) ] );
		$this->toggle( 'wheel_gestures', __( 'Wheel Gestures', 'sobelz-embla-carousel-widget' ), false, [ 'condition' => [ 'draggable' => 'yes' ] ] );
		$this->select_control( 'wheel_axis', __( 'Wheel input axis', 'sobelz-embla-carousel-widget' ), [ 'auto' => __( 'Match carousel axis', 'sobelz-embla-carousel-widget' ), 'x' => __( 'Horizontal wheel', 'sobelz-embla-carousel-widget' ), 'y' => __( 'Vertical wheel', 'sobelz-embla-carousel-widget' ) ], 'auto', [ 'condition' => [ 'wheel_gestures' => 'yes', 'draggable' => 'yes' ] ] );
		$this->end_controls_section();
	}

	private function navigation_controls() {
		$this->start_controls_section( 'section_navigation', [ 'label' => __( 'Navigation and accessibility', 'sobelz-embla-carousel-widget' ) ] );
		$this->toggle( 'show_arrows', __( 'Show arrows', 'sobelz-embla-carousel-widget' ), true );
		$this->toggle( 'show_dots', __( 'Show dots', 'sobelz-embla-carousel-widget' ), true );
		$this->toggle( 'show_progress', __( 'Show scroll progress', 'sobelz-embla-carousel-widget' ) );
		$this->toggle( 'show_play_pause', __( 'Show play / pause', 'sobelz-embla-carousel-widget' ), true, [ 'condition' => [ 'motion!' => 'none' ] ] );
		$this->toggle( 'keyboard', __( 'Arrow key navigation', 'sobelz-embla-carousel-widget' ), true );
		$this->add_control( 'accessible_label', [ 'label' => __( 'Carousel accessible label', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Featured slides', 'sobelz-embla-carousel-widget' ) ] );
		$this->end_controls_section();
	}

	private function style_controls() {
		$this->start_controls_section( 'section_card_style', [ 'label' => __( 'Slide card', 'sobelz-embla-carousel-widget' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( Group_Control_Background::get_type(), [ 'name' => 'card_background', 'types' => [ 'classic', 'gradient' ], 'selector' => '{{WRAPPER}} .eew__card' ] );
		$this->add_group_control( Group_Control_Border::get_type(), [ 'name' => 'card_border', 'selector' => '{{WRAPPER}} .eew__card' ] );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .eew__card' ] );
		$this->add_responsive_control( 'card_radius', [ 'label' => __( 'Border radius', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .eew__card' => 'border-radius: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'content_padding', [ 'label' => __( 'Content padding', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'selectors' => [ '{{WRAPPER}} .eew__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->select_control( 'text_align', __( 'Text alignment', 'sobelz-embla-carousel-widget' ), [ 'start' => __( 'Start', 'sobelz-embla-carousel-widget' ), 'center' => __( 'Center', 'sobelz-embla-carousel-widget' ), 'end' => __( 'End', 'sobelz-embla-carousel-widget' ) ], 'start', [ 'selectors' => [ '{{WRAPPER}} .eew__body' => 'text-align: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_image_style', [ 'label' => __( 'Image', 'sobelz-embla-carousel-widget' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'image_height', [ 'label' => __( 'Image height', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER, 'size_units' => [ 'px', 'vh' ], 'range' => [ 'px' => [ 'min' => 40, 'max' => 1000 ] ], 'selectors' => [ '{{WRAPPER}} .eew' => '--eew-image-height: {{SIZE}}{{UNIT}};' ] ] );
		$this->select_control( 'image_fit', __( 'Image fit', 'sobelz-embla-carousel-widget' ), [ 'cover' => 'Cover', 'contain' => 'Contain', 'fill' => 'Fill' ], 'cover', [ 'selectors' => [ '{{WRAPPER}} .eew__image' => 'object-fit: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_text_style', [ 'label' => __( 'Text and button', 'sobelz-embla-carousel-widget' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		foreach ( [ 'title' => __( 'Title', 'sobelz-embla-carousel-widget' ), 'description' => __( 'Description', 'sobelz-embla-carousel-widget' ), 'button' => __( 'Button', 'sobelz-embla-carousel-widget' ) ] as $part => $label ) {
			$this->add_control( $part . '_heading', [ 'type' => Controls_Manager::HEADING, 'label' => $label, 'separator' => 'before' ] );
			$this->add_control( $part . '_color', [ 'label' => __( 'Text color', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .eew__' . $part => 'color: {{VALUE}};' ] ] );
			$this->add_group_control( Group_Control_Typography::get_type(), [ 'name' => $part . '_typography', 'selector' => '{{WRAPPER}} .eew__' . $part ] );
		}
		$this->add_control( 'button_background', [ 'label' => __( 'Button background', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .eew__button' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'button_hover_background', [ 'label' => __( 'Button hover background', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .eew__button:hover, {{WRAPPER}} .eew__button:focus-visible' => 'background-color: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_navigation_style', [ 'label' => __( 'Navigation', 'sobelz-embla-carousel-widget' ), 'tab' => Controls_Manager::TAB_STYLE ] );
		foreach ( [ 'nav_color' => [ __( 'Arrow color', 'sobelz-embla-carousel-widget' ), '--eew-nav-color' ], 'nav_background' => [ __( 'Arrow background', 'sobelz-embla-carousel-widget' ), '--eew-nav-bg' ], 'dot_color' => [ __( 'Dot color', 'sobelz-embla-carousel-widget' ), '--eew-dot-color' ], 'active_color' => [ __( 'Active dot / progress color', 'sobelz-embla-carousel-widget' ), '--eew-active-color' ] ] as $name => $definition ) {
			$this->add_control( $name, [ 'label' => $definition[0], 'type' => Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .eew' => $definition[1] . ': {{VALUE}};' ] ] );
		}
		$this->add_responsive_control( 'arrow_size', [ 'label' => __( 'Arrow button size', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 24, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .eew' => '--eew-arrow-size: {{SIZE}}px;' ] ] );
		$this->add_responsive_control( 'dot_size', [ 'label' => __( 'Dot size', 'sobelz-embla-carousel-widget' ), 'type' => Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 4, 'max' => 30 ] ], 'selectors' => [ '{{WRAPPER}} .eew' => '--eew-dot-size: {{SIZE}}px;' ] ] );
		$this->end_controls_section();
	}

	protected function render() {
		$context = Loop_Template::capture_context();
		try {
			$this->render_carousel();
		} finally {
			Loop_Template::restore_context( $context );
		}
	}

	private function render_carousel() {
		$settings = $this->get_settings_for_display();
		$query = null;
		$document = null;
		if ( 'query' === ( $settings['content_source'] ?? 'manual' ) ) {
			if ( 'loop_item' === ( $settings['query_layout'] ?? 'card' ) ) {
				$document = Loop_Template::document( absint( $settings['loop_template_id'] ?? 0 ) );
				if ( ! $document ) {
					if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
						echo '<p class="eew__template-notice">' . esc_html__( 'Activate Elementor Pro and choose a published Loop Item template. Circular template references are not allowed.', 'sobelz-embla-carousel-widget' ) . '</p>';
					}
					return;
				}
			}
			$query = Carousel_Query::run( $settings, $this );
			$slides = $query->posts;
		} else {
			$slides = $settings['slides'] ?? [];
		}
		if ( ! $slides ) {
			if ( $query && ! empty( $settings['query_empty_text'] ) ) {
				echo '<p class="eew__empty">' . esc_html( $settings['query_empty_text'] ) . '</p>';
			}
			return;
		}
		$config     = Carousel_Settings::build( $settings );
		$plugins    = (array) $config['plugins'];
		$classes    = [ 'eew', 'eew--' . $config['options']['axis'] ];
		$classes[]  = isset( $plugins['fade'] ) ? 'eew--fade' : 'eew--scroll';
		if ( $config['options']['loop'] && ! isset( $plugins['fade'] ) ) {
			$classes[] = 'eew--loop';
		}
		if ( isset( $plugins['autoHeight'] ) ) {
			$classes[] = 'eew--auto-height';
		}
		$viewport_id = wp_unique_id( 'eew-viewport-' . $this->get_id() . '-' );
		$this->add_render_attribute( 'carousel', [
			'class' => $classes, 'dir' => $config['options']['direction'], 'role' => 'region',
			'aria-roledescription' => __( 'carousel', 'sobelz-embla-carousel-widget' ),
			'aria-label' => ( $settings['accessible_label'] ?? '' ) ?: __( 'Featured slides', 'sobelz-embla-carousel-widget' ),
			'data-eew-config' => wp_json_encode( $config ),
		] );
		?>
		<div <?php $this->print_render_attribute_string( 'carousel' ); ?>>
			<div class="eew__viewport" id="<?php echo esc_attr( $viewport_id ); ?>" tabindex="0">
				<div class="eew__container">
					<?php $this->render_items( $slides, $settings, $query, $document ); ?>
				</div>
			</div>
			<div class="eew__controls" hidden>
				<?php if ( 'yes' === ( $settings['show_arrows'] ?? 'yes' ) ) : ?>
					<div class="eew__arrows">
						<button class="eew__arrow eew__prev" type="button" aria-label="<?php esc_attr_e( 'Previous slide group', 'sobelz-embla-carousel-widget' ); ?>" aria-controls="<?php echo esc_attr( $viewport_id ); ?>"><?php $this->arrow_icon(); ?></button>
						<button class="eew__arrow eew__next" type="button" aria-label="<?php esc_attr_e( 'Next slide group', 'sobelz-embla-carousel-widget' ); ?>" aria-controls="<?php echo esc_attr( $viewport_id ); ?>"><?php $this->arrow_icon(); ?></button>
					</div>
				<?php endif; ?>
				<?php if ( 'yes' === ( $settings['show_dots'] ?? 'yes' ) ) : ?>
					<div class="eew__dots" role="group" aria-label="<?php esc_attr_e( 'Choose slide group', 'sobelz-embla-carousel-widget' ); ?>"></div>
				<?php endif; ?>
				<?php if ( ( isset( $plugins['autoplay'] ) || isset( $plugins['autoScroll'] ) ) && 'yes' === ( $settings['show_play_pause'] ?? 'yes' ) ) : ?>
					<button class="eew__toggle" type="button" aria-controls="<?php echo esc_attr( $viewport_id ); ?>"></button>
				<?php endif; ?>
			</div>
			<?php if ( 'yes' === ( $settings['show_progress'] ?? '' ) ) : ?>
				<div class="eew__progress" role="progressbar" aria-label="<?php esc_attr_e( 'Scroll progress', 'sobelz-embla-carousel-widget' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><div class="eew__progress-bar"></div></div>
			<?php endif; ?>
			<p class="eew__status eew__sr-only" aria-live="polite" aria-atomic="true"></p>
		</div>
		<?php
	}

	private function render_items( $items, $settings, $query, $document ) {
		$context = $query ? Loop_Template::capture_context() : null;
		if ( $query ) {
			$GLOBALS['wp_query'] = $query;
		}
		try {
			foreach ( $items as $index => $item ) {
				$post_id = 0;
				if ( $query ) {
					$query->the_post();
					$post_id = get_the_ID();
					$item = [
						'_id' => 'post-' . $post_id, 'content_type' => 'card', 'title' => get_the_title(),
					];
					if ( ! $document ) {
						$length = max( 0, min( 100, (int) ( $settings['query_excerpt_length'] ?? 24 ) ) );
						$item['image'] = [ 'id' => get_post_thumbnail_id() ];
						$item['description'] = $length ? wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), $length ) : '';
						$item['button_text'] = $settings['query_button_text'] ?? __( 'Read more', 'sobelz-embla-carousel-widget' );
						$item['link'] = [ 'url' => get_permalink() ];
					}
				}
				$label = sprintf( __( '%1$d of %2$d', 'sobelz-embla-carousel-widget' ), $index + 1, count( $items ) );
				$label .= empty( $item['title'] ) ? '' : ': ' . wp_strip_all_tags( $item['title'] );
				?>
				<div class="eew__slide elementor-repeater-item-<?php echo esc_attr( $item['_id'] ?? (string) $index ); ?>" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'sobelz-embla-carousel-widget' ); ?>" aria-label="<?php echo esc_attr( $label ); ?>"<?php if ( $post_id ) : ?> data-post-id="<?php echo esc_attr( $post_id ); ?>"<?php endif; ?>>
					<?php
					if ( $document ) {
						Loop_Template::render( $document );
					} else {
						$this->render_slide( $item, $settings, $index );
					}
					?>
				</div>
				<?php
			}
		} finally {
			if ( $context ) {
				Loop_Template::restore_context( $context );
			}
		}
	}

	private function render_slide( $slide, $settings, $index ) {
		if ( 'template' === ( $slide['content_type'] ?? 'card' ) ) {
			$this->render_template( absint( $slide['template_id'] ?? 0 ) );
			return;
		}
		$tag = in_array( $settings['title_tag'] ?? '', [ 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ], true ) ? $settings['title_tag'] : 'h3';
		?>
		<article class="eew__card">
			<?php if ( ! empty( $slide['image']['id'] ) ) : ?>
				<?php echo wp_get_attachment_image( absint( $slide['image']['id'] ), $settings['image_size'] ?? 'large', false, [ 'class' => 'eew__image', 'loading' => 0 === $index ? 'eager' : 'lazy', 'draggable' => 'false' ] ); ?>
			<?php elseif ( ! empty( $slide['image']['url'] ) ) : ?>
				<img class="eew__image" src="<?php echo esc_url( $slide['image']['url'] ); ?>" alt="" loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>" draggable="false">
			<?php else : ?>
				<div class="eew__placeholder" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></div>
			<?php endif; ?>
			<div class="eew__body">
				<?php if ( ! empty( $slide['title'] ) ) : ?>
					<<?php echo $tag; ?> class="eew__title"><?php echo esc_html( $slide['title'] ); ?></<?php echo $tag; ?>>
				<?php endif; ?>
				<?php if ( ! empty( $slide['description'] ) ) : ?>
					<div class="eew__description"><?php echo wpautop( esc_html( $slide['description'] ) ); ?></div>
				<?php endif; ?>
				<?php if ( ! empty( $slide['button_text'] ) && ! empty( $slide['link']['url'] ) ) : ?>
					<?php $this->add_link_attributes( 'slide-link-' . $index, $slide['link'] ); ?>
					<a class="eew__button" <?php $this->print_render_attribute_string( 'slide-link-' . $index ); ?>><?php echo esc_html( $slide['button_text'] ); ?></a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private function render_template( $template_id ) {
		$post = $template_id ? get_post( $template_id ) : null;
		if ( ! $post || 'elementor_library' !== $post->post_type || 'publish' !== $post->post_status || in_array( $template_id, self::$template_stack, true ) || count( self::$template_stack ) >= 3 ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p class="eew__template-notice">' . esc_html__( 'Select a published Elementor template. Circular template references are not allowed.', 'sobelz-embla-carousel-widget' ) . '</p>';
			}
			return;
		}
		self::$template_stack[] = $template_id;
		try {
			// Elementor renders and sanitizes its own saved document, including its CSS.
			echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $template_id, true );
		} finally {
			array_pop( self::$template_stack );
		}
	}

	private function arrow_icon() {
		echo '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="m14 6-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}
}
