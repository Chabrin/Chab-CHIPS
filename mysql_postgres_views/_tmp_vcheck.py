import pyodbc
conn = pyodbc.connect(
    'DRIVER={ODBC Driver 17 for SQL Server};'
    'SERVER=localhost\\SQL2017;DATABASE=CHABRINS;Trusted_Connection=yes'
)
cur = conn.cursor()

# Latest values from each table
print('=== CHPVHH PVNO (latest 10) ===')
cur.execute("SELECT TOP 10 RTRIM(LTRIM(PVNO)) AS pv FROM CHPVHH ORDER BY PVNO DESC")
for r in cur.fetchall():
    print(f'  {r.pv}')

print('\n=== CHRHMS VOUCHERNO (latest 10) ===')
cur.execute("SELECT TOP 10 RTRIM(LTRIM(VOUCHERNO)) AS v FROM CHRHMS WHERE LEN(RTRIM(LTRIM(VOUCHERNO))) > 0 ORDER BY VOUCHERNO DESC")
for r in cur.fetchall():
    print(f'  {r.v}')

# Exact match
print('\n=== EXACT: VOUCHERNO = PVNO ===')
cur.execute("SELECT COUNT(*) FROM CHRHMS h JOIN CHPVHH p ON RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))")
print(f'  {cur.fetchone()[0]}')

# C prefix match
print('\n=== PREFIX: C + VOUCHERNO = PVNO ===')
cur.execute("SELECT COUNT(*) FROM CHRHMS h JOIN CHPVHH p ON 'C' + RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))")
print(f'  {cur.fetchone()[0]}')

# Strip C match
print('\n=== STRIP: VOUCHERNO = SUBSTRING(PVNO,2) ===')
cur.execute("SELECT COUNT(*) FROM CHRHMS h JOIN CHPVHH p ON RTRIM(LTRIM(h.VOUCHERNO)) = SUBSTRING(RTRIM(LTRIM(p.PVNO)),2,20)")
print(f'  {cur.fetchone()[0]}')

# Side-by-side matched
print('\n=== SIDE-BY-SIDE (C+PV match, 10 rows) ===')
cur.execute("""
    SELECT TOP 10
        RTRIM(LTRIM(h.VOUCHERNO)) AS rhms,
        RTRIM(LTRIM(p.PVNO)) AS pvhh,
        h.BLOCKID, h.YEARR, h.MONTHH,
        p.PVDESC, p.VENDNAM
    FROM CHRHMS h
    JOIN CHPVHH p ON 'C' + RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))
    ORDER BY h.YEARR DESC, h.MONTHH DESC
""")
for r in cur.fetchall():
    print(f'  CHRHMS={r.rhms:18s} CHPVHH={r.pvhh:18s} block={r.BLOCKID} {r.YEARR}/{r.MONTHH} desc={r.PVDESC} vendor={r.VENDNAM}')

conn.close()
