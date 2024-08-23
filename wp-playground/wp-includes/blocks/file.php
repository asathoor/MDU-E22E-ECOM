<?php
 function render_block_core_file( $attributes, $content ) { if ( ! empty( $attributes['displayPreview'] ) ) { $suffix = wp_scripts_get_suffix(); if ( defined( 'IS_GUTENBERG_PLUGIN' ) && IS_GUTENBERG_PLUGIN ) { $module_url = gutenberg_url( '/build/interactivity/file.min.js' ); } wp_register_script_module( '@wordpress/block-library/file', isset( $module_url ) ? $module_url : includes_url( "blocks/file/view{$suffix}.js" ), array( '@wordpress/interactivity' ), defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : get_bloginfo( 'version' ) ); wp_enqueue_script_module( '@wordpress/block-library/file' ); $processor = new WP_HTML_Tag_Processor( $content ); $processor->next_tag(); $processor->set_attribute( 'data-wp-interactive', 'core/file' ); $processor->next_tag( 'object' ); $processor->set_attribute( 'data-wp-bind--hidden', '!state.hasPdfPreview' ); $processor->set_attribute( 'hidden', true ); $filename = $processor->get_attribute( 'aria-label' ); $has_filename = ! empty( $filename ) && 'PDF embed' !== $filename; $label = $has_filename ? sprintf( __( 'Embed of %s.' ), $filename ) : __( 'PDF embed' ); $processor->set_attribute( 'aria-label', $label ); return $processor->get_updated_html(); } return $content; } function register_block_core_file() { register_block_type_from_metadata( __DIR__ . '/file', array( 'render_callback' => 'render_block_core_file', ) ); } add_action( 'init', 'register_block_core_file' ); 