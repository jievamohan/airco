# /mysql-export

Exporteer de **huidige staat** van de lokale Compose-MySQL-database naar `exports/db/` (full + schema-only SQL).

**Uitvoeren** (repo-root, `mysql`-service moet draaien):

```bash
make mysql-export
```

Of direct:

```bash
bash scripts/local/mysql-export.sh
```

Extra compose-files (zoals overrides):

```bash
bash scripts/local/mysql-export.sh -f docker-compose.yml -f docker-compose.override.yml
```

**Output:** `exports/db/fissa-mysql-full-<timestamp>.sql` en `fissa-mysql-schema-only-<timestamp>.sql`. Zie `exports/db/README.md` en `docs/runbooks/commands.md` (sectie Local MySQL export). `*.sql` staat in `.gitignore`.
