<?php

require __DIR__ . '/TestDatabaseFactory.php';

use PHPUnit\Framework\TestCase;

class LinkCountTest extends TestCase {
	private static $db;
	private static $statements;
	private static $defaultExpected;

	private $pageIDs = [];
	private $targetIDs = [];
	private $counter = 0;

	public static function setUpBeforeClass(): void {
		self::$db = TestDatabaseFactory::create();

		self::$statements = [
			'page' => self::$db->prepare('INSERT INTO `page` (page_id, page_namespace, page_title) VALUES (?, ?, ?)'),
			'redirect' => self::$db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, NULL)'),
			'iwredirect' => self::$db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, ?)'),
			'pagelink' => self::$db->prepare('INSERT INTO pagelinks (pl_from, pl_from_namespace, pl_target_id) VALUES (?, ?, ?)'),
			'templatelink' => self::$db->prepare('INSERT INTO templatelinks (tl_from, tl_from_namespace, tl_target_id) VALUES (?, ?, ?)'),
			'categorylink' => self::$db->prepare('INSERT INTO categorylinks (cl_from, cl_to) VALUES (?, ?)'),
			'imagelink' => self::$db->prepare('INSERT INTO imagelinks (il_from, il_to, il_from_namespace) VALUES (?, ?, ?)'),
			'linktarget' => self::$db->prepare('INSERT INTO linktarget (lt_id, lt_namespace, lt_title) VALUES (?, ?, ?)')
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
		if (!array_key_exists($ns, $this->pageIDs)) {
			$this->pageIDs[$ns] = [];
		}

		if (!array_key_exists($title, $this->pageIDs[$ns])) {
			$id = ++$this->counter;
			self::$statements['page']->execute([$id, $ns, $title]);
			$this->pageIDs[$ns][$title] = $id;
		}

		return $this->pageIDs[$ns][$title];
	}

	private function ensureTarget($ns, $title) {
		if (!array_key_exists($ns, $this->targetIDs)) {
			$this->targetIDs[$ns] = [];
		}

		if (!array_key_exists($title, $this->targetIDs[$ns])) {
			$id = ++$this->counter;
			self::$statements['linktarget']->execute([$id, $ns, $title]);
			$this->targetIDs[$ns][$title] = $id;
		}

		return $this->targetIDs[$ns][$title];
	}

	private function addRedirect($pagelink, $fromNS, $fromTitle, $toNS, $toTitle, $iw = null) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);

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
		$targetID = $this->ensureTarget($toNS, $toTitle);
		self::$statements['pagelink']->execute([$fromID, $fromNS, $targetID]);
	}

	private function addTemplateLink($fromNS, $fromTitle, $toNS, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$targetID = $this->ensureTarget($toNS, $toTitle);
		self::$statements['templatelink']->execute([$fromID, $fromNS, $targetID]);
	}

	private function addCategoryLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		self::$statements['categorylink']->execute([$fromID, $toTitle]);
	}

	private function addImageLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
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
		self::$db->exec("
			TRUNCATE TABLE categorylinks;
			TRUNCATE TABLE imagelinks;
			TRUNCATE TABLE page;
			TRUNCATE TABLE pagelinks;
			TRUNCATE TABLE redirect;
			TRUNCATE TABLE templatelinks;
			TRUNCATE TABLE linktarget;
		");

		$this->pageIDs = [];
		$this->targetIDs = [];
		$this->counter = 0;

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
	public function testHtmlOutput($page, $expected, $project = null) {
		$this->assertEquals($expected, (new LinkCount($page, $project ?? 'linkcounttest'))->getHtml());
	}

	public function provideHtmlOutput() {
		return [
			'main namespace' => [
				'Page',
				"<div role='table' aria-rowcount='4' class='out'><div role='row'><div role='columnheader'>Type</div><div role='columnheader'>All</div><abbr title='Number of pages that link to page using the actual page name' role='columnheader'>Direct</abbr><abbr title='Number of pages that link to the page through a redirect' role='columnheader'>Indirect</abbr></div><div id='wikilinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidetrans=1&amp;hideimages=1'>Wikilinks</a> <a href='#wikilinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='redirects' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&amp;hidetrans=1&amp;hideimages=1'>Redirects</a> <a href='#redirects' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>&#8210;</div><div role='cell' class='indirect'>&#8210;</div></div><div id='transclusions' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&amp;hideimages=1'>Transclusions</a> <a href='#transclusions' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div></div><div class='links'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page'>What links here</a></div>"
			],
			'talk namespace' => [
				'Talk:Page',
				"<div role='table' aria-rowcount='4' class='out'><div role='row'><div role='columnheader'>Type</div><div role='columnheader'>All</div><abbr title='Number of pages that link to page using the actual page name' role='columnheader'>Direct</abbr><abbr title='Number of pages that link to the page through a redirect' role='columnheader'>Indirect</abbr></div><div id='wikilinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidetrans=1&amp;hideimages=1'>Wikilinks</a> <a href='#wikilinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='redirects' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&amp;hidetrans=1&amp;hideimages=1'>Redirects</a> <a href='#redirects' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>&#8210;</div><div role='cell' class='indirect'>&#8210;</div></div><div id='transclusions' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&amp;hideimages=1'>Transclusions</a> <a href='#transclusions' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div></div><div class='links'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage'>What links here</a></div>"
			],
			'category namespace' => [
				'Category:Category',
				"<div role='table' aria-rowcount='5' class='out'><div role='row'><div role='columnheader'>Type</div><div role='columnheader'>All</div><abbr title='Number of pages that link to page using the actual page name' role='columnheader'>Direct</abbr><abbr title='Number of pages that link to the page through a redirect' role='columnheader'>Indirect</abbr></div><div id='categorylinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Category%3ACategory'>Category links</a> <a href='#categorylinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='wikilinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidetrans=1&amp;hideimages=1'>Wikilinks</a> <a href='#wikilinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='redirects' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&amp;hidetrans=1&amp;hideimages=1'>Redirects</a> <a href='#redirects' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>&#8210;</div><div role='cell' class='indirect'>&#8210;</div></div><div id='transclusions' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&amp;hideimages=1'>Transclusions</a> <a href='#transclusions' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div></div><div class='links'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory'>What links here</a></div>"
			],
			'file namespace' => [
				'File:Image.png',
				"<div role='table' aria-rowcount='5' class='out'><div role='row'><div role='columnheader'>Type</div><div role='columnheader'>All</div><abbr title='Number of pages that link to page using the actual page name' role='columnheader'>Direct</abbr><abbr title='Number of pages that link to the page through a redirect' role='columnheader'>Indirect</abbr></div><div id='filelinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&amp;hidelinks=1'>File links</a> <a href='#filelinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>3</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>3</div></div><div id='wikilinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&amp;hideimages=1'>Wikilinks</a> <a href='#wikilinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='redirects' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&amp;hidetrans=1&amp;hideimages=1'>Redirects</a> <a href='#redirects' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>1</div><div role='cell' class='direct'>&#8210;</div><div role='cell' class='indirect'>&#8210;</div></div><div id='transclusions' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&amp;hideimages=1'>Transclusions</a> <a href='#transclusions' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div></div><div class='links'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png'>What links here</a></div>"
			],
			'? in title' => [
				'?',
				"<div role='table' aria-rowcount='4' class='out'><div role='row'><div role='columnheader'>Type</div><div role='columnheader'>All</div><abbr title='Number of pages that link to page using the actual page name' role='columnheader'>Direct</abbr><abbr title='Number of pages that link to the page through a redirect' role='columnheader'>Indirect</abbr></div><div id='wikilinks' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidetrans=1&amp;hideimages=1'>Wikilinks</a> <a href='#wikilinks' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div><div id='redirects' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&amp;hidetrans=1&amp;hideimages=1'>Redirects</a> <a href='#redirects' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>&#8210;</div><div role='cell' class='indirect'>&#8210;</div></div><div id='transclusions' role='row'><div role='cell' class='type'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&amp;hideimages=1'>Transclusions</a> <a href='#transclusions' title='Link to row' class='hash-link'>(#)</a></div><div role='cell' class='all'>0</div><div role='cell' class='direct'>0</div><div role='cell' class='indirect'>0</div></div></div><div class='links'><a href='https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F'>What links here</a></div>"
			],
			'no parameters' => [
				'',
				'',
				''
			],
			'no title (error)' => [
				'',
				'<div class=\'error\'>Page name is required.</div>'
			]
		];
	}

	/**
	 * @dataProvider provideJsonOutput
	 */
	public function testJsonOutput($page, $expected, $project = null) {
		$this->assertEquals($expected, (new LinkCount($page, $project ?? 'linkcounttest'))->getJson());
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
			],
			'no parameters' => [
				'',
				'{}',
				''
			],
			'no title (error)' => [
				'',
				'{"error":"Page name is required."}'
			]
		];
	}
}
