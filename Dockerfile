FROM php:8.2-fpm-alpine

# OS & build deps
RUN apk add --no-cache \
    bash curl git unzip rsync \
    icu-dev libxml2-dev libzip-dev oniguruma-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libxslt-dev gmp-dev libsodium-dev \
    imagemagick imagemagick-dev \
    curl-dev linux-headers tzdata $PHPIZE_DEPS

# PHP extensions (explicit + Magento-required)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
      bcmath intl gd mbstring pdo_mysql soap xsl zip sodium \
      ftp sockets curl dom simplexml \
 && docker-php-ext-enable opcache

# PECL
RUN pecl install redis imagick \
 && docker-php-ext-enable redis imagick

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dev PHP tweaks
RUN { \
  echo "memory_limit=2048M"; \
  echo "upload_max_filesize=64M"; \
  echo "post_max_size=64M"; \
  echo "max_execution_time=1800"; \
  echo "opcache.validate_timestamps=1"; \
  echo "opcache.revalidate_freq=0"; \
} > /usr/local/etc/php/conf.d/magento.ini

CMD ["php-fpm"]
