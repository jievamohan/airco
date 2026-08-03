# KlimaatX — production deploy (run on the TransIP / DirectAdmin VPS)
.PHONY: deploy-production deploy-production-dry-run rollback-production

deploy-production:
	@./scripts/deploy-production.sh

deploy-production-dry-run:
	@./scripts/deploy-production.sh --dry-run

rollback-production:
	@./scripts/deploy-production.sh --rollback
