# Dashboard leave overview

The HR Manager and Director dashboards include an employee/year leave account. Employee options include Active (1) and On Leave (4) staff. The endpoint checks the authenticated user's `hr_manager` or `directors` role independently of the dashboard page.

## Sources and interpretation

- Entitlement, recorded balance, pending reservations, and available-to-request figures come from the existing read-only `LeavePlanService::availableDays` used by leave applications. Viewing the dashboard never creates or approves an annual plan, changes a balance, or changes request status.
- Approved allocated days are summed from the saved approved daily fractions for approved/completed requests (5/49), by each day's actual year. Approved future days are allocations, not evidence the employee has already taken leave. Refunded/cancelled daily records (41) are displayed with zero charge.
- A legacy approved request with no daily records is shown as an undated recorded total when wholly within the selected year. A cross-year request with missing daily records cannot be accurately allocated and is flagged rather than assigned invented dates.
- Pending dates are explicitly a preview using current category, working-day, and holiday rules, including Saturday half-days. For single-day requests the requested fraction is preserved. Their total is compared with the reserved amount recorded on the requests.
- Annual-plan approval, missing hire dates, missing configured entitlement, and differences between daily details, request totals, and plan balances appear as warnings. This view exposes existing discrepancies without rewriting the underlying accounting or guessing whether a difference is an adjustment.
- Available balance follows the application rule: recorded balance less pending reservations, minimum zero. A negative recorded balance remains visible. Categories are shown individually rather than summed into one interchangeable leave allowance.
- Category filters affect both tables; month filters affect the daily table only, leaving the year-level account unchanged. All figures are in days; pay tiers and rates are shown only when saved on daily records.

### Missing or inactive leave categories

The overview includes categories referenced by the employee's requests even if those categories are now inactive or absent from HR settings. Missing categories are labelled Unconfigured leave category #ID; inactive categories retain their name with an inactive label. Their recorded pending totals and approved daily records remain visible, but entitlement, balance and available-to-request figures are unavailable until HR confirms the setup. Legacy plans under a missing category are not automatically mapped to a current category, and no records are changed.

For pending requests without category rules, a single-day request shows the recorded requested amount; a multi-day request shows one clearly labelled request-period total instead of an invented daily breakdown. An unresolved cross-year allocation is unavailable, not zero. Month filters retain overlapping request periods without assigning the full request total to the selected month. Setup warnings open automatically. An approved cross-year request without daily records, or an approved day without its fraction, is also flagged as unavailable rather than treated as zero.

## Verification

Refresh `/dashboard` as a Director or HR Manager. Select an active employee and year. Compare annual entitlement/balance with their plan and leave application availability. Compare approved entries with the request's saved daily records, including a half-day, refunded date, future approval, and cross-year request. Check pending previews around weekends and public holidays. Use a historical employee with missing details to confirm warnings and unavailable values are visible. Change category/month/year and verify filters and error states. No migration is required.
