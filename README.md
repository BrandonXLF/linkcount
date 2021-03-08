# linkcount

**linkcount** is a web program that shows the number of links to any page in a Wikimedia project. It is hosted at <https://linkcount.toolforge.org/>.

## Installing

To run you will need to install the PHP dependencies using `composer install` and the Node JS dependencies using `npm install`.

## Config

Copy the file `config.ini-example` to `config.ini` and fill in the fields with values from your `replica.my.cnf` file.

## Database

To use the tool, you will need a connection to the Wikimedia replica database.

To connect to the database on your computer you can use the command `ssh -N USERNAME@dev.toolforge.org -L 3306:metawiki.web.db.svc.eqiad.wmflabs:3306` and add `127.0.0.1 metawiki.web.db.svc.eqiad.wmflabs` to your `hosts` file. Replace `USERNAME` with your toolforge username.

You also may need to replace the first number with a different port along with `port` in your `config.ini` file if you're already using port 3306.
