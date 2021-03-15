<?php

class APIHelp {
    private static function defineObject(...$keys) {
        $list = '';
    
        foreach ($keys as list($key, $type, $status, $desc)) {
            $list .= "<li><strong><code>$key</code></strong> - $status <code>$type</code> - $desc</li>";
        }

        return "<ul>$list</ul>";
    }

    public static function html() {
        $prefix = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

        $examples = [
            'page=Main_Page&project=en.wikipedia.org',
            'page=Wikipédia:Accueil_principal&project=fr.wikipedia.org',
            'page=Category:Main Page&project=en.wikipedia.org',
            'page=File:Example.png&project=en.wikipedia.org'
        ];
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
        <?php
            echo '<h2>Request</h2>' . self::defineObject(
                ['page', 'string', 'required', 'The name of the page get the link count for.'],
                ['project', 'string', 'optional', 'The project (domain, name, or database) the page is in, default is en.wikipedia.org.']
            );
            
            echo '<h2>Response</h2>' . self::defineObject(
                ['filelinks', 'integer', 'optional', 'Number of pages that show the file.'],
                ['categorylinks', 'LinkCountObject', 'optional', 'Number of category links.'],
                ['wikilinks', 'LinkCountObject', 'required', 'Number of wikilinks.'],
                ['redirects', 'integer', 'required', 'Number of redirects to the page.'],
                ['transclusions', 'LinkCountObject', 'required', 'Number of page that transclude the page.']
            );
            
            echo '<h3>LinkCountObject</h3>' . self::defineObject(
                ['direct', 'integer', 'required', 'Number of links the directly link to the page.'],
                ['indirect', 'integer', 'required', 'Number of links that link to the page through a redirect.'],
                ['all', 'integer', 'required', 'Sum of direct and indirect links.']
            );
        ?>
        <h2>Examples</h2>
        <ul>
            <?php foreach ($examples as $example) {
                $url = $prefix . '?' . $example;
                echo "<li><a href=\"$url\">$url</a></li>";
            } ?>
        </ul>
        <a href="..">&larr; Back</a>
    </body>
</html>
<?php
    }
}