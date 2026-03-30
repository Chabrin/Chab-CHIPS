"""
fixing_sequences.py
-------------------
Fix all PostgreSQL sequences in chips_db_v3 so that each sequence's
current value equals (or exceeds) the actual MAX(id) in the linked table.

Two-phase approach:
  Phase 1 — General:  discovers sequences via pg_get_serial_sequence() for
             every serial-backed column in the target schema, then advances
             the sequence when MAX(col) > last_value.
  Phase 2 — cb_ sweep: scans pg_class for sequences named cb_* that were
             NOT already handled in phase 1 (e.g. detached or renamed
             sequences), derives the table name by stripping '_id_seq',
             and advances if needed.

Both phases only call setval() when the table's max id *exceeds* the
current sequence value — they never lower a sequence.

Usage:
    python fixing_sequences.py [OPTIONS]

Options (all optional, will prompt if not supplied):
    --host      HOST      Postgres host           (default: localhost)
    --port      PORT      Postgres port           (default: 5432)
    --dbname    DBNAME    Database name           (default: chips_db_v3)
    --schema    SCHEMA    Target schema           (default: chips_db_v3)
    --user      USER      Postgres username       (will prompt if omitted)
    --password  PASS      Postgres password       (will prompt if omitted)
    --dry-run             Print what would change, but do NOT call setval()
"""

import argparse
import getpass
import sys

try:
    import psycopg2
    import psycopg2.extras
except ImportError:
    sys.exit("psycopg2 is not installed.  Run:  pip install psycopg2-binary")


# ── CLI ───────────────────────────────────────────────────────────────────────

def parse_args():
    p = argparse.ArgumentParser(
        description="Fix PostgreSQL sequences to match actual table max IDs."
    )
    p.add_argument("--host",     default="localhost")
    p.add_argument("--port",     type=int, default=5432)
    p.add_argument("--dbname",   default="chips_db_v3")
    p.add_argument("--schema",   default="chips_db_v3")
    p.add_argument("--user",     default=None)
    p.add_argument("--password", default=None)
    p.add_argument("--dry-run",  action="store_true",
                   help="Show what would change without modifying anything")
    return p.parse_args()


# ── Helpers ───────────────────────────────────────────────────────────────────

def connect(args):
    user = args.user or input("Postgres username: ").strip()
    password = args.password or getpass.getpass("Postgres password: ")
    try:
        conn = psycopg2.connect(
            host=args.host,
            port=args.port,
            dbname=args.dbname,
            user=user,
            password=password,
        )
        conn.autocommit = False
        return conn
    except psycopg2.OperationalError as e:
        sys.exit(f"Connection failed: {e}")


def get_last_value(cur, seq_name):
    """Return the current last_value of a sequence."""
    cur.execute(f"SELECT last_value FROM {seq_name}")
    return cur.fetchone()[0]


def fix_sequence(cur, seq_name, table_ref, column, max_val, dry_run, phase):
    """Advance sequence to max_val if needed.  Returns 'fixed'|'ok'|'empty'."""
    cur_val = get_last_value(cur, seq_name)

    if max_val == 0:
        print(f"  [EMPTY ]  ({phase}) {table_ref}.{column}  seq={seq_name}  (table empty, skipped)")
        return "empty"

    if max_val > cur_val:
        if not dry_run:
            cur.execute("SELECT setval(%s, %s)", (seq_name, max_val))
        tag = "DRY-RUN" if dry_run else "FIXED  "
        print(f"  [{tag}]  ({phase}) {table_ref}.{column}  seq was {cur_val:,} → now {max_val:,}")
        return "fixed"
    else:
        print(f"  [OK    ]  ({phase}) {table_ref}.{column}  seq={cur_val:,}  max={max_val:,}")
        return "ok"


# ── Phase 1: general – all serial-backed columns ──────────────────────────────

PHASE1_SQL = """
SELECT
    n.nspname                                                       AS schema_name,
    t.relname                                                       AS table_name,
    a.attname                                                       AS column_name,
    pg_get_serial_sequence(
        format('%%I.%%I', n.nspname, t.relname), a.attname::text)  AS sequence_name
FROM pg_class     t
JOIN pg_attribute a ON a.attrelid = t.oid
JOIN pg_namespace n ON n.oid      = t.relnamespace
WHERE t.relkind = 'r'
  AND a.attnum  > 0
  AND NOT a.attisdropped
  AND pg_get_serial_sequence(
        format('%%I.%%I', n.nspname, t.relname), a.attname::text) IS NOT NULL
  AND n.nspname = %s
ORDER BY t.relname, a.attname
"""

def phase1(conn, schema, dry_run):
    print(f"\n{'─'*60}")
    print(f"  Phase 1 — General serial-column discovery  (schema: {schema})")
    print(f"{'─'*60}")

    cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute(PHASE1_SQL, (schema,))
    rows = cur.fetchall()

    handled_seqs = set()
    counts = {"fixed": 0, "ok": 0, "empty": 0}

    for row in rows:
        seq   = row["sequence_name"]   # fully-qualified, e.g. chips_db_v3.foo_id_seq
        tbl   = f"{row['schema_name']}.{row['table_name']}"
        col   = row["column_name"]

        cur2 = conn.cursor()
        cur2.execute(
            f"SELECT COALESCE(MAX({psycopg2.extensions.quote_ident(col, cur2)}), 0) "
            f"FROM {tbl}"
        )
        max_val = cur2.fetchone()[0]
        cur2.close()

        result = fix_sequence(cur, seq, tbl, col, max_val, dry_run, "P1")
        counts[result] += 1
        handled_seqs.add(seq)

    cur.close()
    print(f"\n  Phase 1 summary: {counts['fixed']} fixed,  "
          f"{counts['ok']} already ok,  {counts['empty']} empty tables")
    return handled_seqs


# ── Phase 2: cb_* safety sweep ────────────────────────────────────────────────

PHASE2_SQL = """
SELECT
    format('%%I.%%I', n.nspname, s.relname) AS seq_full,
    s.relname                               AS seq_name
FROM pg_class     s
JOIN pg_namespace n ON n.oid = s.relnamespace
WHERE s.relkind  = 'S'
  AND n.nspname  = %s
  AND s.relname LIKE 'cb\\_%%'
ORDER BY s.relname
"""

def phase2(conn, schema, handled_seqs, dry_run):
    print(f"\n{'─'*60}")
    print(f"  Phase 2 — cb_* sequence sweep  (schema: {schema})")
    print(f"{'─'*60}")

    cur = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    cur.execute(PHASE2_SQL, (schema,))
    rows = cur.fetchall()

    counts = {"fixed": 0, "ok": 0, "empty": 0, "skipped": 0}

    for row in rows:
        seq_full = row["seq_full"]   # schema-qualified

        if seq_full in handled_seqs:
            print(f"  [SKIP  ]  (P2) {seq_full}  (already handled in Phase 1)")
            counts["skipped"] += 1
            continue

        # Derive table name: strip trailing _id_seq
        seq_bare = row["seq_name"]
        tbl_bare = seq_bare[:-len("_id_seq")] if seq_bare.endswith("_id_seq") else seq_bare
        tbl_full = f"{schema}.{tbl_bare}"

        # Check the table exists before querying it
        cur2 = conn.cursor()
        cur2.execute(
            "SELECT 1 FROM information_schema.tables "
            "WHERE table_schema = %s AND table_name = %s",
            (schema, tbl_bare),
        )
        if not cur2.fetchone():
            print(f"  [WARN  ]  (P2) {seq_full}  → table '{tbl_full}' not found, skipped")
            cur2.close()
            counts["skipped"] += 1
            continue

        cur2.execute(f"SELECT COALESCE(MAX(id), 0) FROM {tbl_full}")
        max_val = cur2.fetchone()[0]
        cur2.close()

        result = fix_sequence(cur, seq_full, tbl_full, "id", max_val, dry_run, "P2")
        counts[result] += 1

    cur.close()
    print(f"\n  Phase 2 summary: {counts['fixed']} fixed,  "
          f"{counts['ok']} already ok,  {counts['empty']} empty,  "
          f"{counts['skipped']} skipped")


# ── Main ──────────────────────────────────────────────────────────────────────

def main():
    args = parse_args()

    if args.dry_run:
        print("*** DRY-RUN MODE — no sequences will be changed ***")

    print(f"\nConnecting to {args.host}:{args.port}/{args.dbname} …")
    conn = connect(args)
    print("Connected.\n")

    try:
        handled = phase1(conn, args.schema, args.dry_run)
        phase2(conn, args.schema, handled, args.dry_run)

        if not args.dry_run:
            conn.commit()
            print("\n✓  All changes committed.")
        else:
            conn.rollback()
            print("\n(Dry-run: nothing committed.)")
    except Exception as e:
        conn.rollback()
        sys.exit(f"\nError — rolled back: {e}")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
