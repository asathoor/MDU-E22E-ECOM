<?php
 class WP_Sitemaps_Posts extends WP_Sitemaps_Provider { public function __construct() { $this->name = 'posts'; $this->object_type = 'post'; } public function get_object_subtypes() { $post_types = get_post_types( array( 'public' => true ), 'objects' ); unset( $post_types['attachment'] ); $post_types = array_filter( $post_types, 'is_post_type_viewable' ); return apply_filters( 'wp_sitemaps_post_types', $post_types ); } public function get_url_list( $page_num, $object_subtype = '' ) { $post_type = $object_subtype; $supported_types = $this->get_object_subtypes(); if ( ! isset( $supported_types[ $post_type ] ) ) { return array(); } $url_list = apply_filters( 'wp_sitemaps_posts_pre_url_list', null, $post_type, $page_num ); if ( null !== $url_list ) { return $url_list; } $args = $this->get_posts_query_args( $post_type ); $args['paged'] = $page_num; $query = new WP_Query( $args ); $url_list = array(); if ( 'page' === $post_type && 1 === $page_num && 'posts' === get_option( 'show_on_front' ) ) { $sitemap_entry = array( 'loc' => home_url( '/' ), ); $latest_posts = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, ) ); if ( ! empty( $latest_posts->posts ) ) { $posts = wp_list_sort( $latest_posts->posts, 'post_modified_gmt', 'DESC' ); $sitemap_entry['lastmod'] = wp_date( DATE_W3C, strtotime( $posts[0]->post_modified_gmt ) ); } $sitemap_entry = apply_filters( 'wp_sitemaps_posts_show_on_front_entry', $sitemap_entry ); $url_list[] = $sitemap_entry; } foreach ( $query->posts as $post ) { $sitemap_entry = array( 'loc' => get_permalink( $post ), 'lastmod' => wp_date( DATE_W3C, strtotime( $post->post_modified_gmt ) ), ); $sitemap_entry = apply_filters( 'wp_sitemaps_posts_entry', $sitemap_entry, $post, $post_type ); $url_list[] = $sitemap_entry; } return $url_list; } public function get_max_num_pages( $object_subtype = '' ) { if ( empty( $object_subtype ) ) { return 0; } $post_type = $object_subtype; $max_num_pages = apply_filters( 'wp_sitemaps_posts_pre_max_num_pages', null, $post_type ); if ( null !== $max_num_pages ) { return $max_num_pages; } $args = $this->get_posts_query_args( $post_type ); $args['fields'] = 'ids'; $args['no_found_rows'] = false; $query = new WP_Query( $args ); $min_num_pages = ( 'page' === $post_type && 'posts' === get_option( 'show_on_front' ) ) ? 1 : 0; return isset( $query->max_num_pages ) ? max( $min_num_pages, $query->max_num_pages ) : 1; } protected function get_posts_query_args( $post_type ) { $args = apply_filters( 'wp_sitemaps_posts_query_args', array( 'orderby' => 'ID', 'order' => 'ASC', 'post_type' => $post_type, 'posts_per_page' => wp_sitemaps_get_max_urls( $this->object_type ), 'post_status' => array( 'publish' ), 'no_found_rows' => true, 'update_post_term_cache' => false, 'update_post_meta_cache' => false, 'ignore_sticky_posts' => true, ), $post_type ); return $args; } } 