# /ci-fetch-logs

Fetch CI logs for the last failed run and write artifacts.

Usage:
- Host:
  - scripts/ci/gh_fetch_logs.sh host <RUN_ID>
- Container:
  - scripts/ci/gh_fetch_logs.sh container <RUN_ID>

Protocol:
1) If RUN_ID not provided, use artifacts/current/ci-last-run-id.txt from /ci-watch.
2) Save full run log to artifacts/current/ci-logs/run-<RUN_ID>.log.
3) Write artifacts/current/ci-failures.md summarizing where to look.
4) Generate or update a Lane I task "ci-green" containing the key error snippet(s).