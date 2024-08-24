<?php
 if ( ! function_exists( '_' ) ) { function _( $message ) { return $message; } } function _wp_can_use_pcre_u( $set = null ) { static $utf8_pcre = 'reset'; if ( null !== $set ) { $utf8_pcre = $set; } if ( 'reset' === $utf8_pcre ) { $utf8_pcre = @preg_match( '/^./u', 'a' ); } return $utf8_pcre; } function _is_utf8_charset( $charset_slug ) { if ( ! is_string( $charset_slug ) ) { return false; } return ( 0 === strcasecmp( 'UTF-8', $charset_slug ) || 0 === strcasecmp( 'UTF8', $charset_slug ) ); } if ( ! function_exists( 'mb_substr' ) ) : function mb_substr( $string, $start, $length = null, $encoding = null ) { return _mb_substr( $string, $start, $length, $encoding ); } endif; function _mb_substr( $str, $start, $length = null, $encoding = null ) { if ( null === $str ) { return ''; } if ( null === $encoding ) { $encoding = get_option( 'blog_charset' ); } if ( ! _is_utf8_charset( $encoding ) ) { return is_null( $length ) ? substr( $str, $start ) : substr( $str, $start, $length ); } if ( _wp_can_use_pcre_u() ) { preg_match_all( '/./us', $str, $match ); $chars = is_null( $length ) ? array_slice( $match[0], $start ) : array_slice( $match[0], $start, $length ); return implode( '', $chars ); } $regex = '/(
		[\x00-\x7F]                  # single-byte sequences   0xxxxxxx
		| [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
		| \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		| [\xE1-\xEC][\x80-\xBF]{2}
		| \xED[\x80-\x9F][\x80-\xBF]
		| [\xEE-\xEF][\x80-\xBF]{2}
		| \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
		| [\xF1-\xF3][\x80-\xBF]{3}
		| \xF4[\x80-\x8F][\x80-\xBF]{2}
	)/x'; $chars = array( '' ); do { array_pop( $chars ); $pieces = preg_split( $regex, $str, 1000, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ); $chars = array_merge( $chars, $pieces ); } while ( count( $pieces ) > 1 && $str = array_pop( $pieces ) ); return implode( '', array_slice( $chars, $start, $length ) ); } if ( ! function_exists( 'mb_strlen' ) ) : function mb_strlen( $string, $encoding = null ) { return _mb_strlen( $string, $encoding ); } endif; function _mb_strlen( $str, $encoding = null ) { if ( null === $encoding ) { $encoding = get_option( 'blog_charset' ); } if ( ! _is_utf8_charset( $encoding ) ) { return strlen( $str ); } if ( _wp_can_use_pcre_u() ) { preg_match_all( '/./us', $str, $match ); return count( $match[0] ); } $regex = '/(?:
		[\x00-\x7F]                  # single-byte sequences   0xxxxxxx
		| [\xC2-\xDF][\x80-\xBF]       # double-byte sequences   110xxxxx 10xxxxxx
		| \xE0[\xA0-\xBF][\x80-\xBF]   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		| [\xE1-\xEC][\x80-\xBF]{2}
		| \xED[\x80-\x9F][\x80-\xBF]
		| [\xEE-\xEF][\x80-\xBF]{2}
		| \xF0[\x90-\xBF][\x80-\xBF]{2} # four-byte sequences   11110xxx 10xxxxxx * 3
		| [\xF1-\xF3][\x80-\xBF]{3}
		| \xF4[\x80-\x8F][\x80-\xBF]{2}
	)/x'; $count = 1; do { --$count; $pieces = preg_split( $regex, $str, 1000 ); $count += count( $pieces ); } while ( $str = array_pop( $pieces ) ); return --$count; } if ( ! function_exists( 'hash_hmac' ) ) : function hash_hmac( $algo, $data, $key, $binary = false ) { return _hash_hmac( $algo, $data, $key, $binary ); } endif; function _hash_hmac( $algo, $data, $key, $binary = false ) { $packs = array( 'md5' => 'H32', 'sha1' => 'H40', ); if ( ! isset( $packs[ $algo ] ) ) { return false; } $pack = $packs[ $algo ]; if ( strlen( $key ) > 64 ) { $key = pack( $pack, $algo( $key ) ); } $key = str_pad( $key, 64, chr( 0 ) ); $ipad = ( substr( $key, 0, 64 ) ^ str_repeat( chr( 0x36 ), 64 ) ); $opad = ( substr( $key, 0, 64 ) ^ str_repeat( chr( 0x5C ), 64 ) ); $hmac = $algo( $opad . pack( $pack, $algo( $ipad . $data ) ) ); if ( $binary ) { return pack( $pack, $hmac ); } return $hmac; } if ( ! function_exists( 'hash_equals' ) ) : function hash_equals( $known_string, $user_string ) { $known_string_length = strlen( $known_string ); if ( strlen( $user_string ) !== $known_string_length ) { return false; } $result = 0; for ( $i = 0; $i < $known_string_length; $i++ ) { $result |= ord( $known_string[ $i ] ) ^ ord( $user_string[ $i ] ); } return 0 === $result; } endif; if ( ! function_exists( 'sodium_crypto_box' ) ) { require ABSPATH . WPINC . '/sodium_compat/autoload.php'; } if ( ! function_exists( 'is_countable' ) ) { function is_countable( $value ) { return ( is_array( $value ) || $value instanceof Countable || $value instanceof SimpleXMLElement || $value instanceof ResourceBundle ); } } if ( ! function_exists( 'array_key_first' ) ) { function array_key_first( array $array ) { foreach ( $array as $key => $value ) { return $key; } } } if ( ! function_exists( 'array_key_last' ) ) { function array_key_last( array $array ) { if ( empty( $array ) ) { return null; } end( $array ); return key( $array ); } } if ( ! function_exists( 'array_is_list' ) ) { function array_is_list( $arr ) { if ( ( array() === $arr ) || ( array_values( $arr ) === $arr ) ) { return true; } $next_key = -1; foreach ( $arr as $k => $v ) { if ( ++$next_key !== $k ) { return false; } } return true; } } if ( ! function_exists( 'str_contains' ) ) { function str_contains( $haystack, $needle ) { if ( '' === $needle ) { return true; } return false !== strpos( $haystack, $needle ); } } if ( ! function_exists( 'str_starts_with' ) ) { function str_starts_with( $haystack, $needle ) { if ( '' === $needle ) { return true; } return 0 === strpos( $haystack, $needle ); } } if ( ! function_exists( 'str_ends_with' ) ) { function str_ends_with( $haystack, $needle ) { if ( '' === $haystack ) { return '' === $needle; } $len = strlen( $needle ); return substr( $haystack, -$len, $len ) === $needle; } } if ( ! defined( 'IMAGETYPE_AVIF' ) ) { define( 'IMAGETYPE_AVIF', 19 ); } if ( ! defined( 'IMG_AVIF' ) ) { define( 'IMG_AVIF', IMAGETYPE_AVIF ); } 