<?php

require("vendor/autoload.php");

use Flintstone\Flintstone;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$users = new Flintstone('users', [
	'dir' => 'store'
]);

$users = array_filter($users->getAll(), function($user){
	return isset($user['COUNT']);
});

// echo (new Parse\Printer())->outTable(['company', 'fio', 'internal_id', 'type', 'city', 'contacts'], $users);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
// $sheet->fromArray($users);

foreach (array_values($users) as $i => $user) {
	$contacts = array_pop($user);
	$contacts = array_pop($user);
	$other = implode(':', $user);
	
	$sheet->setCellValue('A'.$i, $contacts);
	$sheet->setCellValue('B'.$i, $other);
}

$writer = new Xlsx($spreadsheet);
$writer->save('users2.xlsx');
