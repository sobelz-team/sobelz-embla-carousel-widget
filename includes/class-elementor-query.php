<?php
namespace EEW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adapt saved Embla controls to the installed Loop Carousel query providers. */
final class Elementor_Query {
	private static function widget( array $settings, $skin, array $query_settings ) {
		if ( ! class_exists( '\\ElementorPro\\Modules\\LoopBuilder\\Widgets\\Loop_Carousel' ) || ! \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'loop-carousel' ) ) {
			return null;
		}
		return \Elementor\Plugin::$instance->elements_manager->create_element_instance( [
			'id' => wp_unique_id( 'eew-query-' ),
			'elType' => 'widget',
			'widgetType' => 'loop-carousel',
			'settings' => array_merge( [
				'_skin' => $skin,
				'template_id' => $settings['loop_template_id'] ?? '',
				'posts_per_page' => $settings['query_per_page'] ?? 6,
			], $query_settings ),
		] );
	}

	private static function term_taxonomy_ids( $ids, $taxonomy ) {
		$result = [];
		foreach ( wp_parse_id_list( $ids ) as $id ) {
			$term = get_term( $id, $taxonomy );
			if ( $term instanceof \WP_Term ) {
				$result[] = (int) $term->term_taxonomy_id;
			}
		}
		return $result;
	}

	/** Build with Pro itself; execute separately so Query ID hooks stay scoped. */
	public static function post_args( array $settings, array $args ) {
		if ( ! class_exists( '\\ElementorPro\\Modules\\QueryControl\\Classes\\Elementor_Post_Query' ) ) {
			return $args;
		}
		$source = Carousel_Query::source( $settings );
		$type = $args['post_type'] ?? 'post';
		$skin = 'product' === $type && class_exists( '\\WooCommerce' ) && 'related' !== $source ? 'product' : 'post';
		$prefix = $skin . '_query_';
		$native_type = [ 'selection' => 'by_id', 'current' => 'current_query', 'related' => 'related' ][ $source ] ?? $type;
		$include_terms = self::term_taxonomy_ids( $settings['query_term_ids'] ?? '', $settings['query_taxonomy'] ?? 'category' );
		$authors = wp_parse_id_list( $settings['query_authors'] ?? '' );
		$include = [];
		if ( $include_terms || 'related' === $source ) {
			$include[] = 'terms';
		}
		if ( $authors ) {
			$include[] = 'authors';
		}
		// Product queries are transformed by Pro's WooCommerce module. Supply
		// their selections to that provider rather than losing them in its filter.
		if ( 'product' === $skin && ! empty( $args['post__in'] ) ) {
			$native_type = 'by_id';
		}
		$native = self::widget( $settings, $skin, [
			$prefix . 'post_type' => $native_type,
			$prefix . 'posts_ids' => $args['post__in'] ?? [],
			$prefix . 'include' => $include,
			$prefix . 'include_term_ids' => $include_terms,
			$prefix . 'include_authors' => $authors,
			$prefix . 'exclude' => ! empty( $args['post__not_in'] ) ? [ 'manual_selection' ] : [],
			$prefix . 'exclude_ids' => $args['post__not_in'] ?? [],
			$prefix . 'orderby' => $args['orderby'] ?? 'date',
			$prefix . 'order' => $args['order'] ?? 'DESC',
			$prefix . 'offset' => $args['offset'] ?? 0,
			$prefix . 'ignore_sticky_posts' => 'yes',
			$prefix . 'avoid_duplicates' => $settings['query_avoid_duplicates'] ?? '',
			$prefix . 'related_taxonomies' => array_filter( [ $settings['query_taxonomy'] ?? '' ] ),
			$prefix . 'related_fallback' => 'fallback_none',
		] );
		if ( ! $native ) {
			return $args;
		}
		$context = Loop_Template::capture_context();
		try {
			if ( 'current' === $source && ( $GLOBALS['wp_the_query'] ?? null ) instanceof \WP_Query ) {
				$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
			} elseif ( 'related' === $source && get_the_ID() ) {
				// Resolve related data against the containing post, including when
				// this carousel is nested in another Loop Item.
				$post = get_post( get_the_ID() );
				$current = new \WP_Query();
				$current->parse_query( [ 'p' => $post->ID, 'post_type' => $post->post_type ] );
				$current->queried_object = $post;
				$current->queried_object_id = $post->ID;
				$GLOBALS['wp_query'] = $current;
			}
			$class = 'related' === $source ? '\\ElementorPro\\Modules\\QueryControl\\Classes\\Elementor_Related_Query' : '\\ElementorPro\\Modules\\QueryControl\\Classes\\Elementor_Post_Query';
			$builder = new $class( $native, $native->get_query_name(), $args );
			return $builder->get_query_args();
		} finally {
			Loop_Template::restore_context( $context );
		}
	}

	/** Use the same provider, taxonomy filter and get_terms path as native loops. */
	public static function terms( array $settings, $widget, array $args, \WP_Term_Query $query ) {
		$provider_class = '\\ElementorPro\\Modules\\LoopBuilder\\Providers\\Taxonomy_Loop_Provider';
		if ( ! class_exists( $provider_class ) ) {
			return null;
		}
		$taxonomy = get_taxonomy( $args['taxonomy'] );
		$skin_id = $taxonomy && in_array( 'product', $taxonomy->object_type, true ) && class_exists( '\\WooCommerce' ) ? 'product_taxonomy' : 'post_taxonomy';
		$native = self::widget( $settings, $skin_id, [
			$skin_id . '_query_post_type' => $args['taxonomy'],
			$skin_id . '_query_filter_by' => ! empty( $args['include'] ) || ! empty( $args['exclude'] ) ? 'manual_selection' : 'show_all',
			$skin_id . '_hide_empty' => $args['hide_empty'] ? 'yes' : 'no',
			$skin_id . '_posts_ids' => $args['include'] ?? [],
			$skin_id . '_exclude_ids' => $args['exclude'] ?? [],
			$skin_id . '_orderby' => $args['orderby'],
			$skin_id . '_order' => $args['order'],
			$skin_id . '_offset' => $args['offset'],
		] );
		$skin = $native ? $native->get_current_skin() : null;
		if ( ! $skin || ! is_callable( [ $skin, 'get_filtered_taxonomies' ] ) ) {
			return null;
		}
		$provider = new $provider_class( $skin_id, $args['taxonomy'] );
		$native_settings = $provider->get_query_settings( $native->get_settings_for_display() );
		$native_settings['_eew_query_token'] = wp_unique_id( 'eew-terms-' );
		$native_settings['number'] = $args['number'];
		$prepare = static function ( $candidate, $candidate_settings ) use ( $native_settings, $args, $widget, $settings, $query ) {
			if ( ( $candidate_settings['_eew_query_token'] ?? null ) !== $native_settings['_eew_query_token'] ) {
				return $candidate;
			}
			if ( isset( $args['parent'] ) ) {
				$candidate['parent'] = $args['parent'];
			}
			if ( 'yes' === ( $settings['query_avoid_duplicates'] ?? '' ) ) {
				$candidate['exclude'] = array_merge( (array) ( $candidate['exclude'] ?? [] ), \ElementorPro\Modules\LoopBuilder\Module::get_taxonomies_avoid_list_ids() );
			}
			$candidate = Carousel_Query::validate_term_args( apply_filters( 'eew/term_query_args', $candidate, $widget, $settings ) );
			if ( ! $candidate ) {
				$candidate = [ 'taxonomy' => $args['taxonomy'], 'include' => [ 0 ], 'number' => 1, 'fields' => 'all' ];
			}
			$query->query_vars = $candidate;
			return $candidate;
		};
		add_filter( 'elementor/loop_taxonomy/args', $prepare, PHP_INT_MAX, 2 );
		try {
			$terms = $skin->get_filtered_taxonomies( $native_settings, $native_settings );
			$query->terms = is_array( $terms ) ? $terms : [];
			\ElementorPro\Modules\LoopBuilder\Module::add_to_taxonomies_avoid_list( wp_list_pluck( $query->terms, 'term_id' ) );
			return $query;
		} finally {
			remove_filter( 'elementor/loop_taxonomy/args', $prepare, PHP_INT_MAX );
		}
	}
}
