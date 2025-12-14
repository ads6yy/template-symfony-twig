FROM php:8.4-apache as client_php

ENV \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1


RUN apt-get update \
    && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev wget git libzip-dev unzip python-is-python3 sudo default-mysql-client libwebp-dev

RUN  docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ --with-webp=/usr/include/ \
     && docker-php-ext-install gd \
     && :\
     && docker-php-ext-install pdo pdo_mysql opcache zip \
     && docker-php-ext-enable pdo pdo_mysql opcache zip \
     && docker-php-ext-install bcmath

WORKDIR /srv

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN if [ -n "$NON_PROD" ] ; then printf "User-agent: *\nDisallow: /\nNoindex: /\n" > ./web/robots.txt; fi

RUN a2enmod rewrite

CMD ["sudo", "/usr/sbin/apache2ctl", "-D", "FOREGROUND"]