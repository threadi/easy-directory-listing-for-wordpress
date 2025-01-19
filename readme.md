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

`vendor/bin/phpcs --extensions=php --ignore=*/vendor/*/vendor/*,*/node_modules/*,*/build/* --standard=ruleset.xml .`

### Repair

`vendor/bin/phpcbf --extensions=php --ignore=*/vendor/*/vendor/*,*/node_modules/*,*/build/* --standard=ruleset.xml .`

## Check for WordPress VIP Coding Standards

Hint: this check runs against the VIP-GO-platform which is not our target for this plugin. Many warnings can be ignored.

### Run

`vendor/bin/phpcs --extensions=php --ignore=*/vendor/*/vendor/*,*/node_modules/* --standard=WordPress-VIP-Go .`
