<?php

require("vendor/autoload.php");
$conf = require("config.php");

use Flintstone\Flintstone;

$users = new Flintstone('users', [
	'dir' => 'store'
]);

foreach ($conf as $urlPattern => $schema) {
	preg_match('#{(.*)-(.*)}#', $urlPattern, $matches);
	$start = $matches[1];
	$end = $matches[2];

	$lastId = max($users->getKeys());
	if ($lastId > $start) {
		$start = $lastId++;
	}

	foreach (range($start, $end) as $id) {
		$url = preg_replace('#{.*}#', $id, $urlPattern);
		$page = new Parse\Page($url);

		foreach ($schema as $selector => $fields) {
			if (!is_array($fields)) {
				var_dump('not implement for scalar');
				die();
			}

			$data = $page->getFieldByCss($selector);
			if (!$data) {
				echo sprintf("%d) nop...\n", $id, $selector);
				continue;
			}

			if (count($data) != count($fields)) {
				echo sprintf("not equal counts! data: %d, fields: %d\n", count($data), count($fields));
				$data['COUNT'] = count($data);
				$users->set($id, $data);
				echo sprintf("%d) save part user!\n", $id);
				continue;
			}

			$result = [];
			foreach ($fields as $i => $fieldName) {
				$result[$fieldName] = $data[$i];
			}
			$users->set($id, $result);
			echo sprintf("%d) save user!\n", $id);
		}
	}
}