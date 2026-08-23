FROM php:8.4-fpm-alpine

WORKDIR /var/www

# ── Системные пакеты: PHP-ext'и + nginx + supervisor ────────────────────
# nginx + supervisor нужны для Render: у них один контейнер на сервис,
# поэтому nginx и php-fpm живут вместе, а supervisor следит за обоими.
# postgresql-dev нужен для сборки pdo_pgsql.
RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        git \
        unzip \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
        opcache \
    && apk del $PHPIZE_DEPS

# ── Composer ─────────────────────────────────────────────────────────────
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/php/conf.d/ $PHP_INI_DIR/conf.d/
ENV COMPOSER_HOME=/tmp/composer

# ── Код приложения ──────────────────────────────────────────────────────
# Локально в docker-compose код подкидывался через volume `.:/var/www`,
# поэтому здесь COPY не было. На Render volume нет — печём код в образ.
# Копируем ВЕСЬ проект перед composer install: symfony/flex-плагину нужны
# config/ и bin/ во время install (он генерирует bundle.php и т.п.).
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && mkdir -p var/cache var/log \
 && chown -R www-data:www-data var

# ── Адаптация docker/nginx/app.conf под "один контейнер" ────────────────
# Локально nginx ходит в `app:9000` (отдельный контейнер php-fpm).
# На Render оба процесса в одном контейнере → 127.0.0.1:9000.
# Порт 8080 — Render умеет с ним работать, в UI выставим PORT=8080.
COPY docker/nginx/app.conf /tmp/app.conf
RUN sed -e 's/listen 80;/listen 8080;/' \
        -e 's/fastcgi_pass app:9000;/fastcgi_pass 127.0.0.1:9000;/' \
        /tmp/app.conf > /etc/nginx/conf.d/default.conf

# ── supervisord (конфиг прямо тут, отдельного файла не плодим) ──────────
RUN { \
        echo '[supervisord]'; \
        echo 'nodaemon=true'; \
        echo 'logfile=/dev/null'; \
        echo 'logfile_maxbytes=0'; \
        echo 'pidfile=/run/supervisord.pid'; \
        echo; \
        echo '[program:php-fpm]'; \
        echo 'command=/usr/local/sbin/php-fpm'; \
        echo 'autostart=true'; \
        echo 'autorestart=true'; \
        echo 'stdout_logfile=/dev/stdout'; \
        echo 'stdout_logfile_maxbytes=0'; \
        echo 'stderr_logfile=/dev/stderr'; \
        echo 'stderr_logfile_maxbytes=0'; \
        echo; \
        echo '[program:nginx]'; \
        echo 'command=/usr/sbin/nginx -g "daemon off;"'; \
        echo 'autostart=true'; \
        echo 'autorestart=true'; \
        echo 'stdout_logfile=/dev/stdout'; \
        echo 'stdout_logfile_maxbytes=0'; \
        echo 'stderr_logfile=/dev/stderr'; \
        echo 'stderr_logfile_maxbytes=0'; \
    } > /etc/supervisord.conf

EXPOSE 8080

# На старте контейнера: миграции → настройка messenger-транспортов →
# supervisord (держит nginx + php-fpm). exec заменяет процесс на supervisord,
# чтобы он стал PID 1 и корректно обрабатывал SIGTERM.
CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction --env=prod && php bin/console messenger:setup-transports --env=prod && exec /usr/bin/supervisord -c /etc/supervisord.conf"]
