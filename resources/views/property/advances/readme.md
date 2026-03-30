Property Management System – Advance Transactions Module
System Development Notes
1. Advance Types
The system shall support three categories of advances:
    1. Expense Advances
    2. Client Payment Advances
    3. Overpayment Advances
Each category shall have distinct rules, charges, and interest calculations.

2. Expense Advances
Description
These are advances issued to facilitate operational expenses related to property management.
Eligible Expense Categories
The system shall allow expense advances for the following:
    • Repairs and maintenance
    • Caretaker salaries
    • Garbage collection
    • Electricity bills
    • Water bills
    • Printing and photocopying
    • Refund of tenant deposits
    • Eviction costs
    • Locking devices
    • Security services
Financial Rules
Interest
    • No interest shall be charged on expense advances.
Cheque Processing Charges
The system shall apply cheque charges as follows:
Expense Type	Charge
General expenses	KES 100
Caretaker salaries	KES 150
Refund of deposits	KES 150
Charge Allocation
    • The applicable cheque charge shall be billed to the client account except from refund of deposit which is billed from the tenant/recipient of the payment.

3. Client Payment Advances
Description
These are advances issued directly to property owners (clients) prior to the onset of the rent collection cycle.
Payment Method
The system shall support:
    • Direct transfer to client bank account
    • Alternative payment instructions as directed by the client
The system must therefore allow flexible payment instruction configuration.

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
