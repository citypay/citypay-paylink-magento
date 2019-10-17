FROM ubuntu:19.04

COPY files/auth.json /root/.composer/auth.json

ENV TZ=Europe/London
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone && \
        apt-get update && apt-get install -yq --no-install-recommends \
        apt-utils \
        curl \
        # Install git
        git \
        # Install apache
        apache2 \
        # Install php 7.2
        libapache2-mod-php7.2 \
        php7.2-cli \
        php7.2-json \
        php7.2-curl \
        php7.2-fpm \
        php7.2-gd \
        php7.2-ldap \
        php7.2-mbstring \
        php7.2-mysql \
        php7.2-soap \
        php7.2-sqlite3 \
        php7.2-xml \
        php7.2-zip \
        php7.2-bcmath \
        php7.2-intl \
        php-imagick \
        # Install tools
        openssl \
        nano \
        graphicsmagick \
        imagemagick \
        ghostscript \
        mysql-client \
        iputils-ping \
        locales \
        sqlite3 \
        ca-certificates \
        unzip \
        cron \
        && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN locale-gen en_US.UTF-8 en_GB.UTF-8

RUN composer create-project --repository=https://repo.magento.com/ magento/project-community-edition /var/www/html/magento

RUN cd /var/www/html/magento && \
    chown -R www-data:www-data . && \
    find var generated vendor pub/static pub/media app/etc -type f -exec chmod u+w {} + && \
    find var generated vendor pub/static pub/media app/etc -type d -exec chmod u+w {} + && \
    chmod u+x bin/magento

RUN a2enmod rewrite && \
    echo "\
    <Directory \"/var/www/html\"> \n\
            AllowOverride  All \n\
    </Directory>" >> /etc/apache2/sites-available/000-default.conf

COPY files/etc/ /var/www/html/magento/app/etc

RUN chown -R www-data:www-data /var/www/html/magento/app/etc/ && \
    mkdir -p /var/www/html/magento/app/code/Magento  && \
    chown -R www-data:www-data /var/www/html/magento/app/code && \
    touch /var/log/cron.log && \
    touch /var/log/syslog

RUN echo "\
* * * * * /usr/bin/php /var/www/html/magento/bin/magento cron:run | grep -v \"Ran jobs by schedule\" >> /var/www/html/magento/var/log/magento.cron.log \n\
* * * * * /usr/bin/php /var/www/html/magento/update/cron.php >> /var/www/html/magento/var/log/update.cron.log \n\
* * * * * /usr/bin/php /var/www/html/magento/bin/magento setup:cron:run >> /var/www/html/magento/var/log/setup.cron.log \n\
" | crontab -u www-data -

RUN ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log




CMD cron && apachectl -D FOREGROUND