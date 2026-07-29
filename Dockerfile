# syntax=docker/dockerfile:1

# 3B Fuhrparkmanagement — FrankenPHP deployment image
#
# Classic (non-worker) mode is intentional, not an oversight. With 3-5 users
# and ~20 vehicles, Laravel Octane's worker mode buys no measurable throughput
# here, but it does buy the failure class "state survives between requests"
# (static properties, singletons, anything left over in a booted container) -
# a class of bug that is especially nasty with Livewire and Filament, both of
# which lean heavily on request-scoped component state. Do not "optimize" this
# into `frankenphp php-server --worker` or Octane without re-reading this
# comment and understanding what you are trading away.
#
# Base image: dunglas/frankenphp, pinned to a concrete FrankenPHP + PHP
# version + Debian codename (not `latest`, not a floating `-php8.4` tag) so a
# rebuild next year still produces the same image. See README for the
# rationale behind the exact tag.
#
# PHP 8.4, not 8.3: composer.json declares "php": "^8.3", but composer.lock
# actually locks symfony/console (and siblings) at v8.1.x, which requires PHP
# >=8.4.1 - the composer.json constraint is stale. DDEV runs this project on
# PHP 8.4 (.ddev/config.yaml: php_version: "8.4"), and the 215-test suite has
# only ever been verified there. Building this image against PHP 8.3 fails
# outright (composer install reports the version conflict above); PHP 8.4 is
# the only version this dependency tree - and this app - has actually run on.
ARG FRANKENPHP_VERSION=1.12.6
ARG PHP_VERSION=8.4.23
ARG DEBIAN_CODENAME=bookworm

########################################
# Stage 1: composer binary
#
# Only the composer executable is taken from this stage; it never contributes
# a layer to the runtime image.
########################################
FROM composer:2.8 AS composer_bin

########################################
# Stage 2: build — install Composer dependencies against the real app source
########################################
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_CODENAME} AS build

# intl: required by Filament. pdo_mysql: required for the MariaDB connection.
# zip: lets Composer unpack dist archives directly instead of shelling out to
# a separate unzip binary this image doesn't otherwise need.
RUN install-php-extensions intl pdo_mysql zip

COPY --from=composer_bin /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first, keyed only on the lock file, so Docker's layer
# cache survives application code changes that don't touch composer.*.
# --no-scripts here: the app code (artisan, config/, ...) that the post-install
# scripts (package:discover, filament:upgrade) need isn't present yet.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-interaction --prefer-dist

# Now bring in the application and finish the install for real: this re-runs
# composer install with the full source present, so post-autoload-dump scripts
# succeed, and builds the optimized, classmap-based autoloader.
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

########################################
# Stage 3: runtime image
########################################
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_CODENAME} AS runtime

RUN install-php-extensions intl pdo_mysql

# Production-tuned php.ini (display_errors off, etc.). APP_DEBUG=false is the
# primary guard against leaking errors to users; this is the PHP-level
# backstop underneath it.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/opcache-prod.ini "$PHP_INI_DIR/conf.d/zz-opcache-prod.ini"

WORKDIR /app

# Bring over the fully built application (vendor/ included) from the build
# stage, owned by the unprivileged user from the start.
COPY --from=build --chown=www-data:www-data /app /app

# storage/ and bootstrap/cache/ are the only paths this app writes to at
# runtime: generated exports land in storage/app, logs in storage/logs,
# framework caches in bootstrap/cache. Everything else can stay as copied.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache

# Caddy, bundled inside FrankenPHP, keeps its own state under /data and
# /config (autosave config, and TLS certificates when FrankenPHP terminates
# HTTPS itself for a real domain, see README). The unprivileged user needs to
# write there too.
RUN mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# The app must not run as root. This image's frankenphp binary already has
# cap_net_bind_service set, so www-data can still bind ports 80/443 below
# without needing root or a setuid wrapper.
USER www-data

EXPOSE 80 443 443/udp

# A real response from the public homepage (no login required) is the
# healthcheck: it exercises routing, PHP, and the view layer in one request.
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -fsS http://localhost/ -o /dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
