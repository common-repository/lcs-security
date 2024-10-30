<?php
	if (!isset($_GET['captcha_type'])) exit;
	
	lcs_sec_captcha();
	
	function lcs_sec_captcha()
	{
		session_start();
		if (isset($_POST['len'])) :
			$len = $_POST['len'];
		else :
			$len = 6;
		endif;
		$string = '';
		$possible = '23456789bcdfghjkmnprstvwxyz';
		$i = 0;
		while ($i < $len) :
			$string .= substr($possible, mt_rand(0, strlen($possible)-1), 1);
			$i++;
		endwhile;
		$display_string = '';
		for ($i=0; $i< $len; $i++) :
			$display_string = $display_string.substr($string,$i,1).' ';
		endfor;
		$captcha = imagecreatetruecolor(120,25);
		$font_color = imagecolorallocate($captcha, 146, 49, 82);
		$line_color = imagecolorallocate($captcha,146,49,82);
		$bg = imagecolorallocate($captcha,230,230,230);
		imagefill($captcha, 0, 0, $bg);
		for ($i=0; $i< 150; $i = $i + 15) :
			$x1 = rand($i,$i + 10);
			$x2 = rand($x1-20,$x1+20);
			$y1 = rand(0,25);
			$y2 = rand(0,25);
			imageline($captcha,$x1,0,$x2,25,$line_color);
		endfor;
		imagestring($captcha, 5, 10, 5, $display_string, $font_color);
		$_SESSION['lcs_captcha_key_'.$_GET['captcha_type']] = md5(strtolower($string));
		header("Content-type: image/png");
		imagepng($captcha);
	}
?>
