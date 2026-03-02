"""
Parse the mapping CSV and query both MSSQL (CHABRINS) and PostgreSQL (chips_db_v3)
to get column definitions for all mapped tables, then generate migration views.
"""
import csv
import re
import subprocess
import json

def parse_mapping_csv(path):
    """Parse the mapping CSV to extract MSSQL->PostgreSQL table pairs."""
    mappings = []
    
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # The CSV has a peculiar format: multi-line cells with quotes
    # Let's parse it by finding patterns: MSSQL table name -> PG table name
    # Pattern: **TABLE_NAME** in first column, table name in third column
    
    # Find all MSSQL table references with their PG targets
    # Format in col1: "-- **CHACCE**\n-- description\n-- Key columns: [...]"
    # Format in col3: sys_gl_accounts_entries  (or empty if unmapped)
    
    lines = content.split('\n')
    current_mssql = None
    current_desc = ''
    current_mssql_keys = []
    current_pg = None
    
    entries = []
    
    # Better approach: regex to find all MSSQL table names and their PG targets
    # Pattern: **TABLE** appears in first col, pg table in last col
    
    blocks = re.split(r'"--\s+\*\*', content)
    
    for block in blocks[1:]:  # skip first empty split
        # Get MSSQL table name
        m = re.match(r'(\w+)\*\*', block)
        if not m:
            continue
        mssql_table = m.group(1)
        
        # Get key columns from MSSQL
        km = re.search(r'Key columns:\s*\[([^\]]+)\]', block)
        mssql_keys = []
        if km:
            mssql_keys = [k.strip().strip('[]') for k in km.group(1).split(',')]
        
        # Get description
        desc_m = re.search(r'\*\*\s*(?:- .+?)?\n--\s*(.+?)(?:\n|$)', block)
        desc = desc_m.group(1).strip() if desc_m else ''
        
        # Get PG table (last field before next block)
        # The PG table is the last non-empty value in the CSV row
        pg_m = re.search(r'",(\w[\w_]*)\s*$', block, re.MULTILINE)
        if not pg_m:
            # Unmapped - no PG target
            entries.append({
                'mssql': mssql_table,
                'pg': None,
                'desc': desc,
                'mssql_keys': mssql_keys,
            })
            continue
        
        pg_table = pg_m.group(1)
        
        # Get PG key columns
        pk_m = re.search(r'\*\*Key columns:\*\*\s*(.+?)(?:\n|")', block)
        pg_keys = []
        if pk_m:
            pg_keys = [k.strip() for k in pk_m.group(1).split(',')]
        
        entries.append({
            'mssql': mssql_table,
            'pg': pg_table,
            'desc': desc,
            'mssql_keys': mssql_keys,
            'pg_keys': pg_keys,
        })
    
    return entries


def get_mssql_columns(table):
    """Get column names and types from MSSQL CHABRINS."""
    query = f"""
        SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = '{table}'
        ORDER BY ORDINAL_POSITION
    """
    result = subprocess.run(
        ['sqlcmd', '-S', 'localhost', '-d', 'CHABRINS', '-E', '-Q', query, '-W', '-s', '|', '-h-1'],
        capture_output=True, text=True
    )
    columns = []
    for line in result.stdout.strip().split('\n'):
        line = line.strip()
        if not line or 'rows affected' in line or line.startswith('-'):
            continue
        parts = [p.strip() for p in line.split('|')]
        if len(parts) >= 3:
            columns.append({
                'name': parts[0],
                'type': parts[1],
                'max_len': parts[2] if parts[2] != 'NULL' else None,
                'nullable': parts[3] if len(parts) > 3 else 'YES',
                'default': parts[4] if len(parts) > 4 else None,
            })
    return columns


def get_pg_columns(table):
    """Get column names and types from PostgreSQL chips_db_v3."""
    import os
    os.environ['PGPASSWORD'] = 'k3nn3th'
    query = f"""
        SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_schema = 'chips_db_v3' AND table_name = '{table}'
        ORDER BY ordinal_position
    """
    result = subprocess.run(
        ['psql', '-U', 'postgres', '-h', '127.0.0.1', '-d', 'chips_db_v3', '-t', '-A', '-F', '|', '-c', query],
        capture_output=True, text=True
    )
    columns = []
    for line in result.stdout.strip().split('\n'):
        line = line.strip()
        if not line:
            continue
        parts = [p.strip() for p in line.split('|')]
        if len(parts) >= 2:
            columns.append({
                'name': parts[0],
                'type': parts[1],
                'max_len': parts[2] if len(parts) > 2 and parts[2] else None,
                'nullable': parts[3] if len(parts) > 3 else 'YES',
                'default': parts[4] if len(parts) > 4 else None,
            })
    return columns


if __name__ == '__main__':
    csv_path = r'c:\CHIPS\chips_2025\mysql_postgres_views\mapping_mysql_postgres.csv'
    entries = parse_mapping_csv(csv_path)
    
    print(f"\nFound {len(entries)} entries in mapping CSV")
    print(f"Mapped: {sum(1 for e in entries if e['pg'])}")
    print(f"Unmapped: {sum(1 for e in entries if not e['pg'])}")
    print()
    
    # Get schemas for all mapped tables
    results = {}
    for e in entries:
        if not e['pg']:
            continue
        mssql = e['mssql']
        pg = e['pg']
        
        print(f"Fetching: {mssql} -> {pg}")
        mssql_cols = get_mssql_columns(mssql)
        pg_cols = get_pg_columns(pg)
        
        results[mssql] = {
            'mssql_table': mssql,
            'pg_table': pg,
            'desc': e['desc'],
            'mssql_keys': e.get('mssql_keys', []),
            'mssql_cols': mssql_cols,
            'pg_cols': pg_cols,
        }
    
    # Save to JSON for the view generator
    with open(r'c:\CHIPS\chips_2025\mysql_postgres_views\schema_data.json', 'w') as f:
        json.dump(results, f, indent=2)
    
    print(f"\nSaved schema data for {len(results)} table mappings to schema_data.json")
