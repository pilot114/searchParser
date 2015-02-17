<?php

return [
	'debug' => false,
	'connection' => 'mongodb://localhost/',
	'db'	=> 'plumcake',
	'users' => [
		'admin' => 'VHY!YHV',
	],
	'proxy_timeout' => 5,
	'engines' => [
		'google' => [
			'url' => 'http://www.google.ru/search',
			'query' => 'q',
			'page' => ['start', 1, true], // name, first index, shifting
			'selector' => 'h3.r a',
			'num' => 'num=100',
			'full' => [99, 105] // min max for RUN status
		],
		'yandex' => [
			'url' => 'http://yandex.ru/yandsearch',
			'query' => 'text',
			'page' => ['p', 0, false],
			'selector' => 'h2.serp-item__title a',
			'num' => 'numdoc=50',
			'full' => [49, 50]
		],
		'mail' => [
			'url' => 'http://go.mail.ru/search',
			'query' => 'q',
			'page' => ['sf', 0, true],
			'selector' => 'h3.result__title a',
			'full' => [9, 10]
		],
		'bing' => [
			'url' => 'http://www.bing.com/search',
			'query' => 'q',
			'page' => ['first', 1, true],
			'selector' => 'h2 a',
			'full' => [9, 10]
		]
	]
];