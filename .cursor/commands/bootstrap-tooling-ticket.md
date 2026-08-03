# /bootstrap-tooling-ticket

Create a new task file in /tasks:
- id: 0001
- title: "bootstrap-tooling"
- status: ready
- scope_in: scripts/config to satisfy gates C, D, F for apps/web and apps/api
- scope_out: any game/application logic
- acceptance criteria:
  - web has lint/typecheck/test/build/size scripts
  - api has phpstan/test/audit scripts
  - CI runs gates on PR
  - artifacts generation path exists (/artifacts)

Also include lanes:
- I (deps/infra) for scripts/config/CI
- W2 minimal if needed for typecheck wiring
- A2 minimal if needed for phpstan wiring
- T for minimal test harness

Reminder: Step 6 is baseline scripts/config via this tooling ticket before any game logic.
