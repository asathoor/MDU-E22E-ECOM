<?php
 function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) { if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) { if ( $wp_error ) { return new WP_Error( 'invalid_timestamp', __( 'Event timestamp must be a valid Unix timestamp.' ) ); } return false; } $event = (object) array( 'hook' => $hook, 'timestamp' => $timestamp, 'schedule' => false, 'args' => $args, ); $pre = apply_filters( 'pre_schedule_event', null, $event, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_schedule_event_false', __( 'A plugin prevented the event from being scheduled.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } $crons = _get_cron_array(); $key = md5( serialize( $event->args ) ); $duplicate = false; if ( $event->timestamp < time() + 10 * MINUTE_IN_SECONDS ) { $min_timestamp = 0; } else { $min_timestamp = $event->timestamp - 10 * MINUTE_IN_SECONDS; } if ( $event->timestamp < time() ) { $max_timestamp = time() + 10 * MINUTE_IN_SECONDS; } else { $max_timestamp = $event->timestamp + 10 * MINUTE_IN_SECONDS; } foreach ( $crons as $event_timestamp => $cron ) { if ( $event_timestamp < $min_timestamp ) { continue; } if ( $event_timestamp > $max_timestamp ) { break; } if ( isset( $cron[ $event->hook ][ $key ] ) ) { $duplicate = true; break; } } if ( $duplicate ) { if ( $wp_error ) { return new WP_Error( 'duplicate_event', __( 'A duplicate event already exists.' ) ); } return false; } $event = apply_filters( 'schedule_event', $event ); if ( ! $event ) { if ( $wp_error ) { return new WP_Error( 'schedule_event_false', __( 'A plugin disallowed this event.' ) ); } return false; } $crons[ $event->timestamp ][ $event->hook ][ $key ] = array( 'schedule' => $event->schedule, 'args' => $event->args, ); uksort( $crons, 'strnatcasecmp' ); return _set_cron_array( $crons, $wp_error ); } function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array(), $wp_error = false ) { if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) { if ( $wp_error ) { return new WP_Error( 'invalid_timestamp', __( 'Event timestamp must be a valid Unix timestamp.' ) ); } return false; } $schedules = wp_get_schedules(); if ( ! isset( $schedules[ $recurrence ] ) ) { if ( $wp_error ) { return new WP_Error( 'invalid_schedule', __( 'Event schedule does not exist.' ) ); } return false; } $event = (object) array( 'hook' => $hook, 'timestamp' => $timestamp, 'schedule' => $recurrence, 'args' => $args, 'interval' => $schedules[ $recurrence ]['interval'], ); $pre = apply_filters( 'pre_schedule_event', null, $event, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_schedule_event_false', __( 'A plugin prevented the event from being scheduled.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } $event = apply_filters( 'schedule_event', $event ); if ( ! $event ) { if ( $wp_error ) { return new WP_Error( 'schedule_event_false', __( 'A plugin disallowed this event.' ) ); } return false; } $key = md5( serialize( $event->args ) ); $crons = _get_cron_array(); $crons[ $event->timestamp ][ $event->hook ][ $key ] = array( 'schedule' => $event->schedule, 'args' => $event->args, 'interval' => $event->interval, ); uksort( $crons, 'strnatcasecmp' ); return _set_cron_array( $crons, $wp_error ); } function wp_reschedule_event( $timestamp, $recurrence, $hook, $args = array(), $wp_error = false ) { if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) { if ( $wp_error ) { return new WP_Error( 'invalid_timestamp', __( 'Event timestamp must be a valid Unix timestamp.' ) ); } return false; } $schedules = wp_get_schedules(); $interval = 0; if ( isset( $schedules[ $recurrence ] ) ) { $interval = $schedules[ $recurrence ]['interval']; } if ( 0 === $interval ) { $scheduled_event = wp_get_scheduled_event( $hook, $args, $timestamp ); if ( $scheduled_event && isset( $scheduled_event->interval ) ) { $interval = $scheduled_event->interval; } } $event = (object) array( 'hook' => $hook, 'timestamp' => $timestamp, 'schedule' => $recurrence, 'args' => $args, 'interval' => $interval, ); $pre = apply_filters( 'pre_reschedule_event', null, $event, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_reschedule_event_false', __( 'A plugin prevented the event from being rescheduled.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } if ( 0 === $interval ) { if ( $wp_error ) { return new WP_Error( 'invalid_schedule', __( 'Event schedule does not exist.' ) ); } return false; } $now = time(); if ( $timestamp >= $now ) { $timestamp = $now + $interval; } else { $timestamp = $now + ( $interval - ( ( $now - $timestamp ) % $interval ) ); } return wp_schedule_event( $timestamp, $recurrence, $hook, $args, $wp_error ); } function wp_unschedule_event( $timestamp, $hook, $args = array(), $wp_error = false ) { if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) { if ( $wp_error ) { return new WP_Error( 'invalid_timestamp', __( 'Event timestamp must be a valid Unix timestamp.' ) ); } return false; } $pre = apply_filters( 'pre_unschedule_event', null, $timestamp, $hook, $args, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_unschedule_event_false', __( 'A plugin prevented the event from being unscheduled.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } $crons = _get_cron_array(); $key = md5( serialize( $args ) ); unset( $crons[ $timestamp ][ $hook ][ $key ] ); if ( empty( $crons[ $timestamp ][ $hook ] ) ) { unset( $crons[ $timestamp ][ $hook ] ); } if ( empty( $crons[ $timestamp ] ) ) { unset( $crons[ $timestamp ] ); } return _set_cron_array( $crons, $wp_error ); } function wp_clear_scheduled_hook( $hook, $args = array(), $wp_error = false ) { if ( ! is_array( $args ) ) { _deprecated_argument( __FUNCTION__, '3.0.0', __( 'This argument has changed to an array to match the behavior of the other cron functions.' ) ); $args = array_slice( func_get_args(), 1 ); $wp_error = false; } $pre = apply_filters( 'pre_clear_scheduled_hook', null, $hook, $args, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_clear_scheduled_hook_false', __( 'A plugin prevented the hook from being cleared.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } $crons = _get_cron_array(); if ( empty( $crons ) ) { return 0; } $results = array(); $key = md5( serialize( $args ) ); foreach ( $crons as $timestamp => $cron ) { if ( isset( $cron[ $hook ][ $key ] ) ) { $results[] = wp_unschedule_event( $timestamp, $hook, $args, true ); } } $errors = array_filter( $results, 'is_wp_error' ); $error = new WP_Error(); if ( $errors ) { if ( $wp_error ) { array_walk( $errors, array( $error, 'merge_from' ) ); return $error; } return false; } return count( $results ); } function wp_unschedule_hook( $hook, $wp_error = false ) { $pre = apply_filters( 'pre_unschedule_hook', null, $hook, $wp_error ); if ( null !== $pre ) { if ( $wp_error && false === $pre ) { return new WP_Error( 'pre_unschedule_hook_false', __( 'A plugin prevented the hook from being cleared.' ) ); } if ( ! $wp_error && is_wp_error( $pre ) ) { return false; } return $pre; } $crons = _get_cron_array(); if ( empty( $crons ) ) { return 0; } $results = array(); foreach ( $crons as $timestamp => $args ) { if ( ! empty( $crons[ $timestamp ][ $hook ] ) ) { $results[] = count( $crons[ $timestamp ][ $hook ] ); } unset( $crons[ $timestamp ][ $hook ] ); if ( empty( $crons[ $timestamp ] ) ) { unset( $crons[ $timestamp ] ); } } if ( empty( $results ) ) { return 0; } $set = _set_cron_array( $crons, $wp_error ); if ( true === $set ) { return array_sum( $results ); } return $set; } function wp_get_scheduled_event( $hook, $args = array(), $timestamp = null ) { $pre = apply_filters( 'pre_get_scheduled_event', null, $hook, $args, $timestamp ); if ( null !== $pre ) { return $pre; } if ( null !== $timestamp && ! is_numeric( $timestamp ) ) { return false; } $crons = _get_cron_array(); if ( empty( $crons ) ) { return false; } $key = md5( serialize( $args ) ); if ( ! $timestamp ) { $next = false; foreach ( $crons as $timestamp => $cron ) { if ( isset( $cron[ $hook ][ $key ] ) ) { $next = $timestamp; break; } } if ( ! $next ) { return false; } $timestamp = $next; } elseif ( ! isset( $crons[ $timestamp ][ $hook ][ $key ] ) ) { return false; } $event = (object) array( 'hook' => $hook, 'timestamp' => $timestamp, 'schedule' => $crons[ $timestamp ][ $hook ][ $key ]['schedule'], 'args' => $args, ); if ( isset( $crons[ $timestamp ][ $hook ][ $key ]['interval'] ) ) { $event->interval = $crons[ $timestamp ][ $hook ][ $key ]['interval']; } return $event; } function wp_next_scheduled( $hook, $args = array() ) { $next_event = wp_get_scheduled_event( $hook, $args ); if ( ! $next_event ) { return false; } return $next_event->timestamp; } function spawn_cron( $gmt_time = 0 ) { if ( ! $gmt_time ) { $gmt_time = microtime( true ); } if ( defined( 'DOING_CRON' ) || isset( $_GET['doing_wp_cron'] ) ) { return false; } $lock = (float) get_transient( 'doing_cron' ); if ( $lock > $gmt_time + 10 * MINUTE_IN_SECONDS ) { $lock = 0; } if ( $lock + WP_CRON_LOCK_TIMEOUT > $gmt_time ) { return false; } $crons = wp_get_ready_cron_jobs(); if ( empty( $crons ) ) { return false; } $keys = array_keys( $crons ); if ( isset( $keys[0] ) && $keys[0] > $gmt_time ) { return false; } if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) { if ( 'GET' !== $_SERVER['REQUEST_METHOD'] || defined( 'DOING_AJAX' ) || defined( 'XMLRPC_REQUEST' ) ) { return false; } $doing_wp_cron = sprintf( '%.22F', $gmt_time ); set_transient( 'doing_cron', $doing_wp_cron ); ob_start(); wp_redirect( add_query_arg( 'doing_wp_cron', $doing_wp_cron, wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); echo ' '; wp_ob_end_flush_all(); flush(); require_once ABSPATH . 'wp-cron.php'; return true; } $doing_wp_cron = sprintf( '%.22F', $gmt_time ); set_transient( 'doing_cron', $doing_wp_cron ); $cron_request = apply_filters( 'cron_request', array( 'url' => add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) ), 'key' => $doing_wp_cron, 'args' => array( 'timeout' => 0.01, 'blocking' => false, 'sslverify' => apply_filters( 'https_local_ssl_verify', false ), ), ), $doing_wp_cron ); $result = wp_remote_post( $cron_request['url'], $cron_request['args'] ); return ! is_wp_error( $result ); } function wp_cron() { if ( did_action( 'wp_loaded' ) ) { return _wp_cron(); } add_action( 'wp_loaded', '_wp_cron', 20 ); } function _wp_cron() { if ( str_contains( $_SERVER['REQUEST_URI'], '/wp-cron.php' ) || ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) { return 0; } $crons = wp_get_ready_cron_jobs(); if ( empty( $crons ) ) { return 0; } $gmt_time = microtime( true ); $keys = array_keys( $crons ); if ( isset( $keys[0] ) && $keys[0] > $gmt_time ) { return 0; } $schedules = wp_get_schedules(); $results = array(); foreach ( $crons as $timestamp => $cronhooks ) { if ( $timestamp > $gmt_time ) { break; } foreach ( (array) $cronhooks as $hook => $args ) { if ( isset( $schedules[ $hook ]['callback'] ) && ! call_user_func( $schedules[ $hook ]['callback'] ) ) { continue; } $results[] = spawn_cron( $gmt_time ); break 2; } } if ( in_array( false, $results, true ) ) { return false; } return count( $results ); } function wp_get_schedules() { $schedules = array( 'hourly' => array( 'interval' => HOUR_IN_SECONDS, 'display' => __( 'Once Hourly' ), ), 'twicedaily' => array( 'interval' => 12 * HOUR_IN_SECONDS, 'display' => __( 'Twice Daily' ), ), 'daily' => array( 'interval' => DAY_IN_SECONDS, 'display' => __( 'Once Daily' ), ), 'weekly' => array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once Weekly' ), ), ); return array_merge( apply_filters( 'cron_schedules', array() ), $schedules ); } function wp_get_schedule( $hook, $args = array() ) { $schedule = false; $event = wp_get_scheduled_event( $hook, $args ); if ( $event ) { $schedule = $event->schedule; } return apply_filters( 'get_schedule', $schedule, $hook, $args ); } function wp_get_ready_cron_jobs() { $pre = apply_filters( 'pre_get_ready_cron_jobs', null ); if ( null !== $pre ) { return $pre; } $crons = _get_cron_array(); $gmt_time = microtime( true ); $results = array(); foreach ( $crons as $timestamp => $cronhooks ) { if ( $timestamp > $gmt_time ) { break; } $results[ $timestamp ] = $cronhooks; } return $results; } function _get_cron_array() { $cron = get_option( 'cron' ); if ( ! is_array( $cron ) ) { return array(); } if ( ! isset( $cron['version'] ) ) { $cron = _upgrade_cron_array( $cron ); } unset( $cron['version'] ); return $cron; } function _set_cron_array( $cron, $wp_error = false ) { if ( ! is_array( $cron ) ) { $cron = array(); } $cron['version'] = 2; $result = update_option( 'cron', $cron ); if ( $wp_error && ! $result ) { return new WP_Error( 'could_not_set', __( 'The cron event list could not be saved.' ) ); } return $result; } function _upgrade_cron_array( $cron ) { if ( isset( $cron['version'] ) && 2 === $cron['version'] ) { return $cron; } $new_cron = array(); foreach ( (array) $cron as $timestamp => $hooks ) { foreach ( (array) $hooks as $hook => $args ) { $key = md5( serialize( $args['args'] ) ); $new_cron[ $timestamp ][ $hook ][ $key ] = $args; } } $new_cron['version'] = 2; update_option( 'cron', $new_cron ); return $new_cron; } 