# Extra Mappings — CHABRINS (MSSQL) → chips_db_v3 (PostgreSQL)

> Tables/concepts that are **not in the original mapping CSV** but are required
> to fully resolve FK columns in the migration views.

---

## 1. Zones

|                       | Detail                                                            |
| --------------------- | ----------------------------------------------------------------- |
| **MSSQL Source**      | `CHABRINS.dbo.CHBLOCKS` — column **`BLOC`**                       |
| **PostgreSQL Target** | `chips_db_v3.sys_set_zones`                                       |
| **Migration View**    | `vw_sys_set_zones` (view #7a in `chabrins_to_postgres_views.sql`) |

### How it maps

`CHBLOCKS.BLOC` contains a single-letter zone code assigned to every property block.
There are **7 distinct values** (A–G), which match the 7 rows in `sys_set_zones` in chips_2 MySQL.

```
CHABRINS CHBLOCKS.BLOC   →   chips_db_v3 sys_set_zones.zone_name
─────────────────────────────────────────────────────────────────
A                         →   A  (id = 1)
B                         →   B  (id = 2)
C                         →   C  (id = 3)
D                         →   D  (id = 4)
E                         →   E  (id = 5)
F                         →   F  (id = 6)
G                         →   G  (id = 7)
```

`zone_id` is resolved in:

- `vw_sys_pm_properties_001` — via `CHBLOCKS.BLOC`
- `vw_sys_pm_lease_agreements_005` — via `CHBLOCKS.BLOC` (joined through `prop`)

### CHBLOCKS columns used

| CHBLOCKS Column | Description                             | Maps to                        |
| --------------- | --------------------------------------- | ------------------------------ |
| `BLOC`          | Single-letter zone code (A–G)           | `sys_set_zones.zone_name`      |
| `BLOCAL`        | Locality/area name (e.g. "Githurai 45") | `sys_set_zone_areas.area_name` |

---

## 2. Zone Areas

|                       | Detail                                                                 |
| --------------------- | ---------------------------------------------------------------------- |
| **MSSQL Source**      | `CHABRINS.dbo.CHBLOCKS` — column **`BLOCAL`**                          |
| **PostgreSQL Target** | `chips_db_v3.sys_set_zone_areas`                                       |
| **Migration View**    | `vw_sys_set_zone_areas` (view #7b in `chabrins_to_postgres_views.sql`) |

### How it maps

`CHBLOCKS.BLOCAL` contains the locality name for each block (e.g. "Githurai 45",
"Kasarani", "Zimmerman"). These map to `sys_set_zone_areas.area_name`.

`zone_id` within `sys_set_zone_areas` is resolved by matching `CHBLOCKS.BLOC`
to the zones view ROW_NUMBER.

> **⚠ Volume mismatch:**
> CHBLOCKS has **115 distinct BLOCAL values** but chips_2 MySQL has only **75 zone_areas**.
> The 40 extra localities from CHABRINS will be new rows in PostgreSQL.
> After migration, run a manual review to merge duplicates/typos
> (e.g. "BABA DOGO" vs "BABA NDOGO", "BURUBURU" vs "BURU BURU").

`zone_area_id` is resolved in:

- `vw_sys_pm_properties_001` — via `CHBLOCKS.BLOCAL`

---

## 3. Service Agreements / SLA

|                       | Detail                                       |
| --------------------- | -------------------------------------------- |
| **MSSQL Source**      | **NONE — no SLA concept exists in CHABRINS** |
| **PostgreSQL Target** | `chips_db_v3.sys_pm_service_agreements`      |
| **Migration View**    | N/A                                          |

### Status

CHABRINS has **no SLA or service-agreement table**. The `sla_id` FK in these target tables
has **no automatic source** and must be assigned manually after migration:

| Target table               | Column   | Action                                                                                                 |
| -------------------------- | -------- | ------------------------------------------------------------------------------------------------------ |
| `sys_pm_lease_agreements`  | `sla_id` | NULL during migration. Assign manually by matching property to its SLA in `sys_pm_service_agreements`. |
| `sys_pm_properties`        | `sla_id` | NULL during migration.                                                                                 |
| `sys_pm_deposits`          | `sla_id` | NULL during migration.                                                                                 |
| `sys_pm_deposits_requests` | `sla_id` | NULL during migration.                                                                                 |
| `sys_pm_tenants_payments`  | `sla_id` | NULL during migration.                                                                                 |

> **chips_2 MySQL already has 1,261 rows** in `sys_pm_service_agreements`
> (id 1–1261). These will need to be copied to `chips_db_v3` as-is before
> the manual `sla_id` assignment can be done.

---

---

## 4. Debtors Invoices

|                       | Detail                                                                      |
| --------------------- | --------------------------------------------------------------------------- |
| **MSSQL Source**      | `CHABRINS.dbo.CHTNTRS` (1,156,397 rows)                                     |
| **PostgreSQL Target** | `chips_db_v3.sys_pm_debtors_invoices`                                       |
| **Migration View**    | `vw_sys_pm_debtors_invoices` (view #19 in `chabrins_to_postgres_views.sql`) |

### How it maps

`CHTNTRS` is the tenant transactions table — every charge raised against a tenant/unit appears as a row here.

| CHTNTRS Column   | sys_pm_debtors_invoices Column  | Notes                                                                |
| ---------------- | ------------------------------- | -------------------------------------------------------------------- |
| BATCHID, ENTRYNO | id (ROW_NUMBER)                 | Ordered by BATCHID, ENTRYNO                                          |
| TRADATE          | date_time                       | Transaction date                                                     |
| DATEDUE          | date_due                        | varchar(50) — `TRY_CAST` handles malformed values                    |
| INVONUM          | reference_number                | Invoice number                                                       |
| TRANSDESC        | narration / trans_details       | Transaction description                                              |
| TRANSTYP         | debtor_type_id / return_type_id | RENT (796K), NULL (317K), House Deposit, Service Charge, Water, etc. |
| TRANSAMT         | total_amount                    | Charge amount                                                        |
| AMTPAY           | payable_amount                  | Amount to be paid                                                    |
| BALAMT           | current_balance                 | Outstanding balance                                                  |
| PAYPERC          | comm_per                        | Payment/commission percentage                                        |
| INVPRT           | processed                       | 1 = printed/processed → 'Yes', else 'No'                             |
| DELSTAT          | current_status_id               | 'Del' → 2 (deleted/inactive), else 1                                 |
| TRAYR, TRAMNTH   | returns_period                  | `DATEFROMPARTS(TRAYR, TRAMNTH, 1)` with NULL guard                   |

**FK resolution chain (same patterns as all other views):**

- `client_id` → `CHBLOCKS.LLORDID` → `CHLRDS` ROW_NUMBER (ORDER BY LORDID)
- `property_id` → `CHBLOCKS` ROW_NUMBER (ORDER BY BLOCKID)
- `unit_id` → `CHBUNITS` ROW_NUMBER (ORDER BY BLOCKID, UNITNO)
- `tenant_id` → `CHTENS` ROW_NUMBER (ORDER BY TENNAME, BLOCKNO, UNITNO)
- `zone_id` → `CHBLOCKS.BLOC` via DISTINCT-then-ROW_NUMBER subquery
- `field_officer_id` / `zone_supervisor_id` / `zone_manager_id` → `CHOFFICERS` via `CHBLOCKS.OFFID`, `SFOID`, `ZMGRID`

> **⚠ Volume note:** CHTNTRS has 1,156,397 rows vs 279,413 in MySQL `sys_pm_debtors_invoices`.
> The extra rows include historical data and possibly data not yet in the MySQL system.
> All rows are migrated — no TRANSTYP filter is applied.

---

## 5. Client Returns

|                       | Detail                                                                     |
| --------------------- | -------------------------------------------------------------------------- |
| **MSSQL Source**      | `CHABRINS.dbo.CHRHMS` (35,217 rows)                                        |
| **PostgreSQL Target** | `chips_db_v3.sys_pm_clients_returns`                                       |
| **Migration View**    | `vw_sys_pm_clients_returns` (view #20 in `chabrins_to_postgres_views.sql`) |

### How it maps

`CHRHMS` is the returns master table — each row is a monthly landlord return for one property.
Previous version used `CHCCRST` (only 4 columns, no financial data, no FKs) — now replaced.

| CHRHMS Column | sys_pm_clients_returns Column  | Notes                                                             |
| ------------- | ------------------------------ | ----------------------------------------------------------------- |
| RETURNID      | ref_number                     | Return reference (e.g. R1202601LAN-00242113F)                     |
| YEARR, MONTHH | date_time                      | `DATEFROMPARTS(YEARR, MONTHH, 1)` with NULL guard                 |
| PREPDAT       | return_date / invoice_date     | datetime — when return was prepared                               |
| RETDESC       | narration                      | Return description                                                |
| LORDID        | client_id                      | Landlord ID → CHLRDS ROW_NUMBER (same pattern)                    |
| BLOCKID       | property_id                    | Block ID → CHBLOCKS ROW_NUMBER                                    |
| RETZON        | zone_id                        | BLOC letter (A-G) → same DISTINCT-then-ROW_NUMBER subquery        |
| TOTALCR       | trans_amount                   | Total credits collected for the period                            |
| NETAMT        | payable_amount                 | Net payable to landlord after commission                          |
| SUMCOM        | comm_amount                    | Total commission charged                                          |
| SUMVATBL      | commissionable_amount          | Vatable/commissionable amount                                     |
| COMMRATE      | comm_perc                      | Commission rate %                                                 |
| NETBAL        | invoice_balance                | Remaining unpaid balance                                          |
| AMTPAYD       | paid_out                       | Amount actually paid to landlord                                  |
| RETSTAT       | current_status_id              | 1 = processed, 0/NULL = pending                                   |
| PAIDST        | paid_status_id                 | 1 = paid, 0 = unpaid                                              |
| APPST         | approval_status                | Approval stage                                                    |
| VOUCHERNO     | batch_id                       | Payment voucher reference                                         |
| RETTYPE       | trans_type_id / return_type_id | 'MAINRETURN' (79.7%), 'MASTERRETURN' (14.1%), 'MINIRETURN' (6.3%) |
| PAYDAT        | date_paid_out                  | varchar(30) — `TRY_CAST` handles malformed/empty values           |

> **Note:** CHRHMS is property-level — `unit_id`, `tenant_id`, `lease_id` are NULL.
> These are landlord-facing financial returns, not tenant charge lines.

> **⚠ Volume note:** CHRHMS has 35,217 rows vs 288,196 in MySQL `sys_pm_clients_returns`.
> The MySQL system has grown significantly post-migration from CHABRINS.
> CHABRINS data (35K) represents the historical backlog to migrate.

---

## 6. Summary — which views now resolve which FKs

| View                              | client_id     | property_id     | unit_id         | tenant_id     | zone_id           | zone_area_id       | sla_id |
| --------------------------------- | ------------- | --------------- | --------------- | ------------- | ----------------- | ------------------ | ------ |
| `vw_sys_pm_clients_landlords_002` | ✅ self       | —               | —               | —             | —                 | —                  | —      |
| `vw_sys_pm_clients_tenants_004`   | ✅ (TENANTID) | ✅ via CHBLOCKS | ✅ via CHBUNITS | —             | —                 | —                  | —      |
| `vw_sys_pm_properties_001`        | ✅ via CHLRDS | —               | —               | —             | ✅ CHBLOCKS.BLOC  | ✅ CHBLOCKS.BLOCAL | NULL   |
| `vw_sys_pm_units_003`             | ✅ via CHLRDS | ✅ via CHBLOCKS | —               | —             | —                 | —                  | —      |
| `vw_sys_pm_lease_agreements_005`  | ✅ via CHLRDS | ✅ via CHBLOCKS | ✅ via CHBUNITS | ✅ via CHTENS | ✅ CHBLOCKS.BLOC  | —                  | NULL   |
| `vw_sys_set_zones`                | —             | —               | —               | —             | ✅ self           | —                  | —      |
| `vw_sys_set_zone_areas`           | —             | —               | —               | —             | ✅ via zones view | ✅ self            | —      |
| `vw_sys_pm_debtors_invoices`      | ✅ via CHLRDS | ✅ via CHBLOCKS | ✅ via CHBUNITS | ✅ via CHTENS | ✅ CHBLOCKS.BLOC  | —                  | NULL   |
| `vw_sys_pm_clients_returns`       | ✅ via CHLRDS | ✅ via CHBLOCKS | NULL (prop-lvl) | NULL (prop)   | ✅ CHRHMS.RETZON  | —                  | NULL   |

---

## 7. Load order

The following load order must be respected for FK integrity:

```
1.  vw_sys_set_banks                        → sys_set_banks
2.  vw_sys_set_banks_branches               → sys_set_banks_branches
3.  vw_sys_gl_chart_accounts_master         → sys_gl_chart_accounts_master
4.  vw_sys_set_invoiceable_items            → sys_set_invoiceable_items
5.  vw_sys_set_clients_dependent_charges    → sys_set_clients_dependent_charges
6.  vw_sys_set_zones_006                    → sys_set_zones
7.  vw_sys_set_zone_areas_007               → sys_set_zone_areas
8.  vw_sys_pm_clients_landlords_002         → sys_pm_clients  (landlords)
9.  vw_sys_pm_clients_tenants_004           → sys_pm_clients  (tenants, appended)
10. vw_sys_pm_properties_001                → sys_pm_properties
11. vw_sys_pm_units_003                     → sys_pm_units
12. vw_sys_pm_lease_agreements_005          → sys_pm_lease_agreements
    (+ manual POST step: assign sla_id after copying sys_pm_service_agreements)
13. vw_sys_pm_debtors_invoices              → sys_pm_debtors_invoices
14. vw_sys_pm_clients_returns               → sys_pm_clients_returns
```
