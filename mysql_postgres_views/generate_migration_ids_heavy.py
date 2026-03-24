#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CHABRINS ID Generator — HEAVY (tables >400K rows)
====================================================
Run this AFTER generate_migration_ids_light.py.

Handles the 4 largest transaction tables (>400K rows):
  CHTNTRS (1.2M), CHRTXS (1.5M), CHTPAYS (1.4M), CHTACC (2.6M)

Also stamps FK columns on these tables (block chain, unit, tenant,
and cross-references like return_id, invoice_id, payment_id).

Prerequisite: generate_migration_ids_light.py must have run first
(CHBLOCKS, CHLRDS, CHOFFICERS, CHBUNITS, CHTENS, CHRHMS, CHTNTRS
IDs need to be stamped already).

Usage:
  python generate_migration_ids_heavy.py
"""

import pyodbc
import re
import sys
import time

# ===============================================================================
# CONFIG
# ===============================================================================
MSSQL_SERVER   = r'localhost\SQL2017'
MSSQL_DATABASE = 'CHABRINS'
MSSQL_DRIVER   = '{SQL Server}'
BATCH = 50_000

# ===============================================================================
# TABLE DEFINITIONS — HEAVY (>400K rows)
# ===============================================================================
TABLES = [
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
    {
        'table':    'CHTPAYS',
        'id_col':   'payment_id',
        'order_by': 'ISNULL(BATCHID, 0), ISNULL(ENTRYID, 0), ISNULL(RCNO, 0), TENNAME, BLOCKID, UNITNO',
        'where':    'TENNAME IS NOT NULL AND BLOCKID IS NOT NULL',
        'desc':     '_011 -- sys_pm_tenants_payments (primary payment table)',
    },
    {
        'table':    'CHTACC',
        'id_col':   'acc_id',
        'order_by': 'TRANSDATE, TENNAME, BLOCKID, UNITNO, ISNULL(RCNO, 0), INVONUM',
        'where':    "DRCR = 'CR' AND RCNO IS NOT NULL AND INVONUM IS NOT NULL",
        'desc':     '_012 -- sys_pm_tenants_payments_invoices (payment-to-invoice allocations)',
    },
]


# ===============================================================================
# HELPERS
# ===============================================================================
def connect():
    conn_str = (
        f'DRIVER={MSSQL_DRIVER};'
        f'SERVER={MSSQL_SERVER};'
        f'DATABASE={MSSQL_DATABASE};'
        f'Trusted_Connection=yes;'
    )
    return pyodbc.connect(conn_str, autocommit=False)


def col_exists(cursor, table, column):
    cursor.execute("""
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ? AND COLUMN_NAME = ?
    """, (table, column))
    return cursor.fetchone() is not None


def index_exists(cursor, index_name):
    cursor.execute("SELECT 1 FROM sys.indexes WHERE name = ?", (index_name,))
    return cursor.fetchone() is not None


def table_exists(cursor, table):
    cursor.execute("""
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = ?
    """, (table,))
    return cursor.fetchone() is not None


def get_row_count(cursor, table, where_clause=None):
    sql = f"SELECT COUNT(*) FROM [{table}]"
    if where_clause:
        sql += f" WHERE {where_clause}"
    cursor.execute(sql)
    return cursor.fetchone()[0]


def fmt_elapsed(start):
    elapsed = time.time() - start
    if elapsed < 60:
        return f"{elapsed:.1f}s"
    return f"{elapsed / 60:.1f}m"


def set_recovery_simple(conn):
    cur = conn.cursor()
    try:
        cur.execute(f"ALTER DATABASE [{MSSQL_DATABASE}] SET RECOVERY SIMPLE WITH NO_WAIT")
        conn.commit()
        cur.execute("CHECKPOINT")
        conn.commit()
        print(f"  [log]  {MSSQL_DATABASE} recovery -> SIMPLE  (log reuse enabled)")
    except Exception as e:
        print(f"  [warn] Could not set SIMPLE recovery: {e}")
    finally:
        cur.close()


def set_recovery_full(conn):
    cur = conn.cursor()
    try:
        cur.execute(f"ALTER DATABASE [{MSSQL_DATABASE}] SET RECOVERY FULL WITH NO_WAIT")
        conn.commit()
        print(f"  [log]  {MSSQL_DATABASE} recovery -> FULL   (restored)")
    except Exception as e:
        print(f"  [warn] Could not restore FULL recovery: {e}")
    finally:
        cur.close()


# ===============================================================================
# MAIN LOGIC
# ===============================================================================
def add_id_column(conn, entry):
    table     = entry['table']
    col_name  = entry['id_col']
    order_by  = entry['order_by']
    where_cls = entry['where']
    desc      = entry['desc']
    cur = conn.cursor()

    if col_exists(cur, table, col_name):
        print(f"  [skip] Column {col_name} already exists on {table}")
    else:
        cur.execute(f"ALTER TABLE [{table}] ADD [{col_name}] INT NULL")
        conn.commit()
        print(f"  [add]  Column {col_name} added to {table}")

    while True:
        cur.execute(f"UPDATE TOP({BATCH}) [{table}] SET [{col_name}] = NULL WHERE [{col_name}] IS NOT NULL")
        conn.commit()
        if cur.rowcount == 0:
            break
    cur.execute("CHECKPOINT"); conn.commit()

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
    cur.execute("CHECKPOINT"); conn.commit()
    print(f"  [fill] {rows_updated:>10,} rows numbered in {fmt_elapsed(t0)}  ({desc})")

    idx_name = f"IX_{table}_{col_name}"
    if index_exists(cur, idx_name):
        print(f"  [skip] Index {idx_name} already exists")
    else:
        cur.execute(f"""
            CREATE NONCLUSTERED INDEX [{idx_name}]
            ON [{table}] ([{col_name}]) WHERE [{col_name}] IS NOT NULL
        """)
        conn.commit()
        print(f"  [idx]  Index {idx_name} created")

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


def stamp_heavy_fk_columns(conn):
    """
    FK stamping for the 4 heavy transaction tables.
    Depends on light script having run first (CHBLOCKS, CHBUNITS, CHTENS,
    CHRHMS, CHTNTRS all need their IDs stamped).
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

            set_null   = ', '.join(f'[{c}] = NULL' for c in cols)
            null_check = f'[{cols[0]}] IS NOT NULL'
            while True:
                cur.execute(f"UPDATE TOP({BATCH}) [{tbl}] SET {set_null} WHERE {null_check}")
                conn.commit()
                if cur.rowcount == 0:
                    break
            cur.execute("CHECKPOINT"); conn.commit()

            t0 = time.time()
            sql_up = sql.upper()
            use_inner = 'INNER JOIN' in sql_up and 'LEFT JOIN' not in sql_up

            if use_inner:
                null_cond = f"t.[{cols[0]}] IS NULL"
                batched = re.sub(r'(?i)\bUPDATE\s+t\b', f'UPDATE TOP({BATCH}) t', sql.strip(), count=1)
                if re.search(r'(?i)\bWHERE\b', batched):
                    batched = batched.rstrip() + f'\n  AND {null_cond}'
                else:
                    batched = batched.rstrip() + f'\nWHERE {null_cond}'

                total_rows = 0
                while True:
                    cur.execute(batched)
                    n = cur.rowcount
                    conn.commit()
                    total_rows += n
                    if n == 0:
                        break
                    cur.execute("CHECKPOINT"); conn.commit()
                rows = total_rows
            else:
                cur.execute(sql)
                rows = cur.rowcount
                conn.commit()
                cur.execute("CHECKPOINT"); conn.commit()

            print(f"    {rows:>10,} rows stamped  ({fmt_elapsed(t0)})")
            for c in cols:
                _ensure_idx(tbl, c)
            total_ops += 1
        except Exception as e:
            print(f"    ERROR: {e}")
            total_err += 1
            try: conn.rollback()
            except Exception: pass

    def _stamp_block_chain(tbl, block_col):
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
        sql = f"""
            UPDATE t SET {set_clause}
            FROM [{tbl}] t
            INNER JOIN [CHBLOCKS] blk
              ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.[{block_col}]))
        """
        _stamp(tbl, cols, sql, f'{block_col} -> CHBLOCKS chain')

    def _stamp_unit(tbl, block_col, unit_col='UNITNO'):
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
    print(f"\n{'=' * 70}")
    print(f"  FK STAMPING — HEAVY (4 large transaction tables)")
    print(f"{'=' * 70}")

    # --- CHTNTRS (1.2M rows) -------------------------------------------------
    print(f"\n  --- CHTNTRS (invoices) ---")
    _stamp_block_chain('CHTNTRS', 'BLOCKID')
    _stamp_unit('CHTNTRS', 'BLOCKID')
    _stamp_tenant('CHTNTRS', 'TENNAME', 'BLOCKID')

    # --- CHRTXS (1.5M rows) --------------------------------------------------
    print(f"\n  --- CHRTXS (return lines) ---")
    _stamp_block_chain('CHRTXS', 'BLOCKID')
    _stamp_unit('CHRTXS', 'BLOCKID')
    _stamp_tenant('CHRTXS', 'TENNAME', 'BLOCKID')

    _stamp('CHRTXS', ['return_id'], """
        UPDATE t SET t.[return_id] = ref.[return_id]
        FROM [CHRTXS] t
        INNER JOIN [CHRHMS] ref ON ref.RETURNID = t.RETURNID
        WHERE ref.[return_id] IS NOT NULL
    """, 'RETURNID -> CHRHMS')

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

    # --- CHTPAYS (1.4M rows) -------------------------------------------------
    print(f"\n  --- CHTPAYS (tenant payments) ---")
    _stamp_block_chain('CHTPAYS', 'BLOCKID')
    _stamp_unit('CHTPAYS', 'BLOCKID')
    _stamp_tenant('CHTPAYS', 'TENNAME', 'BLOCKID')

    # --- CHTACC (2.6M rows) --------------------------------------------------
    print(f"\n  --- CHTACC (payment-invoice allocations) ---")
    _stamp_block_chain('CHTACC', 'BLOCKID')
    _stamp_unit('CHTACC', 'BLOCKID')
    _stamp_tenant('CHTACC', 'TENNAME', 'BLOCKID')

    _stamp('CHTACC', ['invoice_id'], """
        UPDATE t SET t.[invoice_id] = ref.[invoice_id]
        FROM [CHTACC] t
        INNER JOIN [CHTNTRS] ref
          ON RTRIM(LTRIM(ref.INVONUM)) = RTRIM(LTRIM(t.INVONUM))
        WHERE ref.[invoice_id] IS NOT NULL
          AND t.INVONUM IS NOT NULL
    """, 'INVONUM -> CHTNTRS.INVONUM -> invoice_id')

    _stamp('CHTACC', ['payment_id'], """
        UPDATE t SET t.[payment_id] = ref.min_payment_id
        FROM [CHTACC] t
        INNER JOIN (
            SELECT RCNO, MIN(payment_id) AS min_payment_id
            FROM [CHTPAYS]
            WHERE payment_id IS NOT NULL AND RCNO IS NOT NULL
            GROUP BY RCNO
        ) ref ON ref.RCNO = t.RCNO
        WHERE t.RCNO IS NOT NULL
    """, 'RCNO -> CHTPAYS.RCNO -> payment_id (MIN for rare RCNO ties)')

    # ==========================================================================
    print(f"\n{'=' * 70}")
    print(f"  Heavy FK stamping complete: {total_ops} succeeded, {total_err} failed")
    print(f"{'=' * 70}")
    cur.close()
    return total_ops, total_err


def print_summary(conn):
    cur = conn.cursor()
    print(f"\n{'=' * 78}")
    print(f"  VERIFICATION SUMMARY (HEAVY)")
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

    fk_checks = [
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
        ('CHTPAYS',   'property_id'),
        ('CHTPAYS',   'client_id'),
        ('CHTPAYS',   'zone_id'),
        ('CHTPAYS',   'field_officer_id'),
        ('CHTPAYS',   'zone_supervisor_id'),
        ('CHTPAYS',   'zone_manager_id'),
        ('CHTPAYS',   'unit_id'),
        ('CHTPAYS',   'tenant_id'),
        ('CHTACC',    'property_id'),
        ('CHTACC',    'client_id'),
        ('CHTACC',    'zone_id'),
        ('CHTACC',    'field_officer_id'),
        ('CHTACC',    'zone_supervisor_id'),
        ('CHTACC',    'zone_manager_id'),
        ('CHTACC',    'unit_id'),
        ('CHTACC',    'tenant_id'),
        ('CHTACC',    'invoice_id'),
        ('CHTACC',    'payment_id'),
    ]

    print(f"\n{'=' * 78}")
    print(f"  FK STAMP VERIFICATION (HEAVY)")
    print(f"{'=' * 78}")
    print(f"  {'Table':<16} {'FK Column':<22} {'Total':>10} {'Stamped':>10} {'NULL':>10}")
    print(f"  {'-' * 16} {'-' * 22} {'-' * 10} {'-' * 10} {'-' * 10}")

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


def check_prerequisites(conn):
    """Verify that light script has been run first."""
    cur = conn.cursor()
    missing = []
    checks = [
        ('CHBLOCKS',   'property_id'),
        ('CHLRDS',     'client_id'),
        ('CHOFFICERS', 'officer_id'),
        ('CHBUNITS',   'unit_id'),
        ('CHTENS',     'tenant_id'),
        ('CHRHMS',     'return_id'),
    ]
    for tbl, col in checks:
        if not col_exists(cur, tbl, col):
            missing.append(f"{tbl}.{col}")
        else:
            cur.execute(f"SELECT COUNT(*) FROM [{tbl}] WHERE [{col}] IS NOT NULL")
            if cur.fetchone()[0] == 0:
                missing.append(f"{tbl}.{col} (exists but empty)")
    cur.close()
    return missing


def main():
    print("=" * 70)
    print("  CHABRINS — ID Generator (HEAVY)")
    print("  Large transaction tables (>400K rows)")
    print("=" * 70)
    print(f"  Server:   {MSSQL_SERVER}")
    print(f"  Database: {MSSQL_DATABASE}")
    print()

    t_start = time.time()

    try:
        conn = connect()
        print(f"  Connected to {MSSQL_DATABASE} via Windows Auth")
    except Exception as e:
        print(f"  FAILED to connect: {e}")
        sys.exit(1)

    # Check prerequisites
    missing = check_prerequisites(conn)
    if missing:
        print(f"\n  ERROR: Light script prerequisite columns missing:")
        for m in missing:
            print(f"    - {m}")
        print(f"\n  Run first: python generate_migration_ids_light.py")
        sys.exit(1)
    print(f"  Prerequisites OK (light script IDs found)")

    set_recovery_simple(conn)
    print()

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
            try: conn.rollback()
            except Exception: pass

    try:
        fk_ok, fk_err = stamp_heavy_fk_columns(conn)
        success += fk_ok
        errors  += fk_err
    except Exception as e:
        print(f"  [ERROR] FK stamping: {e}")
        errors += 1

    set_recovery_full(conn)
    print_summary(conn)

    print(f"\n{'=' * 70}")
    print(f"  DONE (HEAVY)  --  {success} succeeded, {errors} failed  ({fmt_elapsed(t_start)})")
    print(f"{'=' * 70}")

    if errors > 0:
        print(f"\n  WARNING: {errors} table(s) had errors. Check output above.")

    conn.close()


if __name__ == '__main__':
    main()
