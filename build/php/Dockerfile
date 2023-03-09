FROM phpdockerio/php:8.1-cli

ARG APP_DIR

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get -y --no-install-recommends install build-essential php8.1-dev php-pear php8.1-mongodb php-pgsql php-bcmath php-http-request2 \
    && apt-get install -y libpq-dev tzdata gettext-base gettext msmtp procps libpcre3 libpcre3-dev git \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN pecl channel-update pecl.php.net
RUN pecl install mongodb

RUN echo "extension=mongodb.so" > /etc/php/8.1/cli/conf.d/20-mongodb.ini && \
	echo "extension=mongodb.so" > /etc/php/8.1/mods-available/mongodb.ini

COPY ./ ${APP_DIR}/

WORKDIR ${APP_DIR}
RUN rm -rf build

RUN composer install

CMD ["/bin/bash"]