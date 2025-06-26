#!/usr/bin/env bash
# Simple script to install Composer globally for the current user on Debian/Ubuntu systems.
set -e
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
