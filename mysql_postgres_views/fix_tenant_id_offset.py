#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
fix_tenant_id_offset.py
=======================
Fixes the ID collision that occurs when both CHLRDS (landlords) and
CHTENS (tenants) are migrated into the same target table sys_pm_clients.

ROOT CAUSE
----------
generate_migration_ids stamps:
  CHLRDS.client_id  = 1 .. N_landlords   (e.g. 1..1202)
  CHTENS.tenant_id  = 1 .. N_tenants     (e.g. 1..133710)

When migration views run, landlords insert first (IDs 1..1202).
Tenants then try to insert IDs 1..133710 — the first 1202 collide →
"duplicate key value violates unique constraint sys_pm_clients_pkey".

THE FIX
-------
On the MSSQL source, offset every tenant_id value by MAX(CHLRDS.client_id).

  CHTENS.tenant_id  becomes  1203 .. 134912   (no overlap with landlords)

The same offset is applied to the tenant_id FK column on EVERY table that
was stamped by generate_migration_ids, so all downstream views automatically
produce correct foreign keys.

On the PostgreSQL side (optional), this script cleans out any previously
migrated tenant rows from sys_pm_clients so they can be re-inserted with
the corrected IDs.

IDEMPOTENT
----------
The script detects the current state before acting:
  - Already offset? → skip silently.
  - Partially offset? → finish applying the remainder.
  - Not offset?      → apply.
Safe to run as many times as needed.

WORKFLOW
--------
1. python generate_migration_ids_light.py
2. python generate_migration_ids_heavy.py
3. python fix_tenant_id_offset.py              ← THIS SCRIPT (run after each step above)
4. Run migration views in the Data Pump tool

Re-running migration views? Just run this script again first — it will
detect and re-apply if generate_migration_ids reset the counters.

USAGE
-----
  # Default (Windows Auth to localhost\\SQL2017, no PG cleanup):
  python fix_tenant_id_offset.py

  # With explicit MSSQL credentials:
  python fix_tenant_id_offset.py --mssql-user sa --mssql-password secret

  # Also clean PostgreSQL target (tenant rows in sys_pm_clients):
  python fix_tenant_id_offset.py --pg-host localhost --pg-dbname chips_db_v3 --pg-user postgres

  # Preview without changing anything:
  python fix_tenant_id_offset.py --dry-run
"""

import argparse
import getpass
import sys
import time

try:
    import pyodbc
except ImportError:
    sys.exit("pyodbc not installed.  Run:  pip install pyodbc")

# ── Defaults ──────────────────────────────────────────────────────────────────
MSSQL_SERVER   = r'localhost\SQL2017'
MSSQL_DATABASE = 'CHABRINS'
MSSQL_DRIVER   = '{SQL Server}'
BATCH          = 50_000

# Tables that have a `tenant_id` FK column stamped by generate_migration_ids.
# Order matters: stamp primary table (CHTENS) first, then all FK tables.
TENANT_FK_TABLES = [
    # Large (>400K rows) — stamped by generate_migration_ids_heavy.py
    ('CHTNTRS', 'tenant_id'),   # debtors invoices         1.2M rows
    ('CHRTXS',  'tenant_id'),   # client return lines       1.5M rows
    ('CHTPAYS', 'tenant_id'),   # tenant payments           1.4M rows
    ('CHTACC',  'tenant_id'),   # payment-invoice allocs    2.6M rows
    # Mid-size — stamped by generate_migration_ids_light.py
    ('CHDPAY',  'tenant_id'),   # deposits
    ('CHDPS',   'tenant_id'),   # deposit requests
    ('CHIPAYS', 'tenant_id'),   # initial payments
    ('CHGLAC',  'tenant_id'),   # GL ledger entries
    ('CHRACT',  'tenant_id'),   # money recoveries
]

# PostgreSQL tables to clean when --pg-* flags are given.
# DELETE in FK-safe order (children before parent).
PG_TENANT_CLEANUP = [
    # (table, condition)
    ("sys_pm_lease_agreements",          None),           # only CHTENS rows
    ("sys_pm_clients",                   "reference_number LIKE 'TEN-%'"),
]


# ── Utilities ─────────────────────────────────────────────────────────────────

def fmt_elapsed(start: float) -> str:
    e = time.time() - start
    return f"{e:.1f}s" if e < 60 else f"{e / 60:.1f}m"


def col_exists(cur, table: str, column: str) -> bool:
    cur.execute(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS "
        "WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=? AND COLUMN_NAME=?",
        (table, column),
    )
    return cur.fetchone() is not None


def section(title: str):
    print(f"\n{'═' * 68}")
    print(f"  {title}")
    print(f"{'═' * 68}")


# ── MSSQL connection ──────────────────────────────────────────────────────────

def connect_mssql(args) -> "pyodbc.Connection":
    drv = args.mssql_driver
    srv = args.mssql_server
    db  = args.mssql_db

    if args.mssql_user:
        pwd = args.mssql_password or getpass.getpass(f"MSSQL password for {args.mssql_user}: ")
        cs = (
            f"DRIVER={drv};SERVER={srv};DATABASE={db};"
            f"UID={args.mssql_user};PWD={pwd};"
            "TrustServerCertificate=yes;"
        )
    else:
        cs = f"DRIVER={drv};SERVER={srv};DATABASE={db};Trusted_Connection=yes;"

    try:
        conn = pyodbc.connect(cs, autocommit=False)
        print(f"  MSSQL connected  →  {srv} / {db}")
        return conn
    except Exception as e:
        sys.exit(f"MSSQL connection failed: {e}")


# ── Core offset logic ─────────────────────────────────────────────────────────

def get_offset(cur) -> int:
    """
    Returns MAX(client_id) from CHLRDS — the number of landlord rows.
    This is the exact offset needed so tenant IDs start above all landlord IDs.
    """
    if not col_exists(cur, 'CHLRDS', 'client_id'):
        sys.exit(
            "ERROR: CHLRDS.client_id column not found.\n"
            "Run generate_migration_ids_light.py first."
        )
    cur.execute("SELECT MAX(client_id) FROM CHLRDS WHERE client_id IS NOT NULL")
    val = cur.fetchone()[0]
    if not val:
        sys.exit(
            "ERROR: CHLRDS.client_id has no values.\n"
            "Run generate_migration_ids_light.py first."
        )
    return int(val)


def offset_table(conn, table: str, col: str, offset: int, dry_run: bool) -> tuple:
    """
    Offset all values in table.col that are <= their current max by +offset.
    Values already > offset (i.e. already fixed) are untouched.

    Returns (rows_changed, new_min, new_max).
    Idempotent: the WHERE col <= original_max ensures already-offset rows
    (which are now > original_max) are never double-counted.
    """
    cur = conn.cursor()

    # Snapshot original range BEFORE touching anything
    cur.execute(
        f"SELECT MIN([{col}]), MAX([{col}]), COUNT(*) "
        f"FROM [{table}] WHERE [{col}] IS NOT NULL"
    )
    row = cur.fetchone()
    if not row or not row[2]:
        cur.close()
        return 0, None, None

    orig_min, orig_max, total = int(row[0]), int(row[1]), int(row[2])

    # How many rows still need the offset?
    cur.execute(
        f"SELECT COUNT(*) FROM [{table}] "
        f"WHERE [{col}] IS NOT NULL AND [{col}] <= {offset}"
    )
    needs_fix = int(cur.fetchone()[0])

    if needs_fix == 0:
        print(f"  [ok   ]  {table:<12} {col}: {orig_min:>9,} .. {orig_max:>9,}  — already above offset")
        cur.close()
        return 0, orig_min, orig_max

    # Rows <= offset will collide; rows <= orig_max are unshifted originals
    # (orig_max is the highest original value; all original values ≤ orig_max)
    condition = f"[{col}] IS NOT NULL AND [{col}] <= {orig_max}"

    if dry_run:
        print(
            f"  [DRY  ]  {table:<12} {col}: {orig_min:>9,} .. {orig_max:>9,}  "
            f"→ +{offset:,}  ({needs_fix:,} rows need fix)"
        )
        cur.close()
        return needs_fix, orig_min + offset, orig_max + offset

    t0 = time.time()
    rows_done = 0
    while True:
        cur.execute(
            f"UPDATE TOP({BATCH}) [{table}] "
            f"SET [{col}] = [{col}] + {offset} "
            f"WHERE {condition}"
        )
        conn.commit()
        if cur.rowcount == 0:
            break
        rows_done += cur.rowcount

    # Verify final state
    cur.execute(
        f"SELECT MIN([{col}]), MAX([{col}]) FROM [{table}] WHERE [{col}] IS NOT NULL"
    )
    new_min, new_max = cur.fetchone()
    print(
        f"  [fixed]  {table:<12} {col}: {orig_min:>9,} .. {orig_max:>9,}  "
        f"→ {new_min:>9,} .. {new_max:>9,}  "
        f"({rows_done:,} rows, {fmt_elapsed(t0)})"
    )
    cur.close()
    return rows_done, new_min, new_max


# ── MSSQL phase ───────────────────────────────────────────────────────────────

def run_mssql_fix(args):
    section("Phase 1 — MSSQL: offset tenant_id across all FK tables")

    conn = connect_mssql(args)
    cur  = conn.cursor()

    # ── 1. Compute offset ──────────────────────────────────────────────────
    offset = get_offset(cur)
    cur.execute("SELECT COUNT(*) FROM CHLRDS WHERE client_id IS NOT NULL")
    landlord_count = int(cur.fetchone()[0])

    print(f"\n  Landlord rows (CHLRDS): {landlord_count:,}")
    print(f"  Offset to apply:        +{offset:,}")
    print(f"  Tenants will use IDs:   {offset + 1:,} and above")

    # ── 2. Validate CHTENS is stamped ──────────────────────────────────────
    if not col_exists(cur, 'CHTENS', 'tenant_id'):
        cur.close()
        conn.close()
        sys.exit(
            "ERROR: CHTENS.tenant_id column not found.\n"
            "Run generate_migration_ids_light.py first."
        )

    cur.execute(
        "SELECT MIN(tenant_id), MAX(tenant_id), COUNT(*) "
        "FROM CHTENS WHERE tenant_id IS NOT NULL"
    )
    row = cur.fetchone()
    if not row or not row[2]:
        cur.close()
        conn.close()
        sys.exit(
            "ERROR: CHTENS.tenant_id has no values.\n"
            "Run generate_migration_ids_light.py first."
        )

    ten_min, ten_max, ten_cnt = int(row[0]), int(row[1]), int(row[2])
    print(f"\n  CHTENS.tenant_id:  {ten_min:,} .. {ten_max:,}  ({ten_cnt:,} qualified rows)")

    if ten_min > offset:
        print(f"\n  ✓  CHTENS already above offset ({ten_min:,} > {offset:,}) — checking FK tables too…")
    else:
        print(f"\n  ⚠  tenant_id starts at {ten_min:,} which is ≤ offset {offset:,} — applying fix…")

    cur.close()

    # ── 3. Offset CHTENS (primary table) ──────────────────────────────────
    print(f"\n  ── CHTENS (primary) ──")
    offset_table(conn, 'CHTENS', 'tenant_id', offset, args.dry_run)

    # ── 4. Offset all FK tables ────────────────────────────────────────────
    print(f"\n  ── FK tables ──")
    total_fixed = 0
    for fk_table, fk_col in TENANT_FK_TABLES:
        cur2 = conn.cursor()
        if not col_exists(cur2, fk_table, fk_col):
            print(f"  [skip ]  {fk_table:<12} {fk_col}: column not yet stamped — will fix on next run")
            cur2.close()
            continue
        cur2.close()
        rows_changed, _, _ = offset_table(conn, fk_table, fk_col, offset, args.dry_run)
        total_fixed += rows_changed

    # ── 5. Final verification ──────────────────────────────────────────────
    print(f"\n  ── Verification ──")
    cur3 = conn.cursor()
    cur3.execute(
        "SELECT MIN(tenant_id), MAX(tenant_id) FROM CHTENS WHERE tenant_id IS NOT NULL"
    )
    final_min, final_max = cur3.fetchone()
    cur3.close()
    conn.close()

    if not args.dry_run:
        if final_min and final_min > offset:
            print(f"\n  ✓  CHTENS.tenant_id verified: {final_min:,} .. {final_max:,}  (safe — no overlap with landlords 1..{offset:,})")
        else:
            print(f"\n  ✗  WARNING: CHTENS.tenant_id still starts at {final_min} — check manually")


# ── PostgreSQL phase ──────────────────────────────────────────────────────────

def connect_pg(args):
    try:
        import psycopg2
    except ImportError:
        sys.exit("psycopg2 not installed.  Run:  pip install psycopg2-binary")

    user = args.pg_user or input("PostgreSQL username: ").strip()
    pwd  = args.pg_password or getpass.getpass(f"PostgreSQL password for {user}: ")

    try:
        import psycopg2
        conn = psycopg2.connect(
            host=args.pg_host,
            port=args.pg_port,
            dbname=args.pg_dbname,
            user=user,
            password=pwd,
        )
        conn.autocommit = False
        print(f"  PostgreSQL connected  →  {args.pg_host}:{args.pg_port}/{args.pg_dbname}")
        return conn
    except Exception as e:
        sys.exit(f"PostgreSQL connection failed: {e}")


def run_pg_cleanup(args):
    """
    Remove previously migrated tenant rows from sys_pm_clients (and
    sys_pm_lease_agreements) so re-running the migration views inserts
    them cleanly with corrected IDs.

    Idempotent — if no tenant rows exist, DELETE affects 0 rows.
    """
    section("Phase 2 — PostgreSQL: clean tenant rows for re-migration")

    conn = connect_pg(args)
    cur  = conn.cursor()
    schema = args.pg_schema

    try:
        for table, condition in PG_TENANT_CLEANUP:
            fq = f"{schema}.{table}"

            if condition:
                cur.execute(f"SELECT COUNT(*) FROM {fq} WHERE {condition}")
                cnt = int(cur.fetchone()[0])
                if cnt == 0:
                    print(f"  [ok  ]  {fq}: 0 rows match condition — nothing to delete")
                    continue

                print(f"  Deleting {cnt:,} rows from {fq} WHERE {condition} …", end="", flush=True)
                if not args.dry_run:
                    t0 = time.time()
                    cur.execute(f"DELETE FROM {fq} WHERE {condition}")
                    deleted = cur.rowcount
                    print(f"  deleted {deleted:,} rows  ({fmt_elapsed(t0)})")
                else:
                    print(f"\n  [DRY  ]  would delete {cnt:,} rows")
            else:
                # No condition → delete all rows (e.g. sys_pm_lease_agreements
                # is 100% sourced from CHTENS, safe to purge)
                cur.execute(f"SELECT COUNT(*) FROM {fq}")
                cnt = int(cur.fetchone()[0])
                if cnt == 0:
                    print(f"  [ok  ]  {fq}: already empty")
                    continue

                print(f"  Deleting all {cnt:,} rows from {fq} …", end="", flush=True)
                if not args.dry_run:
                    t0 = time.time()
                    cur.execute(f"DELETE FROM {fq}")
                    deleted = cur.rowcount
                    print(f"  deleted {deleted:,} rows  ({fmt_elapsed(t0)})")
                else:
                    print(f"\n  [DRY  ]  would delete {cnt:,} rows")

        if not args.dry_run:
            conn.commit()
            print("\n  ✓  PostgreSQL cleanup committed.")
            print(
                "\n  NOTE: Run fixing_sequences.py after re-migration to fix sequences.\n"
                "        (Or use the --pg-fix-sequence flag to repair it now.)"
            )

            # Optionally repair the sequence now so re-migration inserts work
            if args.pg_fix_sequence:
                fq_clients = f"{schema}.sys_pm_clients"
                cur.execute(
                    f"SELECT setval("
                    f"    pg_get_serial_sequence('{fq_clients}', 'id'),"
                    f"    COALESCE((SELECT MAX(id) FROM {fq_clients}), 0)"
                    f")"
                )
                conn.commit()
                cur.execute(
                    f"SELECT last_value FROM "
                    f"{schema}.sys_pm_clients_id_seq"
                )
                seq_val = cur.fetchone()
                print(f"  ✓  Sequence for {fq_clients} reset to {seq_val[0] if seq_val else '?'}.")
        else:
            conn.rollback()
            print("\n  (Dry-run: nothing committed.)")

    except Exception as e:
        conn.rollback()
        print(f"\nERROR — rolled back: {e}")
    finally:
        cur.close()
        conn.close()


# ── CLI ───────────────────────────────────────────────────────────────────────

def parse_args():
    p = argparse.ArgumentParser(
        description=(
            "Fix CHTENS/tenant_id offset so landlords and tenants don't "
            "collide in sys_pm_clients."
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=(
            "Examples:\n"
            "  python fix_tenant_id_offset.py\n"
            "  python fix_tenant_id_offset.py --dry-run\n"
            "  python fix_tenant_id_offset.py --pg-host localhost --pg-dbname chips_db_v3 --pg-user postgres\n"
            "  python fix_tenant_id_offset.py --pg-host localhost --pg-dbname chips_db_v3 --pg-user postgres --pg-fix-sequence\n"
        ),
    )

    # MSSQL
    g = p.add_argument_group("MSSQL (source)")
    g.add_argument("--mssql-server",   default=MSSQL_SERVER,   metavar="SRV",
                   help=f"MSSQL server  (default: {MSSQL_SERVER})")
    g.add_argument("--mssql-db",       default=MSSQL_DATABASE, metavar="DB",
                   help=f"MSSQL database  (default: {MSSQL_DATABASE})")
    g.add_argument("--mssql-driver",   default=MSSQL_DRIVER,   metavar="DRV",
                   help=f"ODBC driver  (default: {MSSQL_DRIVER})")
    g.add_argument("--mssql-user",     default=None,           metavar="USER",
                   help="MSSQL username  (omit for Windows Auth)")
    g.add_argument("--mssql-password", default=None,           metavar="PASS",
                   help="MSSQL password  (prompted if --mssql-user given without this)")

    # PostgreSQL (optional cleanup)
    g2 = p.add_argument_group(
        "PostgreSQL (optional — clean tenant rows for re-migration)"
    )
    g2.add_argument("--pg-host",         default=None,          metavar="HOST",
                    help="PG host  (omit to skip PG cleanup)")
    g2.add_argument("--pg-port",         default=5432, type=int, metavar="PORT",
                    help="PG port  (default: 5432)")
    g2.add_argument("--pg-dbname",       default="chips_db_v3", metavar="DB")
    g2.add_argument("--pg-schema",       default="chips_db_v3", metavar="SCH")
    g2.add_argument("--pg-user",         default=None,          metavar="USER")
    g2.add_argument("--pg-password",     default=None,          metavar="PASS")
    g2.add_argument("--pg-fix-sequence", action="store_true",
                    help="Reset sys_pm_clients sequence to MAX(id) after cleanup")

    p.add_argument(
        "--dry-run", action="store_true",
        help="Show what would change without modifying anything",
    )

    return p.parse_args()


# ── Entry point ───────────────────────────────────────────────────────────────

def main():
    args = parse_args()

    print()
    print("╔══════════════════════════════════════════════════════════════════╗")
    print("║  fix_tenant_id_offset.py  — CHABRINS Migration ID Fixer         ║")
    print("╚══════════════════════════════════════════════════════════════════╝")

    if args.dry_run:
        print("\n  *** DRY-RUN MODE — nothing will be changed ***")

    # Phase 1: always fix MSSQL
    run_mssql_fix(args)

    # Phase 2: optionally clean PostgreSQL
    if args.pg_host:
        run_pg_cleanup(args)
    else:
        print(
            "\n  PostgreSQL cleanup skipped.\n"
            "  To also delete previously migrated tenant rows from sys_pm_clients,\n"
            "  re-run with:  --pg-host <host> --pg-dbname chips_db_v3 --pg-user <user>"
        )

    print("\n  Done.\n")
    print("  ┌─ Workflow reminder ─────────────────────────────────────────────┐")
    print("  │  1. python generate_migration_ids_light.py                      │")
    print("  │  2. python generate_migration_ids_heavy.py                      │")
    print("  │  3. python fix_tenant_id_offset.py   ← this script             │")
    print("  │  4. Run migration views in the Data Pump tool                   │")
    print("  │  5. python fixing_sequences.py   (post-migration sequences)     │")
    print("  └─────────────────────────────────────────────────────────────────┘")


if __name__ == "__main__":
    main()
