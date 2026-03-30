# Returns Approval Workflow

## Overview

Each generated return (`cb_returns_summary`) gets a 4-level approval workflow. Approval items are created automatically during return generation.

## Approval Levels

| Level | Role               | Source                               |
| ----- | ------------------ | ------------------------------------ |
| 1     | Field Officer      | `sys_pm_properties.field_officer_id` |
| 2     | Zone Manager       | `sys_pm_properties.zone_manager_id`  |
| 3     | Clerical Officer   | User with `clerical_officers` role   |
| 4     | Accountant (Final) | User with `accountants` role         |

## Flow

1. **Generation** — `processGenerate` creates `cb_returns_clients` lines, `cb_returns_summary` records, and a `cb_approval_items` entry per summary with Level 1 approver assigned.
2. **Level 1–3 Approve** — Approver clicks Approve → their `cb_item_approvers` status becomes `Approved` → `cb_approval_items.current_level` increments → next level approver is auto-assigned.
3. **Level 4 (Final) Approve** — Accountant approves → `cb_approval_items.status = 'Approved'` → `cb_returns_summary.approved_status = 1`.
4. **Reject** — Level 2+ approvers can reject and return to any lower level. The lower approver's status resets to `Pending` and they can re-approve.

## Database Tables

- **`cb_approval_items`** — One per return summary. `item_type = 'return_summary'`, `item_id = cb_returns_summary.id`. Tracks `status` (Pending/InProgress/Approved/Rejected) and `current_level`.
- **`cb_item_approvers`** — One per approver per approval item. Tracks `approver_level`, `approval_status` (Pending/Approved/Rejected/Skipped).
- **`cb_approval_actions`** — Audit trail. Records every action (Initiated, Approved, Returned, Commented) with timestamps, comments, and `sequence_number` for tracking cycles.

## API Endpoints

| Method | URL                                       | Action                   |
| ------ | ----------------------------------------- | ------------------------ |
| GET    | `/ajax/returns/{encId}/approvals`         | Load approval timeline   |
| POST   | `/ajax/returns/{encId}/approvals/approve` | Approve at current level |
| POST   | `/ajax/returns/{encId}/approvals/reject`  | Reject to lower level    |
| POST   | `/ajax/returns/{encId}/approvals/comment` | Add comment              |

## UI

The **Unprocessed Return Details** page has two tabs:

- **Detail Lines** — rent line items table
- **Approvals** — conversation trail (left, col-8) + compact timeline (right, col-4), with Approve/Reject buttons shown only to the current-level approver
