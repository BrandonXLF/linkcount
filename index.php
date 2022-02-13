<?php

require 'vendor/autoload.php';

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Link Count</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script src="node_modules/jquery/dist/jquery.min.js"></script>
		<script src="node_modules/oojs/dist/oojs.min.js"></script>
		<script src="node_modules/oojs-ui/dist/oojs-ui.min.js"></script>
		<script src="node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.js"></script>
		<script src="static/widgets.js"></script>
		<link rel="stylesheet" href="node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.css">
		<link rel="stylesheet" href="static/index.css">
		<link rel="shortcut icon" type="image/png" href="static/icon.png">
	</head>
	<body>
		<main>
			<h1>Link Count</h1>
			<?php echo (new Form)->getHtml(); ?>
			<div id="out"><?php echo (new LinkCount(get('page'), get('project'), get('namespaces')))->getHtml(); ?></div>
		</main>
		<footer>
			Checkout the <a href="api">API</a>.
			View source on <a href="https://github.com/BrandonXLF/linkcount">GitHub</a>.
			Created by <a href="https://en.wikipedia.org/wiki/User:BrandonXLF">BrandonXLF</a>.
		</footer>
		<script src="static/index.js"></script>
	</body>
</html>
