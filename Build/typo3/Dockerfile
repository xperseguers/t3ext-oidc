# syntax=docker/dockerfile:1.7-labs
FROM php:8.3-apache AS webserver

# Install common tools
# gettext-base provides envsubst, used for fixture import to db
RUN apt-get update \
    && apt-get install -y \
        wget \
        rsync \
        unzip \
        gettext-base \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Install mysql client
RUN apt-get update \
    && apt-get install -y \
        mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Install imagemagick
RUN apt-get update \
    && apt-get install -y \
        imagemagick \
    && rm -rf /var/lib/apt/lists/*
ENV TYPO3_GFX_PROCESSOR_PATH=/usr/bin/
ENV TYPO3_GFX_PROCESSOR_PATH_LZW=/usr/bin/

# Install apcu caching
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Install PHP extensions
RUN apt-get update \
    && apt-get install -y \
        libxml2-dev libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        libzip-dev \
        python3 \
        python3-setuptools \
        libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install -j$(nproc) \
        exif \
        mysqli \
        soap \
        curl \
        zip

# Install php extension intl
RUN apt-get update \
    && apt-get install -y \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install intl

# Install php redis client
RUN pecl install -o -f redis \
    &&  rm -rf /tmp/pear \
    &&  docker-php-ext-enable redis

# PHP gd
RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/
RUN docker-php-ext-install -j$(nproc) gd

# Link php binary where TYPO3 expects it
RUN ln -s /usr/local/bin/php /usr/bin/php

# Configure PHP
ADD docker/typo3.ini /usr/local/etc/php/conf.d/typo3.ini

# Install xdebug
RUN pecl install xdebug \
	&& docker-php-ext-enable xdebug

# Setup locales
RUN apt-get update \
    && apt-get install -y \
        locales \
    && rm -rf /var/lib/apt/lists/* \
    && echo "# Docker locales" > /etc/locale.gen \
    && echo "en_GB.UTF-8 UTF-8" >> /etc/locale.gen \
    && echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen \
    && echo "de_DE.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen

# Configure Apache as ssl server
RUN a2enmod rewrite
RUN a2enmod ssl
COPY --from=certs /developer.pem /etc/ssl/certs/developer.pem
COPY --from=certs /developer.key /etc/ssl/private/developer.key
RUN ln -s /etc/ssl/certs/developer.pem /etc/ssl/certs/`openssl x509 -noout -hash -in /etc/ssl/certs/developer.pem`.0
ADD docker/000-default.conf /etc/apache2/sites-available/000-default.conf
ADD docker/000-default-ssl.conf /etc/apache2/sites-available/000-default-ssl.conf
RUN a2ensite 000-default-ssl.conf
RUN rm /etc/apache2/sites-available/default-ssl.conf

COPY --chmod=744 docker/entrypoint.sh /entrypoint.sh
CMD ["/entrypoint.sh"]

WORKDIR /app
EXPOSE 80
EXPOSE 443



FROM composer:2.7.2 AS php-composer



FROM php-composer AS php-dependencies
COPY --from=typo3-version composer.* /app/
COPY --parents ./typo3/packages/./*/composer.json /app/packages
COPY --from=oidc composer.json /app/packages/oidc/composer.json

RUN composer install --no-dev



FROM webserver AS oidc-webserver
COPY --from=php-dependencies /app /app
RUN install -d -o www-data -g www-data -m 775 -v /app/var
RUN cp vendor/typo3/cms-install/Resources/Private/FolderStructureTemplateFiles/root-htaccess public/.htaccess

COPY ./typo3 /app/
COPY --from=oidc / /app/packages/oidc
