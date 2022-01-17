<?php

use PHPUnit\Framework\TestCase;

Config::set('db-host', 'localhost');
Config::set('db-name', 'linkcounttest');

class LinkCountTest extends TestCase {
	private static $db;
	private static $statements;
	private static $defaultExpected;

	private $pageIDs = [];
	private $pageIDCounter = 0;

	public static function setUpBeforeClass(): void {
		self::$db = new Database('localhost', '');

		self::$statements = [
			'page' => self::$db->prepare('INSERT INTO `page` (page_id, page_namespace, page_title) VALUES (?, ?, ?)'),
			'redirect' => self::$db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, NULL)'),
			'iwredirect' => self::$db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, ?)'),
			'pagelink' => self::$db->prepare('INSERT INTO pagelinks (pl_from, pl_namespace, pl_title, pl_from_namespace) VALUES (?, ?, ?, ?)'),
			'templatelink' => self::$db->prepare('INSERT INTO templatelinks (tl_from, tl_namespace, tl_title, tl_from_namespace) VALUES (?, ?, ?, ?)'),
			'categorylink' => self::$db->prepare('INSERT INTO categorylinks (cl_from, cl_to) VALUES (?, ?)'),
			'imagelink' => self::$db->prepare('INSERT INTO imagelinks (il_from, il_to, il_from_namespace) VALUES (?, ?, ?)')
		];

		self::$defaultExpected = [
			'filelinks' => null,
			'categorylinks' => null,
			'wikilinks' => [0,0,0],
			'redirects' => 0,
			'transclusions' => [0,0,0]
		];

		self::$db->exec(file_get_contents(__DIR__ . '/createdb.sql'));
	}

	private function ensurePage($ns, $title) {
		if(!array_key_exists($ns, $this->pageIDs)) {
			$this->pageIDs[$ns] = [];
		}

		if(!array_key_exists($title, $this->pageIDs[$ns])) {
			$id = ++$this->pageIDCounter;
			self::$statements['page']->execute([$id, $ns, $title]);
			$this->pageIDs[$ns][$title] = $id;
		}

		return $this->pageIDs[$ns][$title];
	}

	private function addRedirect($pagelink, $fromNS, $fromTitle, $toNS, $toTitle, $iw = null) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);

		if ($iw !== null) {
			self::$statements['iwredirect']->execute([$fromID, $toNS, $toTitle, $iw]);
		} else {
			self::$statements['redirect']->execute([$fromID, $toNS, $toTitle]);
		}

		if ($pagelink) {
			$this->addPageLink($fromNS, $fromTitle, $toNS, $toTitle);
		}
	}

	private function addPageLink($fromNS, $fromTitle, $toNS, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);
		self::$statements['pagelink']->execute([$fromID, $toNS, $toTitle, $fromNS]);
	}

	private function addTemplateLink($fromNS, $fromTitle, $toNS, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);
		self::$statements['templatelink']->execute([$fromID, $toNS, $toTitle, $fromNS]);
	}

	private function addCategoryLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage(14, $toTitle);
		self::$statements['categorylink']->execute([$fromID, $toTitle]);
	}

	private function addImageLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage(6, $toTitle);
		self::$statements['imagelink']->execute([$fromID, $toTitle, $fromNS]);
	}

	private function compareCounts($page, $expected, $namespaces = '') {
		$expected += self::$defaultExpected;
		$actual = (new LinkCount($page, 'linkcounttest', $namespaces))->counts;

		foreach ($expected as $key => $val) {
			if (is_array($val)) {
				$expected[$key] = [
					'all' => $val[0],
					'direct' => $val[1],
					'indirect' => $val[2]
				];
			}
		}

		$this->assertEquals($expected, $actual, "page = $page, namespaces = $namespaces");
	}

	/**
	 * @dataProvider provideCounts
	 */
	public function testCounts($links, $counts, $nscounts = []) {
		self::$db->exec("TRUNCATE TABLE categorylinks;TRUNCATE TABLE imagelinks;TRUNCATE TABLE page;TRUNCATE TABLE pagelinks;TRUNCATE TABLE redirect;TRUNCATE TABLE templatelinks;");

		$this->pageIDs = [];
		$this->pageIDCounter = 0;

		if (array_key_exists('redirects+pagelinks', $links)) {
			foreach($links['redirects+pagelinks'] as $link) {
				$this->addRedirect(true, ...$link);
			}
		}

		if (array_key_exists('redirects', $links)) {
			foreach($links['redirects'] as $link) {
				$this->addRedirect(false, ...$link);
			}
		}

		if (array_key_exists('pagelinks', $links)) {
			foreach($links['pagelinks'] as $link) {
				$this->addPageLink(...$link);
			}
		}

		if (array_key_exists('templatelinks', $links)) {
			foreach($links['templatelinks'] as $link) {
				$this->addTemplateLink(...$link);
			}
		}

		if (array_key_exists('categorylinks', $links)) {
			foreach($links['categorylinks'] as $link) {
				$this->addCategoryLink(...$link);
			}
		}

		if (array_key_exists('imagelinks', $links)) {
			foreach($links['imagelinks'] as $link) {
				$this->addImageLink(...$link);
			}
		}

		foreach ($counts as $page => $expected) {
			$this->compareCounts($page, $expected);
		}

		foreach ($nscounts as $ns => $counts) {
			foreach ($counts as $page => $expected) {
				$this->compareCounts($page, $expected, $ns);
			}
		}
	}

	public function provideCounts() {
		return [
			'page without links' => [
				[
					'pages' => [
						['pages', 0, 'Page']
					]
				],
				[
					'Page' => []
				]
			],

			'redirects' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page'],
						[0, 'Another_redirect', 0, 'Page']
					],
				],
				[
					'Page' => [
						'redirects' => 2
					]
				]
			],
			'redirect with interwiki' => [
				[
					'redirects' => [
						[0, 'Redirect', 0, 'Page', 'en']
					]
				],
				[
					'Page' => [
						'redirects' => 0
					]
				]
			],
			'redirect with blank interwiki' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page', '']
					]
				],
				[
					'Page' => [
						'redirects' => 1
					]
				]
			],

			'direct wikilink' => [
				[
					'pagelinks' => [
						[0, 'Link', 0, 'Page']
					],
				],
				[
					'Page' => [
						'wikilinks' => [1,1,0]
					]
				]
			],
			'direct category link' => [
				[
					'categorylinks' => [
						[0, 'Link', 'Category']
					],
				],
				[
					'Category:Category' => [
						'categorylinks' => [1,1,0]
					]
				]
			],
			'direct transclusion' => [
				[
					'templatelinks' => [
						[0, 'Link', 10, 'Template']
					]
				],
				[
					'Template:Template' => [
						'transclusions' => [1,1,0]
					]
				]
			],
			'direct file link' => [
				[
					'imagelinks' => [
						[0, 'Link', 'Image.png']
					],
				],
				[
					'File:Image.png' => [
						'filelinks' => [1,1,0]
					]
				]
			],

			'wikilink to redirect' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page']
					],
					'pagelinks' => [
						[0, 'Link', 0, 'Redirect']
					]
				],
				[
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'category links to redirect' => [
				[
					'redirects+pagelinks' => [
						[14, 'Redirect', 14, 'Category']
					],
					'categorylinks' => [
						[0, 'Link', 'Redirect']
					]
				],
				[
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'transclusion of redirect' => [
				[
					'redirects+pagelinks' => [
						[10, 'Redirect', 10, 'Template']
					],
					'templatelinks' => [
						[0, 'Link', 10, 'Redirect'],
						// Count transclusion of redirect as a transclusion of the redirect target
						[0, 'Link', 10, 'Template']
					]
				],
				[
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'file link to redirect' => [
				[
					'redirects+pagelinks' => [
						[6, 'Redirect.png', 6, 'Image.png']
					],
					'imagelinks' => [
						[0, 'Link', 'Redirect.png'],
						// Count file link to redirect as a file link to the redirect target
						[0, 'Link', 'Image.png']
					]
				],
				[
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],

			'transclusion of redirect content' => [
				[
					'redirects+pagelinks' => [
						[10, 'Redirect', 10, 'Template']
					],
					'templatelinks' => [
						[0, 'Link', 10, 'Redirect']
					]
				],
				[
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [0,0,0],
						'redirects' => 1
					]
				]
			],
			'file link to redirect content' => [
				[
					'redirects+pagelinks' => [
						[6, 'Redirect.png', 6, 'Image.png']
					],
					'imagelinks' => [
						[0, 'Link', 'Redirect.png']
					]
				],
				[
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [0,0,0],
						'redirects' => 1
					]
				]
			],

			'wikilinks to two redirects' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page'],
						[0, 'Another_redirect', 0, 'Page']
					],
					'pagelinks' => [
						[0, 'Link', 0, 'Redirect'],
						[0, 'Link', 0, 'Another_redirect']
					]
				],
				[
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Another_redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [1,0,1],
						'redirects' => 2,
					]
				]
			],
			'category links to two redirects' => [
				[
					'redirects+pagelinks' => [
						[14, 'Redirect', 14, 'Category'],
						[14, 'Another_redirect', 14, 'Category']
					],
					'categorylinks' => [
						[0, 'Link', 'Redirect'],
						[0, 'Link', 'Another_redirect']
					]
				],
				[
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Another_redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [1,0,1],
						'redirects' => 2,
					]
				]
			],
			'transclusions to two redirects' => [
				[
					'redirects+pagelinks' => [
						[10, 'Redirect', 10, 'Template'],
						[10, 'Another_redirect', 10, 'Template']
					],
					'templatelinks' => [
						[0, 'Link', 10, 'Redirect'],
						[0, 'Link', 10, 'Another_redirect'],
						// Count transclusion of redirect as a transclusion of the redirect target
						[0, 'Link', 10, 'Template'],
					]
				],
				[
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Another_redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1,0,1],
						'redirects' => 2,
					]
				]
			],
			'file links to two redirects' => [
				[
					'redirects+pagelinks' => [
						[6, 'Redirect.png', 6, 'Image.png'],
						[6, 'Another_redirect.png', 6, 'Image.png']
					],
					'imagelinks' => [
						[0, 'Link', 'Redirect.png'],
						[0, 'Link', 'Another_redirect.png'],
						// Count file link to redirect as a file link to the redirect target
						[0, 'Link', 'Image.png'],
					],
				],
				[
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Another_redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1,0,1],
						'redirects' => 2,
					]
				]
			],

			'wikilinks to redirect and target' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page']
					],
					'pagelinks' => [
						[0, 'Link', 0, 'Redirect'],
						[0, 'Link', 0, 'Page']
					]
				],
				[
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [1,1,1],
						'redirects' => 1,
					]
				]
			],
			'category links to redirect and target' => [
				[
					'redirects+pagelinks' => [
						[14, 'Redirect', 14, 'Category']
					],
					'categorylinks' => [
						[0, 'Link', 'Redirect'],
						[0, 'Link', 'Category']
					]
				],
				[
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [1,1,1],
						'redirects' => 1,
					]
				]
			],
			'transclusions to redirect and target' => [
				[
					'redirects+pagelinks' => [
						[10, 'Redirect', 10, 'Template']
					],
					'templatelinks' => [
						[0, 'Link', 10, 'Redirect'],
						[0, 'Link', 10, 'Template']
						// No way to distinguish from transclusion of a redirect and transclusions of a redirect and it's target
					]
				],
				[
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1,0,1],
						'redirects' => 1,
					]
				]
			],
			'file links to redirect and target' => [
				[
					'redirects+pagelinks' => [
						[6, 'Redirect.png', 6, 'Image.png']
					],
					'imagelinks' => [
						[0, 'Link', 'Redirect.png'],
						[0, 'Link', 'Image.png']
						// No way to distinguish from file link to a redirect and file links to a redirect and it's target
					]
				],
				[
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1,0,1],
						'redirects' => 1,
					]
				]
			],

			'wikilinks to pages with same title in different namespaces' => [
				[
					'pagelinks' => [
						[0, 'Link 1', 0, 'Page'],
						[0, 'Link 2', 1, 'Page']
					]
				],
				[
					'Page' => [
						'wikilinks' => [1,1,0]
					],
					'Talk:Page' => [
						'wikilinks' => [1,1,0]
					]
				]
			],
			'transclusion of pages with same title in different namespaces' => [
				[
					'templatelinks' => [
						[0, 'Link 1', 10, 'Template'],
						[0, 'Link 2', 0, 'Template']
					],
				],
				[
					'Template:Template' => [
						'transclusions' => [1,1,0]
					],
					'Template' => [
						'transclusions' => [1,1,0]
					]
				]
			],

			'namespace conditions for wikilink' => [
				[
					'pagelinks' => [
						[0, 'Link', 0, 'Page'],
						[1, 'Link', 0, 'Page'],
						[2, 'Link', 0, 'Page']
					]
				],
				[],
				[
					'' => [
						'Page' => [
							'wikilinks' => [3,3,0]
						]
					],
					'0,1' => [
						'Page' => [
							'wikilinks' => [2,2,0]
						]
					],
					'0' => [
						'Page' => [
							'wikilinks' => [1,1,0]
						]
					]
				]
			],
			'namespace conditions for category links' => [
				[
					'categorylinks' => [
						[0, 'Link', 'Category'],
						[1, 'Link', 'Category'],
						[2, 'Link', 'Category']
					]
				],
				[],
				[
					'' => [
						'Category:Category' => [
							'categorylinks' => [3,3,0]
						]
					],
					'0,1' => [
						'Category:Category' => [
							'categorylinks' => [2,2,0]
						]
					],
					'0' => [
						'Category:Category' => [
							'categorylinks' => [1,1,0]
						]
					]
				]
			],
			'namespace conditions for transclusions' => [
				[
					'templatelinks' => [
						[0, 'Link', 10, 'Template'],
						[1, 'Link', 10, 'Template'],
						[2, 'Link', 10, 'Template']
					]
				],
				[],
				[
					'' => [
						'Template:Template' => [
							'transclusions' => [3,3,0]
						]
					],
					'0,1' => [
						'Template:Template' => [
							'transclusions' => [2,2,0]
						]
					],
					'0' => [
						'Template:Template' => [
							'transclusions' => [1,1,0]
						]
					]
				]
			],
			'namespace conditions for file links' => [
				[
					'imagelinks' => [
						[0, 'Link', 'Image.png'],
						[1, 'Link', 'Image.png'],
						[2, 'Link', 'Image.png']
					]
				],
				[],
				[
					'' => [
						'File:Image.png' => [
							'filelinks' => [3,3,0]
						]
					],
					'0,1' => [
						'File:Image.png' => [
							'filelinks' => [2,2,0]
						]
					],
					'0' => [
						'File:Image.png' => [
							'filelinks' => [1,1,0]
						]
					]
				]
			],

			'namespace conditions from indirect for wikilinks' => [
				[
					'redirects+pagelinks' => [
						[0, 'Redirect', 0, 'Page'],
					],
					'pagelinks' => [
						[0, 'Link', 0, 'Redirect'],
						[1, 'Link', 0, 'Redirect'],
						[2, 'Link', 0, 'Redirect']
					]
				],
				[],
				[
					'' => [
						'Redirect' => [
							'wikilinks' => [3,3,0]
						],
						'Page' => [
							'wikilinks' => [3,0,3],
							'redirects' => 1
						],
					],
					'0,1' => [
						'Redirect' => [
							'wikilinks' => [2,2,0]
						],
						'Page' => [
							'wikilinks' => [2,0,2],
							'redirects' => 1
						]
					],
					'0' => [
						'Redirect' => [
							'wikilinks' => [1,1,0]
						],
						'Page' => [
							'wikilinks' => [1,0,1],
							'redirects' => 1
						]
					]
				]
			],
			'namespace conditions from indirect for category links' => [
				[
					'redirects+pagelinks' => [
						[14, 'Redirect', 14, 'Category'],
					],
					'categorylinks' => [
						[0, 'Link', 'Redirect'],
						[1, 'Link', 'Redirect'],
						[2, 'Link', 'Redirect']
					],
				],
				[],
				[
					'' => [
						'Category:Redirect' => [
							'categorylinks' => [3,3,0]
						],
						'Category:Category' => [
							'categorylinks' => [3,0,3],
							'redirects' => 1
						],
					],
					'0,1' => [
						'Category:Redirect' => [
							'categorylinks' => [2,2,0]
						],
						'Category:Category' => [
							'categorylinks' => [2,0,2],
							'redirects' => 0
						]
					],
					'0' => [
						'Category:Redirect' => [
							'categorylinks' => [1,1,0]
						],
						'Category:Category' => [
							'categorylinks' => [1,0,1],
							'redirects' => 0
						]
					]
				]
			],
			'namespace conditions from indirect for transclusions' => [
				[
					'redirects+pagelinks' => [
						[10, 'Redirect', 10, 'Template'],
					],
					'templatelinks' => [
						[0, 'Link', 10, 'Redirect'],
						[1, 'Link', 10, 'Redirect'],
						[2, 'Link', 10, 'Redirect'],
						// Count transclusions of redirect as transclusions of the redirect target
						[0, 'Link', 10, 'Template'],
						[1, 'Link', 10, 'Template'],
						[2, 'Link', 10, 'Template'],
					]
				],
				[],
				[
					'' => [
						'Template:Redirect' => [
							'transclusions' => [3,3,0]
						],
						'Template:Template' => [
							'transclusions' => [3,0,3],
							'redirects' => 1
						],
					],
					'0,1' => [
						'Template:Redirect' => [
							'transclusions' => [2,2,0]
						],
						'Template:Template' => [
							'transclusions' => [2,0,2],
							'redirects' => 0
						]
					],
					'0' => [
						'Template:Redirect' => [
							'transclusions' => [1,1,0]
						],
						'Template:Template' => [
							'transclusions' => [1,0,1],
							'redirects' => 0
						]
					]
				]
			],
			'namespace conditions from indirect for transclusions' => [
				[
					'redirects+pagelinks' => [
						[6, 'Redirect.png', 6, 'Image.png'],
					],
					'imagelinks' => [
						[0, 'Link', 'Redirect.png'],
						[1, 'Link', 'Redirect.png'],
						[2, 'Link', 'Redirect.png'],
						// Count transclusions of redirect as transclusions of the redirect target
						[0, 'Link', 'Image.png'],
						[1, 'Link', 'Image.png'],
						[2, 'Link', 'Image.png'],
					],
				],
				[],
				[
					'' => [
						'File:Redirect.png' => [
							'filelinks' => [3,3,0]
						],
						'File:Image.png' => [
							'filelinks' => [3,0,3],
							'redirects' => 1
						],
					],
					'0,1' => [
						'File:Redirect.png' => [
							'filelinks' => [2,2,0]
						],
						'File:Image.png' => [
							'filelinks' => [2,0,2]
						]
					],
					'0' => [
						'File:Redirect.png' => [
							'filelinks' => [1,1,0]
						],
						'File:Image.png' => [
							'filelinks' => [1,0,1]
						]
					]
				]
			],
		];
	}

	/**
	 * @dataProvider provideHtmlOutput
	 */
	public function testHtmlOutput($page, $expected) {
		$this->assertEquals($expected, (new LinkCount($page, 'linkcounttest', ''))->html());
	}

	public function provideHtmlOutput() {
		return [
			'main namespace' => [
				'Page',
				'<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidetrans=1&hideimages=1">Wikilinks</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></div><div class="all">0</div><div class="direct">‒</div><div class="indirect">‒</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&hideimages=1">Transclusions</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page">What links here</a></div>'
			],
			'talk namespace' => [
				'Talk:Page',
				'<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidetrans=1&hideimages=1">Wikilinks</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></div><div class="all">0</div><div class="direct">‒</div><div class="indirect">‒</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&hideimages=1">Transclusions</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage">What links here</a></div>'
			],
			'category namespace' => [
				'Category:Category',
				'<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div><div class="type"><a href="https://en.wikipedia.org/wiki/Category%3ACategory">Category links</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidetrans=1&hideimages=1">Wikilinks</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></div><div class="all">0</div><div class="direct">‒</div><div class="indirect">‒</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&hideimages=1">Transclusions</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory">What links here</a></div>'
			],
			'file namespace' => [
				'File:Image.png',
				'<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&hidelinks=1">File links</a></div><div class="all">3</div><div class="direct">0</div><div class="indirect">3</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&hideimages=1">Wikilinks</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></div><div class="all">1</div><div class="direct">‒</div><div class="indirect">‒</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&hideimages=1">Transclusions</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png">What links here</a></div>'
			],
			'? in title' => [
				'?',
				'<div class="out"><div class="header">Type</div><div class="header">All</div><div class="header">Direct</div><div class="header">Indirect</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidetrans=1&hideimages=1">Wikilinks</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></div><div class="all">0</div><div class="direct">‒</div><div class="indirect">‒</div><div class="type"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hideimages=1">Transclusions</a></div><div class="all">0</div><div class="direct">0</div><div class="indirect">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F">What links here</a></div>'
			]
		];
	}

	/**
	 * @dataProvider provideJsonOutput
	 */
	public function testJsonOutput($page, $expected) {
		$this->assertEquals($expected, (new LinkCount($page, 'linkcounttest', ''))->json(false));
	}

	public function provideJsonOutput() {
		return [
			'main namespace' => [
				'Page',
				'{"filelinks":null,"categorylinks":null,"wikilinks":{"all":0,"direct":0,"indirect":0},"redirects":0,"transclusions":{"all":0,"direct":0,"indirect":0}}'
			],
			'talk namespace' => [
				'Talk:Page',
				'{"filelinks":null,"categorylinks":null,"wikilinks":{"all":0,"direct":0,"indirect":0},"redirects":0,"transclusions":{"all":0,"direct":0,"indirect":0}}'
			],
			'category namespace' => [
				'Category:Category',
				'{"filelinks":null,"categorylinks":{"all":0,"direct":0,"indirect":0},"wikilinks":{"all":0,"direct":0,"indirect":0},"redirects":0,"transclusions":{"all":0,"direct":0,"indirect":0}}'
			],
			'file namespace' => [
				'File:Image.png',
				'{"filelinks":{"all":3,"direct":0,"indirect":3},"categorylinks":null,"wikilinks":{"all":0,"direct":0,"indirect":0},"redirects":1,"transclusions":{"all":0,"direct":0,"indirect":0}}'
			]
		];
	}

	/**
	 * @dataProvider provideDBInfo
	 */
	public function testDBInfo($page, $expected) {
		$actual = LinkCount::getDBInfo('linkcounttest', 'https://en.wikipedia.org', $page);
		$this->assertEquals($expected, $actual);
	}

	public function provideDBInfo() {
		return [
			'main namespace' => [
				'Foo',
				[0, 'Foo']
			],
			'main namespace with colon' => [
				':Foo',
				[0, 'Foo']
			],
			'talk namespace' => [
				'Talk:Foo',
				[1, 'Foo']
			],
			'namespace with space' => [
				'Template talk:Foo',
				[11, 'Foo']
			],
			'namespace with underscores' => [
				'Template_talk:Foo',
				[11, 'Foo']
			],
			'page with spaces' => [
				'Foo bar baz',
				[0, 'Foo_bar_baz']
			],
			'page with underscores' => [
				'Foo_bar_baz',
				[0, 'Foo_bar_baz']
			],
			'lowercase first letter' => [
				'foo bar baz',
				[0, 'Foo_bar_baz']
			],
			'case-sensitive namespace' => [
				'Gadget definition talk:foo',
				[2303, 'foo']
			],
			'alias namespace' => [
				'Image:Foo.png',
				[6, 'Foo.png']
			],
			'template namespace with colon' => [
				':Template:Foo',
				[10, 'Foo']
			],
			'test fragment' => [
				'Foo#Bar',
				[0, 'Foo']
			],
			'test invalid namespace' => [
				'Foo:Bar',
				[0, 'Foo:Bar']
			],
			'test invalid namespace with prefix colon' => [
				':Foo:Bar',
				[0, 'Foo:Bar']
			],
			'test lowercase invalid namespace' => [
				'foo:bar',
				[0, 'Foo:bar']
			],
			'test canonical name' => [
				'Project:Foo',
				[4, 'Foo']
			],
			'test wiki defined name' => [
				'Wikipedia:Foo',
				[4, 'Foo']
			]
		];
	}
}
