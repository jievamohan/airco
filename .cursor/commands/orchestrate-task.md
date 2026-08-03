# /orchestrate-task

You are the orchestrator subagent.

Goal: take a single task file from /tasks (user will specify which) and drive it to MERGE-READY using the project rules, including remote CI verification, automatic CI remediation, and committing CI/PR artifacts so the working tree stays clean.

Defaults:
- CI_WATCH_MODE: host (fallback to container)
- MAX_CI_FIX_LOOPS: 3
- Base branch: GitHub default branch (auto-detected via gh)

Protocol:

0) Branch discipline (hard stop) + single-PR override

Single-PR override (when invoked from /feature):
- If `artifacts/current/pr-number.txt` exists:
  - You are operating inside an existing feature PR.
  - Do NOT create or switch branches.
  - Stay on the current branch and continue.

Otherwise (standalone task execution):
- Check current branch: `git branch --show-current`
- If on `main` or `master`:
  - Create/switch to feature branch: `feat/{task.id}-{slug}`
- Do not modify files until you are off main/master.
- Record active branch name in `artifacts/current/plan.md`.

1) Validate task contract (contract-first)
- Open the task file in /tasks and validate YAML frontmatter:
  - scope_in, scope_out, acceptance, lanes (with file globs), gates, risks
- If incomplete or ambiguous:
  - Fix the task file ONLY (no implementation yet)
  - Re-validate and continue.

2) Plan + risk artifacts (required before implementation)
- Create/overwrite:
  - `artifacts/current/plan.md`:
    - Task summary + acceptance criteria mapping
    - Wave plan (0..3)
    - Lane assignments + file ownership globs
    - Branch plan (per-lane branches + integration plan if applicable)
  - `artifacts/current/risk.md`:
    - Risk areas (deps/infra/db/auth/security/perf) + mitigations
    - Explicitly flag high-risk changes (auth/crypto/payments)

2.1) PR_BODY_SEED (no code changes)
- Ensure `artifacts/current/pr.md` exists and includes a task checklist for this feature run.
- Generate the checklist from the task files created in /tasks for this feature (ordered):
  - Format:
    ## Tasks
    - [ ] <task-id>-<task-slug>
- Append (or create) this section in artifacts/current/pr.md BEFORE PR bootstrap so the PR body contains it.
- Do not mark any task as done here; all start unchecked.

3) Split into lane subtasks + dispatch subagents (parallel)
- Dispatch based on lanes in the task:
  - W1/W2 -> implementer-web
  - A1/A2 -> implementer-api
  - T -> tester
  - I -> deps-infra (only if task includes infra/deps/CI/docker/scripts)
  - D -> db (only if task includes migrations/schema)
- Always dispatch reviewer, security, performance near the end for verification/review.

4) Enforce lane ownership (strict-default, soft-by-exception)
- Each lane may only edit its owned globs by default.
- If cross-lane edits are needed:
  - Create `artifacts/current/ownership-request.md` explaining file(s), reason, impact, rollback
  - STOP and re-slice/serialize work or request explicit approval in the plan.

5) Implement + integrate (wave-based)
- Run wave-based implementation:
  - Wave 0: shared contracts/types/config (serialized, minimal diff)
  - Wave 1: backend lanes (A2 + A1) parallel
  - Wave 2: frontend lanes (W2 + W1) parallel
  - Wave 3: tests + hardening (T) + final verification
- Integrate in prescribed order:
  - I -> D -> A2 -> A1 -> W2 -> W1 -> T
- Ensure required artifacts exist and indicate PASS:
  - `artifacts/current/typecheck.md`
  - `artifacts/current/security.md`
  - `artifacts/current/perf.md`
  - `artifacts/current/tests.md`
  - `artifacts/current/review.md`
- If full-autonomy areas changed, ensure conditional artifacts exist:
  - `artifacts/current/dependency-review.md` (deps)
  - `artifacts/current/infra-review.md` (CI/Docker)
  - `artifacts/current/db-review.md` (migrations)
- Produce/refresh `artifacts/current/pr.md`:
  - PR-ready summary tied to acceptance criteria + commands run + risks + rollback

6) Push changes (mandatory before CI)
- Ensure branch is pushed to origin.
- If your workflow requires force-push (rebased history), do so safely and document it in `artifacts/current/pr.md`.

7) PR_BOOTSTRAP (mandatory before CI watch)
- Detect GitHub default base branch:
  - `BASE="$(gh repo view --json defaultBranchRef -q .defaultBranchRef.name)"`
- Determine whether a PR already exists for the current branch:
  - `PR_NUM="$(gh pr view --json number -q .number 2>/dev/null || true)"`
- If no PR exists:
  - Create one using artifacts/current/pr.md as body:
    - `gh pr create --base "$BASE" --head "$(git branch --show-current)" --title "[{task.id}] {task.title}" --body-file artifacts/current/pr.md`
  - Then re-fetch PR number:
    - `PR_NUM="$(gh pr view --json number -q .number)"`
- Record PR number + URL in:
  - `artifacts/current/pr.md` (append a short PR metadata block)

Hard stop:
- If PR cannot be created (auth/permissions), mark task BLOCKED and stop.

8) CI_VERIFY (remote, bounded)
- Run `/ci-watch` (bounded) for PR_NUM (host mode; fallback to container).
- If exit code 0: CI SUCCESS. If exit code 1 or 3: CI failed or pending; do not proceed.

9) CI auto-fix loop (bounded, immediate execution)
If CI SUCCESS:
- Proceed to step 10.

If CI FAILED:
- Run `/ci-fetch-logs` to create:
  - `artifacts/current/ci-logs/run-<RUN_ID>.log`
  - `artifacts/current/ci-failures.md`
- For up to MAX_CI_FIX_LOOPS times:
  a) Generate or overwrite `tasks/0002-ci-green.md`:
     - Lane I as primary
     - Strict scope: ONLY CI/scripts/config/deps/infra fixes (no app/game logic)
     - Acceptance: All GitHub Actions checks PASS on PR
     - Failure evidence: include 2–5 short snippets from the run log with context
  b) Execute the CI fix immediately by dispatching the right subagents:
     - deps-infra (primary)
     - security + performance (re-validate after changes)
     - reviewer (scope + risk enforcement)
     - tester ONLY if failures indicate test harness problems
  c) Apply fixes on the SAME PR branch (do not open a separate PR).
  d) Push updates.
  e) Re-run `/ci-watch`.
  f) If CI SUCCESS: break and proceed.
- If still failing after MAX_CI_FIX_LOOPS:
  - Mark the original task BLOCKED
  - Summarize remaining failures in `artifacts/current/review.md` + `artifacts/current/pr.md`
  - Do NOT claim MERGE-READY.

10) ARTIFACTS_COMMIT (keep working tree clean)
- Check working tree status:
  - `git status --porcelain`
- If clean: continue.
- If dirty:
  - If ALL changed files are under `artifacts/**` only:
    - `git add artifacts`
    - `git commit -m "chore(artifacts): update ci/pr metadata [{task.id}]" || true`
    - `git push`
    - Re-run `/ci-watch` once (bounded) to ensure CI is still green after artifact commit.
  - Else (non-artifacts changes present):
    - Mark task BLOCKED:
      - Explain which files are dirty and why this violates merge-ready
      - Require either committing them properly (if intended) or reverting them

11) TASK_CLOSE + PR_CHECKLIST_SYNC
- Mark the current task as completed in its task file:
  - Update the task frontmatter field:
    - `status: "done"`
  - Do this only if all gates are PASS and CI is SUCCESS.
- Commit the task status change on the current PR branch:
  - `git add tasks/<this-task-file>.md`
  - `git commit -m "chore(task): mark {task.id} done" || true`
  - `git push`
- Sync PR body checkboxes (if present):
  - Run: `scripts/ci/gh_pr_tasks_sync.sh`
  - If it changes PR body, no git commit is needed (PR body is remote).

12) Output (merge-ready criteria)
Output:
- Branch involved (PR branch; lane branches if used; integration notes)
- PR number + URL
- Explicit statement “MERGE-READY” ONLY if:
  - merge-ready checklist satisfied
  - AND ci-watch was run and exited 0 (CI SUCCESS for current head SHA)
  - AND working tree is clean
- Otherwise output “BLOCKED” with pointers to relevant artifacts.