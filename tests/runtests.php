<?php

require __DIR__ . '/../includes/Config.php';
require __DIR__ . '/../includes/LinkCount.php';

$db = new Database('localhost', '');
$db->exec(file_get_contents(__DIR__ . '/linkcounttest.sql'));

echo "Created database linkcounttest.\n";

$cnf['database'] = 'linkcounttest';

function obj($direct, $indirect) {
	return [
		'direct' => $direct,
		'indirect' => $indirect,
		'all' => $direct + $indirect
	];
}

$testLinkCount = [
	['1', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(4, 3),
		'redirects' => 1,
		'transclusions' => obj(1, 0)
	]],
	['1', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(3, 3),
		'redirects' => 1,
		'transclusions' => obj(1, 0)
	], '0,1'],
	['1', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(2, 2),
		'redirects' => 1,
		'transclusions' => obj(1, 0)
	], '0'],
	['2', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(2, 1),
		'redirects' => 1,
		'transclusions' => obj(0, 0)
	]],
	['3', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(1, 0),
		'redirects' => 0,
		'transclusions' => obj(0, 0)
	]],
	['Template:1', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(1, 0),
		'redirects' => 1,
		'transclusions' => obj(0, 1)
	]],
	['Template:2', [
		'filelinks' => null,
		'categorylinks' => null,
		'wikilinks' => obj(0, 0),
		'redirects' => 0,
		'transclusions' => obj(1, 0)
	]],
	['File:1.png', [
		'filelinks' => obj(0, 1),
		'categorylinks' => null,
		'wikilinks' => obj(1, 1),
		'redirects' => 1,
		'transclusions' => obj(1, 0)
	]],
	['File:2.png', [
		'filelinks' => obj(1, 0),
		'categorylinks' => null,
		'wikilinks' => obj(1, 0),
		'redirects' => 0,
		'transclusions' => obj(0, 0)
	]],
	['Category:1', [
		'filelinks' => null,
		'categorylinks' => obj(0, 1),
		'wikilinks' => obj(0, 0),
		'redirects' => 1,
		'transclusions' => obj(0, 0),
	]],
	['Category:2', [
		'filelinks' => null,
		'categorylinks' => obj(1, 0),
		'wikilinks' => obj(0, 0),
		'redirects' => 0,
		'transclusions' => obj(0, 0),
	]]
];

$testLinkHTML = [
	['1', '<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">4</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">7</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">1</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">1</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/1">What links here</a></div>'],
	['?', '<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">0</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/%3F">What links here</a></div>'],
	['Category:1', '<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Category%3A1">Direct category links</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hidelinks=1&hidetrans=1&hideimages=1">All category links</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">0</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">0</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/Category%3A1">What links here</a></div>'],
	['File:1.png', '<div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hideredirs=1&hidetrans=1&hidelinks=1">Direct file links</a></h2><div class="num">0</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hidetrans=1&hidelinks=1">All file links</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hideredirs=1&hidetrans=1&hideimages=1">Direct wikilinks</a></h2><div class="num">1</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hidetrans=1&hideimages=1">All wikilinks</a></h2><div class="num">2</div></div><div class="out"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hidelinks=1&hidetrans=1&hideimages=1">Redirects</a></h2><div class="num">1</div></div><div class="out left"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hideredirs=1&hidelinks=1&hideimages=1">Direct transclusions</a></h2><div class="num">1</div></div><div class="out right"><h2><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png?hidelinks=1&hideimages=1">All transclusions</a></h2><div class="num">1</div></div><div class="links"><a href="https://en.wikipedia.org/wiki/Special:WhatLinksHere/File%3A1.png">What links here</a></div>']
];

$testLinkJSON = [
	['1', '{"filelinks":null,"categorylinks":null,"wikilinks":{"direct":4,"indirect":3,"all":7},"redirects":1,"transclusions":{"direct":1,"indirect":0,"all":1}}']
];

$failure = 0;

echo "\nLink count tests:\n";

foreach ($testLinkCount as $test) {
	$page = $test[0];
	$expected = $test[1];
	$namespaces = $test[2] ?? '';
	$localFailure = false;
	$linkCount = new LinkCount($page, 'linkcounttest', $namespaces);
	$actual = $linkCount->counts;
	$fromNamespaces = $namespaces !== '' ? " from namespaces $namespaces" : '';
	$keys = array_unique(array_merge(array_keys($expected), array_keys($actual)));

	foreach ($keys as $key) {
		if (!array_key_exists($key, $expected) || !array_key_exists($key, $actual) || $expected[$key] !== $actual[$key]) {
			$expectedStr = array_key_exists($key, $expected) ? json_encode($expected[$key]) : 'NOT SET';
			$realityStr = array_key_exists($key, $actual) ? json_encode($actual[$key]) : 'NOT SET';
			$localFailure = true;
			$failure++;
			echo "❌ Failure for $page$fromNamespaces at key $key. Expected $expectedStr, got $realityStr.\n";
		}
	}

	if (!$localFailure) {
		echo "✅ Passed for $page$fromNamespaces.\n";
	}
}

echo "\nLink count HTML tests:\n";

foreach ($testLinkHTML as $test) {
	$page = $test[0];
	$expected = $test[1];
	$actual = (new LinkCount($page, 'linkcounttest', ''))->html();

	if ($expected !== $actual) {
		$failure++;
		echo "❌ Failure for $page.\nExpected:\n$expected\nActual:\n$actual\n";
	} else {
		echo "✅ Passed for $page.\n";
	}
}

echo "\nLink count JSON tests:\n";

foreach ($testLinkJSON as $test) {
	$page = $test[0];
	$expected = $test[1];
	$actual = (new LinkCount($page, 'linkcounttest', ''))->json(false);

	if ($expected !== $actual) {
		$failure++;
		echo "❌ Failure for $page.\nExpected:\n$expected\nActual:\n$actual\n";
	} else {
		echo "✅ Passed for $page.\n";
	}
}

if ($failure) {
	echo "\n❌ $failure test(s) failed. :(\n";
} else {
	echo "\n✅ All tests passed! 🎉\n";
}
