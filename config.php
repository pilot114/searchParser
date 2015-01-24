<?php

return [
	'connection' => 'mongodb://localhost/',
	'engines' => [
		'google' => [
			'url' => 'http://www.google.ru/search',
			'query' => 'q',
			'page' => ['start', 1, true], // name, first index, shifting
			'selector' => 'h3.r a',
			'num' => 'num=100',
			'full' => [99, 100] // min max for RUN status
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
	],
	'proxy_list' =>
		'218.59.144.120:81
		59.151.103.15:80
		130.185.81.139:3128
		61.234.123.64:8080
		61.156.3.166:80
		36.250.74.88:80
		221.238.140.164:8080
		218.60.56.95:8080
		180.183.123.231:3128
		199.200.120.140:3127
		64.31.22.143:3127
		183.219.151.108:8123',
];