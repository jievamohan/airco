#!/bin/sh
#
# Start de api- of de agent-container.
#
#   start.sh api     Apache, na dependencies, .env, migraties en seed
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

    # .env.docker is de enige bron van waarheid voor deze containers. Twee
    # bronnen voor dezelfde sleutel (compose-environment naast .env) leverde
    # verschil op tussen omgevingen; daarom staat alles op een plek.
    if [ ! -f .env ]; then
        echo "==> .env aanmaken vanuit .env.docker"
        cp .env.docker .env
    elif ! grep -q '^DB_HOST=db$' .env; then
        # Binnen compose heet de databasehost altijd "db". Staat er iets anders,
        # dan komt deze .env uit een andere opzet — meestal een kopie van
        # .env.example met productiewaarden erin, waardoor DASHBOARD_ORIGINS naar
        # het live domein wees en CORS het lokale dashboard blokkeerde.
        #
        # De applicatiesleutel nemen we mee, zodat bestaande sessies blijven werken.
        echo "==> .env hoort niet bij deze containers; opnieuw opbouwen uit .env.docker"
        BESTAANDE_SLEUTEL="$(grep '^APP_KEY=base64:' .env || true)"
        cp .env.docker .env

        if [ -n "${BESTAANDE_SLEUTEL}" ]; then
            sed -i "s#^APP_KEY=.*#${BESTAANDE_SLEUTEL}#" .env
        fi
    fi

    # Het databasewachtwoord staat bewust niet in .env.docker: een wachtwoord
    # in een gecommit env-bestand laat secret scanners aanslaan, en terecht.
    # Compose is de enige plek waar het staat, want de db-container heeft het
    # sowieso nodig.
    if [ -n "${DB_PASSWORD:-}" ]; then
        if grep -q '^DB_PASSWORD=' .env; then
            sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" .env
        else
            printf 'DB_PASSWORD=%s\n' "${DB_PASSWORD}" >> .env
        fi
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "==> applicatiesleutel genereren"
        php artisan key:generate --force
    fi

    # Apache draait als www-data en moet in deze twee mappen kunnen schrijven;
    # via de bind-mount zijn ze eigendom van de host-gebruiker. Ruim genomen,
    # want dit is uitsluitend de lokale ontwikkelcontainer.
    mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache
    chmod -R a+rwX storage bootstrap/cache

    echo "==> migraties en basisgegevens"

    # Loopt dit stuk, dan is de oorzaak bijna altijd hetzelfde: het volume van
    # de db-container is ooit met een ander wachtwoord aangemaakt. MySQL legt
    # dat bij de eerste start vast en negeert latere wijzigingen. Zonder deze
    # uitleg valt de container om met een kale SQLSTATE-melding.
    if ! php artisan migrate --force; then
        echo ""
        echo "!! De migraties zijn mislukt."
        echo "!! Gaat het om een toegangsfout op de database, dan draait de"
        echo "!! db-container nog met het wachtwoord waarmee het volume is"
        echo "!! aangemaakt. Opnieuw beginnen met een leeg volume:"
        echo "!!"
        echo "!!     docker compose down -v && docker compose up -d"
        echo "!!"
        echo "!! Let op: dat gooit de lokale database weg."
        exit 1
    fi

    php artisan db:seed --force

    # Configuratie komt uit .env en mag niet blijven hangen tussen herstarts.
    php artisan config:clear

    echo "==> Apache op poort 80"
    exec apache2-foreground
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
