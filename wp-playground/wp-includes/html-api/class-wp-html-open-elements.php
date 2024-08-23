<?php
 class WP_HTML_Open_Elements { public $stack = array(); private $has_p_in_button_scope = false; private $pop_handler = null; private $push_handler = null; public function set_pop_handler( Closure $handler ) { $this->pop_handler = $handler; } public function set_push_handler( Closure $handler ) { $this->push_handler = $handler; } public function contains_node( $token ) { foreach ( $this->walk_up() as $item ) { if ( $token->bookmark_name === $item->bookmark_name ) { return true; } } return false; } public function count() { return count( $this->stack ); } public function current_node() { $current_node = end( $this->stack ); return $current_node ? $current_node : null; } public function has_element_in_specific_scope( $tag_name, $termination_list ) { foreach ( $this->walk_up() as $node ) { if ( $node->node_name === $tag_name ) { return true; } if ( '(internal: H1 through H6 - do not use)' === $tag_name && in_array( $node->node_name, array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) { return true; } switch ( $node->node_name ) { case 'HTML': return false; } if ( in_array( $node->node_name, $termination_list, true ) ) { return false; } } return false; } public function has_element_in_scope( $tag_name ) { return $this->has_element_in_specific_scope( $tag_name, array( ) ); } public function has_element_in_list_item_scope( $tag_name ) { return $this->has_element_in_specific_scope( $tag_name, array( 'OL', 'UL', ) ); } public function has_element_in_button_scope( $tag_name ) { return $this->has_element_in_specific_scope( $tag_name, array( 'BUTTON' ) ); } public function has_element_in_table_scope( $tag_name ) { throw new WP_HTML_Unsupported_Exception( 'Cannot process elements depending on table scope.' ); return false; } public function has_element_in_select_scope( $tag_name ) { throw new WP_HTML_Unsupported_Exception( 'Cannot process elements depending on select scope.' ); return false; } public function has_p_in_button_scope() { return $this->has_p_in_button_scope; } public function pop() { $item = array_pop( $this->stack ); if ( null === $item ) { return false; } if ( 'context-node' === $item->bookmark_name ) { $this->stack[] = $item; return false; } $this->after_element_pop( $item ); return true; } public function pop_until( $tag_name ) { foreach ( $this->walk_up() as $item ) { if ( 'context-node' === $item->bookmark_name ) { return true; } $this->pop(); if ( '(internal: H1 through H6 - do not use)' === $tag_name && in_array( $item->node_name, array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ), true ) ) { return true; } if ( $tag_name === $item->node_name ) { return true; } } return false; } public function push( $stack_item ) { $this->stack[] = $stack_item; $this->after_element_push( $stack_item ); } public function remove_node( $token ) { if ( 'context-node' === $token->bookmark_name ) { return false; } foreach ( $this->walk_up() as $position_from_end => $item ) { if ( $token->bookmark_name !== $item->bookmark_name ) { continue; } $position_from_start = $this->count() - $position_from_end - 1; array_splice( $this->stack, $position_from_start, 1 ); $this->after_element_pop( $item ); return true; } return false; } public function walk_down() { $count = count( $this->stack ); for ( $i = 0; $i < $count; $i++ ) { yield $this->stack[ $i ]; } } public function walk_up( $above_this_node = null ) { $has_found_node = null === $above_this_node; for ( $i = count( $this->stack ) - 1; $i >= 0; $i-- ) { $node = $this->stack[ $i ]; if ( ! $has_found_node ) { $has_found_node = $node === $above_this_node; continue; } yield $node; } } public function after_element_push( $item ) { switch ( $item->node_name ) { case 'BUTTON': $this->has_p_in_button_scope = false; break; case 'P': $this->has_p_in_button_scope = true; break; } if ( null !== $this->push_handler ) { ( $this->push_handler )( $item ); } } public function after_element_pop( $item ) { switch ( $item->node_name ) { case 'BUTTON': $this->has_p_in_button_scope = $this->has_element_in_button_scope( 'P' ); break; case 'P': $this->has_p_in_button_scope = $this->has_element_in_button_scope( 'P' ); break; } if ( null !== $this->pop_handler ) { ( $this->pop_handler )( $item ); } } public function __wakeup() { throw new \LogicException( __CLASS__ . ' should never be unserialized' ); } } 