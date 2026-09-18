# linkcount

**linkcount** is a web program that shows the number of links to any page in a Wikimedia project. It is hosted at <https://linkcount.toolforge.org/>.

## Installing

Before running, you first need to install JS and CSS dependencies using `cd static && npm install && cd ..`. Then, install PHP dependencies and build static files using `composer install`.

## Config

Copy the file `config-example.ini` to `config.ini` and fill in the fields with values from your `replica.my.cnf` file.

## Testing

Test are run using `composer test-win` on windows and `composer test-lin` on linux etc. When testing, the `linkcounttest` table is created. You can also run `composer createdb` to create the `linkcounttest` table for manual testing from a browser. When manual testing, `en.wikipedia.org` is linked to the `linkcounttest` database. Wikis with a url starting with `e` are also added to the `wiki` table to allow for testing of the project input autocomplete.

### Wikimedia Testing

To test using production Wikimedia projects, a tunnel needs to be established to the Wikimedia database replicas. This can be done using the following command:

> ssh -N USERNAME@login.toolforge.org -L DB_META_PORT:meta.web.db.svc.wikimedia.cloud:3306 -L DB_PORT:PROJECT.web.db.svc.wikimedia.cloud:3306

`DB_META_PORT` and `DB_PORT` should match the port values in config.ini, `PROJECT` should be replaced with the dbname of the Wikimedia project you want to connect to, and `USERNAME` should be replaced with your Toolforge username.
