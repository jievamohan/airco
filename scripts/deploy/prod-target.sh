# shellcheck shell=bash
# shellcheck disable=SC2034  # dit bestand wordt gesourcet; de variabelen
#                             worden elders gebruikt.
# Productiedoel van KlimaatX (TransIP-VPS, DirectAdmin, geen Docker).
#
# Monorepo-clone op de server; Laravel staat in apps/api, net als lokaal.
# De document root van het domein wijst naar apps/api/public: daar staat de
# Laravel front controller én de statische Nuxt-build. Eén origin dus, geen
# CORS tussen de site en de API.
#
# Pas alleen KLIMAATX_REPO_ROOT aan; de rest volgt daaruit. Verhuist de clone,
# dan corrigeert deploy-on-server.sh zichzelf naar de map waar hij zelf staat.

KLIMAATX_DEPLOY_SSH="${KLIMAATX_DEPLOY_SSH:-sinoxi@server}"
KLIMAATX_REPO_ROOT="${KLIMAATX_REPO_ROOT:-/home/sinoxi/domains/airco.sinoxi.nl}"
KLIMAATX_LARAVEL_ROOT="${KLIMAATX_REPO_ROOT}/apps/api"
KLIMAATX_PUBLIC_DIR="${KLIMAATX_LARAVEL_ROOT}/public"
KLIMAATX_SITE_URL="${KLIMAATX_SITE_URL:-https://airco.sinoxi.nl}"
