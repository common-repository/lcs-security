<?php
/*
Plugin Name: LCS Security
Plugin URI: http://www.latcomsystems.com/index.cfm?SheetIndex=wp_lcs_security
Description: Adds a comprehensive suite of security measures to WordPress.
Version: 2.5
Author: LatCom Systems
Author URI: http://www.latcomsystems.com/
License: GPLv2
Licence URI: http://www.gnu.org/licenses/gpl-2.0.html
Copyright 2016-2019 LatCom Systems
*/

if (!defined('ABSPATH')) exit;

if (!class_exists('lcs_security')) :
	class lcs_security
	{
		const LCS_SEC_CONFIG_FILES = 'script_block_uploads,script_block_includes';
		private $ar_config_files = array();
		private $settings_prefix = 'lcs_sec_';
		private $settings = array ();
		
		public function __construct()
		{
			session_start();
			if (empty($_SESSION['lcs_security_rand_key'])) :
				$_SESSION['lcs_security_rand_key'] = $this->generate_random_string(32);
			endif;
			session_write_close();
			global $is_IIS;
			global $is_iis7;
			global $is_apache;
			global $is_nginx;
			$this->settings = array (
				'block_xml_rpc' => array('type' => 'int', 'default' => '1'),
				'block_author_scan' => array('type' => 'int', 'default' => '1'),
				'disable_code_editing' => array('type' => 'int', 'default' => '1'),
				'comment_captcha' => array('type' => 'int', 'default' => '1'),
				'login_captcha' => array('type' => 'int', 'default' => '1'),
				'login_captcha_failed' => array('type' => 'int', 'default' => '1'),
				'login_lockout' => array('type' => 'int', 'default' => '1'),
				'login_lockout_attempts' => array('type' => 'int', 'default' => '4'),
				'login_lockout_minutes' => array('type' => 'int', 'default' => '30'),
				'auto_ban' => array('type' => 'int', 'default' => '1'),
				'auto_ban_attempts' => array('type' => 'int', 'default' => '12'),
				'script_block_uploads' => array('type' => 'int', 'default' => ($is_iis7 || $is_apache ? '1' : '0')),
				'script_block_includes' => array('type' => 'int', 'default' => ($is_iis7 || $is_apache ? '1' : '0')),
				'ip_blacklist' => array('type' => 'text', 'default' => ''),
				'ip_whitelist' => array('type' => 'text', 'default' => ($this->get_ip() > '' && $this->get_ip() != 'UNKNOWN' ? $this->get_ip() : '')),
			);
			$this->ar_config_files = explode(',', self::LCS_SEC_CONFIG_FILES);
			register_activation_hook(__FILE__, array($this, 'lcs_sec_activation'));
			register_deactivation_hook(__FILE__, array($this, 'lcs_sec_deactivation'));
			$this->check_for_updates();
			$this->lcs_sec_run();
			//add_action('init', array($this, 'lcs_sec_run'));
			add_action('admin_menu', array($this, 'lcs_sec_menu'));
			add_action('wp_login_failed', array($this, 'login_failed'));
			add_action('wp_login', array($this, 'login_success'), 10, 2);
			add_action('wp_logout', array($this, 'logout'));
			require_once(__DIR__.'/classes/lcs_export_excel.php');
			$export_excel = new lcs\security\lcs_export_excel();
		}
		
		public function lcs_sec_activation()
		{
			global $wpdb;
			$table_prefix = $wpdb->prefix;
			$table_name = $table_prefix . 'lcs_sec_log';
			if($wpdb->get_var("show tables like '$table_name'") != $table_name) :
				$sql = "CREATE TABLE `$table_name` (
						`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`log_time` DATETIME NOT NULL ,
						`log_type` VARCHAR (25) NOT NULL ,
						`long_time` INT(20) UNSIGNED NOT NULL ,
						`ip` VARCHAR(100) NULL ,
						`user_name` VARCHAR(255) NULL ,
						`log_text` VARCHAR(255) NULL,
						`http_user_agent` VARCHAR( 255 ) NULL 
						);";
				$wpdb->query($sql);
				$sql = "ALTER TABLE `$table_name` ADD INDEX (`ip`);";
				$wpdb->query($sql);
			endif;	
			/*
				ip_status in the following table is as follows:
				0 - IP is open, no restrictions
				1 - IP is being monitored for a lockout
				2 - IP is currently in a temporary lockout
				3 - IP is banned from site completely
			*/
			$table_name = $table_prefix . 'lcs_sec_ip';
			if($wpdb->get_var("show tables like '$table_name'") != $table_name) :
				$sql = "CREATE TABLE `$table_name` (
						`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`ip` VARCHAR(100) NOT NULL ,
						`update_time` DATETIME NOT NULL ,
						`ip_status` SMALLINT NOT NULL ,
						`ip_status_time` DATETIME NOT NULL ,
						`failed_attempts_ban` INT( 11 ) NOT NULL ,
						`failed_attempts_lockout` INT( 11 ) NOT NULL ,
						`country_code` VARCHAR(25) NULL ,
						`country_name` VARCHAR(255) NULL ,
						`region_code` VARCHAR(25) NULL ,
						`region_name` VARCHAR(255) NULL ,
						`city` VARCHAR(255) NULL ,
						`latitude` VARCHAR(255) NULL ,
						`longitude` VARCHAR(255) NULL 
						);";
				$wpdb->query($sql);
				$sql = "ALTER TABLE `$table_name` ADD UNIQUE INDEX (`ip`);";
				$wpdb->query($sql);
			endif;	
			foreach ($this->settings as $key => $value) :
				if (get_option($this->settings_prefix.$key) === false) :
					update_option($this->settings_prefix.$key, $value['default']);
				endif;
			endforeach;
			foreach ($this->ar_config_files as $key) :
				if (!$this->update_config_file($key, get_option($this->settings_prefix.$key))) :
					update_option($this->settings_prefix.$key, '0');
				endif;
			endforeach;
		}
		
		public function lcs_sec_deactivation()
		{
			foreach ($this->ar_config_files as $key) :
				$this->update_config_file($key, 0);
			endforeach;
		}

		private function check_for_updates() {
			if (is_admin()) :
				$metadata = get_file_data(__FILE__, ['Version' => 'Version'], false);
				$plugin_version = $metadata['Version'];
				if (get_option($this->settings_prefix.'version') != $plugin_version) :
					add_action('admin_menu', array($this, 'lcs_sec_activation'));
					update_option($this->settings_prefix.'version', $plugin_version);
				endif;
			endif;
		}

		public function lcs_sec_menu()
		{
			session_start();
			add_menu_page(__('LCS Security'), __('LCS Security'), 'activate_plugins', 'lcs_sec_admin', array($this, 'lcs_sec_options'), 'dashicons-shield', NULL);
			add_submenu_page('lcs_sec_admin', __('Options'), __('Options'), 'activate_plugins', 'lcs_sec_admin', array($this, 'lcs_sec_options'));
			add_submenu_page('lcs_sec_admin', __('Log'), __('Log'), 'activate_plugins', 'lcs_sec_log', array($this, 'lcs_sec_log'));
			add_submenu_page('lcs_sec_admin', __('Locked IP\'s'), __('Locked IP\'s'), 'activate_plugins', 'lcs_sec_ip_locked', array($this, 'lcs_sec_ip_locked'));
			add_submenu_page('lcs_sec_admin', __('Banned IP\'s'), __('Banned IP\'s'), 'activate_plugins', 'lcs_sec_ip_banned', array($this, 'lcs_sec_ip_banned'));
			session_write_close();
		}
	
		public function lcs_sec_options()
		{
			wp_enqueue_style( 'lcs-sec-admin-style', plugins_url('lcs_sec_admin.css', __FILE__ ), array(), filemtime(dirname(__FILE__).'/lcs_sec_admin.css'));
			echo '<h1>LCS Security Options</h1>';
			$_POST = stripslashes_deep($_POST);
			if (is_array($_POST)) :
				$_POST = $this->array_map_r('trim', $_POST);
			endif;
			global $is_IIS;
			global $is_iis7;
			global $is_apache;
			global $is_nginx;
			$form_message = '';
			require_once(__DIR__.'/classes/lcs_forms.php');
			$form = new lcs\security\lcs_forms();
			$form->err_flag = false;
			if ($_POST['option_form_submitted'] == '1') :
				check_admin_referer('lcs_sec_options');
				if ($_POST['login_lockout_attempts'] < '1' || $_POST['login_lockout_attempts'] > '99') :
					$form->ar_err['login_lockout_attempts'] = 'Invalid!';
					$form->err_flag = true;
				endif;
				if ($_POST['login_lockout_minutes'] < '1' || $_POST['login_lockout_minutes'] > '999') :
					$form->ar_err['login_lockout_minutes'] = 'Invalid!';
					$form->err_flag = true;
				endif;
				if ($_POST['auto_ban_attempts'] < '1' || $_POST['auto_ban_attempts'] > '99') :
					$form->ar_err['auto_ban_attempts'] = 'Invalid!';
					$form->err_flag = true;
				endif;
				if (!$form->err_flag) :
					foreach ($this->settings as $key => $value) :
						if ($value['type'] == 'int') :
							$_POST[$key] = $this->nz($_POST[$key]);
						endif;
						if (in_array($key, $this->ar_config_files)) :
							if ($this->update_config_file($key, $_POST[$key])) :
								update_option($this->settings_prefix.$key, $_POST[$key]);
							else :
								$form->ar_err[$key] = 'Unable to update config file!';
							endif;
						else :
							update_option($this->settings_prefix.$key, $_POST[$key]);
						endif;
					endforeach;
					$form_message = '<h2 style="color:green;">Saved successfully!</h2>';
				else :
					$form_message = '<h2 style="color:red;">Errors found!</h2>';
				endif;
			else :
				foreach ($this->settings as $key => $value) :
					if (get_option($this->settings_prefix.$key) !== false) :
						$_POST[$key] = get_option($this->settings_prefix.$key);
					endif;
				endforeach;
			endif;
			echo ($form_message > '' ? $form_message : '');
			echo '<form method="post" action="">';
			wp_nonce_field('lcs_sec_options');
			echo '<input name="option_form_submitted" type="hidden" value="1" />';
			echo '<div class="input_column half_width">';
			echo '<hr><h2>XML RPC Protection</h2>';
			$form->form_field('block_xml_rpc', 'checkbox', 50, 0, false, false, false, '', '', 'block_xml_rpc', 'Block XML RPC requests.  Uncheck if using Jetpack forms and they stop working.');
			echo '<hr><h2>Author Scanning Prevention</h2>';
			$form->form_field('block_author_scan', 'checkbox', 50, 0, false, false, false, '', '', 'block_author_scan', 'Block author scan (i.e. yoursite.com/?author=nn or yoursite.com/wp-json/wp/v2/users/nn).  Prevents user name scanning.');
			echo '<hr><h2>Code Editing Prevention</h2>';
			$form->form_field('disable_code_editing', 'checkbox', 50, 0, false, false, false, '', '', 'disable_code_editing', 'Disable code editing within wp-admin.');
			echo '<hr><h2>Malicious Script Blocking</h2>';
			echo 'For script blocking to work, <strong>wp-includes</strong> and/or <strong>wp-content/uploads</strong> directories must be writeable.<br />';
			echo '.htaccess (Apache) / web.config (IIS 7+) must not exist or be writeable in <strong>wp-includes</strong> and/or <strong>wp-content/uploads</strong>.<br />';
			$disabled = ($is_iis7 || $is_apache ? false : true);
			$form->form_field('script_block_uploads', 'checkbox', 50, 0, false, $disabled, $disabled, '', '', 'script_block_uploads', 'Block direct execution of scripts in the UPLOADS folder (Apache/IIS 7+ only).');
			$disabled = ($is_iis7 || $is_apache ? false : true);
			$form->form_field('script_block_includes', 'checkbox', 50, 0, false, $disabled, $disabled, '', '', 'script_block_includes', 'Block direct execution of scripts in the WP-INCLUDES folder (Apache/IIS 7+ only).  Uncheck if some plugins stop working.');
			echo '<hr><h2>Comment Spam Prevention</h2>';
			$form->form_field('comment_captcha', 'checkbox', 50, 0, false, false, false, '', '', 'comment_captcha', 'Use CAPTCHA when adding comments (except if logged in as administrator).');
			echo '<hr><h2>User Login Protection</h2>';
			$form->form_field('login_captcha', 'checkbox', 50, 0, false, false, false, '', '', 'login_captcha', 'Use CAPTCHA on login page.');
			echo '<div class="input_field_indented">';
			$form->form_field('login_captcha_failed', 'checkbox', 50, 0, false, false, false, '', '', 'login_captcha_failed', 'Show CAPTCHA only after a failed attempt or if IP has not been verified yet.');
			echo '</div>';
			$form->form_field('login_lockout', 'checkbox', 50, 0, false, false, false, '', '', 'login_lockout', 'Lockout login page after repeated failed attempts.');
			echo '<div class="input_field_indented">';
			$form->form_field('login_lockout_attempts', 'text', 2, 0, false, false, false, '', 'int_width', 'login_lockout_attempts', 'Number of failed attempts before lockout.  Values 1-99.');
			$form->form_field('login_lockout_minutes', 'text', 3, 0, false, false, false, '', 'int_width', 'login_lockout_minutes', 'Number of minutes for the lockout to last before it resets.  Values 1-999.');
			echo '</div>';
			echo '<hr><h2>Automatic IP Ban</h2>';
			$form->form_field('auto_ban', 'checkbox', 50, 0, false, false, false, '', '', 'auto_ban', 'Permanently ban IP for entire site after a specified number of failed logins.  Values 1-99.<br />');
			echo '<div class="input_field_indented">';
			$form->form_field('auto_ban_attempts', 'text', 2, 0, false, false, false, '', 'int_width', 'auto_ban_attempts', 'Number of failed logins in a row after which to ban IP.');
			echo '</div>';
			echo '<hr><h2>IP Blacklist</h2>';
			$form->form_field('ip_blacklist', 'textarea', 50, 10, false, false, false, 'max-width:500px;', '', 'ip_blacklist', 'One IP address per line. These IP addresses will be banned from the site. <strong>Don\'t ban yourself!</strong>');
			echo '<hr><h2>IP Whitelist</h2>';
			$form->form_field('ip_whitelist', 'textarea', 50, 10, false, false, false, 'max-width:500px;', '', 'ip_whitelist', 'One IP address per line. These IP addresses will always be allowed.  <strong>This list takes precedence over all others</strong>.<br />Your IP is automatically added when you first install and activate this plugin.');
			echo '<hr>';
			echo '</div>';
			echo '<br />';
			echo '<input type="submit" value="Save" />';
			echo '</form>';
			$this->copyright();
		}
	
		public function lcs_sec_log()
		{
			wp_enqueue_style( 'lcs-sec-admin-style', plugins_url('lcs_sec_admin.css', __FILE__ ), array(), filemtime(dirname(__FILE__).'/lcs_sec_admin.css'));
			echo '<h1>LCS Security Log</h1>';
			global $wpdb;
			echo '<p><form action="" method="post"><p>Delete log entries older than 30 days: &emsp;';
			wp_nonce_field('lcs_sec_delete_log');
			echo '<input type="hidden" name="form_cleanup_submitted" value="1" /><input type="submit" value="Clean up log" /></form></p>';
			if ($_POST['form_cleanup_submitted'] == '1') :
				check_admin_referer('lcs_sec_delete_log');
				$sql = "DELETE FROM `".$wpdb->prefix."lcs_sec_log` WHERE log_time < (NOW() - INTERVAL 30 DAY)";
				$wpdb->query($sql);
				echo '<h2 style="color:green;">Old log entries deleted successfully!</h2>';
				$_REQUEST['start_page'] = '1';
				$_GET['start_page'] = '1';
				$_REQUEST['search'] = '';
				$_GET['search'] = '';
			endif;
			require_once(__DIR__.'/classes/lcs_data_panel.php');
			$data_panel = new lcs\security\lcs_data_panel();
			$search_str = '';
			if (!empty($_REQUEST['search'])) :
				$search_str_fields = 'l.log_type,l.ip,l.user_name,l.log_text,i.country_name,i.region_name,i.city,l.http_user_agent';
				$search_int_fields = '';
				$search_str = $data_panel->search_tokenize($search_str_fields, $search_int_fields, $_REQUEST['search']);
			endif;
			$sql = "SELECT l.id, l.log_time, l.log_type, l.ip, l.user_name, l.log_text, i.country_name, i.region_name, i.city, l.http_user_agent FROM `".$wpdb->prefix."lcs_sec_log` l LEFT OUTER JOIN `".$wpdb->prefix."lcs_sec_ip` i ON (l.ip = i.ip) ".$search_str." ORDER BY l.log_time DESC, l.id DESC";
			$data_panel->data_show($sql, array('log_type'=>array($this, 'lcs_sec_log_callbak'), 'log_time'=>array($this, 'lcs_sec_log_callbak')));
			$this->copyright();
		}
		
		public function lcs_sec_log_callbak($value, $row, $field_name)
		{
			if ($field_name == 'log_type') :
				switch (strtolower($value)) :
					case 'warning' :
						$background_color = 'yellow';
						$color = 'black';
						break;
					case 'error' :
						$background_color = 'red';
						$color = 'white';
						break;
					case 'ok' :
						$background_color = 'green';
						$color = 'white';
						break;
				endswitch;
				$retval = '<div style="background-color:'.$background_color.'; color:'.$color.';">'.$value.'</div>';
				return $retval;
			elseif ($field_name == 'log_time') :
				return $this->gmt_to_local($value);
			else :
				return $value;
			endif;
		}
	
		public function lcs_sec_ip_locked()
		{
			wp_enqueue_style( 'lcs-sec-admin-style', plugins_url('lcs_sec_admin.css', __FILE__ ), array(), filemtime(dirname(__FILE__).'/lcs_sec_admin.css'));
			echo '<h1>LCS Security Temporarily Locked IP List</h1>';
			global $wpdb;
			if ($_POST['form_unlock_submitted'] == '1') :
				$sql = $wpdb->prepare("UPDATE `".$wpdb->prefix."lcs_sec_ip` SET ".
						"update_time = %s, ".
						"ip_status = 0, ".
						"ip_status_time = %s, ".
						"failed_attempts_ban = 0, ".
						"failed_attempts_lockout = 0 ".
						"WHERE id = %d"
						, current_time("mysql", 1), current_time("mysql", 1), $_POST['ip_id']);
				$wpdb->query($sql);
				$current_user = wp_get_current_user();
				$this->write_log('Warning', 'IP manually un-locked by admin.', $current_user->user_login, sanitize_text_field($_POST['ip']));
			endif;
			require_once(__DIR__.'/classes/lcs_data_panel.php');
			$data_panel = new lcs\security\lcs_data_panel();
			$search_str = '';
			if (!empty($_REQUEST['search'])) :
				$search_str_fields = 'i.ip,i.country_name,i.region_name,i.city';
				$search_int_fields = '';
				$search_str = $data_panel->search_tokenize($search_str_fields, $search_int_fields, $_REQUEST['search']);
			endif;
			$lockout_minutes = intval(get_option($this->settings_prefix.'login_lockout_minutes'));
			$lockout_cutoff = date('Y-m-d H:i:s', strtotime(current_time("mysql", 1)) - ($lockout_minutes * 60));
			if (trim($search_str) == '') :
				$search_str = " WHERE i.ip_status = 2 AND i.ip_status_time >= '".$lockout_cutoff."' ";
			else :
				$search_str .= " AND i.ip_status = 2 AND i.ip_status_time >= '".$lockout_cutoff."' ";
			endif;
			$sql = "SELECT i.id, i.ip, i.failed_attempts_lockout AS failed_attempts, i.ip_status_time, i.country_name, i.region_name, i.city FROM `".$wpdb->prefix."lcs_sec_ip` i ".$search_str." ORDER BY i.ip_status_time DESC";
			$data_panel->data_show($sql, array('id'=>array($this, 'lcs_sec_ip_locked_callbak'), 'ip_status_time'=>array($this, 'lcs_sec_ip_locked_callbak')));
			$this->copyright();
		}
	
		public function lcs_sec_ip_locked_callbak($value, $row, $field_name)
		{
			if ($field_name == 'id') :
				$retval = $value.'&emsp;<form action="" method="post"><input type="hidden" name="ip_id" value="'.$value.'" /><input type="hidden" name="ip" value="'.$row->ip.'" /><input type="hidden" name="form_unlock_submitted" value="1" /><input type="submit" value="Unlock" /></form>';
				return $retval;
			elseif ($field_name == 'ip_status_time') :
				return $this->gmt_to_local($value);
			else :
				return $value;
			endif;
		}

		public function lcs_sec_ip_banned()
		{
			wp_enqueue_style( 'lcs-sec-admin-style', plugins_url('lcs_sec_admin.css', __FILE__ ), array(), filemtime(dirname(__FILE__).'/lcs_sec_admin.css'));
			echo '<h1>LCS Security Permanently Banned IP List</h1>';
			global $wpdb;
			if ($_POST['form_unban_submitted'] == '1') :
				$sql = $wpdb->prepare("UPDATE `".$wpdb->prefix."lcs_sec_ip` SET ".
						"update_time = %s, ".
						"ip_status = 0, ".
						"ip_status_time = %s, ".
						"failed_attempts_ban = 0, ".
						"failed_attempts_lockout = 0 ".
						"WHERE id = %d"
						, current_time("mysql", 1), current_time("mysql", 1), $_POST['ip_id']);
				$wpdb->query($sql);
				$current_user = wp_get_current_user();
				$this->write_log('Warning', 'IP manually un-banned by admin.', $current_user->user_login, $_POST['ip']);
			endif;
			require_once(__DIR__.'/classes/lcs_data_panel.php');
			$data_panel = new lcs\security\lcs_data_panel();
			$search_str = '';
			if (!empty($_REQUEST['search'])) :
				$search_str_fields = 'i.ip,i.country_name,i.region_name,i.city';
				$search_int_fields = '';
				$search_str = $data_panel->search_tokenize($search_str_fields, $search_int_fields, $_REQUEST['search']);
			endif;
			if (trim($search_str) == '') :
				$search_str = " WHERE i.ip_status = 3 ";
			else :
				$search_str .= " AND i.ip_status = 3 ";
			endif;
			$sql = "SELECT i.id, i.ip, i.failed_attempts_ban AS failed_attempts, i.ip_status_time, i.country_name, i.region_name, i.city FROM `".$wpdb->prefix."lcs_sec_ip` i ".$search_str." ORDER BY i.ip_status_time DESC";
			$data_panel->data_show($sql, array('id'=>array($this, 'lcs_sec_ip_banned_callbak'), 'ip_status_time'=>array($this, 'lcs_sec_ip_banned_callbak')));
			$this->copyright();
		}
		
		public function lcs_sec_ip_banned_callbak($value, $row, $field_name)
		{
			if ($field_name == 'id') :
				$retval = $value.'&emsp;<form action="" method="post"><input type="hidden" name="ip_id" value="'.$value.'" /><input type="hidden" name="ip" value="'.$row->ip.'" /><input type="hidden" name="form_unban_submitted" value="1" /><input type="submit" value="Un-ban" /></form>';
				return $retval;
			elseif ($field_name == 'ip_status_time') :
				return $this->gmt_to_local($value);
			else :
				return $value;
			endif;
		}
		
		private function gmt_to_local($datetime_gmt)
		{
			$date_datetime = new DateTime( $datetime_gmt, new DateTimeZone('GMT') );
			//$date_format = get_option('date_format') . ' - '. get_option('time_format');
			$date_format = 'Y-m-d H:i:s';
			$datetime_local = get_date_from_gmt( $date_datetime->format('Y-m-d H:i:s'), $date_format );
			return $datetime_local;
		}

		public function lcs_sec_run()
		{
			//error_log('entering lcs run');
			$this->ban_check();
			if (get_option($this->settings_prefix.'login_lockout') == '1') :
				$this->login_lockout_check();
			endif;
			if (get_option($this->settings_prefix.'block_xml_rpc') == '1') :
				$this->block_xml_rpc();
			endif;
			if (get_option($this->settings_prefix.'block_author_scan') == '1') :
				$this->block_author_scan();
			endif;
			if (get_option($this->settings_prefix.'comment_captcha') == '1') :
				$this->comment_captcha();
			endif;
			if (get_option($this->settings_prefix.'login_captcha') == '1') :
				$this->login_captcha();
			endif;
			if (get_option($this->settings_prefix.'disable_code_editing') == '1') :
				$this->disable_code_editing();
			endif;
			
		}
		
		private function update_config_file($key, $value)
		{
			global $is_IIS;
			global $is_iis7;
			global $is_apache;
			global $is_nginx;
			if ($key == 'script_block_uploads') :
				if ($is_iis7) :
					$path_obj = wp_upload_dir();
					$path = $path_obj['basedir'];
					$web_config_file = $path.'/web.config';
					if ( ( ( ! file_exists($web_config_file) && win_is_writable($path) ) || win_is_writable($web_config_file) ) ) :
						if ($value == 1) :
							return (file_put_contents($web_config_file, file_get_contents(__DIR__.'/template_web_config.txt', false)) !== false);
						else :
							if (file_exists($web_config_file)) :
								return unlink($web_config_file);
							else :
								return true;
							endif;
						endif;
					endif;
				elseif ($is_apache) :
					$path_obj = wp_upload_dir();
					$path = $path_obj['basedir'];
					$htaccess_file = $path.'/.htaccess';
					if ((!file_exists($htaccess_file) && is_writable($path)) || is_writable($htaccess_file)) :
						if ($value == 1) :
							$rules = explode( "\n", file_get_contents(__DIR__.'/template_htaccess.txt', false) );
						else :
							$rules = array();
						endif;
						return insert_with_markers( $htaccess_file, 'LCS Security', $rules );
					endif;
				else :
					return true;
				endif;
			elseif ($key == 'script_block_includes') :
				if ($is_iis7) :
					$path = get_home_path();
					$web_config_file = $path.'wp-includes/web.config';
					if ( ( ( ! file_exists($web_config_file) && win_is_writable($path) ) || win_is_writable($web_config_file) ) ) :
						if ($value == 1) :
							return (file_put_contents($web_config_file, file_get_contents(__DIR__.'/template_web_config_includes.txt', false)) !== false);
						else :
							if (file_exists($web_config_file)) :
								return unlink($web_config_file);
							else :
								return true;
							endif;
						endif;
					endif;
				elseif ($is_apache) :
					$path = get_home_path();
					$htaccess_file = $path.'wp-includes/.htaccess';
					if ((!file_exists($htaccess_file) && is_writable($path)) || is_writable($htaccess_file)) :
						if ($value == 1) :
							$rules = explode( "\n", file_get_contents(__DIR__.'/template_htaccess_includes.txt', false) );
						else :
							$rules = array();
						endif;
						return insert_with_markers( $htaccess_file, 'LCS Security', $rules );
					endif;
				else :
					return true;
				endif;
			else :
				return false;
			endif;
		}
		
		private function ban_check()
		{
			$ip = $this->get_ip();
			$ar_whitelist = explode(PHP_EOL, get_option($this->settings_prefix.'ip_whitelist'));
			if (in_array($ip, $ar_whitelist)) :
				return;
			endif;
			$ar_blacklist = explode(PHP_EOL, get_option($this->settings_prefix.'ip_blacklist'));
			if (in_array($ip, $ar_blacklist)) :
				$this->ban_ip();
				return;
			endif;
			if (get_option($this->settings_prefix.'auto_ban') == '1') :
				if ($this->get_ip_status($this->get_ip()) == 3) :
					$this->ban_ip();
					return;
				endif;
			endif;
		}
		
		private function ban_ip()
		{
			if (!function_exists('http_response_code')) :
				header('HTTP/1.1 401 Unauthorized', true, 401);
			else :
				http_response_code(401);
			endif;
			exit('<h1>Not Authorized!</h1>');
		}
		
		private function block_xml_rpc()
		{
			add_filter('xmlrpc_enabled', '__return_false');
			if (!isset( $_SERVER['SCRIPT_FILENAME'])) {
				return;
			}
			$script_file = basename($_SERVER['SCRIPT_FILENAME']);
			if ($script_file == 'xmlrpc.php') {
				$header = 'HTTP/1.1 403 Forbidden';
				header($header);
				echo '<h1>'.$header.'</h1>';
				die();
			}
		}
		
		private function block_author_scan()

		{
			if (!is_admin()) :
				if (preg_match('/author=([0-9]*)/i', $_SERVER['QUERY_STRING'])) :
					exit('<h1>Not Authorized!</h1>');
				endif;
				add_filter('redirect_canonical', array($this, 'author_redirect'), 10, 2);
				//add_action ('parse_query', array($this, 'author_redirect'), 1);
				add_filter( 'rest_endpoints', array($this, 'author_json_block'), 10, 2);
			endif;
		}
		
		public function author_redirect($redirect, $request)
		{
			if (preg_match('/\?author=([0-9]*)(\/*)/i', $request)) :
				exit('<h1>Not Authorized!</h1>');
			else :
				return $redirect;
			endif;
			/*
			global $wpdb, $wp_query, $wp_rewrite;
			if (!empty($wp_query) && $wp_query->get('author')) :
				wp_redirect(site_url().'/', 302);
				exit();
			endif;
			*/
		}
		
		public function author_json_block($endpoints)
		{
			if (isset($endpoints['/wp/v2/users'])) :
				unset( $endpoints['/wp/v2/users'] );
			endif;
			if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) :
				unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
			endif;
			return $endpoints;		
		}

		private function disable_code_editing() {
			define('DISALLOW_FILE_EDIT', true);
		}
		
		private function comment_captcha()
		{
			if (!is_admin()) :
				add_action('comment_form', array($this, 'comment_captcha_show'));
				add_action('comment_post', array($this, 'comment_captcha_check'), 10, 2 );
			endif;
		}
		
		public function comment_captcha_show()
		{
			echo '	<style>
						#submit {display:none;}
					</style>
					<p>
						<label for="captcha" style="display:block;">Verification</label>
						<input type="text" name="lcs_captcha" id="lcs_captcha" style="display:inline-block; width:110px;" />
						<img src="'.plugins_url( 'captcha.php?captcha_type=comment', __FILE__ ).'" style="display:inline-block; margin-right:5px;" id="lcs_captcha_image" />
						<a href="javascript:reload_captcha();">
							<img src="'.plugins_url( 'img/reload_icon.png', __FILE__ ).'" style="display:inline-block;"  />
						</a>
					</p>
					<p class="form-submit">
						<input name="submit" type="submit" id="submit-alt" tabindex="6" value="Post Comment"/>
					</p>
					<script>
						function reload_captcha()
						{
							document.getElementById("lcs_captcha_image").src="'.plugins_url( 'captcha.php?captcha_type=comment', __FILE__ ).'&ver="+Math.random() ;
						}
						setInterval(function(){ reload_captcha(); }, 600000);
					</script>';	
			session_start();
			if (isset($_SESSION['lcs_comment_captcha_error']) && $_SESSION['lcs_comment_captcha_error'] === true) :
				echo '<p><strong>ERROR</strong>: Invalid Verification!</p>';
			endif;
			unset($_SESSION['lcs_comment_captcha_error']);
			session_write_close();
		}

		public function comment_captcha_check($comment_id, $comment_approved)
		{
			session_start();
			if (md5(strtolower(trim($_POST['lcs_captcha']))) != $_SESSION['lcs_captcha_key_comment']) :
				wp_delete_comment(absint($comment_id));
				$_SESSION['lcs_comment_captcha_error'] = true;
			else :
				unset($_SESSION['lcs_comment_captcha_error']);
			endif;
			session_write_close();
		}

		private function login_captcha()
		{
			add_action('login_form', array($this, 'login_captcha_show'));
			add_filter('wp_authenticate_user', array($this, 'login_captcha_check') ,10,2);
		}
		
		public function login_captcha_show()
		{
			if ($this->get_ip_status($this->get_ip()) != 0 || get_option($this->settings_prefix.'login_captcha_failed') != '1') :
				echo '	<p>
							<label for="captcha" style="display:block;">Verification</label>
							<input type="text" name="lcs_captcha" id="lcs_captcha" style="display:inline-block; width:110px;" />
							<img src="'.plugins_url( 'captcha.php?captcha_type=login', __FILE__ ).'" style="display:inline-block; margin-right:5px;" id="lcs_captcha_image" />
							<a href="javascript:reload_captcha();">
								<img src="'.plugins_url( 'img/reload_icon.png', __FILE__ ).'" style="display:inline-block;"  />
							</a>
						</p>
						<script>
							function reload_captcha()
							{
								document.getElementById("lcs_captcha_image").src="'.plugins_url( 'captcha.php?captcha_type=login', __FILE__ ).'&ver="+Math.random() ;
							}
							setInterval(function(){ reload_captcha(); }, 600000);
						</script>';	
			endif;			
		}

		public function login_captcha_check($user, $password)
		{
			session_start();
			if ($this->get_ip_status($this->get_ip()) != 0 || get_option($this->settings_prefix.'login_captcha_failed') != '1') :
				if (md5(strtolower(trim($_POST['lcs_captcha']))) != $_SESSION['lcs_captcha_key_login']) :
					$error = new WP_Error('denied', "<strong>ERROR</strong>: Invalid Verification!");
					add_action('login_head', 'wp_shake_js', 12);
					return $error;
				else :
					return $user;
				endif;
			else :
				return $user;
			endif;
			session_write_close();
		}

		private function login_lockout_check()
		{
			global $pagenow;
			if ('wp-login.php' == $pagenow) :
				$ip = $this->get_ip();
				$lockout_minutes = intval(get_option($this->settings_prefix.'login_lockout_minutes'));
				if ($this->get_ip_status($ip) == 2) :
					if ($this->get_ip_status_minutes($ip) < $lockout_minutes) :
						if (!function_exists('http_response_code')) :
							header('HTTP/1.1 401 Unauthorized', true, 401);
						else :
							http_response_code(401);
						endif;
						exit('<h1>Temporary lockout due to multiple failed login attempts!</h1><h2>Try again in '.($lockout_minutes - $this->get_ip_status_minutes($ip)).' minutes.</h2>');
					else :
						global $wpdb;
						$table_prefix = $wpdb->prefix;
						$table_name = $table_prefix . 'lcs_sec_ip';
						$sql = $wpdb->prepare("UPDATE `$table_name` SET ".
								"update_time = %s, ".
								"ip_status = 1, ".
								"ip_status_time = %s, ".
								"failed_attempts_lockout = 0 ".
								"WHERE ip = %s"
								, current_time("mysql", 1), current_time("mysql", 1), $ip);
						$wpdb->query($sql);
					endif;
				endif;
			endif;
		}

		public function login_success($username, $user)
		{
			$ip = $this->get_ip();
			$this->write_log('OK', 'User logged in.', $username, $ip);
			$this->update_ip_db('ok', $ip, $username);
		}

		public function login_failed($username)
		{
			$ip = $this->get_ip();
			$this->write_log('Error', 'Login failed.', $username, $this->get_ip());
			$this->update_ip_db('error', $ip, $username);
		}
		
		public function logout($username)
		{
			$ip = $this->get_ip();
			$current_user = wp_get_current_user();
			$this->write_log('OK', 'User logged out.', $current_user->user_login, $ip);
		}

		private function write_log($log_type, $log_text, $user_name, $ip)
		{
			global $wpdb;
			$table_prefix = $wpdb->prefix;
			$table_name = $table_prefix . 'lcs_sec_log';
			$sql = $wpdb->prepare("INSERT INTO `$table_name` (log_time, log_type, long_time, ip, user_name, log_text, http_user_agent) ".
									"VALUES (%s, %s, ".time().", %s, %s, %s, %s)", current_time("mysql", 1), $log_type, $ip, $user_name, $log_text, $_SERVER['HTTP_USER_AGENT']);
			$wpdb->query($sql);
		}
		
		private function update_ip_db($status, $ip, $username = '')
		{
			global $wpdb;
			$table_prefix = $wpdb->prefix;
			$table_name = $table_prefix . 'lcs_sec_ip';
			$sql = $wpdb->prepare("SELECT * from `$table_name` WHERE ip = %s", $ip);
			$results = $wpdb->get_results($sql);
			if ($wpdb->num_rows <= 0) :
				$geo = $this->get_ip_geo_data($ip);
				$sql = $wpdb->prepare("INSERT INTO `$table_name` (ip, update_time, ip_status, ip_status_time, failed_attempts_ban, failed_attempts_lockout, country_code, country_name, region_code, region_name, city, latitude, longitude) ".
										"VALUES (%s, %s, 0, %s, 0, 0, %s, %s, %s, %s, %s, %s, %s)", 
										$ip, current_time("mysql", 1), current_time("mysql", 1), $geo->geoplugin_countryCode, $geo->geoplugin_countryName, $geo->geoplugin_regionCode, $geo->geoplugin_regionName, $geo->geoplugin_city, $geo->geoplugin_latitude, $geo->geoplugin_longitude);
				$wpdb->query($sql);
			endif;
			if ($status == 'ok') :
				$sql = $wpdb->prepare("UPDATE `$table_name` SET ".
						"update_time = %s, ".
						"ip_status = 0, ".
						"ip_status_time = %s, ".
						"failed_attempts_ban = 0, ".
						"failed_attempts_lockout = 0 ".
						"WHERE ip = %s"
						, current_time("mysql", 1), current_time("mysql", 1), $ip);
				$wpdb->query($sql);
			elseif ($status == 'error') :
				$sql = $wpdb->prepare("SELECT * from `$table_name` WHERE ip = %s", $ip);
				$results = $wpdb->get_results($sql);
				$row = $results[0];
				if (get_option($this->settings_prefix.'auto_ban') == '1' && $row->failed_attempts_ban >= intval(get_option($this->settings_prefix.'auto_ban_attempts')) - 1) :
					$ip_status = 3;
					$this->write_log('Warning', 'IP automatically banned.', $username, $ip);
				elseif (get_option($this->settings_prefix.'login_lockout') == '1' && $row->failed_attempts_lockout >= intval(get_option($this->settings_prefix.'login_lockout_attempts')) - 1) :
					$ip_status = 2;
					$this->write_log('Warning', 'IP on temporary lockout.', $username, $ip);
				else :
					$ip_status = 1;
				endif;
				$sql = $wpdb->prepare("UPDATE `$table_name` SET ".
						"update_time = %s, ".
						"ip_status = %d, ".
						"ip_status_time = %s, ".
						"failed_attempts_ban = failed_attempts_ban + 1, ".
						"failed_attempts_lockout = failed_attempts_lockout + 1 ".
						"WHERE ip = %s"
						, current_time("mysql", 1), $ip_status, current_time("mysql", 1), $ip);
				$wpdb->query($sql);
			endif;
		}

		private function nz($arg, $null_value = '0')
		{
			if (empty($arg)) :
				return $null_value;
			else :
				$arg = trim($arg);
				$arg = str_replace(',', '', $arg);
				return intval($arg);
			endif;
		}

		private function array_map_r($func, $arr)
		{
			$ar_result = array();
			foreach ($arr as $key => $value) :
				if (is_array($value)) :
					$ar_result[$key] = array_map_r($func, $value);
				else :
					$ar_result[$key] = $func($value);
				endif;
			endforeach;
			return $ar_result;
		}
		
		private function get_ip_status($ip)
		{
			global $wpdb;
			$table_prefix = $wpdb->prefix;
			$table_name = $table_prefix . 'lcs_sec_ip';
			$sql = $wpdb->prepare("SELECT * from `$table_name` WHERE ip = %s", $ip);
			$results = $wpdb->get_results($sql);
			if ($wpdb->num_rows > 0) :
				$row = $results[0];
				return $row->ip_status;
			else :
				return -1;
			endif;
		}

		private function get_ip_status_minutes($ip)
		{
			global $wpdb;
			$table_prefix = $wpdb->prefix;
			$table_name = $table_prefix . 'lcs_sec_ip';
			$sql = $wpdb->prepare("SELECT * from `$table_name` WHERE ip = %s", $ip);
			$results = $wpdb->get_results($sql);
			if ($wpdb->num_rows > 0) :
				$row = $results[0];
				$current_time = strtotime(current_time("mysql", 1));
				$ip_time = strtotime($row->ip_status_time);
				return floor(($current_time - $ip_time) / 60);
			else :
				return -1;
			endif;
		}
		
		private function get_ip()
		{
			$ipaddress = '';
			if ($_SERVER['HTTP_CF_CONNECTING_IP']) :
				$ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
			elseif ($_SERVER['HTTP_CLIENT_IP']) :
				$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
			elseif($_SERVER['HTTP_X_FORWARDED_FOR']) :
				$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
			elseif($_SERVER['HTTP_X_FORWARDED']) :
				$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
			elseif($_SERVER['HTTP_FORWARDED_FOR']) :
				$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
			elseif($_SERVER['HTTP_FORWARDED']) :
				$ipaddress = $_SERVER['HTTP_FORWARDED'];
			elseif($_SERVER['REMOTE_ADDR']) :
				$ipaddress = $_SERVER['REMOTE_ADDR'];
			else :
				$ipaddress = 'UNKNOWN';
			endif;
			return $ipaddress;
		}

		private function get_ip_geo_data($ip)
		{
			$ip_geo_data = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip));
			return $ip_geo_data;
		}

		private function generate_random_string($length = 16) 
		{
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$random_string = '';
			for ($i = 0; $i < $length; $i++) :
				$random_string .= $characters[rand(0, strlen($characters) - 1)];
			endfor;
			return $random_string;
		}
		
		private function copyright()
		{
			echo '<div id="lcs_copyright" >';
			?>
			<hr />
			<p>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
			<input type="hidden" name="cmd" value="_s-xclick">
			<input type="hidden" name="hosted_button_id" value="8VNLK58BEEJ3C">
			<table style="margin-left:auto; margin-right:auto;">
			<tr><td><input type="hidden" name="on0" value="Support this plugin by contributing:">Support this plugin by contributing:</td></tr><tr><td><select name="os0">
				<option value="Option 1 -">Option 1 - $25.00 USD</option>
				<option value="Option 2 -">Option 2 - $50.00 USD</option>
				<option value="Option 3 -">Option 3 - $100.00 USD</option>
			</select> </td></tr>
			</table>
			<input type="hidden" name="currency_code" value="USD">
			<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
			<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>
			</p>
			<?php
			echo '&copy; Copyright 2016'.(date('Y') != '2016' ? '-'.date('Y') : '').' - LatCom Systems - <a target="_blank" href="http://www.latcomsystems.com">www.latcomsystems.com</a><br />';
			echo 'Special credits go to <a target="_blank" href="https://github.com/PHPOffice/PHPExcel">PHPExcel</a> for their <i><b>excel</b></i>lent Excel generator library.';
			echo '</div>';
		}
	}
endif;

$lcs_security = new lcs_security();
