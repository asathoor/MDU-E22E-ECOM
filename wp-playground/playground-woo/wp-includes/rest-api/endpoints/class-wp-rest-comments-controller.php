<?php
 class WP_REST_Comments_Controller extends WP_REST_Controller { protected $meta; public function __construct() { $this->namespace = 'wp/v2'; $this->rest_base = 'comments'; $this->meta = new WP_REST_Comment_Meta_Fields(); } public function register_routes() { register_rest_route( $this->namespace, '/' . $this->rest_base, array( array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_items' ), 'permission_callback' => array( $this, 'get_items_permissions_check' ), 'args' => $this->get_collection_params(), ), array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_item' ), 'permission_callback' => array( $this, 'create_item_permissions_check' ), 'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), ), 'schema' => array( $this, 'get_public_item_schema' ), ) ); register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array( 'args' => array( 'id' => array( 'description' => __( 'Unique identifier for the comment.' ), 'type' => 'integer', ), ), array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_item' ), 'permission_callback' => array( $this, 'get_item_permissions_check' ), 'args' => array( 'context' => $this->get_context_param( array( 'default' => 'view' ) ), 'password' => array( 'description' => __( 'The password for the parent post of the comment (if the post is password protected).' ), 'type' => 'string', ), ), ), array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_item' ), 'permission_callback' => array( $this, 'update_item_permissions_check' ), 'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ), ), array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_item' ), 'permission_callback' => array( $this, 'delete_item_permissions_check' ), 'args' => array( 'force' => array( 'type' => 'boolean', 'default' => false, 'description' => __( 'Whether to bypass Trash and force deletion.' ), ), 'password' => array( 'description' => __( 'The password for the parent post of the comment (if the post is password protected).' ), 'type' => 'string', ), ), ), 'schema' => array( $this, 'get_public_item_schema' ), ) ); } public function get_items_permissions_check( $request ) { if ( ! empty( $request['post'] ) ) { foreach ( (array) $request['post'] as $post_id ) { $post = get_post( $post_id ); if ( ! empty( $post_id ) && $post && ! $this->check_read_post_permission( $post, $request ) ) { return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you are not allowed to read the post for this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } elseif ( 0 === $post_id && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_cannot_read', __( 'Sorry, you are not allowed to read comments without a post.' ), array( 'status' => rest_authorization_required_code() ) ); } } } if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit comments.' ), array( 'status' => rest_authorization_required_code() ) ); } if ( ! current_user_can( 'edit_posts' ) ) { $protected_params = array( 'author', 'author_exclude', 'author_email', 'type', 'status' ); $forbidden_params = array(); foreach ( $protected_params as $param ) { if ( 'status' === $param ) { if ( 'approve' !== $request[ $param ] ) { $forbidden_params[] = $param; } } elseif ( 'type' === $param ) { if ( 'comment' !== $request[ $param ] ) { $forbidden_params[] = $param; } } elseif ( ! empty( $request[ $param ] ) ) { $forbidden_params[] = $param; } } if ( ! empty( $forbidden_params ) ) { return new WP_Error( 'rest_forbidden_param', sprintf( __( 'Query parameter not permitted: %s' ), implode( ', ', $forbidden_params ) ), array( 'status' => rest_authorization_required_code() ) ); } } return true; } public function get_items( $request ) { $registered = $this->get_collection_params(); $parameter_mappings = array( 'author' => 'author__in', 'author_email' => 'author_email', 'author_exclude' => 'author__not_in', 'exclude' => 'comment__not_in', 'include' => 'comment__in', 'offset' => 'offset', 'order' => 'order', 'parent' => 'parent__in', 'parent_exclude' => 'parent__not_in', 'per_page' => 'number', 'post' => 'post__in', 'search' => 'search', 'status' => 'status', 'type' => 'type', ); $prepared_args = array(); foreach ( $parameter_mappings as $api_param => $wp_param ) { if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) { $prepared_args[ $wp_param ] = $request[ $api_param ]; } } foreach ( array( 'author_email', 'search' ) as $param ) { if ( ! isset( $prepared_args[ $param ] ) ) { $prepared_args[ $param ] = ''; } } if ( isset( $registered['orderby'] ) ) { $prepared_args['orderby'] = $this->normalize_query_param( $request['orderby'] ); } $prepared_args['no_found_rows'] = false; $prepared_args['update_comment_post_cache'] = true; $prepared_args['date_query'] = array(); if ( isset( $registered['before'], $request['before'] ) ) { $prepared_args['date_query'][0]['before'] = $request['before']; } if ( isset( $registered['after'], $request['after'] ) ) { $prepared_args['date_query'][0]['after'] = $request['after']; } if ( isset( $registered['page'] ) && empty( $request['offset'] ) ) { $prepared_args['offset'] = $prepared_args['number'] * ( absint( $request['page'] ) - 1 ); } $prepared_args = apply_filters( 'rest_comment_query', $prepared_args, $request ); $query = new WP_Comment_Query(); $query_result = $query->query( $prepared_args ); $comments = array(); foreach ( $query_result as $comment ) { if ( ! $this->check_read_permission( $comment, $request ) ) { continue; } $data = $this->prepare_item_for_response( $comment, $request ); $comments[] = $this->prepare_response_for_collection( $data ); } $total_comments = (int) $query->found_comments; $max_pages = (int) $query->max_num_pages; if ( $total_comments < 1 ) { unset( $prepared_args['number'], $prepared_args['offset'] ); $query = new WP_Comment_Query(); $prepared_args['count'] = true; $prepared_args['orderby'] = 'none'; $total_comments = $query->query( $prepared_args ); $max_pages = (int) ceil( $total_comments / $request['per_page'] ); } $response = rest_ensure_response( $comments ); $response->header( 'X-WP-Total', $total_comments ); $response->header( 'X-WP-TotalPages', $max_pages ); $base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ); if ( $request['page'] > 1 ) { $prev_page = $request['page'] - 1; if ( $prev_page > $max_pages ) { $prev_page = $max_pages; } $prev_link = add_query_arg( 'page', $prev_page, $base ); $response->link_header( 'prev', $prev_link ); } if ( $max_pages > $request['page'] ) { $next_page = $request['page'] + 1; $next_link = add_query_arg( 'page', $next_page, $base ); $response->link_header( 'next', $next_link ); } return $response; } protected function get_comment( $id ) { $error = new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment ID.' ), array( 'status' => 404 ) ); if ( (int) $id <= 0 ) { return $error; } $id = (int) $id; $comment = get_comment( $id ); if ( empty( $comment ) ) { return $error; } if ( ! empty( $comment->comment_post_ID ) ) { $post = get_post( (int) $comment->comment_post_ID ); if ( empty( $post ) ) { return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) ); } } return $comment; } public function get_item_permissions_check( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } if ( ! empty( $request['context'] ) && 'edit' === $request['context'] && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit comments.' ), array( 'status' => rest_authorization_required_code() ) ); } $post = get_post( $comment->comment_post_ID ); if ( ! $this->check_read_permission( $comment, $request ) ) { return new WP_Error( 'rest_cannot_read', __( 'Sorry, you are not allowed to read this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } if ( $post && ! $this->check_read_post_permission( $post, $request ) ) { return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you are not allowed to read the post for this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function get_item( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } $data = $this->prepare_item_for_response( $comment, $request ); $response = rest_ensure_response( $data ); return $response; } public function create_item_permissions_check( $request ) { if ( ! is_user_logged_in() ) { if ( get_option( 'comment_registration' ) ) { return new WP_Error( 'rest_comment_login_required', __( 'Sorry, you must be logged in to comment.' ), array( 'status' => 401 ) ); } $allow_anonymous = apply_filters( 'rest_allow_anonymous_comments', false, $request ); if ( ! $allow_anonymous ) { return new WP_Error( 'rest_comment_login_required', __( 'Sorry, you must be logged in to comment.' ), array( 'status' => 401 ) ); } } if ( isset( $request['author'] ) && get_current_user_id() !== $request['author'] && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_comment_invalid_author', sprintf( __( "Sorry, you are not allowed to edit '%s' for comments." ), 'author' ), array( 'status' => rest_authorization_required_code() ) ); } if ( isset( $request['author_ip'] ) && ! current_user_can( 'moderate_comments' ) ) { if ( empty( $_SERVER['REMOTE_ADDR'] ) || $request['author_ip'] !== $_SERVER['REMOTE_ADDR'] ) { return new WP_Error( 'rest_comment_invalid_author_ip', sprintf( __( "Sorry, you are not allowed to edit '%s' for comments." ), 'author_ip' ), array( 'status' => rest_authorization_required_code() ) ); } } if ( isset( $request['status'] ) && ! current_user_can( 'moderate_comments' ) ) { return new WP_Error( 'rest_comment_invalid_status', sprintf( __( "Sorry, you are not allowed to edit '%s' for comments." ), 'status' ), array( 'status' => rest_authorization_required_code() ) ); } if ( empty( $request['post'] ) ) { return new WP_Error( 'rest_comment_invalid_post_id', __( 'Sorry, you are not allowed to create this comment without a post.' ), array( 'status' => 403 ) ); } $post = get_post( (int) $request['post'] ); if ( ! $post ) { return new WP_Error( 'rest_comment_invalid_post_id', __( 'Sorry, you are not allowed to create this comment without a post.' ), array( 'status' => 403 ) ); } if ( 'draft' === $post->post_status ) { return new WP_Error( 'rest_comment_draft_post', __( 'Sorry, you are not allowed to create a comment on this post.' ), array( 'status' => 403 ) ); } if ( 'trash' === $post->post_status ) { return new WP_Error( 'rest_comment_trash_post', __( 'Sorry, you are not allowed to create a comment on this post.' ), array( 'status' => 403 ) ); } if ( ! $this->check_read_post_permission( $post, $request ) ) { return new WP_Error( 'rest_cannot_read_post', __( 'Sorry, you are not allowed to read the post for this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } if ( ! comments_open( $post->ID ) ) { return new WP_Error( 'rest_comment_closed', __( 'Sorry, comments are closed for this item.' ), array( 'status' => 403 ) ); } return true; } public function create_item( $request ) { if ( ! empty( $request['id'] ) ) { return new WP_Error( 'rest_comment_exists', __( 'Cannot create existing comment.' ), array( 'status' => 400 ) ); } if ( ! empty( $request['type'] ) && 'comment' !== $request['type'] ) { return new WP_Error( 'rest_invalid_comment_type', __( 'Cannot create a comment with that type.' ), array( 'status' => 400 ) ); } $prepared_comment = $this->prepare_item_for_database( $request ); if ( is_wp_error( $prepared_comment ) ) { return $prepared_comment; } $prepared_comment['comment_type'] = 'comment'; if ( ! isset( $prepared_comment['comment_content'] ) ) { $prepared_comment['comment_content'] = ''; } if ( ! $this->check_is_comment_content_allowed( $prepared_comment ) ) { return new WP_Error( 'rest_comment_content_invalid', __( 'Invalid comment content.' ), array( 'status' => 400 ) ); } if ( ! isset( $prepared_comment['comment_date_gmt'] ) ) { $prepared_comment['comment_date_gmt'] = current_time( 'mysql', true ); } $missing_author = empty( $prepared_comment['user_id'] ) && empty( $prepared_comment['comment_author'] ) && empty( $prepared_comment['comment_author_email'] ) && empty( $prepared_comment['comment_author_url'] ); if ( is_user_logged_in() && $missing_author ) { $user = wp_get_current_user(); $prepared_comment['user_id'] = $user->ID; $prepared_comment['comment_author'] = $user->display_name; $prepared_comment['comment_author_email'] = $user->user_email; $prepared_comment['comment_author_url'] = $user->user_url; } if ( get_option( 'require_name_email' ) ) { if ( empty( $prepared_comment['comment_author'] ) || empty( $prepared_comment['comment_author_email'] ) ) { return new WP_Error( 'rest_comment_author_data_required', __( 'Creating a comment requires valid author name and email values.' ), array( 'status' => 400 ) ); } } if ( ! isset( $prepared_comment['comment_author_email'] ) ) { $prepared_comment['comment_author_email'] = ''; } if ( ! isset( $prepared_comment['comment_author_url'] ) ) { $prepared_comment['comment_author_url'] = ''; } if ( ! isset( $prepared_comment['comment_agent'] ) ) { $prepared_comment['comment_agent'] = ''; } $check_comment_lengths = wp_check_comment_data_max_lengths( $prepared_comment ); if ( is_wp_error( $check_comment_lengths ) ) { $error_code = $check_comment_lengths->get_error_code(); return new WP_Error( $error_code, __( 'Comment field exceeds maximum length allowed.' ), array( 'status' => 400 ) ); } $prepared_comment['comment_approved'] = wp_allow_comment( $prepared_comment, true ); if ( is_wp_error( $prepared_comment['comment_approved'] ) ) { $error_code = $prepared_comment['comment_approved']->get_error_code(); $error_message = $prepared_comment['comment_approved']->get_error_message(); if ( 'comment_duplicate' === $error_code ) { return new WP_Error( $error_code, $error_message, array( 'status' => 409 ) ); } if ( 'comment_flood' === $error_code ) { return new WP_Error( $error_code, $error_message, array( 'status' => 400 ) ); } return $prepared_comment['comment_approved']; } $prepared_comment = apply_filters( 'rest_pre_insert_comment', $prepared_comment, $request ); if ( is_wp_error( $prepared_comment ) ) { return $prepared_comment; } $comment_id = wp_insert_comment( wp_filter_comment( wp_slash( (array) $prepared_comment ) ) ); if ( ! $comment_id ) { return new WP_Error( 'rest_comment_failed_create', __( 'Creating comment failed.' ), array( 'status' => 500 ) ); } if ( isset( $request['status'] ) ) { $this->handle_status_param( $request['status'], $comment_id ); } $comment = get_comment( $comment_id ); do_action( 'rest_insert_comment', $comment, $request, true ); $schema = $this->get_item_schema(); if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) { $meta_update = $this->meta->update_value( $request['meta'], $comment_id ); if ( is_wp_error( $meta_update ) ) { return $meta_update; } } $fields_update = $this->update_additional_fields_for_object( $comment, $request ); if ( is_wp_error( $fields_update ) ) { return $fields_update; } $context = current_user_can( 'moderate_comments' ) ? 'edit' : 'view'; $request->set_param( 'context', $context ); do_action( 'rest_after_insert_comment', $comment, $request, true ); $response = $this->prepare_item_for_response( $comment, $request ); $response = rest_ensure_response( $response ); $response->set_status( 201 ); $response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $comment_id ) ) ); return $response; } public function update_item_permissions_check( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } if ( ! $this->check_edit_permission( $comment ) ) { return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function update_item( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } $id = $comment->comment_ID; if ( isset( $request['type'] ) && get_comment_type( $id ) !== $request['type'] ) { return new WP_Error( 'rest_comment_invalid_type', __( 'Sorry, you are not allowed to change the comment type.' ), array( 'status' => 404 ) ); } $prepared_args = $this->prepare_item_for_database( $request ); if ( is_wp_error( $prepared_args ) ) { return $prepared_args; } if ( ! empty( $prepared_args['comment_post_ID'] ) ) { $post = get_post( $prepared_args['comment_post_ID'] ); if ( empty( $post ) ) { return new WP_Error( 'rest_comment_invalid_post_id', __( 'Invalid post ID.' ), array( 'status' => 403 ) ); } } if ( empty( $prepared_args ) && isset( $request['status'] ) ) { $change = $this->handle_status_param( $request['status'], $id ); if ( ! $change ) { return new WP_Error( 'rest_comment_failed_edit', __( 'Updating comment status failed.' ), array( 'status' => 500 ) ); } } elseif ( ! empty( $prepared_args ) ) { if ( is_wp_error( $prepared_args ) ) { return $prepared_args; } if ( isset( $prepared_args['comment_content'] ) && empty( $prepared_args['comment_content'] ) ) { return new WP_Error( 'rest_comment_content_invalid', __( 'Invalid comment content.' ), array( 'status' => 400 ) ); } $prepared_args['comment_ID'] = $id; $check_comment_lengths = wp_check_comment_data_max_lengths( $prepared_args ); if ( is_wp_error( $check_comment_lengths ) ) { $error_code = $check_comment_lengths->get_error_code(); return new WP_Error( $error_code, __( 'Comment field exceeds maximum length allowed.' ), array( 'status' => 400 ) ); } $updated = wp_update_comment( wp_slash( (array) $prepared_args ), true ); if ( is_wp_error( $updated ) ) { return new WP_Error( 'rest_comment_failed_edit', __( 'Updating comment failed.' ), array( 'status' => 500 ) ); } if ( isset( $request['status'] ) ) { $this->handle_status_param( $request['status'], $id ); } } $comment = get_comment( $id ); do_action( 'rest_insert_comment', $comment, $request, false ); $schema = $this->get_item_schema(); if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) { $meta_update = $this->meta->update_value( $request['meta'], $id ); if ( is_wp_error( $meta_update ) ) { return $meta_update; } } $fields_update = $this->update_additional_fields_for_object( $comment, $request ); if ( is_wp_error( $fields_update ) ) { return $fields_update; } $request->set_param( 'context', 'edit' ); do_action( 'rest_after_insert_comment', $comment, $request, false ); $response = $this->prepare_item_for_response( $comment, $request ); return rest_ensure_response( $response ); } public function delete_item_permissions_check( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } if ( ! $this->check_edit_permission( $comment ) ) { return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this comment.' ), array( 'status' => rest_authorization_required_code() ) ); } return true; } public function delete_item( $request ) { $comment = $this->get_comment( $request['id'] ); if ( is_wp_error( $comment ) ) { return $comment; } $force = isset( $request['force'] ) ? (bool) $request['force'] : false; $supports_trash = apply_filters( 'rest_comment_trashable', ( EMPTY_TRASH_DAYS > 0 ), $comment ); $request->set_param( 'context', 'edit' ); if ( $force ) { $previous = $this->prepare_item_for_response( $comment, $request ); $result = wp_delete_comment( $comment->comment_ID, true ); $response = new WP_REST_Response(); $response->set_data( array( 'deleted' => true, 'previous' => $previous->get_data(), ) ); } else { if ( ! $supports_trash ) { return new WP_Error( 'rest_trash_not_supported', sprintf( __( "The comment does not support trashing. Set '%s' to delete." ), 'force=true' ), array( 'status' => 501 ) ); } if ( 'trash' === $comment->comment_approved ) { return new WP_Error( 'rest_already_trashed', __( 'The comment has already been trashed.' ), array( 'status' => 410 ) ); } $result = wp_trash_comment( $comment->comment_ID ); $comment = get_comment( $comment->comment_ID ); $response = $this->prepare_item_for_response( $comment, $request ); } if ( ! $result ) { return new WP_Error( 'rest_cannot_delete', __( 'The comment cannot be deleted.' ), array( 'status' => 500 ) ); } do_action( 'rest_delete_comment', $comment, $response, $request ); return $response; } public function prepare_item_for_response( $item, $request ) { $comment = $item; $fields = $this->get_fields_for_response( $request ); $data = array(); if ( in_array( 'id', $fields, true ) ) { $data['id'] = (int) $comment->comment_ID; } if ( in_array( 'post', $fields, true ) ) { $data['post'] = (int) $comment->comment_post_ID; } if ( in_array( 'parent', $fields, true ) ) { $data['parent'] = (int) $comment->comment_parent; } if ( in_array( 'author', $fields, true ) ) { $data['author'] = (int) $comment->user_id; } if ( in_array( 'author_name', $fields, true ) ) { $data['author_name'] = $comment->comment_author; } if ( in_array( 'author_email', $fields, true ) ) { $data['author_email'] = $comment->comment_author_email; } if ( in_array( 'author_url', $fields, true ) ) { $data['author_url'] = $comment->comment_author_url; } if ( in_array( 'author_ip', $fields, true ) ) { $data['author_ip'] = $comment->comment_author_IP; } if ( in_array( 'author_user_agent', $fields, true ) ) { $data['author_user_agent'] = $comment->comment_agent; } if ( in_array( 'date', $fields, true ) ) { $data['date'] = mysql_to_rfc3339( $comment->comment_date ); } if ( in_array( 'date_gmt', $fields, true ) ) { $data['date_gmt'] = mysql_to_rfc3339( $comment->comment_date_gmt ); } if ( in_array( 'content', $fields, true ) ) { $data['content'] = array( 'rendered' => apply_filters( 'comment_text', $comment->comment_content, $comment, array() ), 'raw' => $comment->comment_content, ); } if ( in_array( 'link', $fields, true ) ) { $data['link'] = get_comment_link( $comment ); } if ( in_array( 'status', $fields, true ) ) { $data['status'] = $this->prepare_status_response( $comment->comment_approved ); } if ( in_array( 'type', $fields, true ) ) { $data['type'] = get_comment_type( $comment->comment_ID ); } if ( in_array( 'author_avatar_urls', $fields, true ) ) { $data['author_avatar_urls'] = rest_get_avatar_urls( $comment ); } if ( in_array( 'meta', $fields, true ) ) { $data['meta'] = $this->meta->get_value( $comment->comment_ID, $request ); } $context = ! empty( $request['context'] ) ? $request['context'] : 'view'; $data = $this->add_additional_fields_to_object( $data, $request ); $data = $this->filter_response_by_context( $data, $context ); $response = rest_ensure_response( $data ); if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) { $response->add_links( $this->prepare_links( $comment ) ); } return apply_filters( 'rest_prepare_comment', $response, $comment, $request ); } protected function prepare_links( $comment ) { $links = array( 'self' => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $comment->comment_ID ) ), ), 'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ), ), ); if ( 0 !== (int) $comment->user_id ) { $links['author'] = array( 'href' => rest_url( 'wp/v2/users/' . $comment->user_id ), 'embeddable' => true, ); } if ( 0 !== (int) $comment->comment_post_ID ) { $post = get_post( $comment->comment_post_ID ); $post_route = rest_get_route_for_post( $post ); if ( ! empty( $post->ID ) && $post_route ) { $links['up'] = array( 'href' => rest_url( $post_route ), 'embeddable' => true, 'post_type' => $post->post_type, ); } } if ( 0 !== (int) $comment->comment_parent ) { $links['in-reply-to'] = array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $comment->comment_parent ) ), 'embeddable' => true, ); } $comment_children = $comment->get_children( array( 'count' => true, 'orderby' => 'none', ) ); if ( ! empty( $comment_children ) ) { $args = array( 'parent' => $comment->comment_ID, ); $rest_url = add_query_arg( $args, rest_url( $this->namespace . '/' . $this->rest_base ) ); $links['children'] = array( 'href' => $rest_url, 'embeddable' => true, ); } return $links; } protected function normalize_query_param( $query_param ) { $prefix = 'comment_'; switch ( $query_param ) { case 'id': $normalized = $prefix . 'ID'; break; case 'post': $normalized = $prefix . 'post_ID'; break; case 'parent': $normalized = $prefix . 'parent'; break; case 'include': $normalized = 'comment__in'; break; default: $normalized = $prefix . $query_param; break; } return $normalized; } protected function prepare_status_response( $comment_approved ) { switch ( $comment_approved ) { case 'hold': case '0': $status = 'hold'; break; case 'approve': case '1': $status = 'approved'; break; case 'spam': case 'trash': default: $status = $comment_approved; break; } return $status; } protected function prepare_item_for_database( $request ) { $prepared_comment = array(); if ( isset( $request['content'] ) && is_string( $request['content'] ) ) { $prepared_comment['comment_content'] = trim( $request['content'] ); } elseif ( isset( $request['content']['raw'] ) && is_string( $request['content']['raw'] ) ) { $prepared_comment['comment_content'] = trim( $request['content']['raw'] ); } if ( isset( $request['post'] ) ) { $prepared_comment['comment_post_ID'] = (int) $request['post']; } if ( isset( $request['parent'] ) ) { $prepared_comment['comment_parent'] = $request['parent']; } if ( isset( $request['author'] ) ) { $user = new WP_User( $request['author'] ); if ( $user->exists() ) { $prepared_comment['user_id'] = $user->ID; $prepared_comment['comment_author'] = $user->display_name; $prepared_comment['comment_author_email'] = $user->user_email; $prepared_comment['comment_author_url'] = $user->user_url; } else { return new WP_Error( 'rest_comment_author_invalid', __( 'Invalid comment author ID.' ), array( 'status' => 400 ) ); } } if ( isset( $request['author_name'] ) ) { $prepared_comment['comment_author'] = $request['author_name']; } if ( isset( $request['author_email'] ) ) { $prepared_comment['comment_author_email'] = $request['author_email']; } if ( isset( $request['author_url'] ) ) { $prepared_comment['comment_author_url'] = $request['author_url']; } if ( isset( $request['author_ip'] ) && current_user_can( 'moderate_comments' ) ) { $prepared_comment['comment_author_IP'] = $request['author_ip']; } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) && rest_is_ip_address( $_SERVER['REMOTE_ADDR'] ) ) { $prepared_comment['comment_author_IP'] = $_SERVER['REMOTE_ADDR']; } else { $prepared_comment['comment_author_IP'] = '127.0.0.1'; } if ( ! empty( $request['author_user_agent'] ) ) { $prepared_comment['comment_agent'] = $request['author_user_agent']; } elseif ( $request->get_header( 'user_agent' ) ) { $prepared_comment['comment_agent'] = $request->get_header( 'user_agent' ); } if ( ! empty( $request['date'] ) ) { $date_data = rest_get_date_with_gmt( $request['date'] ); if ( ! empty( $date_data ) ) { list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) = $date_data; } } elseif ( ! empty( $request['date_gmt'] ) ) { $date_data = rest_get_date_with_gmt( $request['date_gmt'], true ); if ( ! empty( $date_data ) ) { list( $prepared_comment['comment_date'], $prepared_comment['comment_date_gmt'] ) = $date_data; } } return apply_filters( 'rest_preprocess_comment', $prepared_comment, $request ); } public function get_item_schema() { if ( $this->schema ) { return $this->add_additional_fields_schema( $this->schema ); } $schema = array( '$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'comment', 'type' => 'object', 'properties' => array( 'id' => array( 'description' => __( 'Unique identifier for the comment.' ), 'type' => 'integer', 'context' => array( 'view', 'edit', 'embed' ), 'readonly' => true, ), 'author' => array( 'description' => __( 'The ID of the user object, if author was a user.' ), 'type' => 'integer', 'context' => array( 'view', 'edit', 'embed' ), ), 'author_email' => array( 'description' => __( 'Email address for the comment author.' ), 'type' => 'string', 'format' => 'email', 'context' => array( 'edit' ), 'arg_options' => array( 'sanitize_callback' => array( $this, 'check_comment_author_email' ), 'validate_callback' => null, ), ), 'author_ip' => array( 'description' => __( 'IP address for the comment author.' ), 'type' => 'string', 'format' => 'ip', 'context' => array( 'edit' ), ), 'author_name' => array( 'description' => __( 'Display name for the comment author.' ), 'type' => 'string', 'context' => array( 'view', 'edit', 'embed' ), 'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field', ), ), 'author_url' => array( 'description' => __( 'URL for the comment author.' ), 'type' => 'string', 'format' => 'uri', 'context' => array( 'view', 'edit', 'embed' ), ), 'author_user_agent' => array( 'description' => __( 'User agent for the comment author.' ), 'type' => 'string', 'context' => array( 'edit' ), 'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field', ), ), 'content' => array( 'description' => __( 'The content for the comment.' ), 'type' => 'object', 'context' => array( 'view', 'edit', 'embed' ), 'arg_options' => array( 'sanitize_callback' => null, 'validate_callback' => null, ), 'properties' => array( 'raw' => array( 'description' => __( 'Content for the comment, as it exists in the database.' ), 'type' => 'string', 'context' => array( 'edit' ), ), 'rendered' => array( 'description' => __( 'HTML content for the comment, transformed for display.' ), 'type' => 'string', 'context' => array( 'view', 'edit', 'embed' ), 'readonly' => true, ), ), ), 'date' => array( 'description' => __( "The date the comment was published, in the site's timezone." ), 'type' => 'string', 'format' => 'date-time', 'context' => array( 'view', 'edit', 'embed' ), ), 'date_gmt' => array( 'description' => __( 'The date the comment was published, as GMT.' ), 'type' => 'string', 'format' => 'date-time', 'context' => array( 'view', 'edit' ), ), 'link' => array( 'description' => __( 'URL to the comment.' ), 'type' => 'string', 'format' => 'uri', 'context' => array( 'view', 'edit', 'embed' ), 'readonly' => true, ), 'parent' => array( 'description' => __( 'The ID for the parent of the comment.' ), 'type' => 'integer', 'context' => array( 'view', 'edit', 'embed' ), 'default' => 0, ), 'post' => array( 'description' => __( 'The ID of the associated post object.' ), 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'default' => 0, ), 'status' => array( 'description' => __( 'State of the comment.' ), 'type' => 'string', 'context' => array( 'view', 'edit' ), 'arg_options' => array( 'sanitize_callback' => 'sanitize_key', ), ), 'type' => array( 'description' => __( 'Type of the comment.' ), 'type' => 'string', 'context' => array( 'view', 'edit', 'embed' ), 'readonly' => true, ), ), ); if ( get_option( 'show_avatars' ) ) { $avatar_properties = array(); $avatar_sizes = rest_get_avatar_sizes(); foreach ( $avatar_sizes as $size ) { $avatar_properties[ $size ] = array( 'description' => sprintf( __( 'Avatar URL with image size of %d pixels.' ), $size ), 'type' => 'string', 'format' => 'uri', 'context' => array( 'embed', 'view', 'edit' ), ); } $schema['properties']['author_avatar_urls'] = array( 'description' => __( 'Avatar URLs for the comment author.' ), 'type' => 'object', 'context' => array( 'view', 'edit', 'embed' ), 'readonly' => true, 'properties' => $avatar_properties, ); } $schema['properties']['meta'] = $this->meta->get_field_schema(); $this->schema = $schema; return $this->add_additional_fields_schema( $this->schema ); } public function get_collection_params() { $query_params = parent::get_collection_params(); $query_params['context']['default'] = 'view'; $query_params['after'] = array( 'description' => __( 'Limit response to comments published after a given ISO8601 compliant date.' ), 'type' => 'string', 'format' => 'date-time', ); $query_params['author'] = array( 'description' => __( 'Limit result set to comments assigned to specific user IDs. Requires authorization.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), ); $query_params['author_exclude'] = array( 'description' => __( 'Ensure result set excludes comments assigned to specific user IDs. Requires authorization.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), ); $query_params['author_email'] = array( 'default' => null, 'description' => __( 'Limit result set to that from a specific author email. Requires authorization.' ), 'format' => 'email', 'type' => 'string', ); $query_params['before'] = array( 'description' => __( 'Limit response to comments published before a given ISO8601 compliant date.' ), 'type' => 'string', 'format' => 'date-time', ); $query_params['exclude'] = array( 'description' => __( 'Ensure result set excludes specific IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), 'default' => array(), ); $query_params['include'] = array( 'description' => __( 'Limit result set to specific IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), 'default' => array(), ); $query_params['offset'] = array( 'description' => __( 'Offset the result set by a specific number of items.' ), 'type' => 'integer', ); $query_params['order'] = array( 'description' => __( 'Order sort attribute ascending or descending.' ), 'type' => 'string', 'default' => 'desc', 'enum' => array( 'asc', 'desc', ), ); $query_params['orderby'] = array( 'description' => __( 'Sort collection by comment attribute.' ), 'type' => 'string', 'default' => 'date_gmt', 'enum' => array( 'date', 'date_gmt', 'id', 'include', 'post', 'parent', 'type', ), ); $query_params['parent'] = array( 'default' => array(), 'description' => __( 'Limit result set to comments of specific parent IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), ); $query_params['parent_exclude'] = array( 'default' => array(), 'description' => __( 'Ensure result set excludes specific parent IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), ); $query_params['post'] = array( 'default' => array(), 'description' => __( 'Limit result set to comments assigned to specific post IDs.' ), 'type' => 'array', 'items' => array( 'type' => 'integer', ), ); $query_params['status'] = array( 'default' => 'approve', 'description' => __( 'Limit result set to comments assigned a specific status. Requires authorization.' ), 'sanitize_callback' => 'sanitize_key', 'type' => 'string', 'validate_callback' => 'rest_validate_request_arg', ); $query_params['type'] = array( 'default' => 'comment', 'description' => __( 'Limit result set to comments assigned a specific type. Requires authorization.' ), 'sanitize_callback' => 'sanitize_key', 'type' => 'string', 'validate_callback' => 'rest_validate_request_arg', ); $query_params['password'] = array( 'description' => __( 'The password for the post if it is password protected.' ), 'type' => 'string', ); return apply_filters( 'rest_comment_collection_params', $query_params ); } protected function handle_status_param( $new_status, $comment_id ) { $old_status = wp_get_comment_status( $comment_id ); if ( $new_status === $old_status ) { return false; } switch ( $new_status ) { case 'approved': case 'approve': case '1': $changed = wp_set_comment_status( $comment_id, 'approve' ); break; case 'hold': case '0': $changed = wp_set_comment_status( $comment_id, 'hold' ); break; case 'spam': $changed = wp_spam_comment( $comment_id ); break; case 'unspam': $changed = wp_unspam_comment( $comment_id ); break; case 'trash': $changed = wp_trash_comment( $comment_id ); break; case 'untrash': $changed = wp_untrash_comment( $comment_id ); break; default: $changed = false; break; } return $changed; } protected function check_read_post_permission( $post, $request ) { $post_type = get_post_type_object( $post->post_type ); if ( ! $post_type ) { return false; } $posts_controller = $post_type->get_rest_controller(); if ( ! $posts_controller instanceof WP_REST_Posts_Controller ) { $posts_controller = new WP_REST_Posts_Controller( $post->post_type ); } $has_password_filter = false; $requested_post = ! empty( $request['post'] ) && ( ! is_array( $request['post'] ) || 1 === count( $request['post'] ) ); $requested_comment = ! empty( $request['id'] ); if ( ( $requested_post || $requested_comment ) && $posts_controller->can_access_password_content( $post, $request ) ) { add_filter( 'post_password_required', '__return_false' ); $has_password_filter = true; } if ( post_password_required( $post ) ) { $result = current_user_can( 'edit_post', $post->ID ); } else { $result = $posts_controller->check_read_permission( $post ); } if ( $has_password_filter ) { remove_filter( 'post_password_required', '__return_false' ); } return $result; } protected function check_read_permission( $comment, $request ) { if ( ! empty( $comment->comment_post_ID ) ) { $post = get_post( $comment->comment_post_ID ); if ( $post ) { if ( $this->check_read_post_permission( $post, $request ) && 1 === (int) $comment->comment_approved ) { return true; } } } if ( 0 === get_current_user_id() ) { return false; } if ( empty( $comment->comment_post_ID ) && ! current_user_can( 'moderate_comments' ) ) { return false; } if ( ! empty( $comment->user_id ) && get_current_user_id() === (int) $comment->user_id ) { return true; } return current_user_can( 'edit_comment', $comment->comment_ID ); } protected function check_edit_permission( $comment ) { if ( 0 === (int) get_current_user_id() ) { return false; } if ( current_user_can( 'moderate_comments' ) ) { return true; } return current_user_can( 'edit_comment', $comment->comment_ID ); } public function check_comment_author_email( $value, $request, $param ) { $email = (string) $value; if ( empty( $email ) ) { return $email; } $check_email = rest_validate_request_arg( $email, $request, $param ); if ( is_wp_error( $check_email ) ) { return $check_email; } return $email; } protected function check_is_comment_content_allowed( $prepared_comment ) { $check = wp_parse_args( $prepared_comment, array( 'comment_post_ID' => 0, 'comment_author' => null, 'comment_author_email' => null, 'comment_author_url' => null, 'comment_parent' => 0, 'user_id' => 0, ) ); $allow_empty = apply_filters( 'allow_empty_comment', false, $check ); if ( $allow_empty ) { return true; } return '' !== $check['comment_content']; } } 