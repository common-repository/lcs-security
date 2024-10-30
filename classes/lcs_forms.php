<?php

namespace lcs\security;

if (!defined('ABSPATH')) exit;

if (!class_exists('lcs_forms')) :
	class lcs_forms
	{
		public $err_flag;
		public $ar_err = array();
		
		public function __construct()
		{
		}

		public function form_field($fname, $ftype, $fsize, $frows = 0, $frequired = false, $disabled = false, $readonly = false, $fstyle = "", $fclass = "", $fdbname = "", $flabel = "", $ar_group = array(), $onchange = "", $onblur = "" )
		{
			if (empty($fdbname)) :
				$fdbname = $fname;
			endif;
			if (!empty($fstyle)) :
				$style = ' style="'.$fstyle.'" ';
			endif;
			if ($frequired) :
				$fclass .= ' required';
			endif;
			if (!empty($this->ar_err[$fname])) :
				$fclass .= ' inerror';
			endif;
			if (!empty($fclass)) :
				$class = ' class="'.trim($fclass).'" ';
			endif;
			if ($disabled) :
				$disabled = ' disabled="disabled" ';
			endif;
			if ($readonly) :
				$readonly = ' readonly="readonly" ';
			endif;
			if (!empty($onchange)) :
				$onchange = ' onchange="'.trim($onchange).'" ';
			endif;
			if ($ftype == 'autocomplete') :
				$onblur .= ' lcs_autocomplete_close(\''.$fname.'\'); ';
			endif;
			if (!empty($onblur)) :
				$onblur = ' onblur="'.trim($onblur).'" ';
			endif;
			$attributes = $style.$class.$disabled.$readonly.$onchange.$onblur;
			switch ($ftype) :
				case "text" :
				case "password" :
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<input name="'.$fname.'" id="'.$fname.'" type="'.$ftype.'" maxlength="'.$fsize.'" value="'.$_POST[$fname].'" '.$attributes.' />';
					$this->show_form_error($this->ar_err[$fname]);
					$ar_ftype[] = $ftype;
					$ar_ffields[] = $fdbname;
					$ar_fupdate[] = $fdbname;
					echo '</div>';
					break;
				case "select":
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<select name="'.$fname.'" id="'.$fname.'" '.$attributes.'>';
					echo '<option value="">Select...</option>';
					if (is_assoc_array($ar_group)) :
						foreach ($ar_group as $key => $value) :
							$selected = '';
							if ((string)$_POST[$fname] == (string)$key) :
								$selected = ' selected="selected" ';
							elseif ($readonly || $disabled) :
								$selected = ' disabled="disabled" ';
							endif;
							echo '<option value="'.$key.'" '.$selected.' /> '.$value.'</option>';
						endforeach;
					else :
						foreach ($ar_group as $value) :
							$selected = '';
							if ((string)$_POST[$fname] == (string)$value) :
								$selected = ' selected="selected" ';
							elseif ($readonly || $disabled) :
								$selected = ' disabled="disabled" ';
							endif;
							echo '<option value="'.$value.'" '.$selected.' /> '.$value.'</option>';
						endforeach;
					endif;
					echo '</select>';
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "autocomplete":
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<input name="'.$fname.'" id="'.$fname.'" type="text" maxlength="'.$fsize.'" value="'.$_POST[$fname].'" '.$attributes.' onfocus="lcs_autocomplete_open(\''.$fname.'\')" onkeyup="lcs_autocomplete_filter(\''.$fname.'\')" />';
					echo '<div id="'.$fname.'_autocomplete" class="autocomplete"><ul>';
					foreach ($ar_group as $value) :
						echo '<li onclick="lcs_autocomplete_pick(this, \''.$fname.'\');">'.$value.'</li>';
					endforeach;
					echo '</ul></div>';
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "radiogroup":
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<fieldset id="'.$fname.'" '.$attributes.'>';
					if (is_assoc_array($ar_group)) :
						foreach ($ar_group as $key => $value) :
							$checked = '';
							if ($_POST[$fname] == $key) :
								$checked = ' checked="checked" ';
							endif;
							echo '<input type="radio" name="'.$fname.'" value="'.$key.'" '.$checked.' '.$attributes.' /> '.$value.'<br />';
							//echo $key.' '.$value.'<br />';
						endforeach;
					else :
						foreach ($ar_group as $value) :
							$checked = '';
							if ($_POST[$fname] == $value) :
								$checked = ' checked="checked" ';
							endif;
							echo '<input type="radio" name="'.$fname.'" value="'.$value.'" '.$checked.' '.$attributes.' /> '.$value.'<br />';
							//echo $value.'<br />';
						endforeach;
					endif;
					echo '</fieldset>';
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "checkgroup":
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<fieldset id="'.$fname.'" '.$attributes.'>';
					if (is_assoc_array($ar_group)) :
						foreach ($ar_group as $key => $value) :
							$checked = '';
							if (in_array($key, $_POST[$fname])) :
								$checked = ' checked="checked" ';
							endif;
							echo '<input type="checkbox" name="'.$fname.'[]" value="'.$key.'" '.$checked.' '.$attributes.' /> '.$value.'<br />';
							//echo $key.' '.$value.'<br />';
						endforeach;
					else :
						foreach ($ar_group as $value) :
							$checked = '';
							if (is_array($_POST[$fname]) && in_array($value, $_POST[$fname])) :
								$checked = ' checked="checked" ';
							endif;
							echo '<input type="checkbox" name="'.$fname.'[]" value="'.$value.'" '.$checked.' '.$attributes.' /> '.$value.'<br />';
							//echo $value.'<br />';
						endforeach;
					endif;
					echo '</fieldset>';
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "checkbox":
				case "radio":
					echo '<div class="input_field">';
					$checked = '';
					if ('1' == $_POST[$fname]) :
						$checked = ' checked="checked" ';
					endif;
					echo '<input type="'.$ftype.'" name="'.$fname.'" id="'.$fname.'" value="1" '.$checked.' '.$attributes.' /> '.$flabel;
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "textarea" :
					echo '<div class="input_field">';
					echo '<label for="'.$fname.'">'.$flabel.'</label>';
					echo '<textarea name="'.$fname.'" id="'.$fname.'" cols="'.$fsize.'" rows="'.$frows.'"  ';
					if (!empty($fstyle)) :
						echo ' style="'.$fstyle.'" ';
					endif;
					if (!empty($fclass)) :
						echo ' class="'.$fclass.'" ';
					endif;
					echo '  >';
					echo $_POST[$fname];
					echo '</textarea>';
					$this->show_form_error($this->ar_err[$fname]);
					echo '</div>';
					break;
				case "date" :
					break;
			endswitch;
		}
		
		public function form_validate($field, $valtype, $required = false, $message = '')
		{
			if ($required && trim($_POST[$field]) == '') :
				if (empty($message)) :
					$message = 'Required!';
				endif;
				$this->ar_err[$field] = $message;
				$this->err_flag = true;
				return;
			endif;
			if (trim($_POST[$field]) != '') :
				if (empty($message)) :
					$message = 'Invalid!';
				endif;
				switch ($valtype) :
					case 'string' :
						break;
					case 'email' :
						if (filter_var($_POST[$field], FILTER_VALIDATE_EMAIL) === false) :
							$this->ar_err[$field] = $message;
							$this->err_flag = true;
						endif;
						break;
					case 'url' :
						if (filter_var($_POST[$field], FILTER_VALIDATE_URL) === false) :
							$this->ar_err[$field] = $message;
							$this->err_flag = true;
						endif;
						break;
					case 'int' :
						if (filter_var($_POST[$field], FILTER_VALIDATE_INT) === false) :
							$this->ar_err[$field] = $message;
							$this->err_flag = true;
						endif;
						break;
					case 'float' :
						if (filter_var($_POST[$field], FILTER_VALIDATE_FLOAT) === false) :
							$this->ar_err[$field] = $message;
							$this->err_flag = true;
						endif;
						break;
					case 'date' :
						$date = $_POST[$field];
						$date = str_replace('/', '-', $date);
						$date = str_replace('.', '-', $date);
						$ar_date = explode('-', $date);
						if (checkdate($ar_date[1], $ar_date[2], $ar_date[0]) === false) :
							$this->ar_err[$field] = $message;
							$this->err_flag = true;
						endif;
						break;
				endswitch;
			endif;
		}
	
		private function show_form_error($err_text)
		{
			if (!empty($err_text)) :
				echo '<br /><span class="form_error">'.$err_text.'</span>';
			endif;
		}
	
		private function is_assoc_array($arr)
		{
			return array_keys($arr) !== range(0, count($arr) - 1);
		}
	
	}
endif;
