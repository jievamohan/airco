# /run-epics

**CRITICAL — Mandatory Slack scripts (run these; do not skip):**
| Step | Script | When |
|------|--------|------|
| a2 | `scripts/ci/slack_post_epic_checklist.sh` | First action when epic starts |
| f | `scripts/ci/slack_post_review.sh --epic ... --pr ... --url ... --verdict ... --merge-allowed ... --blockers ... --majors ... --mediums ... --minors ... --nits ... --branch ... --head-sha ... --pass ...` | After each review pass |
| i | `scripts/ci/gh_pr_merge_with_notify.sh --pr ... --head-sha ... --epic ... --title ...` | For merge (replaces raw `gh pr merge`; posts merge evidence to Slack when bot env is set, then merges, then post-merge webhooks) |

Skills used: architect (planning), code-guru (implementation), security-reviewer (security validation), code-review-expert (PR review). See 70-agent-dispatch for mapping.

Goal: Run all epics from docs/epics.md hands-off:
- execute each epic via /feature
- perform housekeeping inside the original feature branch and PR
- ensure the feature branch is squashed to a single head commit
- ensure CI is green on that squashed head
- run strict self-review on the PR
- print clear, granular review logging directly in the Cursor command output
- if review finds issues: fix them, re-finalize (re-squash), and re-run CI/review
- if review is clean: merge the feature PR with a merge commit (no squash on merge)
- after merge: sync local main and clean up the local feature branch
- continue to the next epic

Protocol:

0) BRANCH SYNC (at start and before each new epic)
- `git checkout main && git pull origin main`
- Ensures each epic starts from up-to-date main.

1) PREVIEW (before execution)
- Read docs/epics.md and split into epics in order.
- For each epic: check `- [x]` (done) vs `- [ ]` (pending).
- Output summary:
  - List: Epic N — status (done/pending).
  - Next to execute: first epic with `- [ ]`, or "All epics done" if none.
- If docs/epic-progress.md exists: reference it in output.
- Then proceed with step 2.

2) For each epic (starting from first pending):
   a) Skip if epic has `- [x]` (already done).

   a2) Epic started — post checklist (mandatory to run):
      - Run `scripts/ci/slack_post_epic_checklist.sh` to post current epic checklist to `SLACK_EPIC_WEBHOOK_URL`.
      - Script does no-op if `SLACK_EPIC_WEBHOOK_URL` is unset.

   b) Execute exactly the /feature block shown under that epic (including `--ci` and `--max-tasks`).

   c) /feature must:
      - bootstrap a single PR
      - run tasks
      - apply housekeeping in the same feature branch:
        - mark the current epic as done in `docs/epics.md`
        - update `docs/epic-progress.md` if that file is used by this repo
      - finalize the branch by squashing all branch commits into one commit on top of base
      - push the squashed branch head
      - run CI watch for the PR head

   d) Confirm PR exists by reading `artifacts/current/pr-number.txt` (or infer via `gh`).
      Also capture:
      - PR number
      - PR URL
      - current branch name
      - current PR head SHA after finalize
      - Epic ID and Epic title (from the `## Epic N — Title` line in docs/epics.md for the current epic)

   e) Ensure CI is green for the PR head:
      - `SLEEP=25 RETRIES=35 scripts/ci/gh_watch.sh host <PR_NUM>`
      - (35×25s ≈ 14.5 min — Gates workflow met Playwright/ZAP kan 6–8 min duren)

   f) Run strict self-review on the PR using the `code-review-expert` skill.
      Strict mode means:
      - any finding blocks merge
      - findings must be empty
      - merge gate must be true
      - confidence must not indicate unresolved critical uncertainty

      Live review visibility is required:
      - all review output must be shown directly in the Cursor command log
      - do not rely on files as the primary review log
      - every review pass must remain visible in the command output history
      - do not compress review output into only a one-line summary

      For each review pass, print the following directly in the Cursor output:

      Review pass header:
      - `===== REVIEW PASS <n>/<MAX_REVIEW_FIX_LOOPS> =====`
      - `Epic: <EPIC_ID>`
      - `PR: #<PR_NUM>`
      - `URL: <PR_URL>`
      - `Branch: <FEATURE_BRANCH>`
      - `Head SHA: <HEAD_SHA>`
      - `Mode: strict`

      Review verdict summary:
      - `Review verdict: <approve | approve-with-follow-ups | changes-required>`
      - `Merge allowed: <true | false>`
      - `Confidence: <high | medium | low>`
      - `Primary blocker: <none or main blocker>`
      - `Findings count: <n>`
      - `Severity totals: blocker=<n> major=<n> medium=<n> minor=<n> nit=<n>`

      Granular findings:
      For each finding, print:
      - `[Finding <index>]`
      - `Severity: <blocker | major | medium | minor | nit>`
      - `Title: <title>`
      - `Why it matters: <impact>`
      - `Evidence: <code evidence or concrete rationale>`
      - `Suggested fix: <smallest strong fix>`
      - `Status: <open | resolved | still-open | new>`

      Missing verification:
      - print any explicitly unknown or unverifiable areas

      Final review gate line:
      - `REVIEW_GATE verdict=<...> merge_allowed=<true|false> findings=<n> blockers=<n> majors=<n> mediums=<n> minors=<n> nits=<n>`

      Slack notification (mandatory to run):
      - After each review pass, run:
        `scripts/ci/slack_post_review.sh --epic "$EPIC_ID" --pr "$PR_NUM" --url "$PR_URL" --verdict "$VERDICT" --merge-allowed "$MERGE_ALLOWED" --blockers "$N" --majors "$N" --mediums "$N" --minors "$N" --nits "$N" --branch "$FEATURE_BRANCH" --head-sha "$HEAD_SHA" --pass "$PASS"`
      - Script does no-op if `SLACK_REVIEW_WEBHOOK_URL` is unset.
      - Pass the values from the review verdict summary and header above.

      Hard rule:
      - if the PR head SHA changes after a review pass, that review is stale
      - after any fix, re-squash, or push, a new review pass must be run and logged before merge is allowed

   g) If review finds issues:
      - print a fix summary directly in the Cursor log before making changes
      - fix only the scoped review findings in the same feature branch
      - preserve housekeeping changes in that same branch
      - rerun relevant validations
      - rerun finalize flow to re-squash the branch into one commit again
      - force-push the squashed branch
      - rerun CI watch on the new head
      - rerun strict self-review
      - repeat until review is clean or max review-fix loops is reached

      The fix summary printed in the Cursor log must include:
      - which findings were targeted
      - what changed
      - what was intentionally not changed
      - whether the branch was re-squashed
      - the new head SHA
      - a short validation result summary

      From the second review pass onward, also print a review delta:
      - `Review delta:`
      - `- resolved: <n>`
      - `- still open: <n>`
      - `- new: <n>`
      - `- verdict trend: <improved | unchanged | worsened>`

   h) Default loop limit:
      - `MAX_REVIEW_FIX_LOOPS=4`
      - If unresolved findings remain after final loop: stop immediately and summarize blockers.
      - Never merge a PR with unresolved findings.

   i) If review is clean and CI is green on the latest squashed head:
      - merge the feature PR and post Slack (single script; incoming webhooks cannot be skipped):
        - `scripts/ci/gh_pr_merge_with_notify.sh --pr <PR_NUM> --head-sha <HEAD_SHA> --epic "$EPIC_ID" --title "$EPIC_TITLE"`
      - The same script runs `slack_post_merge_evidence.sh` first (when `SLACK_BOT_TOKEN` + `SLACK_EVIDENCE_CHANNEL_ID` are set): downloads CI artifacts `merge-evidence-e2e` (PNGs only for Nuxt page files changed in the PR under `apps/web/app/pages/`) and/or `merge-evidence-api-scoped` for that head SHA, and uploads them to Slack. If bot env is unset, it no-ops; merge evidence failures are non-fatal.
      - Do NOT use raw `gh pr merge`; use this script so post-merge Slack (PR merged + epic checklist) runs automatically.

   j) Confirm feature PR is merged.
      - Prefer immediate verification via:
        - `gh pr view <PR_NUM> --json mergedAt,state,url`
      - Fallback polling allowed if needed.

   k) After merge:
      - checkout main
      - pull origin main
      - delete the local feature branch that was just merged:
        - `git branch -D <FEATURE_BRANCH>`
      - continue to next pending epic

Stop conditions:
- If any epic becomes BLOCKED, stop immediately and summarize blockers.
- Never start the next epic until:
  - the previous epic feature PR is merged
  - local `main` is checked out and synced
  - the merged local feature branch has been cleaned up

Output:
- For each epic:
  - PR number
  - PR URL
  - squashed head SHA used for merge
  - latest review verdict
  - latest review severity totals
  - latest REVIEW_GATE line
  - final status (`merged` / `blocked`)