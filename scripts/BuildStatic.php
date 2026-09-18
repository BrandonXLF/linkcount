<?php

class BuildStatic {
	public static function build() {
		$js = [
			'static/node_modules/jquery/dist/jquery.min.js',
			'static/node_modules/oojs/dist/oojs.min.js',
			'static/node_modules/oojs-ui/dist/oojs-ui.min.js',
			'static/node_modules/oojs-ui/dist/oojs-ui-wikimediaui.min.js',
			'static/NamespaceLookupWidget.js',
			'static/PageLookupWidget.js',
			'static/ProjectLookupWidget.js',
			'static/index.js'
		];

		$out = '';

		foreach ($js as $file) {
			$content = file_get_contents($file);
			$out .= "/* $file */\n{$content}\n";
		}

		if (!is_dir('static/build')) {
			mkdir('static/build');
		}

		file_put_contents('static/build/combined.js', $out);
	}
}
