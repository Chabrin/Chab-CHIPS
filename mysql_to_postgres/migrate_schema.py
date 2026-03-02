#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
MySQL to PostgreSQL Schema Migrator
Converts all MySQL tables from chips_2 to PostgreSQL chips_db_v3
Tables only (no data) with proper SERIAL primary keys
"""

import sys
import logging
import mysql.connector
import psycopg2
from psycopg2 import sql as pgsql

# ──────────────────────────────────────────────
# CONFIG
# ──────────────────────────────────────────────
MYSQL_CONFIG = {
    'host': '127.0.0.1',
    'port': 3310,
    'user': 'root',
    'password': '',
    'database': 'chips_2',
}

PG_CONFIG = {
    'host': '127.0.0.1',
    'port': 5432,
    'user': 'postgres',
    'password': 'k3nn3th',
    'dbname': 'chips_db_v3',
}

PG_SCHEMA = 'chips_db_v3'

# ──────────────────────────────────────────────
# LOGGING
# ──────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s  %(levelname)-8s  %(message)s',
    handlers=[
        logging.FileHandler('migration.log', encoding='utf-8'),
        logging.StreamHandler(sys.stdout),
    ],
)
log = logging.getLogger(__name__)

# ──────────────────────────────────────────────
# MySQL -> PostgreSQL TYPE MAP
# ──────────────────────────────────────────────
TYPE_MAP = {
    # integers
    'tinyint':    'SMALLINT',
    'smallint':   'SMALLINT',
    'mediumint':  'INTEGER',
    'int':        'INTEGER',
    'bigint':     'BIGINT',
    # float / decimal
    'float':      'REAL',
    'double':     'DOUBLE PRECISION',
    'decimal':    'NUMERIC',
    # strings
    'char':       'CHAR',
    'varchar':    'VARCHAR',
    'tinytext':   'TEXT',
    'text':       'TEXT',
    'mediumtext': 'TEXT',
    'longtext':   'TEXT',
    # binary / blob
    'mediumblob': 'BYTEA',
    'varbinary':  'BYTEA',
    # date / time
    'date':       'DATE',
    'datetime':   'TIMESTAMP',
    'timestamp':  'TIMESTAMP',
    'time':       'TIME',
    # json
    'json':       'JSONB',
    # enum -> VARCHAR (we'll extract values as CHECK or just use VARCHAR)
    'enum':       'VARCHAR(255)',
}


class SchemaMigrator:
    """Reads MySQL schema and creates equivalent PostgreSQL tables."""

    def __init__(self):
        self.my_conn = None
        self.pg_conn = None
        self.my_cur = None
        self.pg_cur = None

    # ── connections ──────────────────────────────

    def connect_mysql(self):
        self.my_conn = mysql.connector.connect(**MYSQL_CONFIG)
        self.my_cur = self.my_conn.cursor(dictionary=True)
        self.my_cur.execute("SET SESSION sql_mode = ''")
        log.info(f"Connected to MySQL: {MYSQL_CONFIG['database']}")

    def connect_postgres(self):
        self.pg_conn = psycopg2.connect(**PG_CONFIG)
        self.pg_conn.autocommit = True
        self.pg_cur = self.pg_conn.cursor()
        log.info(f"Connected to PostgreSQL: {PG_CONFIG['dbname']}")

    def close(self):
        for c in (self.my_cur, self.pg_cur):
            if c:
                c.close()
        for c in (self.my_conn, self.pg_conn):
            if c:
                c.close()
        log.info("Connections closed")

    # ── schema setup ─────────────────────────────

    def ensure_pg_schema(self):
        self.pg_cur.execute(
            f'CREATE SCHEMA IF NOT EXISTS "{PG_SCHEMA}"'
        )
        log.info(f"PostgreSQL schema '{PG_SCHEMA}' ready")

    # ── read MySQL metadata ─────────────────────

    def get_tables(self):
        self.my_cur.execute("""
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = %s
              AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        """, (MYSQL_CONFIG['database'],))
        return [r['TABLE_NAME'] for r in self.my_cur.fetchall()]

    def get_columns(self, table):
        self.my_cur.execute("""
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                COLUMN_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                EXTRA,
                COLUMN_KEY,
                ORDINAL_POSITION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
            ORDER BY ORDINAL_POSITION
        """, (MYSQL_CONFIG['database'], table))
        return self.my_cur.fetchall()

    def get_indexes(self, table):
        self.my_cur.execute("""
            SELECT
                INDEX_NAME,
                NON_UNIQUE,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns,
                INDEX_TYPE
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
            GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
            ORDER BY INDEX_NAME
        """, (MYSQL_CONFIG['database'], table))
        return self.my_cur.fetchall()

    # ── type conversion ─────────────────────────

    def convert_type(self, col, is_auto_increment):
        """Convert a MySQL column definition to PostgreSQL type."""
        dtype = col['DATA_TYPE'].lower()
        col_type_full = col['COLUMN_TYPE'].lower() if col['COLUMN_TYPE'] else ''

        # AUTO_INCREMENT -> SERIAL / BIGSERIAL
        if is_auto_increment:
            if dtype == 'bigint':
                return 'BIGSERIAL'
            return 'SERIAL'

        # tinyint(1) -> BOOLEAN
        if dtype == 'tinyint' and '(1)' in col_type_full:
            return 'BOOLEAN'

        # unsigned integers -> next size up (PG has no unsigned)
        unsigned = 'unsigned' in col_type_full
        if unsigned:
            if dtype == 'tinyint':
                return 'SMALLINT'
            if dtype == 'smallint':
                return 'INTEGER'
            if dtype == 'mediumint':
                return 'INTEGER'
            if dtype == 'int':
                return 'BIGINT'
            if dtype == 'bigint':
                return 'BIGINT'  # no larger, keep BIGINT

        # decimal / numeric with precision
        if dtype == 'decimal':
            p = col['NUMERIC_PRECISION'] or 10
            s = col['NUMERIC_SCALE'] or 0
            return f'NUMERIC({p},{s})'

        # varchar / char with length
        if dtype in ('varchar', 'char'):
            length = col['CHARACTER_MAXIMUM_LENGTH']
            if length and length <= 10485760:
                base = TYPE_MAP.get(dtype, 'VARCHAR')
                return f'{base}({length})'
            return 'TEXT'

        # enum -> VARCHAR(255)
        if dtype == 'enum':
            return 'VARCHAR(255)'

        return TYPE_MAP.get(dtype, 'TEXT')

    # ── default conversion ──────────────────────

    def convert_default(self, col, pg_type):
        """Convert MySQL default value to PostgreSQL."""
        default = col['COLUMN_DEFAULT']
        extra = (col['EXTRA'] or '').lower()

        # SERIAL columns don't need explicit default
        if 'auto_increment' in extra:
            return None

        if default is None:
            return None

        default_str = str(default)

        # CURRENT_TIMESTAMP
        if default_str.upper() in ('CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()'):
            return 'CURRENT_TIMESTAMP'

        # Invalid MySQL dates -> safe PostgreSQL defaults
        if pg_type in ('TIMESTAMP', 'DATE', 'TIME'):
            if default_str in ('0000-00-00 00:00:00', '0000-00-00', '00:00:00'):
                nullable = col['IS_NULLABLE'] == 'YES'
                if nullable:
                    return 'NULL'
                if pg_type == 'DATE':
                    return "'1970-01-01'"
                if pg_type == 'TIME':
                    return "'00:00:00'"
                return "'1970-01-01 00:00:00'"

        # Boolean
        if pg_type == 'BOOLEAN':
            if default_str in ('1', "'1'", 'b\'1\''):
                return 'TRUE'
            if default_str in ('0', "'0'", 'b\'0\''):
                return 'FALSE'
            return None

        # Numeric types
        if pg_type in ('SMALLINT', 'INTEGER', 'BIGINT', 'REAL', 'DOUBLE PRECISION') or pg_type.startswith('NUMERIC'):
            try:
                float(default_str)
                return default_str
            except (ValueError, TypeError):
                return None

        # NULL string
        if default_str.upper() == 'NULL':
            return None

        # String default
        safe = default_str.replace("'", "''")
        return f"'{safe}'"

    # ── build CREATE TABLE ──────────────────────

    def build_create_table(self, table, columns, indexes):
        """Build a PostgreSQL CREATE TABLE statement."""
        col_defs = []
        pk_columns = []

        for col in columns:
            name = col['COLUMN_NAME']
            extra = (col['EXTRA'] or '').lower()
            is_auto = 'auto_increment' in extra
            pg_type = self.convert_type(col, is_auto)
            nullable = col['IS_NULLABLE'] == 'YES'

            parts = [f'    "{name}"', pg_type]

            # NOT NULL (SERIAL is implicitly NOT NULL)
            if not nullable and 'SERIAL' not in pg_type:
                parts.append('NOT NULL')

            # DEFAULT
            default = self.convert_default(col, pg_type)
            if default is not None:
                parts.append(f'DEFAULT {default}')

            col_defs.append(' '.join(parts))

            # Track primary key columns
            if col['COLUMN_KEY'] == 'PRI':
                pk_columns.append(f'"{name}"')

        # Primary key constraint
        if pk_columns:
            col_defs.append(f'    PRIMARY KEY ({", ".join(pk_columns)})')

        # Unique constraints from indexes
        for idx in indexes:
            if idx['INDEX_NAME'] == 'PRIMARY':
                continue
            if idx['NON_UNIQUE'] == 0:
                idx_cols = [f'"{c.strip()}"' for c in idx['columns'].split(',')]
                constraint_name = f"uq_{table}_{idx['INDEX_NAME']}"
                # Truncate long constraint names (PG limit 63 chars)
                if len(constraint_name) > 63:
                    constraint_name = constraint_name[:63]
                col_defs.append(f'    CONSTRAINT "{constraint_name}" UNIQUE ({", ".join(idx_cols)})')

        cols_sql = ',\n'.join(col_defs)
        ddl = f'CREATE TABLE "{PG_SCHEMA}"."{table}" (\n{cols_sql}\n)'
        return ddl

    # ── build indexes ───────────────────────────

    def build_indexes(self, table, indexes):
        """Build CREATE INDEX statements for non-unique, non-primary indexes."""
        stmts = []
        for idx in indexes:
            if idx['INDEX_NAME'] == 'PRIMARY':
                continue
            if idx['NON_UNIQUE'] == 0:
                continue  # already handled as UNIQUE constraint

            idx_cols = [f'"{c.strip()}"' for c in idx['columns'].split(',')]
            idx_name = f"idx_{table}_{idx['INDEX_NAME']}"
            if len(idx_name) > 63:
                idx_name = idx_name[:63]

            stmts.append(
                f'CREATE INDEX "{idx_name}" ON "{PG_SCHEMA}"."{table}" ({", ".join(idx_cols)})'
            )
        return stmts

    # ── migrate one table ───────────────────────

    def migrate_table(self, table):
        """Migrate a single table schema."""
        columns = self.get_columns(table)
        indexes = self.get_indexes(table)

        if not columns:
            log.warning(f"  Skipping {table}: no columns found")
            return False

        # Drop if exists
        self.pg_cur.execute(f'DROP TABLE IF EXISTS "{PG_SCHEMA}"."{table}" CASCADE')

        # Create table
        ddl = self.build_create_table(table, columns, indexes)
        try:
            self.pg_cur.execute(ddl)
        except Exception as e:
            log.error(f"  [FAILED] {table}: {e}")
            log.error(f"  DDL was:\n{ddl}")
            return False

        # Create indexes
        for idx_sql in self.build_indexes(table, indexes):
            try:
                self.pg_cur.execute(idx_sql)
            except Exception as e:
                log.warning(f"  Index warning on {table}: {e}")

        return True

    # ── run migration ───────────────────────────

    def run(self):
        log.info("Starting MySQL -> PostgreSQL schema migration")
        self.connect_mysql()
        self.connect_postgres()
        self.ensure_pg_schema()

        tables = self.get_tables()
        log.info(f"Found {len(tables)} tables to migrate\n")

        successful = []
        failed = []

        for idx, table in enumerate(tables, 1):
            print(f"[{idx}/{len(tables)}] {table}")

            if self.migrate_table(table):
                successful.append(table)
                log.info(f"  [OK] {table}")
            else:
                failed.append(table)

            if idx % 50 == 0:
                print(f"\n--- Checkpoint: {idx}/{len(tables)} ---\n")

        # Summary
        print(f"\n{'='*60}")
        print("MIGRATION SUMMARY")
        print(f"{'='*60}")
        print(f"Total tables:    {len(tables)}")
        print(f"Successful:      {len(successful)}")
        print(f"Failed:          {len(failed)}")

        if failed:
            print(f"\nFailed tables:")
            for t in failed:
                print(f"  - {t}")

        print(f"\nLog: migration.log")
        print(f"{'='*60}\n")

        self.close()


def main():
    print("""
============================================================
  MySQL -> PostgreSQL Schema Migrator
  Source:  MySQL chips_2
  Target:  PostgreSQL chips_db_v3.chips_db_v3
  Mode:    Tables only (no data)
============================================================
    """)
    try:
        migrator = SchemaMigrator()
        migrator.run()
    except KeyboardInterrupt:
        print("\nInterrupted!")
        sys.exit(1)
    except Exception as e:
        log.error(f"Fatal: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
