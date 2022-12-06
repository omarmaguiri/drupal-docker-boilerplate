# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


# https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
ARG PHP_VERSION=8.1
ARG NGINX_VERSION=1.22

ARG WORKDIR=/srv/app
ARG DRUPAL_VERSION=9.4.8

# Nginx env variables
ARG NGINX_HOST=localhost
ARG NGINX_HTTP_PORT=80

# "php" stage
# see https://www.drupal.org/docs/system-requirements/php-requirements
FROM php:${PHP_VERSION}-fpm-alpine AS drupal_php

ARG WORKDIR
ARG DRUPAL_VERSION

# https://www.drupal.org/node/3060/release
ENV DRUPAL_VERSION ${DRUPAL_VERSION}

# persistent / runtime deps
RUN apk add --no-cache \
		zip \
		unzip \
	;

# install Drupal required PHP extensions
ARG APCU_VERSION=5.1.21
RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
    coreutils \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
		icu-dev \
		libzip-dev \
		zlib-dev \
	; \
	\
	docker-php-ext-configure zip; \
  docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg=/usr/include \
    --with-webp \
  ; \
	docker-php-ext-install -j$(nproc) \
		intl \
		gd \
		opcache \
		pdo_mysql \
		zip \
	; \
	pecl install \
		apcu-${APCU_VERSION} \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		opcache \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-network --virtual .drupal-phpexts-rundeps $runDeps; \
	apk del --no-network .build-deps

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini
COPY docker/php/conf.d/drupal.prod.ini $PHP_INI_DIR/conf.d/drupal.ini
COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="${PATH}:/root/.composer/vendor/bin"

# set project working directory
WORKDIR ${WORKDIR}
# copy project web directory
COPY ./web ./web
## copy composer.json & composer.lock files
COPY composer.* .
# install project dependencies
RUN set -eux; \
	composer install --prefer-dist --no-dev --no-autoloader --no-progress --no-interaction --no-scripts; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	chown -R www-data:www-data web/sites web/modules web/themes;  sync
# put bin directory in PATH environnement variable to have all bins as global commands
ENV PATH ${PATH}:${WORKDIR}/vendor/bin
# DOCKER ENTRYPOINT
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

# "nginx" stage
FROM nginx:${NGINX_VERSION}-alpine AS drupal_nginx

# retrieve ARG variables defined before the "FROM" clause
ARG WORKDIR
ARG NGINX_HOST
ARG NGINX_HTTP_PORT
# Env variables used in default nginx config file <nginx.conf>, it can be override by .env file if needed
ENV PROJECT_WORKDIR ${WORKDIR}
ENV NGINX_HOST ${NGINX_HOST}
ENV NGINX_HTTP_PORT ${NGINX_HTTP_PORT}
# copy default nginx config
COPY docker/nginx/default.conf /etc/nginx/templates/default.conf.template
# set project working directory
WORKDIR ${WORKDIR}
# copy project web directory
COPY --from=drupal_php ${WORKDIR}/web ./web
