---
name: feature-plan-standard
description: >-
  Standard playbook for feature planning before code: tiered explore agents,
  UI option wireframes, parallel council review, CreatePlan output, and
  delivery handoff. Use in Plan mode, when the user asks to plan a feature,
  or before any non-trivial implementation.
---

# Feature Plan Standard

Repo-specific planning protocol for Fissa. Complements `/feature` ([`60-feature-pipeline.mdc`](../../rules/60-feature-pipeline.mdc)) and agent dispatch ([`70-agent-dispatch.mdc`](../../rules/70-agent-dispatch.mdc)) — it does **not** replace them. Use this skill for **interactive plan-first** work (Cursor Plan mode, "plan feature X", "bouw feature X" with plan before code).

**Reference implementation:** Category Health (`/admin/categories/health`) — raster-first UI ([`46-minimal-subtext.mdc`](../../rules/46-minimal-subtext.mdc)), thin API payloads ([`47-thin-api-payloads.mdc`](../../rules/47-thin-api-payloads.mdc)), generic API errors ([`25-api-no-sql-leak.mdc`](../../rules/25-api-no-sql-leak.mdc)).

---

## When to use

Use this skill when:

- **Plan mode** is active (no code until user approves Build)
- User says "plan feature X", "design X", "hoe bouwen we X"
- User says "bouw feature X" but expects a plan first
- Any non-trivial feature (UI and/or API) before writing implementation code

Do **not** use for:

- One-line bug fixes with an obvious single-file change
- Pure questions about existing code (answer directly)
- `/feature` autopilot runs that already have complete `artifacts/current/*` planning outputs (follow [`70-agent-dispatch.mdc`](../../rules/70-agent-dispatch.mdc) instead)

---

## Tier model

| Tier | Explore agents (parallel) | Council calls (parallel) | When |
|------|---------------------------|--------------------------|------|
| **S** | 1 | 3 (1 per role) | Single surface, no migration, no new admin page |
| **M** (default) | 2 | 3 (1 per role) | Typical feature; one primary lens (API **or** UI-heavy) |
| **L** | 3 | 6 (2 per role, different angles) | UI **and** API, migration (D-lane), new admin page |
| **XL** | 3–4 | 9 (3 per role) | Auth, privacy, payments, multi-epic, cross-cutting |

### Auto tier selection

1. Start at **M** unless user specifies otherwise.
2. Escalate to **L** if any: new admin page, DB migration, UI + API both substantial.
3. Escalate to **XL** if any: auth/permissions, privacy/PII, payments, epic spanning >5 tasks.
4. Downgrade to **S** only when user explicitly requests minimal planning or change is truly trivial (document why).

Record chosen tier in the plan header: `Tier: M | Explore: 2 | Council: 3`.

---

## Phase A — Explore (MUST use Task tool)

**Hard requirement:** Launch explore subagents via the **Task** tool (`subagent_type: explore`). Run them **in parallel** in a single message when count > 1.

**BLOCKED** if the agent writes a plan from memory only without Task explore output. Cite file paths and patterns from explore results in the plan.

### Explore lenses (assign one per agent)

| Agent | Lens | Focus |
|-------|------|--------|
| E1 | Domain / API | Routes, models, services, resources, existing admin/public endpoints |
| E2 | UI patterns | Pages, components, DESIGN.md, similar admin screens, responsive behavior |
| E3 | Related systems | Config, site settings, jobs, tests, migrations, cross-feature coupling |
| E4 (XL) | Risk / compliance | Authz, PII, migrations rollback, operational impact |

### Explore prompt template

```
Full Repository Path: /Users/rajiv/docker/fissa
Feature: <one-line title>
Tier: <S|M|L|XL>
Lens: <Domain/API | UI patterns | Related systems | Risk>

Explore the codebase for implementing this feature. Return:
1. Existing files/patterns to reuse (paths)
2. Gaps (what does not exist yet)
3. Constraints from rules 46-minimal-subtext, 47-thin-api-payloads, 25-api-no-sql-leak where relevant
4. Suggested lanes (A1/A2/W1/W2/D/T) and file globs
5. Top 3 risks

Be specific with paths. Read-only; no edits.
```

### Tier dispatch

- **S:** 1× E1 (or E2 if UI-only)
- **M:** 2× parallel — E1 + E2 (swap E2 for E3 if API-only backend feature)
- **L:** 3× parallel — E1 + E2 + E3
- **XL:** 3–4× parallel — E1 + E2 + E3 + E4

---

## Phase B — UI options (UI-facing features only)

Skip if no `apps/web` UI (pure API/script).

1. Propose **2–4 layout options** with **ASCII wireframes** (dense data views: prefer raster/grid like Category Health).
2. Note trade-offs (density, mobile ~390px, a11y, minimal subtext per rule 46).
3. Use **AskQuestion** (or equivalent user choice) for option selection **before** Phase D.
4. **Stitch (optional):** For high-fidelity mockups, note that [`stitch-design`](../stitch-design/SKILL.md) may run after option lock — not required for plan approval.

**Wireframe example (Category Health style):**

```
+-- Filter [x] (icon only) -------------------------------------+
| [≡] Band          [■][■][■][□][□]  (slot grid, no counters) |
| [≡] DJ            [■][□][□][□][□]                            |
+-- legend: icon swatches + sr-only only -----------------------+
```

---

## Phase C — Council (MUST use Task tool, readonly)

**Hard requirement:** Council members are **separate Task calls** (`subagent_type: generalPurpose` or `explore`, `readonly: true`). Launch all council tasks **in parallel** in one message.

**FORBIDDEN:** Simulating council voices in the foreground without Task agents. **FORBIDDEN:** One council agent playing all three roles.

### Roles

| Role | Attack angle (vary per instance) |
|------|----------------------------------|
| **API / architecture** | Boundaries, resources/DTOs, migrations, idempotency, thin payloads |
| **UX / consistency** | DESIGN.md, minimal subtext, mobile, admin shell patterns |
| **QA / acceptance** | Testable criteria, PHPUnit/Playwright/manual mapping, edge cases |

### Council counts

| Tier | Instances | Total Task calls |
|------|-----------|------------------|
| M | 1 per role | 3 |
| L | 2 per role (different angles) | 6 |
| XL | 3 per role | 9 |

**L/XL angle examples:**

- API #1: payload shape + routes; API #2: query performance + migration safety
- UX #1: layout/desktop; UX #2: mobile + a11y + empty/error states
- QA #1: happy path + API tests; QA #2: E2E + regression + manual checks

### Council prompt template

```
Full Repository Path: /Users/rajiv/docker/fissa
Role: <API/architecture | UX/consistency | QA/acceptance>
Angle: <specific lens for this instance>
Feature: <title>
Tier: <tier>
Explore summary: <paste key findings from Phase A>

Review the proposed direction (option <N> if UI). Return:
- Blockers (must fix before implementation) — max 3, severity P0/P1
- Improvements (should fix) — max 3
- Open questions — max 2

Apply rule 46 (minimal subtext), 47 (thin API payloads), 25 (no SQL leak) where relevant.
Read-only; no code changes.
```

### Merge step (foreground agent)

After all council Task results return:

1. **Dedupe** overlapping findings.
2. Assign **severity**: P0 (blocker), P1 (high), P2 (nice).
3. Cap merged output: **max 5 blockers + 5 improvements** in the plan.
4. Resolve or list open questions; unresolved P0 → plan stays BLOCKED.

---

## Phase D — CreatePlan

Produce the plan via Cursor **CreatePlan** (or equivalent structured plan artifact). **No code changes** in this phase.

### Required plan sections

1. **Header** — Feature title, tier, chosen UI option (if any), scope_in / scope_out
2. **Context** — Problem, user outcome, explore citations (file paths)
3. **Architecture** — Optional mermaid diagram (web → Nitro → API → DB)
4. **API contract** — Routes, request/response shapes (explicit field lists; rule 47)
5. **UI contract** — Page route, components, raster/layout notes (rule 46)
6. **Council resolution** — Blockers addressed, accepted improvements, deferred items
7. **Acceptance aspects** — **6–8 aspects**, each with **3–5 test scenarios** mapped to:
   - `PHPUnit` — `docker compose exec api php artisan test`
   - `Playwright` — `docker compose run --rm e2e` (never host)
   - `manual` — explicit steps when automation is insufficient
8. **Implementation todos** — Ordered, lane-tagged (A1/A2/W1/W2/D/T), dependencies noted
9. **Rules checklist** — Explicit nod to 46, 47, 25; gates C/D/F from [`20-quality-gates.mdc`](../../rules/20-quality-gates.mdc)

### Acceptance aspect example

| Aspect | Scenarios | Tool |
|--------|-----------|------|
| Authz | Guest 401; non-admin 403; admin 200 | PHPUnit |
| Index payload | Response has only documented keys; no supplier emails | PHPUnit |
| Raster UI | No helper paragraphs; filter icon-only | Playwright + manual |
| Mobile | Primary actions at 390px width | Playwright chromium-mobile |

### Plan → `/feature` handoff

If the user later runs `/feature`, map this plan into `artifacts/current/*` per [`70-agent-dispatch.mdc`](../../rules/70-agent-dispatch.mdc). The plan is the source of truth for acceptance aspects and todos.

---

## Phase E — Delivery (after user Build / approval)

Only after explicit user approval or Plan mode Build:

1. **Branch** — `feat/<slug>` per [`05-branch-discipline.mdc`](../../rules/05-branch-discipline.mdc); never commit on `main`.
2. **Parallel lanes** — Backend (A1/A2) and frontend (W1/W2) in parallel when contracts are stable and independent.
3. **D-lane** — Run migrations in Compose MySQL; document in `artifacts/current/db-review.md` when using `/orchestrate-task`.
4. **Quality gates** (container-only):
   - `docker compose exec api php artisan test`
   - `docker compose exec api composer run phpstan`
   - `docker compose exec web pnpm run typecheck`
   - `docker compose run --rm e2e pnpm test:e2e` (scoped specs when possible)
5. **Fix iterations** — Dispatch focused subagents (implementer-api/web, tester); do not thrash with repeated full explores.
6. **CI** — For `/feature` delivery, CI watch required per [`50-ci-watch.mdc`](../../rules/50-ci-watch.mdc).

---

## Quick checklist

- [ ] Tier selected and recorded
- [ ] Phase A: Task explore agents launched (count matches tier); BLOCKED if skipped
- [ ] Phase B: UI options + user choice (if UI-facing)
- [ ] Phase C: Task council agents launched (count matches tier); merged ≤5+5
- [ ] Phase D: CreatePlan with 6–8 aspects × 3–5 scenarios; no code
- [ ] Phase E: Only after approval; containers for all test commands
