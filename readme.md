# Easy Directory Listing for WordPress

## Requirements

* _composer_ to install this package.
* _npm_ to compile the scripts.
* WordPress-plugin, theme or _Code Snippet_-plugin to embed them in your project.

## Installation

1. ``composer require threadi/easy-directory-listing-for-wordpress``
2. Switch to ``vendor/thread/easy-directory-listing-for-wordpress``
3. Run ``npm i`` to install dependencies.
4. Run ``npm run build`` to compile the scripts.
5. Add the codes from `doc/embed.php` to your WordPress-project (plugin or theme).

## Check for WordPress Coding Standards

### Initialize

`composer install`

### Run

`vendor/bin/phpcs --standard=vendor/threadi/easy-directory-listing-for-wordpress/ruleset.xml vendor/threadi/easy-directory-listing-for-wordpress/`

### Repair

`vendor/bin/phpcbf --standard=vendor/threadi/easy-directory-listing-for-wordpress/ruleset.xml vendor/threadi/easy-directory-listing-for-wordpress/`

## Analyse with PHPStan

`vendor/bin/phpstan analyse -c vendor/threadi/easy-directory-listing-for-wordpress/phpstan.neon`