<?php

namespace Parse;

use DiDom\Document;

class Page
{
	private $document;

	public function __construct($url)
	{
		$this->document = new Document($url, true, 'windows-1251');
	}

	public function getFieldByCss($css): array
	{
		$elements = $this->document->find($css);
		if (count($elements) == 0) {
			return [];
		}
		$texts = [];
		foreach ($elements as $element) {
			// $texts[] = $element->html();
			$texts[] = $element->text();
		}
		return $texts;
	}
}