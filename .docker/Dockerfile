ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    git \
    unzip \
    libzip-dev \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    intl

# Install pcov for code coverage
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies resolve from the manifest alone, so editing sources does not invalidate this layer.
COPY composer.json ./
RUN composer install --no-interaction --no-progress --no-scripts

COPY . /app

# Run the full test gate (PHPCS + PHPUnit) by default.
# pcov is available in the image for `composer test-cover`.
CMD ["composer", "test"]
