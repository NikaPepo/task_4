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
# IMPORTANT: Symfony Runtime's bootEnv() требует физического файла .env,
# иначе вылетает PathException. .env в .gitignore и в образ не попадает,
# поэтому создаём минимальный файл. Реальные значения подтягиваются
# из Render Environment (DATABASE_URL, MAILER_DSN и т.д.) — они перекрывают
# то, что в .env.
#
# DEFAULT_URI нужен Symfony (symfony/routing) для генерации URL в CLI-контекстах.
# Без него будет EnvNotFoundException. Реальный APP_BASE_URL приходит из
# Render Environment и используется в web-контексте.
RUN { \
        echo 'APP_ENV=prod'; \
        echo 'DEFAULT_URI=http://localhost'; \
    } > .env
# --no-scripts: composer install — ставим пакеты без post-install. Скрипт
# @auto-scripts внутри composer.json дёргает `cache:clear`, который
# требует .env файл. А .env в .gitignore и в образ не копируется — Render
# передаёт env vars напрямую через Environment.
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction \
 && composer dump-autoload --optimize --no-dev --classmap-authoritative \
 && mkdir -p var/cache var/log \
 && chown -R www-data:www-data var

# ── nginx: свой конфиг (в Alpine 1.30 дефолтная структура сломалась) ──
# IMPORTANT: Alpine nginx 1.30+ не инклюдит conf.d/*.conf в правильном
# контексте ("server" directive is not allowed here). Чтобы не возиться
# с дефолтами — пишем свой nginx.conf с полным server-блоком.
# Порт 8080 — Render пробрасывает $PORT.
# 127.0.0.1:9000 — php-fpm в том же контейнере (не отдельный "app").
RUN { \
        echo 'worker_processes 1;'; \
        echo 'pid /run/nginx.pid;'; \
        echo 'error_log /dev/stderr warn;'; \
        echo; \
        echo 'events { worker_connections 1024; }'; \
        echo; \
        echo 'http {'; \
        echo '    include /etc/nginx/mime.types;'; \
        echo '    default_type application/octet-stream;'; \
        echo '    sendfile on;'; \
        echo '    keepalive_timeout 65;'; \
        echo '    client_max_body_size 100M;'; \
        echo; \
        echo '    server {'; \
        echo '        listen 8080;'; \
        echo '        server_name _;'; \
        echo '        root /var/www/public;'; \
        echo '        index index.php;'; \
        echo; \
        echo '        location ~ \.php$ {'; \
        echo '            try_files $uri =404;'; \
        echo '            fastcgi_split_path_info ^(.+\.php)(/.+)$;'; \
        echo '            fastcgi_pass 127.0.0.1:9000;'; \
        echo '            fastcgi_index index.php;'; \
        echo '            include fastcgi_params;'; \
        echo '            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;'; \
        echo '            fastcgi_param PATH_INFO $fastcgi_path_info;'; \
        echo '        }'; \
        echo; \
        echo '        location / {'; \
        echo '            try_files $uri /index.php$is_args$args;'; \
        echo '            gzip_static on;'; \
        echo '        }'; \
        echo '    }'; \
        echo '}'; \
    } > /etc/nginx/nginx.conf

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
# chown var/ (миграции работают как root, поэтому файлы создаются
# от root; php-fpm же работает как www-data — сбрасываем владельца) →
# supervisord (держит nginx + php-fpm). exec заменяет процесс на supervisord,
# чтобы он стал PID 1 и корректно обрабатывал SIGTERM.
CMD ["sh", "-c", "php bin/console doctrine:migrations:migrate --no-interaction --env=prod && php bin/console messenger:setup-transports --env=prod && chown -R www-data:www-data var && exec /usr/bin/supervisord -c /etc/supervisord.conf"]
