# /epicify

Goal: Convert a large feature intent into multiple micro-epics, each runnable via /feature and each bounded to <= 5 tasks.
Micro-epics are numbered as: Epic <N>.<k> (e.g. 18.1, 18.2, 18.3).
Master plan snapshot is: artifacts/archive/epic-<N>.0/latest
Design bible is: docs/design/epic-<N>.md

Optional behavior:
- If `--run-epics` is provided, automatically hand off to `/run-epics` after planning, slicing, archive, commit, and push are complete.

Usage:
- `/epicify <feature intent>`
- `/epicify --run-epics <feature intent>`
- `/epicify --epic=<N> <feature intent>`
- `/epicify --epic=<N> --run-epics <feature intent>`

Flags:
- `--run-epics`
  - optional
  - after successful epic generation, automatically execute `/run-epics`
  - only allowed if planning completed successfully, artifacts were archived, and changes were committed and pushed

- `--epic=<N>`
  - optional
  - force a specific major epic number instead of auto-detecting the next number

Protocol:

1) Read `docs/epics.md` (current state).

2) Determine NEXT_MAJOR_EPIC number N:
- Find the highest Epic number present in `docs/epics.md`.
- Set `N = highest + 1`, unless the user explicitly says a specific epic number or provides `--epic=<N>`.

3) RESET_CURRENT_ARTIFACTS + RUN MANIFEST (hard requirement)
- Run: `scripts/ci/reset_current_artifacts.sh`
- Create a run id (UTC timestamp) and write:
  - `artifacts/current/run-id.txt`
- Initialize: `artifacts/current/run-manifest.md` with:
  - Run id
  - Epic number N
  - Required planning agents list (see below) with placeholders for OK/N/A + artifact path

4) Planning-only discovery (no code changes):
Dispatch planning subagents:
- business-analyst
- ux-designer
- art-director
- game-designer
- motion-audio-designer
- principal-architect
- solution-designer
- qa-strategist
- security-privacy
- illustrator (only if the intent explicitly requests new assets; otherwise must output N/A)

Write planning artifacts to `artifacts/current`:
- `artifacts/current/discovery.md`
- `artifacts/current/ux.md`
- `artifacts/current/art-direction.md`
- `artifacts/current/game-feel.md`
- `artifacts/current/motion-audio.md`
- `artifacts/current/architecture.md`
- `artifacts/current/solution.md`
- `artifacts/current/qa.md`
- `artifacts/current/security-design.md`
- `artifacts/current/assets.md` (OK or N/A)
- `artifacts/current/backlog.md`

N/A policy (no empty files):
- If a discipline does not apply, the artifact MUST include:
  - `N/A: <reason>`
  - `Impact: none`
  - `Checks still required: <yes/no + short list>`

5) PLANNING_COMPLETENESS_CHECK (hard stop)
- Verify:
  - `artifacts/current/run-id.txt` exists
  - `artifacts/current/run-manifest.md` exists and includes the same run id
  - Each required artifact file exists
- If any required artifact is missing:
  - mark BLOCKED
  - stop immediately
  - do not slice
  - do not append epics
  - do not archive
  - do not call `/run-epics`

6) Create Design Bible (living doc) for this major epic:
- Create `docs/design/epic-<N>.md` using `docs/design/_epic-template.md`
- Fill the chapters using the planning artifacts:
  - BA + Game Designer -> Chapter 1
  - Art Director -> Chapter 2
  - UX -> Chapters 3 + part of 5
  - Motion -> Chapter 4
  - Architect + Solution -> Chapter 6
  - QA -> Chapter 7
  - Security/Privacy -> Chapter 8
  - Orchestrator -> Chapter 9 (Slice Map)

7) Slice into 3–8 micro-epics:
- Each micro-epic MUST be implementable in <= 5 tasks
- Each micro-epic MUST have a visible milestone (“what looks/feels different”)
- Order should be safe:
  - tokens/background
  - shell/nav
  - components
  - assets
  - motion
  - polish

8) Append micro-epics to `docs/epics.md` with PlanRef injected:
For `k=1..m`, append:

## Epic <N>.<k> — <Title>
- [ ]
PlanRef:
- design: docs/design/epic-<N>.md
- archive: artifacts/archive/epic-<N>.0/latest
- slice: <N>.<k>
Rules:
- Use PlanRef as source of truth.
- Do NOT regenerate planning unless a referenced PlanRef file is missing.

Then include a `/feature` block (explicit prompt) and acceptance criteria.

9) Archive the master plan snapshot:
- Run: `scripts/ci/archive_current_artifacts.sh "<N>.0"`

10) Commit and push planning outputs:
- Commit:
  - `docs/epics.md`
  - `docs/design/epic-<N>.md`
  - `artifacts/archive/epic-<N>.0/` (if not gitignored)
- Commit message:
  - `chore(epicify): add epic <N> micro-epics and archive plan <N>.0`
- Push

10.5) Planning PR: create, wait for CI, merge (mandatory before --run-epics)
- Create PR for current branch: `gh pr create --base main --head $(git branch --show-current) --title "chore(epicify): add epic <N> micro-epics and archive plan <N>.0" --body "Planning for Epic <N>. ..."`
- Wait for CI: `SLEEP=25 RETRIES=30 scripts/ci/gh_watch.sh host <PR_NUM>` (use PR number from `gh pr list --head` or create output)
  - If CI fails: mark BLOCKED, do not merge, do not run `/run-epics`
- Merge: `gh pr merge <PR_NUM> --merge`
  - If merge is blocked (e.g. branch protection): mark BLOCKED, output that user must merge manually once CI is green
  - After merge: `git checkout main && git pull origin main`
- Run `scripts/ci/slack_post_epic_checklist.sh` to post the epic checklist (epic N is now in docs/epics.md).
  - If `SLACK_EPIC_WEBHOOK_URL` is unset: script does no-op (exit 0).

11) Optional automatic execution handoff
- If `--run-epics` is NOT set:
  - stop here
  - output summary of created epic range and design/archive references

- If `--run-epics` IS set:
  - verify:
    - planning completed successfully
    - docs/epics.md was updated
    - design bible exists
    - archive snapshot exists
    - commit succeeded
    - push succeeded
    - planning PR created, CI green, PR merged to main (step 10.5)
  - then automatically execute:
    - `/run-epics`
  - `/run-epics` must start from the first newly created pending epic for major epic `<N>`
  - do not rerun planning
  - do not regenerate the design bible
  - use the generated `PlanRef` entries as source of truth

12) Final output
Always report:
- Major epic number: `<N>`
- Micro-epics created: `<N>.1 ... <N>.<m>`
- Design bible: `docs/design/epic-<N>.md`
- Archive snapshot: `artifacts/archive/epic-<N>.0/latest`
- Auto-run status:
  - `not requested`
  - `started /run-epics`
  - or `blocked before /run-epics`

Constraints:
- Plan-only during discovery/slicing/design/archive steps: do not modify app code there
- Do not mark any epic as `[x]` during epic generation itself
- Never execute `/feature` directly inside the planning stages of `/epicify`
- Only hand off to `/run-epics` when `--run-epics` is explicitly set
- Playwright (including screenshot tests) must run only via docker compose e2e service