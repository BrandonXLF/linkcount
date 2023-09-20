<?php

require '../vendor/autoload.php';

if ($_SERVER['QUERY_STRING']) {
	header('Content-Type: application/json');
	header('Access-Control-Allow-Origin: *');
	echo (new LinkCount(get('page'), get('project'), get('namespaces')))->getJson();

	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Link Count API</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="shortcut icon" type="image/png" href="../static/icon.png">
		<style>
			body {
				font-family: sans-serif;
				line-height: 1.5;
			}
			h2, h3 {
				margin: 0 0 10px;
			}
			ul {
				margin: 0 0 20px
			}
			li {
				padding: 2px 0;
			}
			code {
				background: #eee;
				font-family: inherit;
				padding: 1px 3px;
			}
		</style>
	</head>
	<body>
		<h1>Link Count API</h1>
		<h2>Request</h2>
		<?php echo (new APIHelpObject(
			['page', 'string', 'required', 'Name of the page to get the link count for.'],
			['project', 'string', 'optional', 'Project (domain, name, or database) the page is in, default is en.wikipedia.org.'],
			['namespace', 'string', 'optional', 'Comma-separated list of namespace numbers which links from are counted. Leave blank to count all namespaces.']
		))->getHtml(); ?>
		<h2>Response</h2>
		<?php echo (new APIHelpObject(
			['filelinks', 'LinkCountObject', 'optional', 'Number of pages that show the file.'],
			['categorylinks', 'LinkCountObject', 'optional', 'Number of category links.'],
			['wikilinks', 'LinkCountObject', 'required', 'Number of wikilinks.'],
			['redirects', 'integer', 'required', 'Number of redirects to the page.'],
			['transclusions', 'LinkCountObject', 'required', 'Number of page that transclude the page.']
		))->getHtml(); ?>
		<h3>LinkCountObject</h3>
		<?php echo (new APIHelpObject(
			['all', 'integer', 'required', 'Sum of direct and indirect links.'],
			['direct', 'integer', 'required', 'Number of links the directly link to the page.'],
			['indirect', 'integer', 'required', 'Number of links that link to the page through a redirect.']
		))->getHtml(); ?>
		<h2>Error Response</h2>
		<?php echo (new APIHelpObject(
			['error', 'string', 'required', 'Message explaining the error that occurred.']
		))->getHtml(); ?>
		<h2>Examples</h2>
		<?php echo (new APIHelpExamples(
			'page=Main_Page&project=en.wikipedia.org',
			'page=Main_Page&project=en.wikipedia.org&namespaces=0,1',
			'page=Wikipédia:Accueil_principal&project=fr.wikipedia.org',
			'page=Category:Main Page&project=en.wikipedia.org',
			'page=File:Example.png&project=en.wikipedia.org'
		))->getHtml(); ?>
		<?php echo (new Footer('..'))->getHTML(); ?>
	</body>
</html>
