#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
CHABRINS ID Generator — LIGHT (tables ≤400K rows)
====================================================
Run this FIRST before generate_migration_ids_heavy.py.

Handles reference/lookup tables AND mid-size tables (≤400K rows):
  CHLRDS (1,185), CHBLOCKS (1,842), CHCHRS (36), CHOFFICERS (159),
  CHSTFF (245), CHBANK (45), CHFACTV (854),
  CHBUNITS (49K), CHTENS (134K), CHRHMS (35K),
  CHRACT (221K), CHBCTH (5.4K), CHCHQ (241K)

Also creates zone/zone_area lookup tables and stamps FK columns on
CHBLOCKS (hub), small reference tables, intermediate tables, and
all mid-size transaction tables.

Usage:
  python generate_migration_ids_light.py
"""

import pyodbc
import re
import sys
import time

# ===============================================================================
# CONFIG
# ===============================================================================
MSSQL_SERVER   = r'localhost\SQL2017'
MSSQL_DATABASE = 'CHABRINS_NEW'
MSSQL_DRIVER   = '{SQL Server}'
BATCH = 50_000

# ===============================================================================
# TABLE DEFINITIONS — LIGHT (reference + mid-size, ≤400K rows)
# ===============================================================================
TABLES = [
    # --- Reference / lookup (run first, others depend on these) ---
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
        'table':    'CHCHRS',
        'id_col':   'item_id',
        'order_by': 'CHRGID',
        'where':    'CHRGID IS NOT NULL',
        'desc':     '_008 -- sys_set_invoiceable_items',
    },
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
    # --- Mid-size tables (≤400K rows) ---
    {
        'table':    'CHBUNITS',
        'id_col':   'unit_id',
        'order_by': 'BLOCKID, UNITNO',
        'where':    'UNITNO IS NOT NULL',
        'desc':     '_003 -- sys_pm_units',
    },
    {
        'table':      'CHTENS',
        'id_col':     'tenant_id',
        'order_by':   'TENNAME, BLOCKNO, UNITNO',
        'where':      "TENNAME IS NOT NULL AND RTRIM(LTRIM(TENNAME)) <> ''",
        'desc':       '_004 -- sys_pm_clients (tenants) / _005 -- lease agreements',
        # ── ID space offset ──────────────────────────────────────────────────
        # CHTENS and CHLRDS both migrate into sys_pm_clients (same PK space).
        # Tenants must start at MAX(CHLRDS.client_id) + 1 so IDs never collide.
        # The value is read at runtime so it adjusts automatically if CHLRDS grows.
        'offset_from': ('CHLRDS', 'client_id'),
    },
    {
        'table':    'CHRHMS',
        'id_col':   'return_id',
        'order_by': 'RETURNID',
        'where':    'RETURNID IS NOT NULL',
        'desc':     'FK -- returns master/headers',
    },
    {
        'table':    'CHRACT',
        'id_col':   'recovery_id',
        'order_by': 'GTTCNUM, BLOCKID, UNITNO',
        'where':    "GTTCNUM IS NOT NULL AND RTRIM(LTRIM(GTTCNUM)) <> ''",
        'desc':     '_020 -- cb_money_recoveries (returns account transactions)',
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
    # --- Own-row identity stamps (not currently consumed by any view --
    #     requested ahead of need so the source rows have a stable raw id) ---
    {
        'table':    'CHOFALL',
        'id_col':   'mapping_id',
        'order_by': 'BLOCKID, OFFID, ALLDATE',
        'where':    "OFFID IS NOT NULL AND BLOCKID IS NOT NULL",
        'desc':     '_027 -- staff_property_mappings (raw row id; not selected by the view)',
    },
    {
        'table':    'CHVENDS',
        'id_col':   'vendor_entry_id',
        'order_by': 'VENDORID, LASTMNT',
        'where':    "VENDORID IS NOT NULL AND RTRIM(LTRIM(VENDORID)) <> ''",
        'desc':     '_028 -- vendors (raw row id; CHVENDS has multiple rows per vendor_code, view dedups separately)',
    },
    {
        'table':    'CHLRH',
        'id_col':   'link_id',
        'order_by': 'BLOCKID, LORDID',
        'where':    "LORDID IS NOT NULL AND BLOCKID IS NOT NULL",
        'desc':     '_029 -- landlord_property_mappings (raw row id; not selected by the view)',
    },
    {
        'table':    'CHLNTR',
        'id_col':   'schedule_id',
        'order_by': 'LORDID, LNSERIE, REPAYNO, TRANSNO',
        'where':    "LORDID IS NOT NULL AND TRANSNO IS NOT NULL",
        'desc':     '_032 -- landlord_advance_repayment_schedule (raw row id; not selected by the view)',
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
def add_id_column(conn, entry, offset=0):
    """
    Populate entry['id_col'] with ROW_NUMBER() + offset.

    offset > 0 is used when a table shares a target PK space with another
    table.  Example: CHTENS (tenants) and CHLRDS (landlords) both map to
    sys_pm_clients.id.  Passing offset = MAX(CHLRDS.client_id) makes
    CHTENS.tenant_id start at  (offset+1)  so IDs never collide no matter
    how many times the scripts are re-run.
    """
    table     = entry['table']
    col_name  = entry['id_col']
    order_by  = entry['order_by']
    where_cls = entry['where']
    desc      = entry['desc']
    cur = conn.cursor()

    if offset:
        print(f"  [info] Applying offset +{offset:,}  (IDs will start at {offset+1:,})")

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

    rn_expr = f"ROW_NUMBER() OVER (ORDER BY {order_by}) + {offset}" if offset else f"ROW_NUMBER() OVER (ORDER BY {order_by})"
    sql = f"""
        ;WITH numbered AS (
            SELECT [{col_name}],
                   {rn_expr} AS rn
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
    expected_max   = qualifying + offset
    cur.execute(f"SELECT MAX([{col_name}]) FROM [{table}]")
    max_id = cur.fetchone()[0] or 0
    cur.execute(f"SELECT COUNT(*) FROM [{table}] WHERE [{col_name}] IS NOT NULL")
    numbered_count = cur.fetchone()[0]

    if max_id != expected_max or numbered_count != qualifying:
        print(f"  [WARN] Mismatch! max_id={max_id}, expected={expected_max} "
              f"(qualifying={qualifying} + offset={offset}), numbered={numbered_count}")
    else:
        null_rows = total_in_table - qualifying
        extra = f" ({null_rows:,} rows NULL -- filtered out)" if null_rows > 0 else ""
        if offset:
            print(f"  [ok]   Verified: {col_name} = {offset+1:,}..{max_id:,}  "
                  f"({qualifying:,} rows, offset +{offset:,}){extra}")
        else:
            print(f"  [ok]   Verified: max {col_name} = {max_id:,} == qualifying rows{extra}")
    cur.close()


def create_zone_lookups(conn):
    cur = conn.cursor()

    # -- _mig_zones
    tbl = '_mig_zones'
    print(f"\n{'=' * 70}")
    print(f"  Creating lookup table: {tbl}")
    if table_exists(cur, tbl):
        cur.execute(f"DROP TABLE [{tbl}]")
        conn.commit()
        print(f"  [drop] Old {tbl} dropped")

    cur.execute(f"""
        SELECT ROW_NUMBER() OVER (ORDER BY BLOC) AS zone_id, BLOC AS bloc
        INTO [{tbl}]
        FROM (
            SELECT DISTINCT RTRIM(LTRIM(BLOC)) AS BLOC
            FROM CHBLOCKS WHERE BLOC IS NOT NULL AND RTRIM(LTRIM(BLOC)) <> ''
        ) z
    """)
    conn.commit()
    cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
    cnt = cur.fetchone()[0]
    print(f"  [fill] {cnt} zones materialised")
    cur.execute(f"ALTER TABLE [{tbl}] ALTER COLUMN zone_id INT NOT NULL")
    cur.execute(f"ALTER TABLE [{tbl}] ADD CONSTRAINT PK_{tbl} PRIMARY KEY (zone_id)")
    conn.commit()
    print(f"  [pk]   Primary key added")

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

    # -- _mig_zone_areas
    tbl = '_mig_zone_areas'
    print(f"\n{'=' * 70}")
    print(f"  Creating lookup table: {tbl}")
    if table_exists(cur, tbl):
        cur.execute(f"DROP TABLE [{tbl}]")
        conn.commit()
        print(f"  [drop] Old {tbl} dropped")

    cur.execute(f"""
        SELECT ROW_NUMBER() OVER (ORDER BY BLOCAL) AS zone_area_id,
               BLOCAL AS blocal, BLOC AS bloc
        INTO [{tbl}]
        FROM (
            SELECT DISTINCT RTRIM(LTRIM(BLOCAL)) AS BLOCAL, RTRIM(LTRIM(BLOC)) AS BLOC
            FROM CHBLOCKS WHERE BLOCAL IS NOT NULL AND RTRIM(LTRIM(BLOCAL)) <> ''
        ) za
    """)
    conn.commit()
    cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
    cnt = cur.fetchone()[0]
    print(f"  [fill] {cnt} zone areas materialised")
    cur.execute(f"ALTER TABLE [{tbl}] ALTER COLUMN zone_area_id INT NOT NULL")
    cur.execute(f"ALTER TABLE [{tbl}] ADD CONSTRAINT PK_{tbl} PRIMARY KEY (zone_area_id)")
    conn.commit()
    print(f"  [pk]   Primary key added")

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


def stamp_light_fk_columns(conn):
    """
    FK stamping Phase 1 (CHBLOCKS hub) + Phase 2 (small refs + intermediate)
    + Phase 3 (mid-size transaction tables).
    Must run before heavy script so CHBLOCKS, CHBUNITS, CHTENS, CHRHMS etc.
    have their IDs stamped and ready for the heavy tables to reference.
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

    def _stamp_block_chain(tbl, block_col, with_officers=False):
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
    print(f"  FK STAMPING — LIGHT (Phase 1, 2 & 3)")
    print(f"{'=' * 70}")

    # ==========================================================================
    # PHASE 1 — CHBLOCKS (central property hub)
    # ==========================================================================
    print(f"\n  --- Phase 1: CHBLOCKS (hub) ---")

    _stamp('CHBLOCKS', ['client_id'], """
        UPDATE t SET t.[client_id] = ref.[client_id]
        FROM [CHBLOCKS] t
        INNER JOIN [CHLRDS] ref
          ON REPLACE(RTRIM(LTRIM(ref.LORDID)), 'LAN-', '') = REPLACE(RTRIM(LTRIM(t.LLORDID)), 'LAN-', '')
        WHERE ref.[client_id] IS NOT NULL
    """, 'LLORDID -> CHLRDS.LORDID  (BUGFIX 2026-08-05: REPLACE was only applied to '
         'LLORDID, so rows where LLORDID already lacked the LAN- prefix never matched '
         "CHLRDS.LORDID which always has it. Verified: was 482/1889 matched, now 1691/1889 -- "
         'the remaining 198 have a genuinely NULL/blank LLORDID in the source.)')

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
    # PHASE 2 — Reference + Intermediate tables
    # ==========================================================================
    print(f"\n  --- Phase 2: Reference + Intermediate tables ---")

    _stamp('CHBBRA', ['bank_id'], """
        UPDATE t SET t.[bank_id] = ref.[bank_id]
        FROM [CHBBRA] t
        INNER JOIN [CHBANK] ref ON ref.BANKCODE = t.BANKCODE
        WHERE ref.[bank_id] IS NOT NULL
    """, 'BANKCODE -> CHBANK')

    _stamp('CHCHQ', ['bank_id'], """
        UPDATE t SET t.[bank_id] = ref.[bank_id]
        FROM [CHCHQ] t
        INNER JOIN [CHBANK] ref ON ref.BANKCODE = LEFT(t.CHQACC, 2)
        WHERE ref.[bank_id] IS NOT NULL
    """, 'LEFT(CHQACC,2) -> CHBANK.BANKCODE')

    _stamp('CHCDC', ['item_id'], """
        UPDATE t SET t.[item_id] = ref.[item_id]
        FROM [CHCDC] t
        INNER JOIN [CHCHRS] ref ON ref.CHRGID = t.PCHRGID
        WHERE ref.[item_id] IS NOT NULL
    """, 'PCHRGID -> CHCHRS.CHRGID')

    _stamp('CHBCTH', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHBCTH] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.BTCRBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'BTCRBY -> CHSTFF.EMPNUMB')

    _stamp('CHJVH', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHJVH] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.CREATEDBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'CREATEDBY -> CHSTFF.EMPNUMB')

    _stamp('CHJVHP', ['staff_id'], """
        UPDATE t SET t.[staff_id] = ref.[staff_id]
        FROM [CHJVHP] t
        INNER JOIN [CHSTFF] ref
          ON RTRIM(LTRIM(ref.EMPNUMB)) = RTRIM(LTRIM(t.CREATEDBY))
        WHERE ref.[staff_id] IS NOT NULL
    """, 'CREATEDBY -> CHSTFF.EMPNUMB')

    # -- CHBUNITS (intermediate: needs CHBLOCKS)
    _stamp('CHBUNITS', ['property_id', 'client_id'], """
        UPDATE t
        SET t.[property_id] = blk.[property_id],
            t.[client_id]   = blk.[client_id]
        FROM [CHBUNITS] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKID -> CHBLOCKS')

    # -- CHTENS (intermediate: needs CHBLOCKS + CHBUNITS)
    _stamp('CHTENS', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHTENS] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
        WHERE blk.[property_id] IS NOT NULL
    """, 'BLOCKNO -> CHBLOCKS.BLOCKID')

    _stamp('CHTENS', ['unit_id'], """
        UPDATE t SET t.[unit_id] = u.[unit_id]
        FROM [CHTENS] t
        INNER JOIN [CHBUNITS] u
          ON RTRIM(LTRIM(u.BLOCKID)) = RTRIM(LTRIM(t.BLOCKNO))
         AND RTRIM(LTRIM(u.UNITNO))  = RTRIM(LTRIM(t.UNITNO))
        WHERE u.[unit_id] IS NOT NULL
    """, 'BLOCKNO+UNITNO -> CHBUNITS')

    # ==========================================================================
    # PHASE 3 — Mid-size transaction tables
    # ==========================================================================
    print(f"\n  --- Phase 3: Mid-size transaction tables ---")

    # --- CHRACT (221K rows) ---------------------------------------------------
    _stamp_block_chain('CHRACT', 'BLOCKID', with_officers=True)
    _stamp_unit('CHRACT', 'BLOCKID')
    _stamp_tenant('CHRACT', 'TENNAME', 'BLOCKID')

    _stamp('CHRACT', ['client_id'], """
        UPDATE t SET t.[client_id] = ref.[client_id]
        FROM [CHRACT] t
        INNER JOIN [CHLRDS] ref
          ON REPLACE(RTRIM(LTRIM(ref.LORDID)), 'LAN-', '') = REPLACE(RTRIM(LTRIM(t.LORDID)), 'LAN-', '')
        WHERE ref.[client_id] IS NOT NULL
    """, 'LORDID (strip LAN- from both sides) -> CHLRDS  (BUGFIX 2026-08-05: REPLACE was '
         'only applied to t.LORDID, but CHRACT.LORDID always keeps its LAN- prefix, so this '
         'matched 0/37106 rows. Verified fix: now matches 37106/37106.)')

    _stamp('CHRACT', ['return_id'], """
        UPDATE t SET t.[return_id] = ref.[return_id]
        FROM [CHRACT] t
        INNER JOIN [CHRHMS] ref ON ref.RETURNID = t.RETNPRD
        WHERE ref.[return_id] IS NOT NULL
    """, 'RETNPRD -> CHRHMS.RETURNID')

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
          ON REPLACE(RTRIM(LTRIM(ref.LORDID)), 'LAN-', '') = REPLACE(RTRIM(LTRIM(t.LORDID)), 'LAN-', '')
        WHERE ref.[client_id] IS NOT NULL
    """, 'LORDID (strip LAN- from both sides) -> CHLRDS  (BUGFIX 2026-08-05: same issue as '
         'CHRACT above -- matched 0/108550 rows before, 108550/108550 after.)')

    _stamp('CHLACC', ['property_id'], """
        UPDATE t SET t.[property_id] = blk.[property_id]
        FROM [CHLACC] t
        INNER JOIN [CHBLOCKS] blk
          ON RTRIM(LTRIM(blk.BLOCKID)) = RTRIM(LTRIM(t.HOUSEID))
        WHERE blk.[property_id] IS NOT NULL
    """, 'HOUSEID -> CHBLOCKS.BLOCKID')

    # ==========================================================================
    print(f"\n{'=' * 70}")
    print(f"  Light FK stamping complete: {total_ops} succeeded, {total_err} failed")
    print(f"{'=' * 70}")
    cur.close()
    return total_ops, total_err


def print_summary(conn):
    cur = conn.cursor()
    print(f"\n{'=' * 78}")
    print(f"  VERIFICATION SUMMARY (LIGHT)")
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

    for tbl, col in [('_mig_zones', 'zone_id'), ('_mig_zone_areas', 'zone_area_id')]:
        try:
            cur.execute(f"SELECT COUNT(*) FROM [{tbl}]")
            cnt = cur.fetchone()[0]
            print(f"  {tbl:<16} {col:<16} {cnt:>12,} {cnt:>12,} {'0':>12} {cnt:>12,}")
        except Exception as e:
            print(f"  {tbl:<16} {col:<16} ERROR: {e}")

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

    fk_checks = [
        ('CHBLOCKS',  'client_id'),
        ('CHBLOCKS',  'field_officer_id'),
        ('CHBLOCKS',  'zone_supervisor_id'),
        ('CHBLOCKS',  'zone_manager_id'),
        ('CHBBRA',    'bank_id'),
        ('CHCHQ',     'bank_id'),
        ('CHCDC',     'item_id'),
        ('CHBCTH',    'staff_id'),
        ('CHJVH',     'staff_id'),
        ('CHJVHP',    'staff_id'),
        ('CHBUNITS',  'property_id'),
        ('CHBUNITS',  'client_id'),
        ('CHTENS',    'property_id'),
        ('CHTENS',    'unit_id'),
        ('CHRACT',    'property_id'),
        ('CHRACT',    'client_id'),
        ('CHRACT',    'zone_id'),
        ('CHRACT',    'field_officer_id'),
        ('CHRACT',    'zone_supervisor_id'),
        ('CHRACT',    'zone_manager_id'),
        ('CHRACT',    'unit_id'),
        ('CHRACT',    'tenant_id'),
        ('CHRACT',    'return_id'),
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

    print(f"\n{'=' * 78}")
    print(f"  FK STAMP VERIFICATION (LIGHT)")
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


def main():
    print("=" * 70)
    print("  CHABRINS — ID Generator (LIGHT)")
    print("  Reference + mid-size tables (≤400K rows)")
    print("=" * 70)
    print(f"  Server:   {MSSQL_SERVER}")
    print(f"  Database: {MSSQL_DATABASE}")
    print()

    t_start = time.time()

    try:
        conn = connect()
        print(f"  Connected to {MSSQL_DATABASE} via Windows Auth")
        set_recovery_simple(conn)
        print()
    except Exception as e:
        print(f"  FAILED to connect: {e}")
        sys.exit(1)

    success = 0
    errors  = 0

    for i, entry in enumerate(TABLES, 1):
        print(f"{'=' * 70}")
        print(f"  [{i}/{len(TABLES)}] {entry['table']}.{entry['id_col']}  --  {entry['desc']}")
        try:
            offset = 0
            if 'offset_from' in entry:
                of_table, of_col = entry['offset_from']
                _cur = conn.cursor()
                _cur.execute(
                    f"SELECT MAX([{of_col}]) FROM [{of_table}] WHERE [{of_col}] IS NOT NULL"
                )
                _row = _cur.fetchone()
                _cur.close()
                offset = int(_row[0]) if _row and _row[0] is not None else 0
                if offset == 0:
                    print(f"  [WARN] offset_from {of_table}.{of_col} returned 0 — "
                          f"ensure {of_table} is stamped before this table.")
                else:
                    print(f"  [info] offset_from {of_table}.{of_col} = {offset:,}")
            add_id_column(conn, entry, offset=offset)
            success += 1
        except Exception as e:
            print(f"  [ERROR] {entry['table']}: {e}")
            errors += 1
            try: conn.rollback()
            except Exception: pass

    try:
        create_zone_lookups(conn)
        success += 2
    except Exception as e:
        print(f"  [ERROR] Zone lookups: {e}")
        errors += 1
        try: conn.rollback()
        except Exception: pass

    try:
        fk_ok, fk_err = stamp_light_fk_columns(conn)
        success += fk_ok
        errors  += fk_err
    except Exception as e:
        print(f"  [ERROR] FK stamping: {e}")
        errors += 1

    set_recovery_full(conn)
    print_summary(conn)

    print(f"\n{'=' * 70}")
    print(f"  DONE (LIGHT)  --  {success} succeeded, {errors} failed  ({fmt_elapsed(t_start)})")
    print(f"  Now run: python generate_migration_ids_heavy.py")
    print(f"{'=' * 70}")

    conn.close()


if __name__ == '__main__':
    main()
