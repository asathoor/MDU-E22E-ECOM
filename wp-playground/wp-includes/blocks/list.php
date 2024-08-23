<?php
 function block_core_list_render( $attributes, $content ) { if ( ! $content ) { return $content; } $processor = new WP_HTML_Tag_Processor( $content ); $list_tags = array( 'OL', 'UL' ); while ( $processor->next_tag() ) { if ( in_array( $processor->get_tag(), $list_tags, true ) ) { $processor->add_class( 'wp-block-list' ); break; } } return $processor->get_updated_html(); } function register_block_core_list() { register_block_type_from_metadata( __DIR__ . '/list', array( 'render_callback' => 'block_core_list_render', ) ); } add_action( 'init', 'register_block_core_list' ); 