DROP DATABASE IF EXISTS `linkcounttest`;
CREATE DATABASE `linkcounttest`;
USE `linkcounttest`;

CREATE TABLE `wiki` (
	`dbname` varbinary(255) NOT NULL DEFAULT '',
	`url` varbinary(255) NOT NULL DEFAULT ''
);
INSERT INTO `wiki` (`dbname`, `url`) VALUES
	('linkcounttest', 'https://en.wikipedia.org'),
	-- Included for testing on autocomplete of the project input
	('eewiki', 'https://ee.wikipedia.org'),
	('elwiki', 'https://el.wikipedia.org'),
	('elwikibooks', 'https://el.wikibooks.org'),
	('elwikinews', 'https://el.wikinews.org'),
	('elwikiquote', 'https://el.wikiquote.org'),
	('elwikisource', 'https://el.wikisource.org'),
	('elwikiversity', 'https://el.wikiversity.org'),
	('elwikivoyage', 'https://el.wikivoyage.org'),
	('elwiktionary', 'https://el.wiktionary.org'),
	('emlwiki', 'https://eml.wikipedia.org'),
	('enwikibooks', 'https://en.wikibooks.org'),
	('enwikinews', 'https://en.wikinews.org'),
	('enwikiquote', 'https://en.wikiquote.org'),
	('enwikisource', 'https://en.wikisource.org'),
	('enwikiversity', 'https://en.wikiversity.org'),
	('enwikivoyage', 'https://en.wikivoyage.org'),
	('enwiktionary', 'https://en.wiktionary.org'),
	('eowiki', 'https://eo.wikipedia.org'),
	('eowikibooks', 'https://eo.wikibooks.org'),
	('eowikinews', 'https://eo.wikinews.org'),
	('eowikiquote', 'https://eo.wikiquote.org'),
	('eowikisource', 'https://eo.wikisource.org'),
	('eowikivoyage', 'https://eo.wikivoyage.org'),
	('eowiktionary', 'https://eo.wiktionary.org'),
	('eswiki', 'https://es.wikipedia.org'),
	('eswikibooks', 'https://es.wikibooks.org'),
	('eswikinews', 'https://es.wikinews.org'),
	('eswikiquote', 'https://es.wikiquote.org'),
	('eswikisource', 'https://es.wikisource.org'),
	('eswikiversity', 'https://es.wikiversity.org'),
	('eswikivoyage', 'https://es.wikivoyage.org'),
	('eswiktionary', 'https://es.wiktionary.org'),
	('etwiki', 'https://et.wikipedia.org'),
	('etwikibooks', 'https://et.wikibooks.org'),
	('etwikimedia', 'https://ee.wikimedia.org'),
	('etwikiquote', 'https://et.wikiquote.org'),
	('etwikisource', 'https://et.wikisource.org'),
	('etwiktionary', 'https://et.wiktionary.org'),
	('euwiki', 'https://eu.wikipedia.org'),
	('euwikibooks', 'https://eu.wikibooks.org'),
	('euwikiquote', 'https://eu.wikiquote.org'),
	('euwikisource', 'https://eu.wikisource.org'),
	('euwiktionary', 'https://eu.wiktionary.org');

CREATE TABLE `categorylinks` (
	`cl_from` int(10) unsigned NOT NULL DEFAULT 0,
	`cl_to` varbinary(255) NOT NULL DEFAULT ''
);
INSERT INTO `categorylinks` (`cl_from`, `cl_to`) VALUES
	(18, '2');

CREATE TABLE `imagelinks` (
	`il_from` int(10) unsigned NOT NULL DEFAULT 0,
	`il_to` varbinary(255) NOT NULL DEFAULT '',
	`il_from_namespace` int(11) NOT NULL DEFAULT 0
);
INSERT INTO `imagelinks` (`il_from`, `il_to`, `il_from_namespace`) VALUES
	(18, '1.png', 0),
	(18, '2.png', 0);

CREATE TABLE `page` (
	`page_id` int(10) unsigned NOT NULL,
	`page_namespace` int(11) NOT NULL,
	`page_title` varbinary(255) NOT NULL
);
INSERT INTO `page` (`page_id`, `page_namespace`, `page_title`) VALUES
	(5, 6, '1.png'),
	(6, 6, '2.png'),
	(11, 14, '1'),
	(12, 14, '2'),
	(13, 0, '1'),
	(14, 0, '2'),
	(15, 0, '3'),
	(16, 10, '1'),
	(17, 10, '2'),
	(18, 0, 'A'),
	(19, 0, 'B'),
	(20, 0, 'C'),
	(21, 1, 'A'),
	(22, 2, 'A');

CREATE TABLE `pagelinks` (
	`pl_from` int(10) unsigned NOT NULL DEFAULT 0,
	`pl_namespace` int(11) NOT NULL DEFAULT 0,
	`pl_title` varbinary(255) NOT NULL DEFAULT '',
	`pl_from_namespace` int(11) NOT NULL DEFAULT 0
);
INSERT INTO `pagelinks` (`pl_from`, `pl_namespace`, `pl_title`, `pl_from_namespace`) VALUES
	(14, 0, '1', 0),
	(19, 0, '1', 0),
	(20, 0, '1', 0),
	(15, 0, '2', 0),
	(19, 0, '2', 0),
	(18, 0, '3', 0),
	(18, 6, '1.png', 0),
	(18, 6, '2.png', 0),
	(19, 10, '1', 0),
	(6, 6, '1.png', 6),
	(17, 10, '1', 10),
	(9, 10, 'A', 10),
	(12, 14, '1', 14),
	(21, 0, '1', 1),
	(21, 0, '2', 1),
	(22, 0, '1', 2);

CREATE TABLE `redirect` (
	`rd_from` int(10) unsigned NOT NULL DEFAULT 0,
	`rd_namespace` int(11) NOT NULL DEFAULT 0,
	`rd_title` varbinary(255) NOT NULL DEFAULT '',
	`rd_interwiki` varbinary(32) DEFAULT NULL
);
INSERT INTO `redirect` (`rd_from`, `rd_namespace`, `rd_title`, `rd_interwiki`) VALUES
	(6, 6, '1.png', NULL),
	(9, 10, 'A', NULL),
	(12, 14, '1', NULL),
	(14, 0, '1', NULL),
	(15, 0, '2', NULL),
	(17, 10, '1', NULL),
	(17, 10, '1', 'en');

CREATE TABLE `templatelinks` (
	`tl_from` int(10) unsigned NOT NULL DEFAULT 0,
	`tl_namespace` int(11) NOT NULL DEFAULT 0,
	`tl_title` varbinary(255) NOT NULL DEFAULT '',
	`tl_from_namespace` int(11) NOT NULL DEFAULT 0
);
INSERT INTO `templatelinks` (`tl_from`, `tl_namespace`, `tl_title`, `tl_from_namespace`) VALUES
	(18, 6, '1.png', 0),
	(18, 10, '1', 0),
	(18, 10, '2', 0),
	(19, 0, '1', 0);
