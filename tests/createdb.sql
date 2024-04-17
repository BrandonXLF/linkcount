DROP DATABASE IF EXISTS linkcounttest;
CREATE DATABASE linkcounttest;
USE linkcounttest;

CREATE TABLE wiki (
	dbname varbinary(255) NOT NULL DEFAULT '',
	url varbinary(255) NOT NULL DEFAULT ''
);

INSERT INTO wiki (dbname, url) VALUES
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

CREATE TABLE categorylinks (
	cl_from int(10) unsigned NOT NULL DEFAULT 0,
	cl_to varbinary(255) NOT NULL DEFAULT ''
);

CREATE TABLE imagelinks (
	il_from int(10) unsigned NOT NULL DEFAULT 0,
	il_to varbinary(255) NOT NULL DEFAULT '',
	il_from_namespace int(11) NOT NULL DEFAULT 0
);

CREATE TABLE page (
	page_id int(10) unsigned NOT NULL,
	page_namespace int(11) NOT NULL,
	page_title varbinary(255) NOT NULL
);

CREATE TABLE pagelinks (
	pl_from int(10) unsigned NOT NULL DEFAULT 0,
	pl_from_namespace int(11) NOT NULL DEFAULT 0,
	pl_target_id bigint(20) unsigned DEFAULT 0
);

CREATE TABLE redirect (
	rd_from int(10) unsigned NOT NULL DEFAULT 0,
	rd_namespace int(11) NOT NULL DEFAULT 0,
	rd_title varbinary(255) NOT NULL DEFAULT '',
	rd_interwiki varbinary(32) DEFAULT NULL
);

CREATE TABLE templatelinks (
	tl_from int(10) unsigned NOT NULL DEFAULT 0,
	tl_from_namespace int(11) NOT NULL DEFAULT 0,
	tl_target_id bigint(20) unsigned DEFAULT 0
);

CREATE TABLE linktarget (
	lt_id bigint(20) unsigned NOT NULL DEFAULT 0,
	lt_namespace int(11) NOT NULL DEFAULT 0,
	lt_title varbinary(255) NOT NULL DEFAULT ''
);
