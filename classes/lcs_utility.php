<?php

namespace lcs\security;

if (!defined('ABSPATH')) exit;

if (!class_exists('lcs_utility')) :
	class lcs_utility
	{
        public static function lcs_encrypt($string, $key) {
            return @openssl_encrypt($string, 'AES-128-CFB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }

        public static function lcs_decrypt($string, $key) {
            return @openssl_decrypt($string, 'AES-128-CFB', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }
    }
endif;