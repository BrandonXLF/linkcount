<?php

use Krinkle\Intuition\Intuition;

require 'vendor/autoload.php';

UI::init();

/** @global Intuition $I18N */
global $I18N;
global $COMMIT;

$linkCount = new LinkCount(get('page'), get('project'), get('namespaces'));

?>
<!DOCTYPE html>
<html lang="<?php echo $I18N->getLang(); ?>">
	<head>
		<title><?php echo $linkCount->getTitle(); ?></title>
		<meta name="description" content="<?php echo escmsg('description'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<script src="js/?ck=<?php echo $COMMIT; ?>" defer></script>
		<link rel="stylesheet" href="css/?ck=<?php echo $COMMIT; ?>">
		<link rel="shortcut icon" type="image/png" href="static/icon.png">
	</head>
	<body>
		<main>
			<a id="skip" href="#out"><?php echo escmsg('nav-skip'); ?></a>
			<header>
				<hgroup>
					<h1>Link Count</h1>
					<img src="./static/icon.png" alt="Link Count logo" style="width: 1.65rem; height: 1.65rem;" />
				</hgroup>
				<?php echo (new LanguageSelector)->getHtml(); ?>
			</header>
			<div><?php echo escmsg('description'); ?></div>
			<?php echo (new Form)->getHtml(); ?>
			<div id="out" tabindex="-1"><?php echo $linkCount->getHtml(); ?></div>
		</main>
		<?php echo (new Footer('.'))->getHTML(); ?>
	</body>
</html>
