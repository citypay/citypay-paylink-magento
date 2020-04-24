FROM ubuntu:18.04

ENV TZ=Europe/London
COPY files/mariadb.list /tmp/mariadb.list

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone && \
        apt-get update && apt-get install -yq --no-install-recommends \
        apt-utils \
        curl \
        wget \
        git \
        apache2 \
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
        sudo\
        jq \
        ssmtp \
        gnupg \
        less vim && \
        apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xF1656F24C74CD1D8 && \
        mv /tmp/mariadb.list /etc/apt/sources.list.d/mariadb.list && \
        apt-get update && \
        apt-get install -yq --no-install-recommends mariadb-server && \
        apt-get clean && rm -rf /var/lib/apt/lists/*


RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN locale-gen en_US.UTF-8 en_GB.UTF-8

# used for access to Magento repo to download Magento
ARG MAGENTO_REPO_USERNAME=""
ARG MAGENTO_REPO_PASSWORD=""
COPY files/init-build.sh /init-build.sh

RUN /init-build.sh && rm /init-build.sh && \
    composer create-project --repository=https://repo.magento.com/ magento/project-community-edition=2.3.3 /var/www/html/magento

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

RUN chown -R www-data:www-data /var/www/html/magento/app/etc/ && \
    mkdir -p /var/www/html/magento/app/code/Magento  && \
    chown -R www-data:www-data /var/www/html/magento/app/code && \
    touch /var/log/cron.log && \
    touch /var/log/syslog

RUN echo "\
0 1 * * * /usr/bin/php /var/www/html/magento/bin/magento cron:run | grep -v \"Ran jobs by schedule\" >> /var/www/html/magento/var/log/magento.cron.log \n\
0 1 * * * /usr/bin/php /var/www/html/magento/update/cron.php >> /var/www/html/magento/var/log/update.cron.log \n\
0 1 * * * /usr/bin/php /var/www/html/magento/bin/magento setup:cron:run >> /var/www/html/magento/var/log/setup.cron.log \n\
" | crontab -u www-data -

RUN cd /usr/local/bin && \
    wget https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-arm64.tgz && \
    tar zxvf ngrok-* && \
    rm -rf ngrok-*

COPY magento/app /var/www/html/magento/app
COPY magento/generated /var/www/html/magento/generated
COPY magento/pub /var/www/html/magento/pub

RUN chown -R www-data:www-data /var/www/html/magento

COPY files/data.zip /var/lib/data.zip

WORKDIR /var/lib

# switch on PHP display errors and setup database
RUN sed -i 's/display_errors = Off/display_errors = On/g' /etc/php/7.2/apache2/php.ini && \
    unzip data.zip && \
    rm -rf mysql && \
    mv data mysql && \
    rm data.zip && \
    chown -R mysql:mysql mysql

WORKDIR /var/www/html/magento
RUN composer require citypay/magento-paylink:1.0.5 && \
    sudo -u www-data php bin/magento module:enable CityPay_Paylink

COPY files/container-startup.sh /container-startup.sh
RUN chmod +x /container-startup.sh && \
    ln -sf /proc/self/fd/1 /var/log/apache2/access.log && \
    ln -sf /proc/self/fd/1 /var/log/apache2/error.log

COPY files/init-build.sh /init-build.sh
RUN echo "mysqld_safe --log-error=/var/log/mysql/error.log --user=mysql &"  > /tmp/config && \
    echo "mysqladmin --password=root --silent --wait=30 ping || exit 1" >> /tmp/config && \
    bash /tmp/config && \
    rm -f /tmp/config && \
    /init-build.sh && rm /init-build.sh && \
    cp /root/.composer/auth.json /var/www/html/magento/var/composer_home/auth.json && \
    chown www-data:www-data /var/www/html/magento/var/composer_home/auth.json && \
    sudo -u www-data php /var/www/html/magento/bin/magento module:enable --all && \
    sudo -u www-data php /var/www/html/magento/bin/magento sampledata:deploy --no-interaction && \
    sudo -u www-data php /var/www/html/magento/bin/magento setup:upgrade && \
    sudo -u www-data php /var/www/html/magento/bin/magento cache:flush && \
    sudo -u www-data php /var/www/html/magento/bin/magento cache:clean && \
    sudo -u www-data php /var/www/html/magento/bin/magento setup:di:compile && \
    rm /var/www/html/magento/var/composer_home/auth.json && \
    rm /root/.composer/auth.json

CMD cron && /container-startup.sh
