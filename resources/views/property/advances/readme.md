Property Management System – Advance Transactions Module
System Development Notes

Note (August 2026): Expense Advances (formerly advance_type_id=1, with its expense_category/charge_billed_to fields) and the Payment Method field on Client Payment Advances (payment_method/payment_instructions) have been removed from the system, frontend and backend. Only Client Payment Advances and Overpayment Advances remain. Sections below referencing them are kept for historical context only.

1. Advance Types
The system supports two categories of advances:
    2. Client Payment Advances
    3. Overpayment Advances
Each category shall have distinct rules, charges, and interest calculations.

3. Client Payment Advances
Description
These are advances issued directly to property owners (clients) prior to the onset of the rent collection cycle.

Interest Calculation Rules
Interest is charged based on the date the advance is issued.
Advance Date	Interest Rule
Before 20th of the month	3% interest of the full amount 
Between 20th – 25th	3% interest prorated based on remaining days in the month
After 25th	No interest charged
System Flexibility
The system shall allow administrative modification of the interest rate.
This ensures:
    • Policy changes can be implemented
    • Different properties or clients may have customized rates.

4. Overpayment Advances
Description
Occurs where funds advanced to the client and later recovered in instalments.

Repayment Rules
    • Repayment shall be automatically scheduled over 3 months.
    • Exceptions can only occur with explicit authorization by an authorized user.

Interest Rules
Monthly Interest
    • 3% interest per month
    • Calculated on a reducing balance basis
Prorated Interest (Early Month Disbursement)
Where overpayment is made before the 25th day of the month:
Interest shall be prorated based on the number of days remaining in the month.

Cheque Charges
    • A KES 100 cheque processing charge shall be applied upon issuance of the overpayment.

5. Authorization and Controls
The system should support:
Role-Based Authorization
Certain actions require authorization, including:
    • Modification of interest rates
    • Alteration of repayment schedules
    • Approval of overpayment advances
    • Manual override of system calculations
Audit Trail
The system shall log:
    • User initiating advance
    • Amount issued
    • Date issued
    • Interest calculation
    • Charges applied
    • Authorizing officer (if applicable)

6. System Functional Requirements
The system must:
    1. Allow creation of advances under the three defined categories
    2. Automatically calculate charges and interest
    3. Support prorated interest calculations
    4. Allow administrative adjustment of interest rates
    5. Schedule automatic repayment plans
    6. Maintain audit logs for accountability
