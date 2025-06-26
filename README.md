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
