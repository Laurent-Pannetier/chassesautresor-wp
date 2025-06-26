# chassesautresor.com Theme

This repository contains the custom WordPress theme used on **chassesautresor.com**. It is built as a child theme of [Astra](https://wpastra.com/) and provides features for running treasure hunts online:

- Custom post types and utilities for hunts, puzzles and organisers.
- Additional templates and shortcodes tailored to the site's gameplay.
- Automated role switching for organisers and various access controls (see `tests/`).

## Setup

The theme targets **PHP 8.1+** and requires the extensions `pdo_mysql`,
`mbstring`, `gd` and `xml`. On Debian/Ubuntu systems these can be installed via:

```bash
sudo apt-get install php php-mysql php-mbstring php-gd php-xml
```

If Composer is not installed, run the helper script provided in `scripts/`:

```bash
./scripts/install-composer.sh
```

Once the prerequisites are available, fetch dependencies and execute the tests
from the repository root:

```bash
composer install
vendor/bin/phpunit --configuration tests/phpunit.xml
```

You can alternatively work inside Docker by launching `docker-compose up` and
running the same Composer and PHPUnit commands in the `web` container.

More details about the theme development workflow are available in the [docs/](docs/) directory.
