<?php
 if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); } if ( function_exists( 'error_reporting' ) ) { error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR ); } if ( file_exists( ABSPATH . 'wp-config.php' ) ) { require_once ABSPATH . 'wp-config.php'; } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) { require_once dirname( ABSPATH ) . '/wp-config.php'; } else { define( 'WPINC', 'wp-includes' ); require_once ABSPATH . WPINC . '/version.php'; require_once ABSPATH . WPINC . '/compat.php'; require_once ABSPATH . WPINC . '/load.php'; wp_check_php_mysql_versions(); wp_fix_server_vars(); define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' ); require_once ABSPATH . WPINC . '/functions.php'; $path = wp_guess_url() . '/wp-admin/setup-config.php'; if ( ! str_contains( $_SERVER['REQUEST_URI'], 'setup-config' ) ) { header( 'Location: ' . $path ); exit; } wp_load_translations_early(); $die = '<p>' . sprintf( __( "There doesn't seem to be a %s file. It is needed before the installation can continue." ), '<code>wp-config.php</code>' ) . '</p>'; $die .= '<p>' . sprintf( __( 'Need more help? <a href="%1$s">Read the support article on %2$s</a>.' ), __( 'https://developer.wordpress.org/advanced-administration/wordpress/wp-config/' ), '<code>wp-config.php</code>' ) . '</p>'; $die .= '<p>' . sprintf( __( "You can create a %s file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file." ), '<code>wp-config.php</code>' ) . '</p>'; $die .= '<p><a href="' . $path . '" class="button button-large">' . __( 'Create a Configuration File' ) . '</a></p>'; wp_die( $die, __( 'WordPress &rsaquo; Error' ) ); } 