<?php

namespace lcs\security;

if (!defined('ABSPATH')) exit;

if (!class_exists('lcs_data_panel')) :
	class lcs_data_panel
	{
		const MYSQL_TYPE_DECIMAL = 0x00;
		const MYSQL_TYPE_TINY = 0x01;
		const MYSQL_TYPE_SHORT = 0x02;
		const MYSQL_TYPE_LONG = 0x03;
		const MYSQL_TYPE_FLOAT = 0x04;
		const MYSQL_TYPE_DOUBLE = 0x05;
		const MYSQL_TYPE_NULL = 0x06;
		const MYSQL_TYPE_TIMESTAMP = 0x07;
		const MYSQL_TYPE_LONGLONG = 0x08;
		const MYSQL_TYPE_INT24 = 0x09;
		const MYSQL_TYPE_DATE = 0x0a;
		const MYSQL_TYPE_TIME = 0x0b;
		const MYSQL_TYPE_DATETIME = 0x0c;
		const MYSQL_TYPE_YEAR = 0x0d;
		const MYSQL_TYPE_NEWDATE = 0x0e;
		const MYSQL_TYPE_VARCHAR = 0x0f;
		const MYSQL_TYPE_BIT = 0x10;
		const MYSQL_TYPE_TIMESTAMP2 = 0x11;
		const MYSQL_TYPE_DATETIME2 = 0x12;
		const MYSQL_TYPE_TIME2 = 0x13;
		const MYSQL_TYPE_NEWDECIMAL = 0xf6;
		const MYSQL_TYPE_ENUM = 0xf7;
		const MYSQL_TYPE_SET = 0xf8;
		const MYSQL_TYPE_TINY_BLOB = 0xf9;
		const MYSQL_TYPE_MEDIUM_BLOB = 0xfa;
		const MYSQL_TYPE_LONG_BLOB = 0xfb;
		const MYSQL_TYPE_BLOB = 0xfc;
		const MYSQL_TYPE_VAR_STRING = 0xfd;
		const MYSQL_TYPE_STRING = 0xfe;
		const MYSQL_TYPE_GEOMETRY = 0xff;
		public $err_flag;
		public $ar_err = array();
		
		public function __construct()
		{
		}

		public function data_show($sql, $ar_funcs = array(), $page_rows = 50) 
		{
			ini_set('display_errors', 0);
			global $wpdb;
			$excel_url = plugins_url( 'export_excel.php', __FILE__ );
			//echo '<p>SQL: '.$sql.'</p>';
			$results = $wpdb->get_results($sql);
			if ($wpdb->num_rows > 0) :
				$current_page = intval($this->nz($_GET['start_page'], '1'));
				$start_row = (intval($this->nz($_GET['start_page'], '1')) - 1) * $page_rows;
				$stop_row = $start_row + $page_rows;
				echo '<style>';
				echo 'form {display:inline-block;}';
				echo 'table.lcs_data_table td {vertical-align:top;}';
				echo '</style>';
				echo '<div>';
				echo $wpdb->num_rows.' records found.&emsp;';
				$ar_excel_url = $_GET;
				$ar_excel_url['export_excel'] = '1';
				require_once('lcs_utility.php');
				$ar_excel_url['sql'] = lcs_utility::lcs_encrypt(gzdeflate($sql,6), $_SESSION['lcs_security_rand_key']);
				$excel_url2 = admin_url('admin.php?'.http_build_query($ar_excel_url));
				echo '<button onclick="location.href = \''.$excel_url2.'\'">Export to Excel&reg;</button>';
				$_SESSION['lcs_security_wpdb_'.$_SESSION['lcs_security_rand_key']] = json_encode($wpdb);
				echo '&emsp;Search: <form method="get" action="">';
				echo '<input type="hidden" name="start_page" value="1" />';
				echo '<input type="hidden" name="page" value="'.$_GET['page'].'" />';
				echo '<input type="text" class="search" name="search" value="'.stripslashes_deep($_GET['search']).'" />';
				echo '<input type="submit" name="submit" value="Find" />';
				echo '</form>';
				echo '&emsp;<form method="get" action="">';
				echo '<input type="hidden" name="start_page" value="1" />';
				echo '<input type="hidden" name="page" value="'.$_GET['page'].'" />';
				echo '<input type="hidden" name="search" value="" />';
				echo '<input type="submit" name="submit" value="List All" />';
				echo '</form>';
				echo '</div>';
				echo '<br /><br />';
				echo '<table class="lcs_data_table" style="border-collapse:collapse; border:1px solid #000000;">';
				echo '<thead><tr>';
				$ar_field_types = array();
				$ar_field_names = array();
				$i = 0;
				while ($i < count((array)$results[0])) :
					$meta = $wpdb->get_col_info('type', $i);
					if (!$meta) :
						$ar_field_types[$i] = 'string';
						$column_name = 'column_'.$i;
					else :
						$ar_field_types[$i] = strtolower($wpdb->get_col_info('type', $i));
						$column_name = $wpdb->get_col_info('name', $i);
					endif;
					$ar_field_names[$i] = $column_name;
					echo '<th style="border:1px solid #000000; padding:5px;">'.$column_name.'</th>';
					$i++;
				endwhile;
				echo '</tr></thead>';
				$ix = $start_row;
				while ($ix < $wpdb->num_rows && $ix <= $stop_row) :
					$row = $results[$ix];
					echo '<tr>';
					$curr_col = 0;
					foreach($row as $row_field) :
						$row_field = utf8_encode($row_field);
						$value = '';
						if ($ar_field_types[$curr_col] == self::MYSQL_TYPE_LONG || $ar_field_types[$curr_col] == self::MYSQL_TYPE_LONGLONG) :
							$align = 'right';
							$value = intval(html_entity_decode($row_field, ENT_QUOTES));
						elseif ($ar_field_types[$curr_col] == self::MYSQL_TYPE_NEWDECIMAL || $ar_field_types[$curr_col] == self::MYSQL_TYPE_FLOAT) :
							$align = 'right';
							$value = number_format(floatval(html_entity_decode($row_field, ENT_QUOTES)), 2, '.', ',');
						else :
							$align = 'left';
							$value = trim(html_entity_decode($row_field, ENT_QUOTES));
							if (strtolower(substr($row_field,0,7)) == 'http://' || strtolower(substr($row_field,0,8)) == 'https://') :
								$value = '<a target="_blank" href="'.$value.'">'.$value.'</a>';
							endif;
						endif;
						if (!empty($ar_funcs[$ar_field_names[$curr_col]])) :
							$func = $ar_funcs[$ar_field_names[$curr_col]];
							$value = call_user_func($func, $value, $row, $ar_field_names[$curr_col]);
							//$value = $ar_funcs[$ar_field_names[$curr_col]]($value, $row, $ar_field_names[$curr_col]);
						endif;
						echo '<td style="border:1px solid #000000; padding:5px;" align="'.$align.'">'.$value.'</td>';
						$curr_col = $curr_col + 1;
					endforeach;
					echo '</tr>';
					$ix++;
				endwhile;
				echo '</table>';
				$ix = 1;
				$ix2 = 1;
				echo '<p>';
				while ($ix <= $wpdb->num_rows) :
					if ($current_page != $ix2) :
						$_GET['start_page'] = $ix2;
						$query_string = http_build_query($_GET);
						$url = admin_url('admin.php?'.$query_string);
						echo '<a href="'.$url.'">'.$ix2.'</a>&emsp;';
					else :
						echo $ix2.'&emsp;';
					endif;
					$ix = $ix + $page_rows;
					$ix2++;
				endwhile;
				echo '</p>';
			else :
				echo '<p>No records found.</p>';
				echo '<form method="get" action="">';
				echo '<input type="hidden" name="start_page" value="1" />';
				echo '<input type="hidden" name="page" value="'.$_GET['page'].'" />';
				echo '<input type="hidden" name="search" value="" />';
				echo '<input type="submit" name="submit" value="List All" />';
				echo '</form>';
			endif;
		}
	
		public function search_tokenize($str_fields, $int_fields, $search) 
		{
			global $wpdb;
			$result = '';
			$ar_result = array();
			if (!empty($str_fields)) :
				$search_str = "'%".addslashes($search)."%'";
				$ar_str = explode(',', $str_fields);
				foreach ($ar_str as $field) :
					$ar_result[] = " ".$field." LIKE ".$search_str." ";
				endforeach;
			endif;
			if (!empty($int_fields)) :
				$search_int = $this->nz($search, '0');
				$ar_int = explode(',', $int_fields);
				foreach ($ar_int as $field) :
					$ar_result[] = " ".$field." = ".$search_int." ";
				endforeach;
			endif;
			$result = implode(" OR ", $ar_result);
			if (!empty($result)) :
				$result = " WHERE (".$result.") ";
			endif;
			return $result;
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

	}
endif;
