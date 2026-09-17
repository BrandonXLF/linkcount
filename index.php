<?php

use Krinkle\Intuition\Intuition;

require 'vendor/autoload.php';

UI::init();

/** @global Intuition $I18N */
global $I18N;

$linkCount = new LinkCount(get('page'), get('project'), get('namespaces'));

?>
<!DOCTYPE html>
<html lang="<?php echo $I18N->getLang(); ?>">
	<head>
		<title><?php echo $linkCount->getTitle(); ?></title>
		<meta name="description" content="<?php echo _html('description'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script src="js/" defer></script>
		<link rel="stylesheet" href="node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.css">
		<link rel="stylesheet" href="static/index.css?v=5">
		<link rel="shortcut icon" type="image/png" href="static/icon.png">
	</head>
	<body>
		<main>
			<a id="skip" href="#out"><?php echo _html('nav-skip'); ?></a>
			<header>
				<hgroup>
					<h1>Link Count</h1>
					<img src="./static/icon.png" alt="Link Count logo" style="width: 1.65rem; height: 1.65rem;" />
				</hgroup>
				<?php echo (new LanguageSelector)->getHtml(); ?>
			</header>
			<div><?php echo _html('description'); ?></div>
			<?php echo (new Form)->getHtml(); ?>
			<div id="out" tabindex="-1"><?php echo $linkCount->getHtml(); ?></div>
		</main>
		<?php echo (new Footer('.'))->getHTML(); ?>
	</body>
</html>
