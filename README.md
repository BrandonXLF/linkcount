# linkcount

**linkcount** is a web program that shows the number of links to any page in a Wikimedia project. It is hosted at <https://linkcount.toolforge.org/>.

## Installing

To run you will need to install the PHP dependencies using `composer install` and the Node JS dependencies using `npm install`. PHP >= 8.4 is recommended.

## Config

Copy the file `config-example.ini` to `config.ini` and fill in the fields with values from your `replica.my.cnf` file.

## Testing

Test are run using `composer test-win` on windows and `composer test-lin` on linux etc. When testing, the `linkcounttest` table is created. You can also run `composer createdb` to create the `linkcounttest` table for manual testing from a browser. When manual testing, `en.wikipedia.org` is linked to the `linkcounttest` database. Wikis with a url starting with `e` are also added to the `wiki` table to allow for testing of the project input autocomplete.
