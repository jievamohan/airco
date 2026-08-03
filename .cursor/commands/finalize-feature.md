# /finalize-feature

Goal: When a feature PR is ready, squash the branch, force-push, re-check CI, and finish.

Protocol:
1) Ensure PR exists (must already be bootstrapped).
2) Run scripts/ci/gh_pr_tasks_sync.sh to tick completed tasks.
3) Run scripts/ci/gh_finalize_feature.sh
4) If CI fails or times out, STOP and report BLOCKED.

Output:
- PR number + URL
- Confirmation that CI is green after squash.