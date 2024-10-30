<?php
	if (!defined('ABSPATH')) exit;
	if (!defined('WP_UNINSTALL_PLUGIN')) :
		die;
	endif;
	global $wpdb;
	$settings = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'lcs_sec_%'" );
	foreach ($settings as $option) :
		delete_option($option->option_name);
	endforeach;
	$table_prefix = $wpdb->prefix;
	$table_name = $table_prefix . 'lcs_sec_log';
	$sql = " DROP TABLE `$table_name` ";
	$wpdb->query($sql);
	$table_name = $table_prefix . 'lcs_sec_ip';
	$sql = " DROP TABLE `$table_name` ";
	$wpdb->query($sql);
?>