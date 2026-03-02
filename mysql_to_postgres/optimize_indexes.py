"""
PostgreSQL Index Optimizer for chips_db_v3
==========================================
Cleans up unnecessary indexes and adds optimized composite indexes
for key business tables (clients, invoices, tenants, returns, payments, transactions).

Actions:
  1. Drop redundant indexes already covered by a UNIQUE constraint
  2. Drop ALL non-PK indexes on josya_*, old_*, zmigra_*, zimport_* tables (non-business)
  3. Drop low-value single-column indexes (narration, remarks, zone_manager, zone_supervisor, etc.)
  4. Keep only parent_id index on _repeat subtables, drop the rest
  5. Add optimized composite indexes on key business tables
"""

import psycopg2
import logging
import sys

# ── Config ──────────────────────────────────────────────
PG_CONFIG = {
    'host': '127.0.0.1',
    'port': 5432,
    'database': 'chips_db_v3',
    'user': 'postgres',
    'password': 'k3nn3th',
}
PG_SCHEMA = 'chips_db_v3'

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s  %(levelname)-8s  %(message)s',
    handlers=[
        logging.FileHandler('optimize_indexes.log', encoding='utf-8'),
        logging.StreamHandler(sys.stdout),
    ],
)
log = logging.getLogger(__name__)


def connect():
    conn = psycopg2.connect(**PG_CONFIG)
    conn.autocommit = True
    return conn


def get_all_indexes(cur):
    """Return all non-PK indexes in the schema."""
    cur.execute("""
        SELECT tablename, indexname, indexdef
        FROM pg_indexes
        WHERE schemaname = %s AND indexname NOT LIKE '%%_pkey'
        ORDER BY tablename, indexname
    """, (PG_SCHEMA,))
    return cur.fetchall()


def drop_index(cur, index_name, reason):
    """Drop an index and log the reason."""
    try:
        cur.execute(f'DROP INDEX IF EXISTS "{PG_SCHEMA}"."{index_name}"')
        log.info(f'  DROPPED {index_name}  ({reason})')
        return True
    except Exception as e:
        log.warning(f'  FAILED  {index_name}: {e}')
        return False


def find_redundant_covered_by_unique(cur):
    """Find regular indexes that duplicate a UNIQUE index on the same columns."""
    cur.execute("""
        WITH idx_cols AS (
            SELECT tablename, indexname,
                   CASE WHEN indexdef LIKE '%%UNIQUE%%' THEN true ELSE false END as is_unique,
                   (regexp_match(indexdef, 'btree \\((.+)\\)'))[1] as columns
            FROM pg_indexes
            WHERE schemaname = %s
        )
        SELECT a.indexname
        FROM idx_cols a
        JOIN idx_cols b ON a.tablename = b.tablename
                       AND a.columns = b.columns
                       AND a.indexname <> b.indexname
        WHERE a.is_unique = false AND b.is_unique = true
        ORDER BY a.indexname
    """, (PG_SCHEMA,))
    return [r[0] for r in cur.fetchall()]


def find_non_business_indexes(all_indexes):
    """Indexes on josya_*, old_*, zmigra_*, zimport_* tables."""
    prefixes = ('josya_', 'old_', 'zmigra_', 'zimport_')
    return [idx for tbl, idx, _ in all_indexes if tbl.startswith(prefixes)]


def find_low_value_indexes(all_indexes):
    """Single-column indexes on low-cardinality / text columns that add little query value."""
    low_value_columns = {
        'narration', 'remarks', 'remarks_id', 'is_arrear_id',
        'debtor_type_id', 'amount_type', 'channel_id',
    }
    low_value_suffixes = ('_manager_id', '_supervisor_id')

    results = []
    for tbl, idx, indexdef in all_indexes:
        # Skip non-business tables (handled separately)
        if tbl.startswith(('josya_', 'old_', 'zmigra_', 'zimport_')):
            continue
        if '_repeat' in tbl:
            continue

        # Extract column(s) from btree (...)
        import re
        m = re.search(r'btree \((.+)\)', indexdef)
        if not m:
            continue
        col = m.group(1).strip().strip('"')

        # Only single-column indexes
        if ',' in col:
            continue

        if col in low_value_columns:
            results.append(idx)
        elif any(col.endswith(s) for s in low_value_suffixes):
            results.append(idx)

    return results


def find_repeat_excess_indexes(all_indexes):
    """On _repeat subtables, keep parent_id index, drop everything else."""
    drop = []
    for tbl, idx, indexdef in all_indexes:
        if '_repeat' not in tbl:
            continue
        import re
        m = re.search(r'btree \((.+)\)', indexdef)
        if not m:
            continue
        col = m.group(1).strip().strip('"')
        # Keep parent_id (FK back to parent table) — drop others
        if col != 'parent_id':
            drop.append(idx)
    return drop


# ── Composite indexes for key business tables ───────────

COMPOSITE_INDEXES = [
    # ── Tenants Invoices: common queries filter by tenant+property or client+date
    {
        'name': 'idx_tenants_invoices_tenant_property',
        'table': 'sys_pm_tenants_invoices',
        'columns': 'tenant_id, property_id',
    },
    {
        'name': 'idx_tenants_invoices_client_unit',
        'table': 'sys_pm_tenants_invoices',
        'columns': 'client_id, unit_id',
    },

    # ── Clients Invoices: client+property, tenant+date_due
    {
        'name': 'idx_clients_invoices_client_property',
        'table': 'sys_pm_clients_invoices',
        'columns': 'client_id, property_id',
    },
    {
        'name': 'idx_clients_invoices_tenant_date_due',
        'table': 'sys_pm_clients_invoices',
        'columns': 'tenant_id, date_due',
    },

    # ── Tenants Payments: tenant+payment_date, client+property
    {
        'name': 'idx_tenants_payments_tenant_date',
        'table': 'sys_pm_tenants_payments',
        'columns': 'tenant_id, payment_date',
    },
    {
        'name': 'idx_tenants_payments_client_property',
        'table': 'sys_pm_tenants_payments',
        'columns': 'client_id, property_id',
    },

    # ── Payments-Invoices allocation: payment+invoice (the join table)
    {
        'name': 'idx_payments_invoices_payment_invoice',
        'table': 'sys_pm_tenants_payments_invoices',
        'columns': 'payment_id, invoice_id',
    },
    {
        'name': 'idx_payments_invoices_tenant_lease',
        'table': 'sys_pm_tenants_payments_invoices',
        'columns': 'tenant_id, lease_id',
    },

    # ── Clients Returns: client+property, tenant+return_date
    {
        'name': 'idx_clients_returns_client_property',
        'table': 'sys_pm_clients_returns',
        'columns': 'client_id, property_id',
    },
    {
        'name': 'idx_clients_returns_tenant_return_date',
        'table': 'sys_pm_clients_returns',
        'columns': 'tenant_id, return_date',
    },
    {
        'name': 'idx_clients_returns_status_client',
        'table': 'sys_pm_clients_returns',
        'columns': 'current_status_id, client_id',
    },

    # ── Debtors Invoices: tenant+client, property+date_due
    {
        'name': 'idx_debtors_invoices_tenant_client',
        'table': 'sys_pm_debtors_invoices',
        'columns': 'tenant_id, client_id',
    },
    {
        'name': 'idx_debtors_invoices_property_date_due',
        'table': 'sys_pm_debtors_invoices',
        'columns': 'property_id, date_due',
    },
    {
        'name': 'idx_debtors_invoices_lease_status',
        'table': 'sys_pm_debtors_invoices',
        'columns': 'lease_id, current_status_id',
    },

    # ── Tenant Account Transactions: tenant+date, client+property
    {
        'name': 'idx_tenant_acct_txn_tenant_date',
        'table': 'sys_pm_tenants_accounts_transactions',
        'columns': 'tenant_id, trans_date_time',
    },
    {
        'name': 'idx_tenant_acct_txn_client_property',
        'table': 'sys_pm_tenants_accounts_transactions',
        'columns': 'client_id, property_id',
    },

    # ── Landlord Account Transactions: client+date, tenant+unit
    {
        'name': 'idx_landlord_acct_txn_client_date',
        'table': 'sys_pm_landlord_accounts_transaction',
        'columns': 'client_id, trans_date_time',
    },
    {
        'name': 'idx_landlord_acct_txn_tenant_unit',
        'table': 'sys_pm_landlord_accounts_transaction',
        'columns': 'tenant_id, unit_id',
    },

    # ── Returns Batches: common batch queries
    {
        'name': 'idx_returns_batches_client_status',
        'table': 'sys_pm_clients_returns_batches',
        'columns': 'client_id, current_status_id',
    },

    # ── Lease Agreements: property+client
    {
        'name': 'idx_lease_agreements_property_client',
        'table': 'sys_pm_lease_agreements',
        'columns': 'property_id, client_id',
    },
]


def create_composite_indexes(cur):
    """Create optimized composite indexes on key tables."""
    created = 0
    for idx in COMPOSITE_INDEXES:
        name = idx['name']
        table = idx['table']
        cols = idx['columns']
        sql = f'CREATE INDEX IF NOT EXISTS "{name}" ON "{PG_SCHEMA}"."{table}" ({cols})'
        try:
            cur.execute(sql)
            log.info(f'  CREATED {name} ON {table}({cols})')
            created += 1
        except Exception as e:
            log.warning(f'  FAILED  {name}: {e}')
    return created


def main():
    conn = connect()
    cur = conn.cursor()
    log.info('=' * 60)
    log.info('INDEX OPTIMIZATION STARTED')
    log.info('=' * 60)

    all_indexes = get_all_indexes(cur)
    total_before = len(all_indexes)
    log.info(f'Total non-PK indexes before: {total_before}')

    dropped = 0

    # ── Step 1: Redundant indexes covered by UNIQUE ─────
    log.info('')
    log.info('--- Step 1: Drop indexes redundant with UNIQUE constraints ---')
    redundant = find_redundant_covered_by_unique(cur)
    log.info(f'Found {len(redundant)} redundant indexes')
    for idx_name in redundant:
        if drop_index(cur, idx_name, 'covered by UNIQUE'):
            dropped += 1

    # ── Step 2: Non-business table indexes ──────────────
    log.info('')
    log.info('--- Step 2: Drop indexes on non-business tables (josya_, old_, zmigra_, zimport_) ---')
    non_biz = find_non_business_indexes(all_indexes)
    log.info(f'Found {len(non_biz)} non-business indexes')
    for idx_name in non_biz:
        if drop_index(cur, idx_name, 'non-business table'):
            dropped += 1

    # ── Step 3: Low-value single-column indexes ─────────
    log.info('')
    log.info('--- Step 3: Drop low-value single-column indexes ---')
    low_val = find_low_value_indexes(all_indexes)
    log.info(f'Found {len(low_val)} low-value indexes')
    for idx_name in low_val:
        if drop_index(cur, idx_name, 'low-value column'):
            dropped += 1

    # ── Step 4: Excess repeat-subtable indexes ──────────
    log.info('')
    log.info('--- Step 4: Clean repeat-subtable indexes (keep only parent_id) ---')
    repeat_excess = find_repeat_excess_indexes(all_indexes)
    log.info(f'Found {len(repeat_excess)} excess repeat-table indexes')
    for idx_name in repeat_excess:
        if drop_index(cur, idx_name, 'repeat subtable - non parent_id'):
            dropped += 1

    # ── Step 5: Add composite indexes on key tables ─────
    log.info('')
    log.info('--- Step 5: Create optimized composite indexes on key tables ---')
    created = create_composite_indexes(cur)

    # ── Summary ─────────────────────────────────────────
    cur.execute("""
        SELECT COUNT(*) FROM pg_indexes
        WHERE schemaname = %s AND indexname NOT LIKE '%%_pkey'
    """, (PG_SCHEMA,))
    total_after = cur.fetchone()[0]

    cur.execute("""
        SELECT COUNT(*) FROM pg_indexes WHERE schemaname = %s
    """, (PG_SCHEMA,))
    grand_total = cur.fetchone()[0]

    log.info('')
    log.info('=' * 60)
    log.info('OPTIMIZATION SUMMARY')
    log.info('=' * 60)
    log.info(f'Indexes before (non-PK):  {total_before}')
    log.info(f'Dropped:                  {dropped}')
    log.info(f'Composite added:          {created}')
    log.info(f'Indexes after (non-PK):   {total_after}')
    log.info(f'Grand total (incl PKs):   {grand_total}')
    log.info('=' * 60)

    cur.close()
    conn.close()


if __name__ == '__main__':
    main()
