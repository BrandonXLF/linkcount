<?php

require __DIR__ . '/../vendor/autoload.php';

Config::set('server', 'localhost');
Config::set('database', 'linkcounttest');

class DatabaseThatRecordsQueries extends Database {
	public $queries = [];

	public function query($sql) {
		$queries[] = $sql;
		return parent::query($sql);
	}
}

class LinkCountThatRecordsQueries extends LinkCount {
	public function __construct($page, $project, $namespaces = '') {
		parent::__construct($page, $project, $namespaces);
		$this->db = new DatabaseThatRecordsQueries('', '');
	}
}

class TestLinkCount {
	private $db;
	private $statements;
	private $pageIDs = [];
	private $pageIDCounter = 0;
	private $defaultExpected;

	public function __construct() {
		$this->db = new Database('localhost', '');

		$this->statements = [
			'page' => $this->db->prepare('INSERT INTO `page` (page_id, page_namespace, page_title) VALUES (?, ?, ?)'),
			'redirect' => $this->db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, NULL)'),
			'iwredirect' => $this->db->prepare('INSERT INTO redirect (rd_from, rd_namespace, rd_title, rd_interwiki) VALUES (?, ?, ?, ?)'),
			'pagelink' => $this->db->prepare('INSERT INTO pagelinks (pl_from, pl_namespace, pl_title, pl_from_namespace) VALUES (?, ?, ?, ?)'),
			'templatelink' => $this->db->prepare('INSERT INTO templatelinks (tl_from, tl_namespace, tl_title, tl_from_namespace) VALUES (?, ?, ?, ?)'),
			'categorylink' => $this->db->prepare('INSERT INTO categorylinks (cl_from, cl_to) VALUES (?, ?)'),
			'imagelink' => $this->db->prepare('INSERT INTO imagelinks (il_from, il_to, il_from_namespace) VALUES (?, ?, ?)')
		];

		$this->defaultExpected = [
			'filelinks' => null,
			'categorylinks' => null,
			'wikilinks' => [0,0,0],
			'redirects' => 0,
			'transclusions' => [0,0,0]
		];

		$this->db->exec(file_get_contents(__DIR__ . '/linkcounttest.sql'));
	}

	function ensurePage($ns, $title) {
		if(!array_key_exists($ns, $this->pageIDs)) {
			$this->pageIDs[$ns] = [];
		}

		if(!array_key_exists($title, $this->pageIDs[$ns])) {
			$id = ++$this->pageIDCounter;
			$this->statements['page']->execute([$id, $ns, $title]);
			$this->pageIDs[$ns][$title] = $id;
		}

		return $this->pageIDs[$ns][$title];
	}

	function addRedirect($pagelink, $fromNS, $fromTitle, $toNS, $toTitle, $iw = null) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);

		if ($iw !== null) {
			$this->statements['iwredirect']->execute([$fromID, $toNS, $toTitle, $iw]);
		} else {
			$this->statements['redirect']->execute([$fromID, $toNS, $toTitle]);
		}

		if ($pagelink) {
			$this->addPageLink($fromNS, $fromTitle, $toNS, $toTitle);
		}
	}

	function addPageLink($fromNS, $fromTitle, $toNS, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);
		$this->statements['pagelink']->execute([$fromID, $toNS, $toTitle, $fromNS]);
	}

	function addTemplateLink($fromNS, $fromTitle, $toNS, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage($toNS, $toTitle);
		$this->statements['templatelink']->execute([$fromID, $toNS, $toTitle, $fromNS]);
	}

	function addCategoryLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage(14, $toTitle);
		$this->statements['categorylink']->execute([$fromID, $toTitle]);
	}

	function addImageLink($fromNS, $fromTitle, $toTitle) {
		$fromID = $this->ensurePage($fromNS, $fromTitle);
		$this->ensurePage(6, $toTitle);
		$this->statements['imagelink']->execute([$fromID, $toTitle, $fromNS]);
	}

	function compareCounts($page, $expected, $namespaces = '') {
		$failed = [];
		$expected += $this->defaultExpected;
		$actual = (new LinkCount($page, 'linkcounttest', $namespaces))->counts;
		$fromNamespaces = $namespaces !== '' ? " from namespaces $namespaces" : '';
		$keys = array_unique(array_merge(array_keys($expected), array_keys($actual)));

		foreach ($expected as $key => $val) {
			if (is_array($val) && array_key_exists(0, $val)) {
				$expected[$key] = [
					'direct' => $val[1],
					'indirect' => $val[2],
					'all' => $val[0]
				];
			}
		}

		foreach ($keys as $key) {
			if (!array_key_exists($key, $expected) || !array_key_exists($key, $actual) || $expected[$key] !== $actual[$key]) {
				$expectedStr = array_key_exists($key, $expected) ? json_encode($expected[$key]) : 'NOT SET';
				$realityStr = array_key_exists($key, $actual) ? json_encode($actual[$key]) : 'NOT SET';
				$failed[] = "$key for $page$fromNamespaces. Expected $expectedStr, got $realityStr.";
			}
		}

		return $failed;
	}

	public function testCounts($data) {
		$this->db->exec("TRUNCATE TABLE categorylinks;TRUNCATE TABLE imagelinks;TRUNCATE TABLE page;TRUNCATE TABLE pagelinks;TRUNCATE TABLE redirect;TRUNCATE TABLE templatelinks;");

		$this->pageIDs = [];
		$this->pageIDCounter = 0;

		if (array_key_exists('redirects+pagelinks', $data)) {
			foreach($data['redirects+pagelinks'] as $link) {
				$this->addRedirect(true, ...$link);
			}
		}

		if (array_key_exists('redirects', $data)) {
			foreach($data['redirects'] as $link) {
				$this->addRedirect(false, ...$link);
			}
		}

		if (array_key_exists('pagelinks', $data)) {
			foreach($data['pagelinks'] as $link) {
				$this->addPageLink(...$link);
			}
		}

		if (array_key_exists('templatelinks', $data)) {
			foreach($data['templatelinks'] as $link) {
				$this->addTemplateLink(...$link);
			}
		}

		if (array_key_exists('categorylinks', $data)) {
			foreach($data['categorylinks'] as $link) {
				$this->addCategoryLink(...$link);
			}
		}

		if (array_key_exists('imagelinks', $data)) {
			foreach($data['imagelinks'] as $link) {
				$this->addImageLink(...$link);
			}
		}

		$failed = [];

		if (isset($data['nscounts'])) {
			foreach ($data['nscounts'] as $ns => $counts) {
				foreach ($counts as $page => $expected) {
					$failed = array_merge($failed, $this->compareCounts($page, $expected, $ns));
				}
			}
		} else {
			foreach ($data['counts'] as $page => $expected) {
				$failed = array_merge($failed, $this->compareCounts($page, $expected));
			}
		}

		return $failed;
	}

	public function provideCounts() {
		return [
			'Test page without links' => [
				'pages' => [
					[0, 'Page']
				],
				'counts' => [
					'Page' => []
				]
			],

			'Test redirects' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page'],
					[0, 'Another_redirect', 0, 'Page']
				],
				'counts' => [
					'Page' => [
						'redirects' => 2
					]
				]
			],
			'Test redirect with interwiki' => [
				'redirects' => [
					[0, 'Redirect', 0, 'Page', 'en']
				],
				'counts' => [
					'Page' => [
						'redirects' => 0
					]
				]
			],
			'Test redirect with blank interwiki' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page', '']
				],
				'counts' => [
					'Page' => [
						'redirects' => 1
					]
				]
			],

			'Test direct wikilink' => [
				'pagelinks' => [
					[0, 'Link', 0, 'Page']
				],
				'counts' => [
					'Page' => [
						'wikilinks' => [1,1,0]
					]
				]
			],
			'Test direct category link' => [
				'categorylinks' => [
					[0, 'Link', 'Category']
				],
				'counts' => [
					'Category:Category' => [
						'categorylinks' => [1,1,0]
					]
				]
			],
			'Test direct transclusion' => [
				'templatelinks' => [
					[0, 'Link', 10, 'Template']
				],
				'counts' => [
					'Template:Template' => [
						'transclusions' => [1,1,0]
					]
				]
			],
			'Test direct file link' => [
				'imagelinks' => [
					[0, 'Link', 'Image.png']
				],
				'counts' => [
					'File:Image.png' => [
						'filelinks' => [1,1,0]
					]
				]
			],

			'Test wikilink to redirect' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page']
				],
				'pagelinks' => [
					[0, 'Link', 0, 'Redirect']
				],
				'counts' => [
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'Test category links to redirect' => [
				'redirects+pagelinks' => [
					[14, 'Redirect', 14, 'Category']
				],
				'categorylinks' => [
					[0, 'Link', 'Redirect']
				],
				'counts' => [
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'Test transclusion of redirect' => [
				'redirects+pagelinks' => [
					[10, 'Redirect', 10, 'Template']
				],
				'templatelinks' => [
					[0, 'Link', 10, 'Redirect'],
					// Count transclusion of redirect as a transclusion of the redirect target
					[0, 'Link', 10, 'Template']
				],
				'counts' => [
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1,0,1],
						'redirects' => 1
					]
				]
			],
			'Test file link to redirect' => [
				'redirects+pagelinks' => [
					[6, 'Redirect.png', 6, 'Image.png']
				],
				'imagelinks' => [
					[0, 'Link', 'Redirect.png'],
					// Count file link to redirect as a file link to the redirect target
					[0, 'Link', 'Image.png']
				],
				'counts' => [
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1,0,1],
						'redirects' => 1
					]
				]
			],

			'Test wikilinks to two redirects' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page'],
					[0, 'Another_redirect', 0, 'Page']
				],
				'pagelinks' => [
					[0, 'Link', 0, 'Redirect'],
					[0, 'Link', 0, 'Another_redirect']
				],
				'counts' => [
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Another_redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [2,0,2],
						'redirects' => 2,
					]
				]
			],
			'Test category links to two redirects' => [
				'redirects+pagelinks' => [
					[14, 'Redirect', 14, 'Category'],
					[14, 'Another_redirect', 14, 'Category']
				],
				'categorylinks' => [
					[0, 'Link', 'Redirect'],
					[0, 'Link', 'Another_redirect']
				],
				'counts' => [
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Another_redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [2,0,2],
						'redirects' => 2,
					]
				]
			],
			'Test transclusions to two redirects' => [
				'redirects+pagelinks' => [
					[10, 'Redirect', 10, 'Template'],
					[10, 'Another_redirect', 10, 'Template']
				],
				'templatelinks' => [
					[0, 'Link', 10, 'Redirect'],
					[0, 'Link', 10, 'Another_redirect'],
					// Count transclusion of redirect as a transclusion of the redirect target
					[0, 'Link', 10, 'Template'],
				],
				'counts' => [
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Another_redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1, -1, 2],
						'redirects' => 2,
					]
				]
			],
			'Test file links to two redirects' => [
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
				'counts' => [
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Another_redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1, -1, 2],
						'redirects' => 2,
					]
				]
			],

			'Test wikilinks to redirect and target' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page']
				],
				'pagelinks' => [
					[0, 'Link', 0, 'Redirect'],
					[0, 'Link', 0, 'Page']
				],
				'counts' => [
					'Redirect' => [
						'wikilinks' => [1,1,0]
					],
					'Page' => [
						'wikilinks' => [2,1,1],
						'redirects' => 1,
					]
				]
			],
			'Test category links to redirect and target' => [
				'redirects+pagelinks' => [
					[14, 'Redirect', 14, 'Category']
				],
				'categorylinks' => [
					[0, 'Link', 'Redirect'],
					[0, 'Link', 'Category']
				],
				'counts' => [
					'Category:Redirect' => [
						'categorylinks' => [1,1,0]
					],
					'Category:Category' => [
						'categorylinks' => [2,1,1],
						'redirects' => 1,
					]
				]
			],
			'Test transclusions to redirect and target' => [
				'redirects+pagelinks' => [
					[10, 'Redirect', 10, 'Template']
				],
				'templatelinks' => [
					[0, 'Link', 10, 'Redirect'],
					[0, 'Link', 10, 'Template']
					// No way to distinguish from transclusion of a redirect and transclusions of a redirect and it's target
				],
				'counts' => [
					'Template:Redirect' => [
						'transclusions' => [1,1,0]
					],
					'Template:Template' => [
						'transclusions' => [1,0,1],
						'redirects' => 1,
					]
				]
			],
			'Test file links to redirect and target' => [
				'redirects+pagelinks' => [
					[6, 'Redirect.png', 6, 'Image.png']
				],
				'imagelinks' => [
					[0, 'Link', 'Redirect.png'],
					[0, 'Link', 'Image.png']
					// No way to distinguish from file link to a redirect and file links to a redirect and it's target
				],
				'counts' => [
					'File:Redirect.png' => [
						'filelinks' => [1,1,0]
					],
					'File:Image.png' => [
						'filelinks' => [1,0,1],
						'redirects' => 1,
					]
				]
			],

			'Test wikilinks to pages with same title in different namespaces' => [
				'pagelinks' => [
					[0, 'Link 1', 0, 'Page'],
					[0, 'Link 2', 1, 'Page']
				],
				'counts' => [
					'Page' => [
						'wikilinks' => [1,1,0]
					],
					'Talk:Page' => [
						'wikilinks' => [1,1,0]
					]
				]
			],
			'Test transclusion of pages with same title in different namespaces' => [
				'templatelinks' => [
					[0, 'Link 1', 10, 'Template'],
					[0, 'Link 2', 0, 'Template']
				],
				'counts' => [
					'Template:Template' => [
						'transclusions' => [1,1,0]
					],
					'Template' => [
						'transclusions' => [1,1,0]
					]
				]
			],

			'Test namespace conditions for wikilink' => [
				'pagelinks' => [
					[0, 'Link', 0, 'Page'],
					[1, 'Link', 0, 'Page'],
					[2, 'Link', 0, 'Page']
				],
				'nscounts' => [
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
			'Test namespace conditions for category links' => [
				'categorylinks' => [
					[0, 'Link', 'Category'],
					[1, 'Link', 'Category'],
					[2, 'Link', 'Category']
				],
				'nscounts' => [
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
			'Test namespace conditions for transclusions' => [
				'templatelinks' => [
					[0, 'Link', 10, 'Template'],
					[1, 'Link', 10, 'Template'],
					[2, 'Link', 10, 'Template']
				],
				'nscounts' => [
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
			'Test namespace conditions for file links' => [
				'imagelinks' => [
					[0, 'Link', 'Image.png'],
					[1, 'Link', 'Image.png'],
					[2, 'Link', 'Image.png']
				],
				'nscounts' => [
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

			'Test namespace conditions from indirect for wikilinks' => [
				'redirects+pagelinks' => [
					[0, 'Redirect', 0, 'Page'],
				],
				'pagelinks' => [
					[0, 'Link', 0, 'Redirect'],
					[1, 'Link', 0, 'Redirect'],
					[2, 'Link', 0, 'Redirect']
				],
				'nscounts' => [
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
			'Test namespace conditions from indirect for category links' => [
				'redirects+pagelinks' => [
					[14, 'Redirect', 14, 'Category'],
				],
				'categorylinks' => [
					[0, 'Link', 'Redirect'],
					[1, 'Link', 'Redirect'],
					[2, 'Link', 'Redirect']
				],
				'nscounts' => [
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
			'Test namespace conditions from indirect for transclusions' => [
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
				],
				'nscounts' => [
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
			'Test namespace conditions from indirect for transclusions' => [
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
				'nscounts' => [
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

	public function testHtmlOutput($page, $expected) {
		$linkCount = new LinkCount($page, 'linkcounttest', '');
		$actual = $linkCount->html();
		$failed = [];

		if ($expected !== $actual) {
			$failed[] = "$page as HTML. Expected $expected. Actual $actual.";
		}

		return $failed;
	}

	public function provideHtmlOutput() {
		return [
			'Test main namespace' => [
				'Page',
				'<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Page">What links here</a></div>'
			],
			'Test talk namespace' => [
				'Talk:Page',
				'<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Talk%3APage">What links here</a></div>'
			],
			'Test category namespace' => [
				'Category:Category',
				'<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Category%3ACategory">Direct category links</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&hidetrans=1&hideimages=1">All category links</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3ACategory">What links here</a></div>'
			],
			'Test file namespace' => [
				'File:Image.png',
				'<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hideredirs=1&hidetrans=1&hidelinks=1">Direct file links</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&hidelinks=1">All file links</a></h2><div class="num">3</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3AImage.png">What links here</a></div>'
			],
			'Test ? in title' => [
				'?',
				'<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F">What links here</a></div>'
			]
		];
	}

	public function testJsonOutput($page, $expected) {
		$linkCount = new LinkCount($page, 'linkcounttest', '');
		$actual = $linkCount->json(false);
		$failed = [];

		if ($expected !== $actual) {
			$failed[] = "$page as HTML. Expected $expected. Actual $actual.";
		}

		return $failed;
	}

	public function provideJsonOutput() {
		return [
			'Test main namespace' => [
				'Page',
				'{"filelinks":null,"categorylinks":null,"wikilinks":{"direct":0,"indirect":0,"all":0},"redirects":0,"transclusions":{"direct":0,"indirect":0,"all":0}}'
			],
			'Test talk namespace' => [
				'Talk:Page',
				'{"filelinks":null,"categorylinks":null,"wikilinks":{"direct":0,"indirect":0,"all":0},"redirects":0,"transclusions":{"direct":0,"indirect":0,"all":0}}'
			],
			'Test category namespace' => [
				'Category:Category',
				'{"filelinks":null,"categorylinks":{"direct":0,"indirect":0,"all":0},"wikilinks":{"direct":0,"indirect":0,"all":0},"redirects":0,"transclusions":{"direct":0,"indirect":0,"all":0}}'
			],
			'Test file namespace' => [
				'File:Image.png',
				'{"filelinks":{"direct":0,"indirect":3,"all":3},"categorylinks":null,"wikilinks":{"direct":0,"indirect":0,"all":0},"redirects":1,"transclusions":{"direct":0,"indirect":0,"all":0}}'
			]
		];
	}
}

$test = new TestLinkCount();
$failedCount = 0;

foreach ($test->provideCounts() as $name => $data) {
	$failed = $test->testCounts($data);

	if ($failed) {
		print "❌ Failed: $name.\n";
		foreach($failed as $i => $msg) {
			print"   $i) $msg\n";
		}
		$failedCount++;
	} else {
		print "✅ Passed: $name.\n";
	}
}

foreach ($test->provideHtmlOutput() as $name => $data) {
	$failed = $test->testHtmlOutput(...$data);

	if ($failed) {
		print "❌ Failed: $name.\n";
		foreach($failed as $i => $msg) {
			print"   $i) $msg\n";
		}
		$failedCount++;
	} else {
		print "✅ Passed: $name.\n";
	}
}

foreach ($test->provideJsonOutput() as $name => $data) {
	$failed = $test->testJsonOutput(...$data);

	if ($failed) {
		print "❌ Failed: $name.\n";
		foreach($failed as $i => $msg) {
			print"   $i) $msg\n";
		}
		$failedCount++;
	} else {
		print "✅ Passed: $name.\n";
	}
}

if ($failedCount) {
	print "\n❌ Failed $failedCount tests.";
} else {
	print "\n✅ Passed all tests! 🎉";
}
