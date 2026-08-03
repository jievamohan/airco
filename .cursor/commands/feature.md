# /feature

Input: a single message describing desired functionality.

Usage:
- /feature <description>                 (DEFAULT: FORCE autopilot, bounded)
- /feature --safe <description>          (SAFE mode: enforce Complexity Gate; plan-only if gate fails)
- /feature --plan <description>          (PLAN_ONLY: always stop after backlog + tasks)
- /feature --max-tasks=5 <description>   (override bound; capped by mode)
- /feature --ci                          (require CI success after each executed task before proceeding)
- /feature --no-finalize                 (skip finalize step)

Goal: Run Discovery -> Generate backlog/tasks -> execute Delivery automatically on a SINGLE feature branch with a SINGLE PR.

Defaults:
- MODE: FORCE
- CI: enabled by default (treat as if --ci was provided)
- MAX_TASKS_FORCE: 5
- MAX_TASKS_SAFE: 3
- STOP_ON_BLOCKED: true

Important invariants:
- One feature run = one branch + one PR.
- Do NOT create a new branch per task during /feature delivery. Stack tasks onto the current feature branch.
- artifacts/current is ephemeral and MUST be reset at the start of every /feature run.

Protocol:

0) Parse flags
- If --plan: MODE=PLAN_ONLY
- Else if --safe: MODE=SAFE
- Else: MODE=FORCE
- If --max-tasks=N:
  - SAFE: MAX_TASKS = min(N, MAX_TASKS_SAFE)
  - FORCE: MAX_TASKS = min(N, MAX_TASKS_FORCE)
- CI behavior:
  - CI is ON by default; treat as required unless explicitly running PLAN_ONLY.
- If --no-finalize: FINALIZE=false
- Else: FINALIZE=true (default)

0.5) RESET_CURRENT_ARTIFACTS + RUN MANIFEST (hard requirement)
- Run: scripts/ci/reset_current_artifacts.sh
- Create a run id (UTC timestamp) and write:
  - artifacts/current/run-id.txt
- Initialize: artifacts/current/run-manifest.md with:
  - Run id
  - Feature title (first line of user input)
  - Required planning agents list with placeholders for OK/N/A + artifact path

1) Discovery (no code changes)

For plan structure and council protocol, follow rule 48 and skill `feature-plan-standard`.

PlanRef override:
- If the feature input contains a "PlanRef:" block:
  - Do NOT regenerate discovery/planning artifacts unless a referenced PlanRef file is missing.
  - Use the referenced design doc + archive directory as the single source of truth.
  - Generate tasks only for the specified slice (e.g. 18.2).

Dispatch planning subagents and produce (write under artifacts/current):
- artifacts/current/discovery.md
- artifacts/current/ux.md
- artifacts/current/architecture.md (use architect skill when producing)
- artifacts/current/solution.md
- artifacts/current/qa.md
- artifacts/current/security-design.md (use security-reviewer skill when producing)
- Plus any feature-specific planning artifacts if required (e.g. art-direction.md, game-feel.md, motion-audio.md, assets.md)

N/A policy (no empty files):
- If a discipline does not apply, the artifact MUST include:
  - "N/A: <reason>"
  - "Impact: none"
  - "Checks still required: <yes/no + short list>"

2) PLANNING_COMPLETENESS_CHECK (hard stop)
- Verify:
  - artifacts/current/run-id.txt exists
  - artifacts/current/run-manifest.md exists and includes the same run id
  - All required artifacts for this run exist (per manifest)
- If anything is missing: mark BLOCKED and stop (no tasks).

3) Backlog synthesis (no code changes)
Create:
- artifacts/current/backlog.md:
  - Epic summary
  - Scope_in / Scope_out
  - Risks + mitigations (tags: deps/infra/db/auth/security/perf/payments/crypto/data-loss/privacy)
  - NFRs (perf, security, a11y)
  - Task list (each task has: title, lanes, gates, acceptance, scope, risk tags)
Generate concrete tasks:
- tasks/00xx-*.md (contract-first YAML frontmatter + body)

3.1) PR_BODY_SEED (no code changes)
- Ensure artifacts/current/pr.md exists and includes a task checklist for this feature run.
- Generate the checklist from the task files created in /tasks for this feature (ordered).
- Append (or create) this section in artifacts/current/pr.md BEFORE PR bootstrap so the PR body contains it.
- Format:
  ## Tasks
  - [ ] <task-id>-<task-slug>
- Do not mark any task as done here; all start unchecked.

4) Complexity Gate (SAFE only)
SAFE delivery allowed ONLY IF:
- tasks_generated <= 3
- no high-risk tags present (auth/payments/crypto/data-loss/privacy)
- lanes do not include I or D unless explicitly allowed in the feature input
- acceptance criteria are testable and include required tests (unit at minimum)

If SAFE gate fails:
- stop as PLAN_ONLY and output tasks/backlog.

5) FEATURE_BRANCH + PR_BOOTSTRAP (mandatory before delivery, once)
- Ensure you are NOT on main/master; create or reuse a feature branch for this feature run.
- Push the branch to origin.
- Bootstrap a SINGLE PR for this feature branch by running:
  - scripts/ci/gh_pr_bootstrap.sh
- After this step:
  - artifacts/current/pr-number.txt and artifacts/current/pr-url.txt MUST exist.

Hard stop:
- If PR cannot be created (auth/permissions or command blocked), stop and report BLOCKED.

6) Delivery execution (FORCE/SAFE only, bounded)
- Select tasks in priority order from artifacts/current/backlog.md.
- Execute /orchestrate-task for each selected task sequentially, up to MAX_TASKS.
- Use code-guru skill for implementation work (implementer-web, implementer-api); use security-reviewer skill for security validation (per 70-agent-dispatch).

Critical instruction to /orchestrate-task:
- Use the CURRENT branch; do not create a new branch for each task.
- If /orchestrate-task attempts branch creation, it must keep working on the same PR branch (no PR sprawl).

- If CI is ON:
  - require CI success for each executed task before proceeding to next (orchestrator already does CI watch/autofix).
  - Playwright (including screenshot tests) must run only via docker compose e2e service.

- If any task becomes BLOCKED: stop immediately and summarize.

FORCE safety floor (always enforced):
- no destructive DB operations; prefer reversible changes only
- high-risk areas must be explicitly flagged in task risks + artifacts/current/risk.md
- keep diffs minimal and reversible

7) FINALIZE (automatic by default)
- If MODE is FORCE or SAFE AND at least one task was executed:
  - If any executed task ended BLOCKED: do NOT finalize; stop and summarize.
  - If all executed tasks completed successfully and CI is green:
    - Run /finalize-feature to:
      - sync PR task checklist
      - squash branch
      - force-push
      - re-run CI watch and require CI SUCCESS
- If finalize fails (CI red, auth blocked, dirty tree): mark BLOCKED and stop.

Optional flags:
- If user passed --no-finalize: skip this step.

8) Output
- Always output:
  - artifacts/current/backlog.md location
  - list of generated task files
  - PR number + URL (from artifacts/current/pr-*.txt) if PR exists
- If executed:
  - list executed tasks + final status (DONE/BLOCKED)