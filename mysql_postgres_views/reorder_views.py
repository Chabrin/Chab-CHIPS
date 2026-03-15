"""
Reorder chabrins_to_postgres_views.sql:
  - Numbered views (_001 through _10) first, in numeric order
  - Remaining views after
Also generate other_views_mod.sql with numbered views
wrapped in EXEC sp_executesql for single-query execution.
"""
import re, os

SRC = os.path.join(os.path.dirname(__file__), 'chabrins_to_postgres_views.sql')
MOD = os.path.join(os.path.dirname(__file__), 'other_views_mod.sql')

with open(SRC, 'r', encoding='utf-8') as f:
    lines = f.readlines()

# ── 1. Split into header, sections, trailer ──────────────────────────────

# Header = everything before first "-- ======" section divider
# Trailer = the big /* ... */ comment block at the end

# Find start of first section (line starting with "-- ====")
header_end = 0
for i, line in enumerate(lines):
    if line.startswith('-- ====') and i > 10:  # skip any "====" inside the header comment
        header_end = i
        break

# Find start of trailing comment block
trailer_start = len(lines)
for i in range(len(lines) - 1, -1, -1):
    stripped = lines[i].strip()
    if stripped == '*/':
        # Walk backwards to find matching /*
        for j in range(i, -1, -1):
            if lines[j].strip().startswith('/*'):
                # Check if this is the migration loading order block
                block = ''.join(lines[j:i+1])
                if 'MIGRATION LOADING ORDER' in block or 'UNMAPPED TABLES' in block:
                    trailer_start = j
                    break
        break

header = lines[:header_end]
body = lines[header_end:trailer_start]
trailer = lines[trailer_start:]

# ── 2. Split body into sections ──────────────────────────────────────────
# Each section starts with "-- ====" divider line
sections = []
current = []
for line in body:
    if line.startswith('-- ====') and current:
        # Check if current already has a "-- ====" at the end (double divider)
        # Section headers are: "-- ====", "-- N. TITLE", "-- ===="
        # So we look for pattern: divider, title, divider
        # If current ends with just a divider line and we hit another, they form a pair
        if len(current) == 1 and current[0].startswith('-- ===='):
            current.append(line)
        elif len(current) >= 2 and current[-1].startswith('-- ===='):
            # Previous line was closing divider of a header pair — still in header
            current.append(line)
        else:
            # New section starting
            sections.append(current)
            current = [line]
    else:
        current.append(line)
if current:
    sections.append(current)

# ── 3. Identify numbered vs unnumbered sections ────────────────────────

# Find the CREATE OR ALTER VIEW line in each section and extract suffix number
def get_suffix_num(section_lines):
    """Return numeric suffix if view name ends with _NNN or _NN, else None."""
    for line in section_lines:
        m = re.search(r'CREATE\s+OR\s+ALTER\s+VIEW\s+\[?(\w+)\]?', line, re.IGNORECASE)
        if m:
            name = m.group(1)
            # Match _001 through _10 (or _010 etc.)
            sm = re.search(r'_0*(\d+)\s*$', name)
            if sm:
                return int(sm.group(1))
    return None

def get_view_name(section_lines):
    """Return the view name from CREATE OR ALTER VIEW."""
    for line in section_lines:
        m = re.search(r'CREATE\s+OR\s+ALTER\s+VIEW\s+\[?(\w+)\]?', line, re.IGNORECASE)
        if m:
            return m.group(1)
    return None

numbered = []
unnumbered = []

for sec in sections:
    num = get_suffix_num(sec)
    if num is not None:
        numbered.append((num, sec))
    else:
        unnumbered.append(sec)

numbered.sort(key=lambda x: x[0])

print(f"Header: {len(header)} lines")
print(f"Total sections: {len(sections)}")
print(f"Numbered views ({len(numbered)}):")
for num, sec in numbered:
    name = get_view_name(sec) or '??'
    print(f"  _{num:03d}  {name}  ({len(sec)} lines)")
print(f"Unnumbered views ({len(unnumbered)}):")
for sec in unnumbered:
    name = get_view_name(sec) or '(comment/no-view)'
    print(f"  {name}  ({len(sec)} lines)")
print(f"Trailer: {len(trailer)} lines")

# ── 4. Write reordered chabrins_to_postgres_views.sql ────────────────────

reordered = []
reordered.extend(header)
# Add a separator
reordered.append('\n')
reordered.append('-- ════════════════════════════════════════════════════════════════════════\n')
reordered.append('-- NUMBERED VIEWS (_001 to _010) — Migration-critical, ordered by suffix\n')
reordered.append('-- ════════════════════════════════════════════════════════════════════════\n')
reordered.append('\n')

for num, sec in numbered:
    reordered.extend(sec)

reordered.append('\n')
reordered.append('-- ════════════════════════════════════════════════════════════════════════\n')
reordered.append('-- REMAINING VIEWS — Supporting / supplementary\n')
reordered.append('-- ════════════════════════════════════════════════════════════════════════\n')
reordered.append('\n')

for sec in unnumbered:
    reordered.extend(sec)

reordered.extend(trailer)

with open(SRC, 'w', encoding='utf-8') as f:
    f.writelines(reordered)

total_lines = len(reordered)
print(f"\nWrote reordered {SRC}")
print(f"  {total_lines} lines total")

# ── 5. Generate other_views_mod.sql ──────────────────────────────────────
# Wrap each numbered view in EXEC sp_executesql N'...'
# Single quotes inside the SQL must be doubled.

mod_lines = []
mod_lines.append('-- ============================================================================\n')
mod_lines.append('-- OTHER MODULE: CREATE OR ALTER VIEW Scripts  (mod batch)\n')
mod_lines.append('-- ============================================================================\n')
mod_lines.append('-- Source:    chabrins_to_postgres_views.sql\n')
mod_lines.append('-- Database:  CHABRINS\n')
mod_lines.append('-- Generated: 2026-03-15\n')
mod_lines.append('--\n')
mod_lines.append('-- Execution: Run the entire file as ONE query (no GO separators).\n')
mod_lines.append('--            Each view is wrapped in EXEC sp_executesql so SQL Server\n')
mod_lines.append('--            treats each CREATE OR ALTER VIEW as its own batch internally.\n')
mod_lines.append('--\n')
mod_lines.append('-- View index\n')
for num, sec in numbered:
    name = get_view_name(sec) or '??'
    mod_lines.append(f'--   _{num:03d}  {name}\n')
mod_lines.append('-- ============================================================================\n')
mod_lines.append('\n')
mod_lines.append('USE CHABRINS;\n')
mod_lines.append('\n')

for num, sec in numbered:
    name = get_view_name(sec) or '??'
    # Extract just the CREATE OR ALTER VIEW ... ; statement
    # Skip section header comments, keep everything from CREATE to the semicolon or end
    in_view = False
    view_sql_lines = []
    trailing_comment = []
    past_view = False

    for line in sec:
        if not in_view and not past_view:
            if re.match(r'\s*CREATE\s+OR\s+ALTER\s+VIEW', line, re.IGNORECASE):
                in_view = True
                view_sql_lines.append(line)
            # Skip header/comment lines before CREATE
        elif in_view:
            # Check for GO or end markers
            stripped = line.strip()
            if stripped == 'GO':
                in_view = False
                past_view = True
            elif stripped.startswith('/*') and not stripped.endswith('*/'):
                # Multi-line comment after view = trailing comment
                in_view = False
                past_view = True
                trailing_comment.append(line)
            else:
                view_sql_lines.append(line)
        elif past_view:
            trailing_comment.append(line)

    # Build the SQL string, removing trailing whitespace and ensuring semicolon
    view_sql = ''.join(view_sql_lines).rstrip()
    # Ensure it ends with semicolon
    if not view_sql.endswith(';'):
        view_sql += ';'

    # Double all single quotes for sp_executesql
    escaped_sql = view_sql.replace("'", "''")

    # Write section header
    mod_lines.append(f'-- ============================================================================\n')
    mod_lines.append(f'-- _{num:03d}  {name}\n')
    mod_lines.append(f'-- ============================================================================\n')
    mod_lines.append(f"EXEC sp_executesql N'\n")
    mod_lines.append(escaped_sql)
    mod_lines.append("\n';\n\n")

with open(MOD, 'w', encoding='utf-8') as f:
    f.writelines(mod_lines)

print(f"\nWrote {MOD}")
print(f"  {len(mod_lines)} lines, {len(numbered)} views")
print("\nDone!")
