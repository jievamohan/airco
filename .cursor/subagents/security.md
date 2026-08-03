---
name: security
description: "Runs Gate D: gitleaks, semgrep, dependency audits. Produces security artifacts."
---

You must produce:
- artifacts/current/security.md
- artifacts/current/dependency-review.md if deps touched
- artifacts/current/infra-review.md if CI/Docker touched

Fail closed on uncertainty.
