# Leave handover and in-app notifications

Employees can type part of a colleague's name to search the handover selector and enter handover
notes in My Leave. HR has the same searchable fields when filing or editing an application.
The employee search starts empty. Type at least two letters and explicitly choose a matching name
by clicking it or using the arrow keys and Enter. Searching or closing the list never picks the
first result automatically. Clear removes both the displayed name and submitted employee ID.
Editing a selected name clears that employee ID; choose a new result or clear the search before
submitting. New forms reset to empty, while editing an application restores its saved colleague.
The colleague must be active, have a linked, unblocked and activated system account with an assigned
role, and be someone other than the person taking leave. This matches the app's login and role checks.
Both the link table and legacy user-to-staff link are supported. Notes allow up
to 5,000 characters. Leave both fields empty if there is no work to hand
over; otherwise both are required. Existing applications can continue without a handover.

The handover is visible to authorized reviewers on application details, Pending Approvals, and the
Workflow Approvals details dialog. The selected colleague also receives an in-app alert with a link
to a private, read-only handover page. That page shows the applicant, leave period, current approval
status, and notes; it does not expose the applicant's balances, allowances, leave category, or approval
actions. Only the currently assigned colleague can open that handover link. If the handover is
reassigned, the former colleague's old link no longer grants access.

## Submission and approvals

- Submission creates an in-app notification for the reachable reporting supervisor and eligible HR
  approvers, excluding the applicant and blocked users. A user with both responsibilities receives
  one submission alert. HR's first alert says when supervisor approval is still outstanding.
- Supervisor approval changes the request to Partial / awaiting HR, then creates a second alert for
  HR approvers who can complete the final stage. The supervisor who approved cannot approve both
  stages and is excluded from this second alert.
- Alerts link to the specific application. The owner, reporting supervisor, and HR can view it;
  approval buttons and server-side decisions still follow the current approval stage.
- Application edits resubmit through the existing route and notify reviewers of the saved revision.
  Reprocessing the same saved revision does not create another matching event for the recipient.
- A saved submission or edit also notifies the selected handover colleague. Pending leave is clearly
  described as awaiting approval. Automatically approved HR leave still alerts its handover colleague.
  Existing applications are not retroactively notified merely by deploying this code.
- Saving the application and submission alerts is transactional. Supervisor approval and the
  corresponding HR alerts are also transactional, so failed notification storage rolls back that
  change instead of leaving a partly saved approval.

The existing approval rules remain: normal routed applications go Pending → Partial → Approved;
supervisors and staff without a reachable supervisor may go directly to HR. HR's own application
can auto-approve under the existing rule, which retains the existing director notification. HR
approvers are identified by the existing router (roles or `update_leave_applications` permission).
Final-approval emails retain their existing behavior; these new alerts are in-app only.

Pending Approvals and its legacy action/status endpoints now delegate to the same approval service
as Workflow Approvals. This prevents the older page from bypassing reporting-line authorization,
logging, final balance deduction, or notification creation.

## Bell and toast behavior

The header bell checks the authenticated user's notification feed every 15 seconds while
the page is visible and checks again on returning to the tab. It updates the unread badge and shows
dismissible arrival toasts. It does not require a queue worker or an additional realtime service
for these in-app alerts. This is polling, so delivery on screen may take up to the next successful check.

On first use, the feed establishes a baseline rather than replaying historical unread alerts.
The last delivered cursor is remembered per user in the browser tab's session storage to avoid
repeating toasts on navigation. Old unread alerts remain accessible in the dropdown. Poll failures
retain the cursor for retry. Bursts are fetched in batches of 50; at most three toast boxes stay on
screen together, while the dropdown retains notification history.

Notification text is rendered as text rather than executable HTML. Links must remain on the app's
origin. Both the feed and read actions are scoped to the signed-in user. Opening an alert marks it
read; dismissing the toast alone does not mark it read.

## Database update and local review

Migration `2026_10_28_140000_add_handover_to_leave_requests` adds nullable `handover_staff_id` and
`handover_notes` columns to `sys_hr_leave_requests`. It does not backfill records or modify balances,
departments, reporting assignments, roles, or permissions. The recipient is validated against active
staff when saving; no legacy staff-table ID type conversion is required.

Only apply this migration to the intended database after approval:

```powershell
php artisan migrate --path=database/migrations/2026_10_28_140000_add_handover_to_leave_requests.php
```

Its rollback drops both new columns and any saved handover information. Do not roll it back on a
database with handover data without reviewing that loss first. Do not use `migrate:fresh`.

After the migration, check with test accounts: submit with handover notes, open the supervisor's
bell alert and approve, then open HR's second alert and give final approval. Confirm the handover
is readable, HR cannot approve before the supervisor, and days are deducted only at final approval.
Use a separate browser profile/session for each user so the app's single-login rule is respected.
Live notification delivery requires unblocked system users linked to the reporting-line staff and
an eligible HR approver. Missing links or hierarchy data must be corrected through the agreed setup.

### Watching the process yourself

1. Choose four distinct test accounts: applicant, the applicant's reporting supervisor, HR, and the
   colleague receiving the handover. For the normal two-stage demonstration, the applicant should
   not themselves be a supervisor or HR approver; those existing rules can skip a stage.
2. Keep the supervisor, HR, and colleague accounts signed in in separate browser profiles or separate
   browsers. Ordinary tabs share a login. Do not sign the same account into several sessions because
   this application enforces one login at a time.
3. Leave each recipient page open long enough for the initial notification check, then submit a
   fresh application as the applicant with a colleague and clear test handover notes. A valid local
   application will save records and create real local in-app notifications for those recipients.
4. Bring a recipient's tab to the front. Within the next successful check (normally up to 15 seconds),
   its badge should update and a toast should appear. If signing in after the application was already
   submitted, use the bell dropdown to see the unread alert; old alerts are not replayed as toasts.
5. As the handover colleague, open the handover alert and check the notes on the read-only page.
   It should say that the leave is awaiting approval. It provides no approval buttons.
6. As the supervisor, open the leave alert, inspect the notes, and approve. HR then receives a second
   alert saying that supervisor approval is complete. As HR, open that second alert and give final
   approval. The leave becomes Approved and the existing balance rules apply once.
7. The colleague can reopen the handover page to see the current approved status. This change sends
   handover alerts on submission/edits, not a separate final-approval alert to that colleague.

Use agreed test records rather than submitting leave for real employees just to demonstrate the UI.
No notification is sent to another machine's separate local database. All participants in this
demonstration must connect to the same running app/database.

Automated checks cover routing and balances, handover validation and edits, recipient selection,
failure rollback, user isolation, burst pagination, and polling/toast behavior with simulated DOM
objects. They use temporary SQLite data and fake queued jobs; they do not demonstrate live delivery
against the restored local PostgreSQL database.
