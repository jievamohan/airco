# KlimaatX — productiedeploy op de TransIP-VPS (DirectAdmin, geen Docker).
#
# Paden staan op één plek: scripts/deploy/prod-target.sh
#
#   make deploy             laptop → SSH → dezelfde deploy op de VPS
#   make deploy-on-server   op de VPS zelf: sync, build, publiceren, worker
#   make deploy-worker      alleen de wachtrij en de scheduler bijwerken
#   make rollback-ui        laatste UI-momentopname terugzetten
.PHONY: deploy deploy-on-server deploy-worker rollback-ui

deploy-on-server:
	@chmod +x scripts/deploy/*.sh
	@bash scripts/deploy/deploy-on-server.sh

# Draait hetzelfde script op de VPS. `bash -lc` laadt het loginprofiel, zodat
# nvm en corepack op PATH staan.
deploy:
	@. scripts/deploy/prod-target.sh \
		&& ssh "$$KLIMAATX_DEPLOY_SSH" "bash -lc 'cd $$KLIMAATX_REPO_ROOT && make deploy-on-server'"

# Zonder pad zoekt het script de Laravel-map naast zichzelf; zo werkt het ook
# als de clone ergens anders staat dan prod-target.sh zegt.
deploy-worker:
	@chmod +x scripts/deploy/install-queue-worker.sh
	@bash scripts/deploy/install-queue-worker.sh

rollback-ui:
	@bash -c 'source scripts/deploy/publish-nuxt-public.sh \
		&& klimaatx_restore_ui apps/api/public apps/api/.deploy/ui.prev'
