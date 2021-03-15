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
        $url = "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

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
            }
            td, th {
                border: 1px solid black;
                padding: 0.5em 1em;
                text-align: left;
            }
        </style>
    </head>
    <body>
        <h1>Link Count API</h1>
        <h2>Parameters</h2>
        <table>
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
            <tr>
                <td>page</td>
                <td>string</td>
                <td>required</td>
                <td>The name of the page get the link count for</td>
            </tr>
            <tr>
                <td>project</td>
                <td>string</td>
                <td>optional</td>
                <td>
                    <div>The project the page is in</div>
                    <div>Default is en.wikipedia.org</div>
                    <div>Accepts site domain, name, or database</div>
                </td>
            </tr>
        </table>
        <h2>Response</h2>
        <table>
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
            <tr>
                <td>filelinks</td>
                <td>integer</td>
                <td>optional</td>
                <td>Number of pages that show the file</td>
            </tr>
            <tr>
                <td>categorylinks</td>
                <td>LinkCountObject</td>
                <td>optional</td>
                <td>Number of category links</td>
            </tr>
            <tr>
                <td>wikilinks</td>
                <td>LinkCountObject</td>
                <td>required</td>
                <td>Number of wikilinks</td>
            </tr>
            <tr>
                <td>redirects</td>
                <td>integer</td>
                <td>required</td>
                <td>Number of redirects to the page</td>
            </tr>
            <tr>
                <td>transclusions</td>
                <td>LinkCountObject</td>
                <td>required</td>
                <td>Number of page that transclude the page</td>
            </tr>
        </table>
        <h3>LinkCountObject</h3>
        <table>
            <tr>
                <th>Key</th>
                <th>Type</th>
                <th>Status</th>
                <th>Description</th>
            </tr>
            <tr>
                <td>direct</td>
                <td>integer</td>
                <td>required</td>
                <td>Number of links the directly link to the page</td>
            </tr>
            <tr>
                <td>indirect</td>
                <td>integer</td>
                <td>required</td>
                <td>Number of links that link to the page through a redirect</td>
            </tr>
            <tr>
                <td>all</td>
                <td>integer</td>
                <td>required</td>
                <td>Sum of direct and indirect links</td>
            </tr>
        </table>
        <h2>Examples</h2>
        <ul>
            <?php foreach ($examples as $example) { ?>
                <li><a href="<?php echo $url . '?' . $example ?>"><?php echo $url . '?' . $example ?></a></li>
            <?php } ?>
        </ul>
        <a href="..">&larr; Back</a>
    </body>
</html>
<?php
    }
}