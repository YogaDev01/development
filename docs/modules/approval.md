# Module — Approval

There is no generic approval engine. Each module implements its own status
chain in its controller, with history in a per-module log table.

- Chains & owners: `docs/workflows/approval.md`.
- Locked transitions (`Concerns\TransitionsStatus`) are used **only** by Cost
  Request; other modules check-then-update.
- Display: `App\Support\WorkflowStatus` (labels, "waiting for whom", tones,
  stepper flows) + `x-status`, `x-workflow-stepper`, `x-workflow-flow`.
  It is presentation only and must not be used to decide transitions.
- Worklist: Todo List (`TodoListController`, `TodoService`,
  `BuildsCrWorklist`) shows each role's pending items, aging since last status
  change, and bulk approve/reject.
- Notifications: `NotifyService::send()` to recipients from `RoleDirectory`
  (queued; in-app + WhatsApp via Fonnte).
