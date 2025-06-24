# chassesautresor.com Theme

This repository contains the custom WordPress theme used on **chassesautresor.com**. It is built as a child theme of [Astra](https://wpastra.com/) and provides features for running treasure hunts online:

- Custom post types and utilities for hunts, puzzles and organisers.
- Additional templates and shortcodes tailored to the site's gameplay.
- Automated role switching for organisers and various access controls (see `tests/`).

## Setup

Install PHP dependencies and run the test suite from the repository root:

```bash
composer install
vendor/bin/phpunit --configuration tests/phpunit.xml
```

More details about the theme development workflow are available in the [docs/](docs/) directory.

## Autoloading

The `inc/` directory is registered with Composer so all helper files are loaded
automatically. After modifying `composer.json` run:

```bash
composer dump-autoload
```

The theme loads `vendor/autoload.php` in `functions.php`. If the autoloader is
missing (e.g. Composer isn't installed in production), the script falls back to
including the helper files from `inc/` manually.

