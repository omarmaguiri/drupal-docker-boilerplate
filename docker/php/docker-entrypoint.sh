#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ]; then
	PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-production"
	if [ "$APP_ENV" != 'prod' ]; then
		PHP_INI_RECOMMENDED="$PHP_INI_DIR/php.ini-development"
	fi
	ln -sf "$PHP_INI_RECOMMENDED" "$PHP_INI_DIR/php.ini"

	# The first time volumes are mounted, the project needs to be recreated
	if [ ! -f composer.json ]; then
		composer create-project "drupal/recommended-project:$DRUPAL_VERSION" tmp --no-interaction --no-progress --prefer-dist

		cd tmp
		composer require "php:>=$PHP_VERSION"
		cp -Rp . ..
		cd -

		rm -Rf tmp/
	fi
fi

exec docker-php-entrypoint "$@"
