# Wikibase to RDF

[This tool](https://tools.wmflabs.org/wb2rdf/) converts Wikibase JSON to RDF.

## Toolforge setup

On Wikimedia Toolforge, this tool runs under the `wb2rdf` tool name.
Source code resides in `~/wb2rdf/`.

If the web service is not running for some reason, run the following command:

```sh
webservice --backend=kubernetes php7.2 start
```

If it’s acting up, try the same command with `restart` instead of `start`.

To update the service, run the following commands after becoming the tool account:

```sh
webservice --backend=kubernetes php7.2 shell
cd ~/wb2rdf
git fetch
git diff @ @{u} # inspect changes
git merge --ff-only @{u}
composer update
```

However, the `webservice … shell` and `composer update` parts are only necessary
when new packages are required (i. e. when `composer.json` was changed).
Otherwise, you can skip them.

## Local development setup

```sh
git clone https://github.com/lucaswerkmeister/wb2rdf.git
cd wb2rdf
composer install
```

Afterwards, set up some web server (e. g. Apache or Nginx) to serve the `index.php` file.

## License

The code in this repository is released under the AGPL v3,
as provided in the `LICENSE` file that accompanies the code.
