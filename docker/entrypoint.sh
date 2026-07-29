#!/bin/sh
# Container entrypoint: validate the environment, cache config for this
# runtime environment, then hand off to FrankenPHP.
#
# config:cache/route:cache/view:cache run HERE and not during `docker build`
# on purpose: at build time none of the real environment variables (DB_*,
# APP_URL, MAIL_*, ...) are known yet, so a config cache baked into the image
# would freeze in empty/default values. Every container would then silently
# ignore whatever environment it was actually started with - the classic
# containerized-Laravel footgun. Caching after the environment is available,
# every time the container starts, is the only correct order.
set -e

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set." >&2
    echo "" >&2
    echo "Copy the APP_KEY from the existing installation's .env into this" >&2
    echo "container's environment - do not generate a new one with" >&2
    echo "'artisan key:generate'. A different key makes already-encrypted" >&2
    echo "data (fuel card numbers, two-factor secrets) permanently unreadable." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrations are a deliberate, separate operational step (see README) and
# never run automatically here: this image may be started as more than one
# instance, and auto-migrating on every container start is unsafe in that case.

exec docker-php-entrypoint "$@"
