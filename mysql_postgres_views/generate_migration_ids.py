#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CHABRINS Auto-Increment ID Generator + FK Stamper
===================================================
Adds stable auto-increment-style ID columns to each key CHABRINS source table
and populates them with deterministic sequential IDs matching the exact
ROW_NUMBER() ORDER BY / WHERE used in the migration views (_001 to _10).

Then stamps FK columns on all tables that have text-based JOINs in the
migration views, so every relationship uses pre-computed integer IDs
instead of fragile text matching.

Why:
  The current views use ROW_NUMBER() OVER (ORDER BY ...) for both the row's
  own id AND for FK lookups (inline subqueries). This is fragile:
  - Tied ORDER BY values get arbitrary numbering across runs
  - Self-joins recompute ROW_NUMBER independently -- no guarantee of match
  - Any INSERT/DELETE in source tables shifts ALL downstream ids

Solution:
  1. Stamp each row ONCE with a permanent primary ID.
  2. Stamp FK columns by resolving text-based matches ONCE and writing
     the integer FK directly on the source table.
  Views and FK lookups then just read the columns -- no recomputation,
  100% deterministic.

Usage:
  python generate_migration_ids.py
  (Requires pyodbc + SQL Server ODBC driver -- Windows Auth to localhost\\SQL2017)

Tables modified -- Primary IDs (views _001 to _10 + FK lookup tables):
  CHLRDS      -> client_id      (ORDER BY LORDID)                   -- _002 clients/landlords
  CHBLOCKS    -> property_id    (ORDER BY BLOCKID)                   -- _001 properties
  CHBUNITS    -> unit_id         (ORDER BY BLOCKID, UNITNO)           -- _003 units
  CHTENS      -> tenant_id       (ORDER BY TENNAME, BLOCKNO, UNITNO)  -- _004 tenants / _005 leases
  CHCHRS      -> item_id         (ORDER BY CHRGID)                    -- _008 invoiceable items
  CHTNTRS     -> invoice_id      (ORDER BY BATCHID, ENTRYNO)          -- _009 debtors invoices
  CHRTXS      -> return_line_id  (ORDER BY RETURNID, TRANSNUM)        -- _10  client returns
  CHOFFICERS  -> officer_id      (ORDER BY OFFID)                     -- FK: field officers
  CHSTFF      -> staff_id        (ORDER BY EMPNUMB)                   -- FK: staff
  CHBANK      -> bank_id         (ORDER BY BANKCODE)                  -- FK: banks
  CHFACTV     -> account_id      (ORDER BY ACCNUM)                    -- FK: chart of accounts
  CHBCTH      -> voucher_id      (ORDER BY BTCHID)                    -- FK: payment voucher headers
  CHCHQ       -> cheque_id       (ORDER BY CHQNO)                     -- FK: cheque books
  CHRHMS      -> return_id       (ORDER BY RETURNID)                  -- FK: returns headers

Lookup tables created:
  _mig_zones       (zone_id, bloc)                -- from DISTINCT CHBLOCKS.BLOC
  _mig_zone_areas  (zone_area_id, blocal, bloc)   -- from DISTINCT CHBLOCKS.BLOCAL

FK columns stamped (39 operations across 20 tables):
  CHBLOCKS    : client_id, field_officer_id, zone_supervisor_id, zone_manager_id
  CHBUNITS    : property_id, client_id
  CHTENS      : property_id, unit_id
  CHBBRA      : bank_id
  CHCHQ       : bank_id
  CHCDC       : item_id
  CHBCTH      : staff_id
  CHJVH       : staff_id
  CHJVHP      : staff_id
  CHTNTRS     : property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id
  CHRTXS      : property_id, client_id, zone_id, field_officer_id,
                zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
                return_id, invoice_id
  CHDPAY      : property_id, client_id, unit_id, tenant_id
  CHDPS       : property_id, client_id, unit_id, tenant_id
  CHIPAYS     : property_id, client_id, unit_id, tenant_id
  CHBCTD      : voucher_id, property_id
  CHCHD       : voucher_id, cheque_id
  CHJVD       : account_id
  CHGLAC      : property_id, unit_id, tenant_id, account_id
  CHCBDE      : bank_id
  CHLACC      : client_id, property_id
"""

import pyodbc
import sys
import time

# ===============================================================================
# CONFIG
# ===============================================================================
MSSQL_SERVER   = r'localhost\SQL2017'
MSSQL_DATABASE = 'CHABRINS'
MSSQL_DRIVER   = '{SQL Server}'  # or '{ODBC Driver 17 for SQL Server}'

# ===============================================================================
# TABLE DEFINITIONS
# ===============================================================================
# Each entry: table, id_col (column name to create), order_by, where, description
# order_by: columns for ROW_NUMBER() OVER (ORDER BY ...)
# where:    exact WHERE filter from the migration view (non-matching rows get NULL id)

TABLES = [
    # -- Core entity tables (views _001 to _10) --------------------------------
    {
        'table':    'CHLRDS',
        'id_col':   'client_id',
        'order_by': 'LORDID',
        'where':    "LORDID IS NOT NULL AND RTRIM(LTRIM(LORDID)) <> ''",
        'desc':     '_002 -- sys_pm_clients (landlords)',
    },
    {
        'table':    'CHBLOCKS',
        'id_col':   'property_id',
        'order_by': 'BLOCKID',
        'where':    'BLOCKID IS NOT NULL',
        'desc':     '_001 -- sys_pm_properties',
    },
    {
        'table':    'CHBUNITS',
        'id_col':   'unit_id',
        'order_by': 'BLOCKID, UNITNO',
        'where':    'UNITNO IS NOT NULL',
        'desc':     '_003 -- sys_pm_units',
    },
    {
        'table':    'CHTENS',
        'id_col':   'tenant_id',
        'order_by': 'TENNAME, BLOCKNO, UNITNO',
        'where':    "TENNAME IS NOT NULL AND RTRIM(LTRIM(TENNAME)) <> ''",
        'desc':     '_004 -- sys_pm_clients (tenants) / _005 -- lease agreements',
    },
    {
        'table':    'CHCHRS',
        'id_col':   'item_id',
        'order_by': 'CHRGID',
        'where':    'CHRGID IS NOT NULL',
        'desc':     '_008 -- sys_set_invoiceable_items',
    },
    {
        'table':    'CHTNTRS',
        'id_col':   'invoice_id',
        'order_by': 'BATCHID, ENTRYNO',
        'where':    'TENNAME IS NOT NULL AND BLOCKID IS NOT NULL',
        'desc':     '_009 -- sys_pm_debtors_invoices',
    },
    {
        'table':    'CHRTXS',
        'id_col':   'return_line_id',
        'order_by': 'RETURNID, TRANSNUM',
        'where':    'RETURNID IS NOT NULL',
        'desc':     '_10  -- sys_pm_clients_returns',
    },

    # -- FK reference/lookup tables ---------------------------------------------
    {
        'table':    'CHOFFICERS',
        'id_col':   'officer_id',
        'order_by': 'OFFID',
        'where':    "OFFID IS NOT NULL AND RTRIM(LTRIM(OFFID)) <> ''",
        'desc':     'FK -- field officers / zone supervisors / zone managers',
    },
    {
        'table':    'CHSTFF',
        'id_col':   'staff_id',
        'order_by': 'EMPNUMB',
        'where':    "EMPNUMB IS NOT NULL AND RTRIM(LTRIM(EMPNUMB)) <> ''",
        'desc':     'FK -- staff (entered_by)',
    },
    {
        'table':    'CHBANK',
        'id_col':   'bank_id',
        'order_by': 'BANKCODE',
        'where':    "BANKNAME IS NOT NULL AND BANKNAME <> ''",
        'desc':     'FK -- banks',
    },
    {
        'table':    'CHFACTV',
        'id_col':   'account_id',
        'order_by': 'ACCNUM',
        'where':    "ACCNUM IS NOT NULL AND ACCNUM <> ''",
        'desc':     'FK -- chart of accounts',
    },
    {
        'table':    'CHBCTH',
        'id_col':   'voucher_id',
        'order_by': 'BTCHID',
        'where':    'BTCHID IS NOT NULL',
        'desc':     'FK -- payment voucher headers',
    },
    {
        'table':    'CHCHQ',
        'id_col':   'cheque_id',
        'order_by': 'CHQNO',
        'where':    'CHQNO IS NOT NULL',
        'desc':     'FK -- cheque books',
    },
    {
        'table':    'CHRHMS',
        'id_col':   'return_id',
        'order_by': 'RETURNID',
        'where':    'RETURNID IS NOT NULL',
        'desc':     'FK -- returns master/headers',
    },
]


# ===============================================================================
# HELPERS
# ===============================================================================
def connect():
    """Connect to MSSQL CHABRINS via Windows Authentication."""
    conn_str = (
        f'DRIVER={MSSQL_DRIVER};'
        f'SERVER={MSSQL_SERVER};'
        f'DATABASE={MSSQL_DATABASE};'
        f'Trusted_Connection=yes;'
    )
    conn = pyodbc.connect(conn_str, autocommit=False)
    return conn


def col_exists(cursor, table, column):
    """Check if a column already exists on the table."""
    cursor.execute("""
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo'
          AND TABLE_NAME   = ?
          AND COLUMN_NAME  = ?
    """, (table, column))
    return cursor.fetchone() is not None


def index_exists(cursor, index_name):
    """Check if an index already exists."""
    cursor.execute("""
        SELECT 1 FROM sys.indexes WHERE name = ?
    """, (index_name,))
    return cursor.fetchone() is not None


def table_exists(cursor, table):
    """Check if a table exists."""
    cursor.execute("""
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?
    """, (table,))
    return cursor.fetchone() is not None


def get_row_count(cursor, table, where_clause=None):
    """Get row count with optional WHERE."""
    sql = f"SELECT COUNT(*) FROM [{table}]"
    if where_clause:
        sql += f" WHERE {where_clause}"
    cursor.execute(sql)
    return cursor.fetchone()[0]


def fmt_elapsed(start):
    """Format elapsed time."""
    elapsed = time.time() - start
    if elapsed < 60:
        return f"{elapsed:.1f}s"
    return f"{elapsed / 60:.1f}m"


# ===============================================================================
# MAIN LOGIC
# ===============================================================================
def add_id_column(conn, entry):
    """
    Add an ID column to a table and populate it with deterministic
    sequential IDs using the exact ROW_NUMBER ORDER BY from the migration view.
    """
    table     = entry['table']
    col_name  = entry['id_col']
    order_by  = entry['order_by']
    where_cls = entry['where']
    desc      = entry['desc']

    cur = conn.cursor()

    # -- 1. Add column if it doesn't exist -------------------------------------
    if col_exists(cur, table, col_name):
        print(f"  [skip] Column {col_name} already exists on {table}")
    else:
        cur.execute(f"ALTER TABLE [{table}] ADD [{col_name}] INT NULL")
        conn.commit()
        print(f"  [add]  Column {col_name} added to {table}")

    # -- 2. Reset all IDs to NULL (idempotent re-run) --------------------------
    cur.execute(f"UPDATE [{table}] SET [{col_name}] = NULL")
    conn.commit()

    # -- 3. Populate ID using CTE + ROW_NUMBER ---------------------------------
    #    The CTE selects only rows matching the view's WHERE filter;
    #    rows not matching keep the id = NULL.
    #    Uses an updatable CTE -- SQL Server supports this natively.
    #    Single pass, no temp table needed.
    sql = f"""
        ;WITH numbered AS (
            SELECT [{col_name}],
                   ROW_NUMBER() OVER (ORDER BY {order_by}) AS rn
            FROM [{table}]
            WHERE {where_cls}
        )
        UPDATE numbered SET [{col_name}] = rn
    """
    t0 = time.time()
    cur.execute(sql)
    rows_updated = cur.rowcount
    conn.commit()
    print(f"  [fill] {rows_updated:>10,} rows numbered in {fmt_elapsed(t0)}  ({desc})")

    # -- 4. Create index for fast FK lookups -----------------------------------
    idx_name = f"IX_{table}_{col_name}"
    if index_exists(cur, idx_name):
        print(f"  [skip] Index {idx_name} already exists")
    else:
        cur.execute(f"""
            CREATE NONCLUSTERED INDEX [{idx_name}]
            ON [{table}] ([{col_name}])
            WHERE [{col_name}] IS NOT NULL
        """)
        conn.commit()
        print(f"  [idx]  Index {idx_name} created")

    # -- 5. Verification: check max id = count of qualifying rows --------------
    total_in_table = get_row_count(cur, table)
    qualifying     = get_row_count(cur, table, where_cls)
    cur.execute(f"SELECT MAX([{col_name}]) FROM [{table}]")
    max_id = cur.fetchone()[0] or 0
    cur.execute(f"SELECT COUNT(*) FROM [{table}] WHERE [{col_name}] IS NOT NULL")
    numbered_count = cur.fetchone()[0]

    if max_id != qualifying or numbered_count != qualifying:
        print(f"  [WARN] Mismatch! max_id={max_id}, qualifying={qualifying}, numbered={numbered_count}")
    else:
        null_rows = total_in_table - qualifying
        extra = f" ({null_rows:,} rows NULL -- filtered out)" if null_rows > 0 else ""
        print(f"  [ok]   Verified: max {col_name} = {max_id:,} == qualifying rows{extra}")

    cur.close()


def create_zone_lookups(conn):
    """
    Create _mig_zones and _mig_zone_areas reference tables.
    Zones and zone_areas are derived from DISTINCT values in CHBLOCKS --
    they don't have their own source tables, so we materialise them.
    """
    cur = conn.cursor()

    # -- _mig_zones ------------------------------------------------------------
    tbl = '_mig_zones'
    print(f"\n{'=' * 70}")
    print(f"  Creating lookup table: {tbl}")
    if table_exists(cur, tbl):
        cur.execute(f"DROP TABLE [{tbl}]")
        conn.commit()
        print(f"  [drop] Old {tbl} dropped")

    cur.execute(f"""
        SELECT
            ROW_NUMBER() OVER (ORDER BY BLOC) AS zone_id,
            BLOC AS bloc
        INTO [{tbl}]
        FROM (
            SELECT DISTINCT RTRIM(LTRIM(BLOC)) AS BLOC
            FROM CHBLOCKS
            WHERE BLOC IS NOT NULL AND RTRIM(LTRIM(BLOC)) <> ''
        ) z
    """)
    conn.commit()
    cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
    cnt = cur.fetchone()[0]
    print(f"  [fill] {cnt} zones materialised")

    # Add PK
    cur.execute(f"""
        ALTER TABLE [{tbl}] ALTER COLUMN zone_id INT NOT NULL
    """)
    cur.execute(f"""
        ALTER TABLE [{tbl}] ADD CONSTRAINT PK_{tbl} PRIMARY KEY (zone_id)
    """)
    conn.commit()
    print(f"  [pk]   Primary key added")

    # Stamp CHBLOCKS with zone_id for direct lookup
    zone_col = 'zone_id'
    if not col_exists(cur, 'CHBLOCKS', zone_col):
        cur.execute(f"ALTER TABLE [CHBLOCKS] ADD [{zone_col}] INT NULL")
        conn.commit()
        print(f"  [add]  Column {zone_col} added to CHBLOCKS")
    cur.execute(f"""
        UPDATE b SET b.[{zone_col}] = z.zone_id
        FROM CHBLOCKS b
        INNER JOIN [{tbl}] z ON z.bloc = RTRIM(LTRIM(b.BLOC))
    """)
    conn.commit()
    print(f"  [fill] CHBLOCKS.{zone_col} populated via {tbl}")

    # -- _mig_zone_areas -------------------------------------------------------
    tbl = '_mig_zone_areas'
    print(f"\n{'=' * 70}")
    print(f"  Creating lookup table: {tbl}")
    if table_exists(cur, tbl):
        cur.execute(f"DROP TABLE [{tbl}]")
        conn.commit()
        print(f"  [drop] Old {tbl} dropped")

    cur.execute(f"""
        SELECT
            ROW_NUMBER() OVER (ORDER BY BLOCAL) AS zone_area_id,
            BLOCAL AS blocal,
            BLOC   AS bloc
        INTO [{tbl}]
        FROM (
            SELECT DISTINCT
                RTRIM(LTRIM(BLOCAL)) AS BLOCAL,
                RTRIM(LTRIM(BLOC))   AS BLOC
            FROM CHBLOCKS
            WHERE BLOCAL IS NOT NULL AND RTRIM(LTRIM(BLOCAL)) <> ''
        ) za
    """)
    conn.commit()
    cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
    cnt = cur.fetchone()[0]
    print(f"  [fill] {cnt} zone areas materialised")

    cur.execute(f"""
        ALTER TABLE [{tbl}] ALTER COLUMN zone_area_id INT NOT NULL
    """)
    cur.execute(f"""
        ALTER TABLE [{tbl}] ADD CONSTRAINT PK_{tbl} PRIMARY KEY (zone_area_id)
    """)
    conn.commit()
    print(f"  [pk]   Primary key added")

    # Stamp CHBLOCKS with zone_area_id
    za_col = 'zone_area_id'
    if not col_exists(cur, 'CHBLOCKS', za_col):
        cur.execute(f"ALTER TABLE [CHBLOCKS] ADD [{za_col}] INT NULL")
        conn.commit()
        print(f"  [add]  Column {za_col} added to CHBLOCKS")
    cur.execute(f"""
        UPDATE b SET b.[{za_col}] = za.zone_area_id
        FROM CHBLOCKS b
        INNER JOIN [{tbl}] za ON za.blocal = RTRIM(LTRIM(b.BLOCAL))
    """)
    conn.commit()
    print(f"  [fill] CHBLOCKS.{za_col} populated via {tbl}")

    cur.close()


def print_summary(conn):
    """Print a verification summary of all ID columns."""
    cur = conn.cursor()
    print(f"\n{'=' * 78}")
    print(f"  VERIFICATION SUMMARY")
    print(f"{'=' * 78}")
    print(f"  {'Table':<16} {'Column':<16} {'Total Rows':>12} {'With ID':>12} {'NULL':>12} {'Max ID':>12}")
    print(f"  {'-' * 16} {'-' * 16} {'-' * 12} {'-' * 12} {'-' * 12} {'-' * 12}")

    for entry in TABLES:
        table  = entry['table']
        id_col = entry['id_col']
        try:
            cur.execute(f"SELECT COUNT(*) FROM [{table}]")
            total = cur.fetchone()[0]
            cur.execute(f"SELECT COUNT(*) FROM [{table}] WHERE [{id_col}] IS NOT NULL")
            with_id = cur.fetchone()[0]
            cur.execute(f"SELECT MAX([{id_col}]) FROM [{table}]")
            max_id = cur.fetchone()[0] or 0
            null_rows = total - with_id
            print(f"  {table:<16} {id_col:<16} {total:>12,} {with_id:>12,} {null_rows:>12,} {max_id:>12,}")
        except Exception as e:
            print(f"  {table:<16} {id_col:<16} ERROR: {e}")

    # Zone lookups
    for tbl, col in [('_mig_zones', 'zone_id'), ('_mig_zone_areas', 'zone_area_id')]:
        try:
            cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
            cnt = cur.fetchone()[0]
            print(f"  {tbl:<16} {col:<16} {cnt:>12,} {cnt:>12,} {'0':>12} {cnt:>12,}")
        except Exception as e:
            print(f"  {tbl:<16} {col:<16} ERROR: {e}")

    # CHBLOCKS extra columns
    try:
        cur.execute("SELECT COUNT(*) FROM CHBLOCKS WHERE zone_id IS NOT NULL")
        zc = cur.fetchone()[0]
        cur.execute("SELECT COUNT(*) FROM CHBLOCKS WHERE zone_area_id IS NOT NULL")
        zac = cur.fetchone()[0]
        total = get_row_count(cur, 'CHBLOCKS')
        print(f"\n  CHBLOCKS extras:")
        print(f"    zone_id      : {zc:>10,} / {total:,} rows populated")
        print(f"    zone_area_id : {zac:>10,} / {total:,} rows populated")
    except Exception:
        pass

    cur.close()


# ===============================================================================
# FK COLUMN STAMPING
# ===============================================================================
# After primary IDs are assigned, stamp FK columns on every source table so
# the migration views can use integer lookups instead of text-based JOINs.
#
# Executed in dependency order:
#   Phase 1 — CHBLOCKS hub (client_id, officers) — must go first so
#             transaction tables can chain through CHBLOCKS.
#   Phase 2 — Intermediate tables (CHBUNITS, CHTENS, small refs).
#   Phase 3 — Large transaction tables (CHTNTRS, CHRTXS, CHDPAY, etc.)
#
# FK relationships resolved (39 stamp operations across 18 tables):
#   CHBLOCKS    : client_id, field_officer_id, zone_supervisor_id, zone_manager_id
#   CHBUNITS    : property_id, client_id
#   CHTENS      : property_id, unit_id
#   CHBBRA      : bank_id
#   CHCHQ       : bank_id
#   CHCDC       : item_id
#   CHBCTH      : staff_id
#   CHJVH       : staff_id
#   CHJVHP      : staff_id
#   CHTNTRS     : property_id, client_id, zone_id, field_officer_id,
#                 zone_supervisor_id, zone_manager_id, unit_id, tenant_id
#   CHRTXS      : property_id, client_id, zone_id, field_officer_id,
#                 zone_supervisor_id, zone_manager_id, unit_id, tenant_id,
#                 return_id, invoice_id
#   CHDPAY      : property_id, client_id, unit_id, tenant_id
#   CHDPS       : property_id, client_id, unit_id, tenant_id
#   CHIPAYS     : property_id, client_id, unit_id, tenant_id
#   CHBCTD      : voucher_id, property_id
#   CHCHD       : voucher_id, cheque_id
#   CHJVD       : account_id
#   CHGLAC      : property_id, unit_id, tenant_id, account_id
#   CHCBDE      : bank_id
#   CHLACC      : client_id, property_id
# ===============================================================================

def stamp_all_fk_columns(conn):
    """
    Stamp FK columns on all source tables, replacing text-based JOINs
    with pre-computed integer IDs.

    Runs AFTER primary IDs (TABLES) and zone lookups are populated.
    """
    cur = conn.cursor()
    total_ops = 0
    total_err = 0

    def _ensure_col(tbl, col):
        if not col_exists(cur, tbl, col):
            cur.execute(f"ALTER TABLE [{tbl}] ADD [{col}] INT NULL")
            conn.commit()

    def _ensure_idx(tbl, col):
        idx = f"IX_{tbl}_{col}"
        if not index_exists(cur, idx):
            try:
                cur.execute(f"""
                    CREATE NONCLUSTERED INDEX [{idx}]
                    ON [{tbl}] ([{col}]) WHERE [{col}] IS NOT NULL
                """)
                conn.commit()
            except Exception:
                pass

    def _stamp(tbl, cols, sql, desc):
        nonlocal total_ops, total_err
        cols_str = ', '.join(cols)
        print(f"\n  {tbl}.{cols_str}  --  {desc}")
        try:
            for c in cols:
                _ensure_col(tbl, c)
            set_null = ', '.join(f'[{c}] = NULL' for c in cols)
            cur.execute(f"UPDATE [{tbl}] SET {set_null}")
            conn.commit()
            t0 = time.time()
            cur.execute(sql)
            rows = cur.rowcount
            conn.commit()
            print(f"    {rows:>10,} rows stamped  ({fmt_elapsed(t0)})")
            for c in cols:
                _ensure_idx(tbl, c)
            total_ops += 1
        except Exception as e:
            print(f"    ERROR: {e}")
            total_err += 1
            try:
                conn.rollback()
            except Exception:
                pass

    # -- Helpers for common patterns -------------------------------------------

    def _stamp_block_chain(tbl, block_col, with_officers=False):
        """Batch-stamp property_id, client_id (+ zone/officers) via CHBLOCKS."""
        if with_officers:
            cols = ['property_id', 'client_id', 'zone_id',
                    'field_officer_id', 'zone_supervisor_id', 'zone_manager_id']
            set_clause = (
                "t.[property_id]         = blk.[property_id],\n"
                "                t.[client_id]           = blk.[client_id],\n"
                "                t.[zone_id]             = blk.[zone_id],\n"
                "                t.[field_officer_id]    = blk.[field_officer_id],\n"
                "                t.[zone_supervisor_id]  = blk.[zone_supervisor_id],\n"
                "                t.[zone_manager_id]     = blk.[zone_manager_id]"
            )
        else:
            cols = ['property_id', 'client_id']
            set_clause = (
                "t.[property_id] = blk.[property_id],\n"
                "                t.[client_id]   = blk.[client_id]"
            )
        sql = f"""
            UPDATE t SET {set_clause}
            FROM [{tbl}] t
            INNER JOIN [CHBLOCKS] blk
              ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.[{block_col}]))
        """
        _stamp(tbl, cols, sql, f'{block_col} -> CHBLOCKS chain')

    def _stamp_unit(tbl, block_col, unit_col='UNITNO'):
        """Stamp unit_id via CHBUNITS BLOCKID+UNITNO match."""
        sql = f"""
            UPDATE t SET t.[unit_id] = u.[unit_id]
            FROM [{tbl}] t
            INNER JOIN [CHBUNITS] u
              ON RTRIM(LTRIM(u.BLOCKID)) = RTRIM(LTRIM(t.[{block_col}]))
             AND RTRIM(LTRIM(u.UNITNO))  = RTRIM(LTRIM(t.[{unit_col}]))
            WHERE u.[unit_id] IS NOT NULL
        """
        _stamp(tbl, ['unit_id'], sql, f'{block_col}+{unit_col} -> CHBUNITS')

    def _stamp_tenant(tbl, name_col, block_col, unit_col='UNITNO'):
        """Stamp tenant_id via CHTENS TENNAME+BLOCKNO+UNITNO match."""
        sql = f"""
            UPDATE t SET t.[tenant_id] = ten.[tenant_id]
            FROM [{tbl}] t
            INNER JOIN [CHTENS] ten
              ON ten.TENNAME = t.[{name_col}]
             AND RTRIM(LTRIM(ten.BLOCKNO)) = RTRIM(LTRIM(t.[{block_col}]))
             AND RTRIM(LTRIM(ten.UNITNO))  = RTRIM(LTRIM(t.[{unit_col}]))
            WHERE ten.[tenant_id] IS NOT NULL
        """
        _stamp(tbl, ['tenant_id'], sql, f'{name_col}+{block_col}+{unit_col} -> CHTENS')

    # ==========================================================================
    # PHASE 1 — CHBLOCKS (central property hub)
    # ==========================================================================
    print(f"\n{'=' * 70}")
    print(f"  FK STAMPING -- Converting text JOINs to integer IDs")
    print(f"{'=' * 70}")
    print(f"\n  --- Phase 1: CHBLOCKS (hub) ---")

    # client_id <- CHLRDS via LLORDID -> LORDID (with LAN- prefix fallback)
    _stamp('CHBLOCKS', ['client_id'], """
        UPDATE t SET t.[client_id] = ref.[client_id]
        FROM [CHBLOCKS] t
        INNER JOIN [CHLRDS] ref
          ON RTRIM(LTRIM(ref.LORDID)) = RTRIM(LTRIM(t.LLORDID))
          OR RTRIM(LTRIM(ref.LORDID)) = REPLACE(RTRIM(LTRIM(t.LLORDID)), 'LAN-', '')
        WHERE ref.[client_id] IS NOT NULL
    """, 'LLORDID -> CHLRDS.LORDID')

    # field_officer_id, zone_supervisor_id, zone_manager_id <- CHOFFICERS
    _stamp('CHBLOCKS', ['field_officer_id', 'zone_supervisor_id', 'zone_manager_id'], """
        UPDATE t
        SET t.[field_officer_id]    = fo.[officer_id],
            t.[zone_supervisor_id]  = sfo.[officer_id],
            t.[zone_manager_id]     = zm.[officer_id]
        FROM [CHBLOCKS] t
        LEFT JOIN [CHOFFICERS] fo  ON RTRIM(LTRIM(fo.OFFID))  = RTRIM(LTRIM(t.OFFID))
                                  AND fo.[officer_id] IS NOT NULL
        LEFT JOIN [CHOFFICERS] sfo ON RTRIM(LTRIM(sfo.OFFID)) = RTRIM(LTRIM(t.SFOID))
                                  AND sfo.[officer_id] IS NOT NULL
        LEFT JOIN [CHOFFICERS] zm  ON RTRIM(LTRIM(zm.OFFID))  = RTRIM(LTRIM(t.ZMGRID))
                                  AND zm.[officer_id] IS NOT NULL
    """, 'OFFID/SFOID/ZMGRID -> CHOFFICERS')

    # ==========================================================================
    # PHASE 2 — Intermediate / reference tables
    # ==========================================================================
    print(f"\n  --- Phase 2: Intermediate tables ---")

    # CHBUNITS <- CHBLOCKS (property_id + client_id)
    _stamp('CHBUNITS', ['property_id', 'client_id'], """
        UPDATE t
        SET t.[property_id] = blk.[property_id],
            t.[client_id]   = blk.[client_id]
        FROM [CHBUNITS] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKID -> CHBLOCKS')

    # CHTENS <- CHBLOCKS (property_id)
    _stamp('CHTENS', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHTENS] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKNO -> CHBLOCKS.BLOCKID')

    # CHTENS <- CHBUNITS (unit_id)
    _stamp('CHTENS', ['unit_id'], """
        UPDATE t SET t.[unit_id] = u.[unit_id]
        FROM [CHTENS] t
        INNER JOIN [CHBUNITS] u
          ON RTRIM(LTRIM(u.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
         AND RTRIM(LTRIM(u.UNITNO))  = RTRIM(LTRIM(t.UNITNO))
        WHERE u.[unit_id] IS NOT NULL
    """, 'BLOCKNO+UNITNO -> CHBUNITS')

    # CHBBRA <- CHBANK (bank_id via BANKCODE)
    _stamp('CHBBRA', ['bank_id'], """
        UPDATE t SET t.[bank_id] = ref.[bank_id]
        FROM [CHBBRA] t
        INNER JOIN [CHBANK] ref ON ref.BANKCODE = t.BANKCODE
        WHERE ref.[bank_id] IS NOT NULL
    """, 'BANKCODE -> CHBANK')

    # CHCHQ <- CHBANK (bank_id via LEFT(CHQACC,2))
    _stamp('CHCHQ', ['bank_id'], """
        UPDATE t SET t.[bank_id] = ref.[bank_id]
        FROM [CHCHQ] t
        INNER JOIN [CHBANK] ref ON ref.BANKCODE = LEFT(t.CHQACC, 2)
        WHERE ref.[bank_id] IS NOT NULL
    """, 'LEFT(CHQACC,2) -> CHBANK.BANKCODE')

    # CHCDC <- CHCHRS (item_id via PCHRGID -> CHRGID)
    _stamp('CHCDC', ['item_id'], """
        UPDATE t SET t.[item_id] = ref.[item_id]
        FROM [CHCDC] t
        INNER JOIN [CHCHRS] ref ON ref.CHRGID = t.PCHRGID
        WHERE ref.[item_id] IS NOT NULL
    """, 'PCHRGID -> CHCHRS.CHRGID')

    # CHBCTH <- CHSTFF (staff_id via BTCRBY -> EMPNUMB)
    _stamp('CHBCTH', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHBCTH] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.BTCRBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'BTCRBY -> CHSTFF.EMPNUMB')

    # CHJVH <- CHSTFF (staff_id via CREATEDBY)
    _stamp('CHJVH', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHJVH] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.CREATEDBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'CREATEDBY -> CHSTFF.EMPNUMB')

    # CHJVHP <- CHSTFF (staff_id via CREATEDBY)
    _stamp('CHJVHP', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHJVHP] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.CREATEDBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'CREATEDBY -> CHSTFF.EMPNUMB')

    # ==========================================================================
    # PHASE 3 — Large transaction tables
    # ==========================================================================
    print(f"\n  --- Phase 3: Transaction tables ---")

    # --- CHTNTRS (1.1M rows) -------------------------------------------------
    _stamp_block_chain('CHTNTRS', 'BLOCKID', with_officers=True)
    _stamp_unit('CHTNTRS', 'BLOCKID')
    _stamp_tenant('CHTNTRS', 'TENNAME', 'BLOCKID')

    # --- CHRTXS (1.5M rows) --------------------------------------------------
    _stamp_block_chain('CHRTXS', 'BLOCKID', with_officers=True)
    _stamp_unit('CHRTXS', 'BLOCKID')
    _stamp_tenant('CHRTXS', 'TENNAME', 'BLOCKID')

    # CHRTXS <- CHRHMS (return_id via RETURNID)
    _stamp('CHRTXS', ['return_id'], """
        UPDATE t SET t.[return_id] = ref.[return_id]
        FROM [CHRTXS] t
        INNER JOIN [CHRHMS] ref ON ref.RETURNID = t.RETURNID
        WHERE ref.[return_id] IS NOT NULL
    """, 'RETURNID -> CHRHMS')

    # CHRTXS <- CHTNTRS (invoice_id via composite key with period+type dedup)
    _stamp('CHRTXS', ['invoice_id'], """
        UPDATE t SET t.[invoice_id] = inv.invoice_id
        FROM [CHRTXS] t
        INNER JOIN (
            SELECT BLOCKID, UNITNO, TENNAME, TRAYR, TRAMNTH, TRANSTYP,
                   MIN(invoice_id) AS invoice_id
            FROM [CHTNTRS]
            WHERE TENNAME IS NOT NULL AND BLOCKID IS NOT NULL
              AND UNITNO IS NOT NULL AND invoice_id IS NOT NULL
            GROUP BY BLOCKID, UNITNO, TENNAME, TRAYR, TRAMNTH, TRANSTYP
        ) inv
          ON inv.BLOCKID  = RTRIM(LTRIM(t.BLOCKID))
         AND inv.UNITNO   = RTRIM(LTRIM(t.UNITNO))
         AND inv.TENNAME  = t.TENNAME
         AND inv.TRAYR    = CAST(LEFT(CAST(t.RETNPRD AS VARCHAR(10)), 4) AS INT)
         AND inv.TRAMNTH  = CAST(RIGHT(CAST(t.RETNPRD AS VARCHAR(10)), 2) AS INT)
         AND inv.TRANSTYP = RTRIM(LTRIM(t.TRANSCODE))
    """, 'period+type+block+unit+tenant -> CHTNTRS (complex)')

    # --- CHDPAY ---------------------------------------------------------------
    _stamp_block_chain('CHDPAY', 'BLOCKID')
    _stamp_unit('CHDPAY', 'BLOCKID')
    _stamp_tenant('CHDPAY', 'TENNAME', 'BLOCKID')

    # --- CHDPS ----------------------------------------------------------------
    _stamp_block_chain('CHDPS', 'BLOCKID')
    _stamp_unit('CHDPS', 'BLOCKID')
    _stamp_tenant('CHDPS', 'TENNAME', 'BLOCKID')

    # --- CHIPAYS (uses BLOCKNO, not BLOCKID) ----------------------------------
    _stamp_block_chain('CHIPAYS', 'BLOCKNO')
    _stamp_unit('CHIPAYS', 'BLOCKNO')
    _stamp_tenant('CHIPAYS', 'TENNAME', 'BLOCKNO')

    # --- CHBCTD (voucher details) ---------------------------------------------
    _stamp('CHBCTD', ['voucher_id'], """
        UPDATE t SET t.[voucher_id] = ref.[voucher_id]
        FROM [CHBCTD] t
        INNER JOIN [CHBCTH] ref ON ref.BTCHID = t.BTCHID
        WHERE ref.[voucher_id] IS NOT NULL
    """, 'BTCHID -> CHBCTH')

    _stamp('CHBCTD', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHBCTD] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKID -> CHBLOCKS')

    # --- CHCHD (cheque payment details) ---------------------------------------
    _stamp('CHCHD', ['voucher_id'], """
        UPDATE t SET t.[voucher_id] = ref.[voucher_id]
        FROM [CHCHD] t
        INNER JOIN [CHBCTH] ref
          ON CAST(ref.BTCHID AS VARCHAR(20)) = RTRIM(LTRIM(t.PAYVOUC))
        WHERE ref.[voucher_id] IS NOT NULL
    """, 'PAYVOUC -> CHBCTH.BTCHID')

    _stamp('CHCHD', ['cheque_id'], """
        UPDATE t SET t.[cheque_id] = ref.[cheque_id]
        FROM [CHCHD] t
        INNER JOIN [CHCHQ] ref
          ON RTRIM(LTRIM(ref.CHQNO))  = RTRIM(LTRIM(t.CHQNO))
         AND RTRIM(LTRIM(ref.CHQACC)) = RTRIM(LTRIM(t.CHQACC))
        WHERE ref.[cheque_id] IS NOT NULL
    """, 'CHQNO+CHQACC -> CHCHQ')

    # --- CHJVD (journal details) ----------------------------------------------
    _stamp('CHJVD', ['account_id'], """
        UPDATE t SET t.[account_id] = ref.[account_id]
        FROM [CHJVD] t
        INNER JOIN [CHFACTV] ref
          ON RTRIM(LTRIM(ref.ACCNUM)) = RTRIM(LTRIM(t.ACCNUM))
        WHERE ref.[account_id] IS NOT NULL
    """, 'ACCNUM -> CHFACTV')

    # --- CHGLAC (GL postings) -------------------------------------------------
    _stamp('CHGLAC', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHGLAC] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKID -> CHBLOCKS')

    _stamp_unit('CHGLAC', 'BLOCKID')
    _stamp_tenant('CHGLAC', 'TENNAME', 'BLOCKID')

    _stamp('CHGLAC', ['account_id'], """
        UPDATE t SET t.[account_id] = ref.[account_id]
        FROM [CHGLAC] t
        INNER JOIN [CHFACTV] ref
          ON RTRIM(LTRIM(ref.ACCNUM)) = RTRIM(LTRIM(t.ACCNUM))
        WHERE ref.[account_id] IS NOT NULL
    """, 'ACCNUM -> CHFACTV')

    # --- CHCBDE (cashbook entries) --------------------------------------------
    _stamp('CHCBDE', ['bank_id'], """
        UPDATE t SET t.[bank_id] = ref.[bank_id]
        FROM [CHCBDE] t
        INNER JOIN [CHBANK] ref ON ref.BANKCODE = LEFT(t.BANKACC, 2)
        WHERE ref.[bank_id] IS NOT NULL
    """, 'LEFT(BANKACC,2) -> CHBANK.BANKCODE')

    # --- CHLACC (landlord account transactions) -------------------------------
    _stamp('CHLACC', ['client_id'], """
        UPDATE t SET t.[client_id] = ref.[client_id]
        FROM [CHLACC] t
        INNER JOIN [CHLRDS] ref
          ON RTRIM(LTRIM(ref.LORDID)) = REPLACE(RTRIM(LTRIM(t.LORDID)), 'LAN-', '')
        WHERE ref.[client_id] IS NOT NULL
    """, 'LORDID (strip LAN-) -> CHLRDS')

    _stamp('CHLACC', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHLACC] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.HOUSEID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'HOUSEID -> CHBLOCKS.BLOCKID')

    # ==========================================================================
    print(f"\n{'=' * 70}")
    print(f"  FK stamping complete: {total_ops} succeeded, {total_err} failed")
    print(f"{'=' * 70}")

    cur.close()
    return total_ops, total_err


def print_fk_summary(conn):
    """Print FK column verification summary."""
    cur = conn.cursor()
    print(f"\n{'=' * 78}")
    print(f"  FK STAMP VERIFICATION")
    print(f"{'=' * 78}")
    print(f"  {'Table':<16} {'FK Column':<22} {'Total':>10} {'Stamped':>10} {'NULL':>10}")
    print(f"  {'-' * 16} {'-' * 22} {'-' * 10} {'-' * 10} {'-' * 10}")

    fk_checks = [
        ('CHBLOCKS',  'client_id'),
        ('CHBLOCKS',  'field_officer_id'),
        ('CHBLOCKS',  'zone_supervisor_id'),
        ('CHBLOCKS',  'zone_manager_id'),
        ('CHBUNITS',  'property_id'),
        ('CHBUNITS',  'client_id'),
        ('CHTENS',    'property_id'),
        ('CHTENS',    'unit_id'),
        ('CHBBRA',    'bank_id'),
        ('CHCHQ',     'bank_id'),
        ('CHCDC',     'item_id'),
        ('CHBCTH',    'staff_id'),
        ('CHJVH',     'staff_id'),
        ('CHJVHP',    'staff_id'),
        ('CHTNTRS',   'property_id'),
        ('CHTNTRS',   'client_id'),
        ('CHTNTRS',   'zone_id'),
        ('CHTNTRS',   'field_officer_id'),
        ('CHTNTRS',   'zone_supervisor_id'),
        ('CHTNTRS',   'zone_manager_id'),
        ('CHTNTRS',   'unit_id'),
        ('CHTNTRS',   'tenant_id'),
        ('CHRTXS',    'property_id'),
        ('CHRTXS',    'client_id'),
        ('CHRTXS',    'zone_id'),
        ('CHRTXS',    'field_officer_id'),
        ('CHRTXS',    'zone_supervisor_id'),
        ('CHRTXS',    'zone_manager_id'),
        ('CHRTXS',    'unit_id'),
        ('CHRTXS',    'tenant_id'),
        ('CHRTXS',    'return_id'),
        ('CHRTXS',    'invoice_id'),
        ('CHDPAY',    'property_id'),
        ('CHDPAY',    'client_id'),
        ('CHDPAY',    'unit_id'),
        ('CHDPAY',    'tenant_id'),
        ('CHDPS',     'property_id'),
        ('CHDPS',     'client_id'),
        ('CHDPS',     'unit_id'),
        ('CHDPS',     'tenant_id'),
        ('CHIPAYS',   'property_id'),
        ('CHIPAYS',   'client_id'),
        ('CHIPAYS',   'unit_id'),
        ('CHIPAYS',   'tenant_id'),
        ('CHBCTD',    'voucher_id'),
        ('CHBCTD',    'property_id'),
        ('CHCHD',     'voucher_id'),
        ('CHCHD',     'cheque_id'),
        ('CHJVD',     'account_id'),
        ('CHGLAC',    'property_id'),
        ('CHGLAC',    'unit_id'),
        ('CHGLAC',    'tenant_id'),
        ('CHGLAC',    'account_id'),
        ('CHCBDE',    'bank_id'),
        ('CHLACC',    'client_id'),
        ('CHLACC',    'property_id'),
    ]

    for tbl, col in fk_checks:
        try:
            if not col_exists(cur, tbl, col):
                continue
            cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
            total = cur.fetchone()[0]
            cur.execute(f"SELECT COUNT(*) FROM [{tbl}] WHERE [{col}] IS NOT NULL")
            stamped = cur.fetchone()[0]
            null_count = total - stamped
            print(f"  {tbl:<16} {col:<22} {total:>10,} {stamped:>10,} {null_count:>10,}")
        except Exception as e:
            print(f"  {tbl:<16} {col:<22} ERROR: {e}")

    cur.close()


def main():
    print("=" * 70)
    print("  CHABRINS -- Auto-Increment ID Generator")
    print("  Adds stable ID columns to source tables")
    print("=" * 70)
    print(f"  Server:   {MSSQL_SERVER}")
    print(f"  Database: {MSSQL_DATABASE}")
    print()

    t_start = time.time()

    try:
        conn = connect()
        print(f"  Connected to {MSSQL_DATABASE} via Windows Auth\n")
    except Exception as e:
        print(f"  FAILED to connect: {e}")
        print(f"\n  Troubleshooting:")
        print(f"    - Ensure SQL Server is running on {MSSQL_SERVER}")
        print(f"    - Ensure {MSSQL_DATABASE} database exists")
        print(f"    - Try changing MSSQL_DRIVER to '{{ODBC Driver 17 for SQL Server}}'")
        print(f"    - pip install pyodbc")
        sys.exit(1)

    # -- Process each table ----------------------------------------------------
    success = 0
    errors  = 0

    for i, entry in enumerate(TABLES, 1):
        print(f"{'=' * 70}")
        print(f"  [{i}/{len(TABLES)}] {entry['table']}.{entry['id_col']}  --  {entry['desc']}")
        try:
            add_id_column(conn, entry)
            success += 1
        except Exception as e:
            print(f"  [ERROR] {entry['table']}: {e}")
            errors += 1
            try:
                conn.rollback()
            except Exception:
                pass

    # -- Zone/ZoneArea lookup tables -------------------------------------------
    try:
        create_zone_lookups(conn)
        success += 2
    except Exception as e:
        print(f"  [ERROR] Zone lookups: {e}")
        errors += 1
        try:
            conn.rollback()
        except Exception:
            pass

    # -- FK column stamping (text JOINs -> integer IDs) ------------------------
    try:
        fk_ok, fk_err = stamp_all_fk_columns(conn)
        success += fk_ok
        errors  += fk_err
    except Exception as e:
        print(f"  [ERROR] FK stamping: {e}")
        errors += 1

    # -- Summary ---------------------------------------------------------------
    print_summary(conn)
    print_fk_summary(conn)

    print(f"\n{'=' * 70}")
    print(f"  DONE  --  {success} succeeded, {errors} failed  ({fmt_elapsed(t_start)})")
    print(f"{'=' * 70}")

    if errors > 0:
        print(f"\n  WARNING: {errors} table(s) had errors. Check output above.")

    conn.close()


if __name__ == '__main__':
    main()
