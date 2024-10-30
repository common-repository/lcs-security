<?php

namespace lcs\security;

if (!defined('ABSPATH')) exit;

if (PHP_SAPI == 'cli')
	die('This example should only be run from a Web Browser');

if (!class_exists('lcs_export_excel')) :
	class lcs_export_excel
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
		
		public function __construct()
		{
			if ($_GET['export_excel'] == '1') :
				$this->export_excel();
			endif;
		}

		private function export_excel()
		{
			ini_set('display_errors', 0);
			session_start();
			
			require_once dirname(__FILE__) . '/PHPExcel.php';
			
			$objPHPExcel = new \PHPExcel();
			$objPHPExcel->getProperties()->setCreator("LatCom Systems")
										 ->setLastModifiedBy("LatCom Systems")
										 ->setTitle("LCS Security for WordPress")
										 ->setSubject("LCS Security for WordPress")
										 ->setDescription("LCS Security for WordPress")
										 ->setKeywords("LCS Security for WordPress")
										 ->setCategory("LCS Security for WordPress");
			$sheet = $objPHPExcel->getActiveSheet();
			$sheet0 = $objPHPExcel->setActiveSheetIndex(0);
			global $wpdb;
			require_once('lcs_utility.php');
			$sql = gzinflate(lcs_utility::lcs_decrypt($_GET['sql'], $_SESSION['lcs_security_rand_key']));
			$results = $wpdb->get_results($sql);
			$first_row = true;
			$curr_row = 1;  /* 3 for setups with 2 lines of headers */
			$tot_cols = 1;
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
				$i++;
			endwhile;
			$ix = 0;
			while ($ix < count($results)) :
				$row = $results[$ix];
				if ($first_row) :
					$curr_col = 0;
					foreach($ar_field_names as $key) :
						$sheet0->setCellValueByColumnAndRow($curr_col, $curr_row, $key);
						$sheet->getStyleByColumnAndRow($curr_col, $curr_row)->applyFromArray(
							array	(	'fill' 	=> array('type'	=> \PHPExcel_Style_Fill::FILL_SOLID,	'color'	=> array('rgb' => '3E4B62')),
										'borders' => array('allborders' => array('style' => \PHPExcel_Style_Border::BORDER_MEDIUM, 'color' => array('argb' => \PHPExcel_Style_Color::COLOR_WHITE))),
										'font' => array('color' => array('argb' => \PHPExcel_Style_Color::COLOR_WHITE)),
										'alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
									)
							);
						$sheet->getColumnDimension('B')->setAutoSize(true);
		
						$curr_col = $curr_col + 1;
					endforeach;
					$tot_cols = $curr_col;
					$curr_row = $curr_row + 1;
					$first_row = false;
				endif;
				$max_col = \PHPExcel_Cell::stringFromColumnIndex($tot_cols - 1);
				$curr_col = 0;
				foreach($row as $row_field) :
					$row_field = utf8_encode($row_field);
					if ($ar_field_types[$curr_col] == self::MYSQL_TYPE_LONG || $ar_field_types[$curr_col] == self::MYSQL_TYPE_LONGLONG || $ar_field_types[$curr_col] == self::MYSQL_TYPE_NEWDECIMAL || $ar_field_types[$curr_col] == self::MYSQL_TYPE_FLOAT) :
						$sheet0->setCellValueExplicitByColumnAndRow($curr_col, $curr_row, html_entity_decode($row_field, ENT_QUOTES), \PHPExcel_Cell_DataType::TYPE_NUMERIC);
					else :
						$sheet0->setCellValueExplicitByColumnAndRow($curr_col, $curr_row, html_entity_decode($row_field, ENT_QUOTES), \PHPExcel_Cell_DataType::TYPE_STRING);
						if (strtolower(substr($row_field,0,7)) == 'http://' || strtolower(substr($row_field,0,8)) == 'https://') :
							$parts = parse_url($row_field);
							parse_str($parts['query'], $query);
							$query['mslink'] = 'true';
							$url = $parts['scheme'].'://'.$parts['host'].$parts['path'].'?'.http_build_query($query); 
							$sheet0->getCellByColumnAndRow($curr_col, $curr_row)->getHyperlink()->setUrl($url);
							$sheet->getStyleByColumnAndRow($curr_col, $curr_row)->applyFromArray(
								array	(	'font' => array('color' => array('argb' => \PHPExcel_Style_Color::COLOR_BLUE)),
										)
								);
						endif;
					endif;
					$curr_col = $curr_col + 1;
				endforeach;
				$curr_row = $curr_row + 1;
				$ix++;
			endwhile;
			$max_row = $curr_row - 1;
			if ($max_row > 3) :
				$i = 0;
				while ($i < count($ar_field_names)) :
					if ($ar_field_types[$i] == 'long' || $ar_field_types[$i] == 'longlong') :
						$sheet->getStyle(\PHPExcel_Cell::stringFromColumnIndex($i).'2:'.\PHPExcel_Cell::stringFromColumnIndex($i).$max_row)->applyFromArray(
							array	('alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT) )
						);
					elseif ($ar_field_types[$i] == 'newdecimal' || $ar_field_types[$i] == 'float') :
						$sheet->getStyle(\PHPExcel_Cell::stringFromColumnIndex($i).'2:'.\PHPExcel_Cell::stringFromColumnIndex($i).$max_row)->applyFromArray(
							array	('alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT) )
							);
						$sheet->getStyle(\PHPExcel_Cell::stringFromColumnIndex($i).'2:'.\PHPExcel_Cell::stringFromColumnIndex($i).$max_row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
					else :
						$sheet->getStyle(\PHPExcel_Cell::stringFromColumnIndex($i).'2:'.\PHPExcel_Cell::stringFromColumnIndex($i).$max_row)->applyFromArray(
							array	('alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_LEFT) )
							);
					endif;
					$i++;
				endwhile;
		
				$sheet->getStyle('A2:'.$max_col.$max_row)->applyFromArray(
					array	(	'fill' 	=> array('type'	=> \PHPExcel_Style_Fill::FILL_SOLID,	'color'	=> array('rgb' => 'ECEDEF'))
								,'borders' => array('allborders' => array('style' => \PHPExcel_Style_Border::BORDER_MEDIUM, 'color' => array('argb' => \PHPExcel_Style_Color::COLOR_WHITE)))
							)
					);
			endif;
		
			foreach (range(0, $tot_cols) as $col) :
				$sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
			endforeach;
			
			// Rename worksheet
			$sheet->setTitle('exportfile');
			
			// Set active sheet index to the first sheet, so Excel opens this as the first sheet
			$sheet0;
			
			// Redirect output to a client’s web browser (Excel5)
			header('Content-Type: application/vnd.ms-excel');
			header('Content-Disposition: attachment;filename="exportfile.xls"');
			header('Cache-Control: max-age=0');
			// If you're serving to IE 9, then the following may be needed
			header('Cache-Control: max-age=1');
			
			// If you're serving to IE over SSL, then the following may be needed
			header ('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header ('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT'); // always modified
			header ('Cache-Control: cache, must-revalidate'); // HTTP/1.1
			header ('Pragma: public'); // HTTP/1.0
			
			$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
			$objWriter->save('php://output');
			exit;
		}
	}
endif;
	
