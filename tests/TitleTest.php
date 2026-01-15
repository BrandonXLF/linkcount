<?php

require_once __DIR__ . '/LoadTestRedis.php';

use PHPUnit\Framework\TestCase;

class TitleTest extends TestCase {
	public static function setUpBeforeClass(): void {
		LoadTestRedis::load();
	}

	/**
	 * @dataProvider provideTitle
	 */
	public function testTitle($page, $expectedNamespace, $expectedDatabaseKey) {
		$title = new Title($page, 'linkcounttest', 'https://en.wikipedia.org');

		$this->assertEquals($expectedNamespace, $title->getNamespaceId());
		$this->assertEquals($expectedDatabaseKey, $title->getDBKey());
	}

	public function provideTitle() {
		return [
			'main namespace' => [
				'Foo',
				0,
				'Foo'
			],
			'main namespace with colon' => [
				':Foo',
				0,
				'Foo'
			],
			'talk namespace' => [
				'Talk:Foo',
				1,
				'Foo'
			],
			'namespace with space' => [
				'Template talk:Foo',
				11,
				'Foo'
			],
			'namespace with underscores' => [
				'Template_talk:Foo',
				11,
				'Foo'
			],
			'page with spaces' => [
				'Foo bar baz',
				0,
				'Foo_bar_baz'
			],
			'page with underscores' => [
				'Foo_bar_baz',
				0,
				'Foo_bar_baz'
			],
			'lowercase first letter' => [
				'foo bar baz',
				0,
				'Foo_bar_baz'
			],
			/*
			// There are no active case-sensitive namespaces
			'case-sensitive namespace' => [
				'Gadget definition talk:foo',
				2303,
				'foo'
			],
			*/
			'alias namespace' => [
				'Image:Foo.png',
				6,
				'Foo.png'
			],
			'template namespace with colon' => [
				':Template:Foo',
				10,
				'Foo'
			],
			'test invalid namespace' => [
				'Foo:Bar',
				0,
				'Foo:Bar'
			],
			'test invalid namespace with prefix colon' => [
				':Foo:Bar',
				0,
				'Foo:Bar'
			],
			'test lowercase invalid namespace' => [
				'foo:bar',
				0,
				'Foo:bar'
			],
			'test canonical name' => [
				'Project:Foo',
				4,
				'Foo'
			],
			'test wiki defined name' => [
				'Wikipedia:Foo',
				4,
				'Foo'
			]
		];
	}
}
