#!/bin/sh
#
# Start de api- of de agent-container.
#
#   start.sh api     webserver: dependencies, .env, migraties, seed, serve
#   start.sh agent   wachtrij en scheduler
#
# Beide containers delen hetzelfde vendor-volume, maar alleen de api vult het.
# De agent wacht daarom tot dat klaar is; anders start hij tegen een lege
# vendor-map en valt hij om op een ontbrekende autoload.php.

set -e

cd /app

if [ "$1" = "api" ]; then
    echo "==> composer install"
    composer install --no-interaction --no-progress

    if [ ! -f .env ]; then
        echo "==> .env aanmaken vanuit .env.example"
        cp .env.example .env
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "==> applicatiesleutel genereren"
        php artisan key:generate --force
    fi

    echo "==> migraties en basisgegevens"
    php artisan migrate --force
    php artisan db:seed --force

    echo "==> webserver op poort 8000"
    exec php artisan serve --host=0.0.0.0 --port=8000
fi

# De healthcheck op de api-service houdt ons hier normaal al tegen; deze lus is
# het vangnet voor het geval de agent toch eerder aan de beurt komt.
while [ ! -f vendor/autoload.php ]; do
    echo "wachten tot de api-container de dependencies heeft geinstalleerd..."
    sleep 3
done

echo "==> wachtrij en scheduler"
php artisan queue:work --tries=3 --timeout=120 &
exec php artisan schedule:work
