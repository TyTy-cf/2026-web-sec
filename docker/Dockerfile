######
# BASE STAGE
######
FROM php:8.2-fpm AS base
ENV COMPOSER_MEMORY_LIMIT=-1

# Add the github script to easy install-php-extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_mysql zip ctype iconv xsl gd intl

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN echo "memory_limit=1G" > /usr/local/etc/php/conf.d/memory_limit.ini
RUN echo "post_max_size=256M" > /usr/local/etc/php/conf.d/post_max_size.ini
RUN echo "upload_max_filesize=256M" > /usr/local/etc/php/conf.d/upload_max_filesize.ini


######
# COMPOSER INSTALL STAGE  ← NOUVEAU
######
FROM base AS composer-install

WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-scripts


######
# DEV STAGE PHP
######
FROM base AS dev

RUN install-php-extensions xdebug
COPY docker/xdebug.ini /usr/local/etc/php/conf.d/

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root

COPY user_entry_point.sh /user_entry_point.sh
RUN chmod +x /user_entry_point.sh
RUN /user_entry_point.sh ${USER_ID} ${GROUP_ID}

#switch to the good user
USER ${USER_ID}:${GROUP_ID}

######
# DEV STAGE NODE
######
FROM node:22 AS node-dev

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root

COPY user_entry_point.sh /user_entry_point.sh
RUN chmod +x /user_entry_point.sh
RUN /user_entry_point.sh ${USER_ID} ${GROUP_ID}

#switch to the good user
USER ${USER_ID}:${GROUP_ID}

FROM node:22 AS node

WORKDIR /app

COPY . .

RUN npm install && \
    npm build && \
    rm -rf node_modules


######
# caddy
######
FROM caddy:2.11 AS caddy

COPY Caddyfile /etc/caddy/Caddyfile

