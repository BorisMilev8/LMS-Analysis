FROM php:8.3-apache-bookworm
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ARG MOODLE_COMMIT=215f44380bdc3ccf852a3a22e34f8423ff64033a
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates curl supervisor locales libicu-dev libpq-dev libzip-dev libonig-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libcurl4-openssl-dev \
    && printf 'en_AU.UTF-8 UTF-8\nen_US.UTF-8 UTF-8\n' > /etc/locale.gen \
    && locale-gen \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 intl pgsql pdo_pgsql zip mbstring gd soap exif opcache curl \
    && rm -rf /var/lib/apt/lists/*
RUN curl -fsSL "https://codeload.github.com/moodle/moodle/tar.gz/${MOODLE_COMMIT}" \
    | tar -xz --strip-components=1 -C /var/www/html \
    && test -f /var/www/html/admin/cli/install_database.php \
    && a2enmod rewrite \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername
COPY block_workloadplanner/ /var/www/html/blocks/workloadplanner/
COPY deploy/render/ /opt/moodle-render/
COPY deploy/render/config.php /var/www/html/config.php
COPY deploy/render/health.php /var/www/html/_health.php
COPY deploy/render/php.ini /usr/local/etc/php/conf.d/moodle.ini
COPY deploy/render/supervisord.conf /etc/supervisor/conf.d/moodle.conf
RUN chmod +x /opt/moodle-render/start.sh /opt/moodle-render/cron.sh \
    && chown -R root:root /var/www/html \
    && mkdir -p /var/moodledata
WORKDIR /var/www/html
EXPOSE 10000
ENTRYPOINT ["/opt/moodle-render/start.sh"]
