<?php
namespace EEW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WordPress queries shared by the built-in cards and Elementor Pro Loop Items. */
final class Carousel_Query {
	const MAX_ITEMS = 100;

	public static function post_types() {
		$types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $types['attachment'], $types['elementor_library'] );
		return $types;
	}

	private static function integer( $settings, $key, $default, $min, $max ) {
		$value = isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ? (int) $settings[ $key ] : $default;
		return max( $min, min( $max, $value ) );
	}

	public static function source( array $settings ) {
		$source = $settings['query_source'] ?? 'posts';
		return in_array( $source, [ 'posts', 'current', 'related', 'selection' ], true ) ? $source : 'posts';
	}

	public static function args( array $settings ) {
		$source = self::source( $settings );
		if ( 'current' === $source ) {
			// Use the main page/archive query, even inside a nested template loop.
			$main = $GLOBALS['wp_the_query'] ?? null;
			$args = $main instanceof \WP_Query ? $main->query_vars : [];
			if ( ! $args ) {
				$args = [ 'post__in' => [ 0 ] ];
			}
			$args['post_status'] = 'publish';
			$limit = (int) ( $args['posts_per_page'] ?? 10 );
			$args['posts_per_page'] = $limit < 1 ? self::MAX_ITEMS : min( self::MAX_ITEMS, $limit );
			$args['no_found_rows'] = true;
			return $args;
		}

		$types = self::post_types();
		$type  = $settings['query_post_type'] ?? 'post';
		$type  = is_string( $type ) && isset( $types[ $type ] ) ? $type : 'post';
		$order = in_array( $settings['query_order'] ?? '', [ 'ASC', 'DESC' ], true ) ? $settings['query_order'] : 'DESC';
		$allowed_orderby = [ 'date', 'modified', 'title', 'menu_order', 'comment_count', 'rand', 'post__in' ];
		$orderby = in_array( $settings['query_orderby'] ?? '', $allowed_orderby, true ) ? $settings['query_orderby'] : 'date';
		$args = [
			'post_type'           => $type,
			'post_status'         => 'publish',
			'posts_per_page'      => self::integer( $settings, 'query_per_page', 6, 1, self::MAX_ITEMS ),
			'offset'              => self::integer( $settings, 'query_offset', 0, 0, 10000 ),
			'orderby'             => $orderby,
			'order'               => $order,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'has_password'        => false,
		];
		$include = wp_parse_id_list( $settings['query_include_ids'] ?? '' );
		$exclude = wp_parse_id_list( $settings['query_exclude_ids'] ?? '' );
		if ( 'selection' === $source || $include ) {
			$args['post__in'] = $include ?: [ 0 ];
		}
		$current_id = get_the_ID();
		if ( 'related' === $source || 'yes' === ( $settings['query_exclude_current'] ?? '' ) ) {
			$exclude[] = $current_id;
		}
		// WP_Query ignores post__not_in when post__in is supplied.
		if ( isset( $args['post__in'] ) && $exclude ) {
			$args['post__in'] = array_values( array_diff( $args['post__in'], $exclude ) ) ?: [ 0 ];
		} elseif ( $exclude ) {
			$args['post__not_in'] = $exclude;
		}
		$authors = wp_parse_id_list( $settings['query_authors'] ?? '' );
		if ( $authors ) {
			$args['author__in'] = $authors;
		}
		$taxonomy = sanitize_key( $settings['query_taxonomy'] ?? 'category' );
		if ( 'related' === $source ) {
			$terms = taxonomy_exists( $taxonomy ) ? wp_get_post_terms( $current_id, $taxonomy, [ 'fields' => 'ids' ] ) : [];
			if ( is_wp_error( $terms ) || ! $terms ) {
				$args['post__in'] = [ 0 ];
			}
		} else {
			$terms = wp_parse_id_list( $settings['query_term_ids'] ?? '' );
		}
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) && taxonomy_exists( $taxonomy ) ) {
			$args['tax_query'] = [ [ 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $terms ] ];
		}
		return $args;
	}

	public static function run( array $settings, $widget ) {
		$args = apply_filters( 'eew/query_args', self::args( $settings ), $widget, $settings );
		$query = new \WP_Query();
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $settings['query_id'] ?? '' ) );
		$callback = static function ( $candidate ) use ( $query, $id, $widget ) {
			if ( $candidate !== $query ) {
				return;
			}
			if ( '' !== $id ) {
				// The standard Elementor Query ID contract. Run after WP has parsed
				// query vars, and never affect another query made by the callback.
				do_action( 'elementor/query/' . $id, $query, $widget );
			}
			$limit = (int) $query->get( 'posts_per_page' );
			$query->set( 'posts_per_page', $limit < 1 ? self::MAX_ITEMS : min( self::MAX_ITEMS, $limit ) );
			$query->set( 'fields', '' );
		};
		add_action( 'pre_get_posts', $callback );
		try {
			$query->query( is_array( $args ) ? $args : [ 'post__in' => [ 0 ] ] );
		} finally {
			remove_action( 'pre_get_posts', $callback );
		}
		// Custom hooks can change post status. Public carousels only render
		// public, unprotected posts, regardless of the viewer's login state.
		$query->posts = array_values( array_filter( $query->posts, static function ( $post ) {
			return $post instanceof \WP_Post && is_post_publicly_viewable( $post ) && '' === $post->post_password;
		} ) );
		$query->post_count = count( $query->posts );
		$query->rewind_posts();
		return $query;
	}
}
