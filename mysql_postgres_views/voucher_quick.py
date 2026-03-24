import pyodbc
conn = pyodbc.connect(
    'DRIVER={ODBC Driver 17 for SQL Server};'
    'SERVER=localhost\\SQL2017;DATABASE=CHABRINS;Trusted_Connection=yes'
)
cur = conn.cursor()

print('--- CHPVHH PVNO latest 5 ---')
cur.execute("SELECT TOP 5 RTRIM(LTRIM(PVNO)) FROM CHPVHH ORDER BY PVNO DESC")
for r in cur.fetchall():
    print(f'  CHPVHH: {r[0]}')

print('--- CHRHMS VOUCHERNO latest 5 ---')
cur.execute("""SELECT TOP 5 RTRIM(LTRIM(VOUCHERNO)) FROM CHRHMS
              WHERE LEN(RTRIM(LTRIM(ISNULL(VOUCHERNO,''))))>0 ORDER BY VOUCHERNO DESC""")
for r in cur.fetchall():
    print(f'  CHRHMS: {r[0]}')

print('--- EXACT match count ---')
cur.execute("""SELECT COUNT(*) FROM CHRHMS h
              JOIN CHPVHH p ON RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))""")
print(f'  Exact: {cur.fetchone()[0]}')

print('--- C prefix match: C + VOUCHERNO = PVNO ---')
cur.execute("""SELECT COUNT(*) FROM CHRHMS h
              JOIN CHPVHH p ON 'C' + RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))
              WHERE LEN(RTRIM(LTRIM(ISNULL(h.VOUCHERNO,''))))>0""")
print(f'  C+PV: {cur.fetchone()[0]}')

print('--- Side-by-side (C+PV match) ---')
cur.execute("""SELECT TOP 5
    RTRIM(LTRIM(h.VOUCHERNO)),
    RTRIM(LTRIM(p.PVNO)),
    RTRIM(LTRIM(h.BLOCKID)),
    h.YEARR, h.MONTHH,
    p.VENDNAM
FROM CHRHMS h
JOIN CHPVHH p ON 'C' + RTRIM(LTRIM(h.VOUCHERNO)) = RTRIM(LTRIM(p.PVNO))
WHERE LEN(RTRIM(LTRIM(ISNULL(h.VOUCHERNO,''))))>0
ORDER BY h.YEARR DESC, h.MONTHH DESC""")
for r in cur.fetchall():
    print(f'  CHRHMS={r[0]:18s} CHPVHH={r[1]:18s} block={r[2]} {r[3]}/{r[4]} vendor={r[5]}')

print('--- CHCHQ.PAYVOUC match ---')
cur.execute("""SELECT COUNT(DISTINCT RTRIM(LTRIM(c.PAYVOUC)))
FROM CHCHQ c
JOIN CHRHMS h ON RTRIM(LTRIM(c.PAYVOUC)) = RTRIM(LTRIM(h.VOUCHERNO))
WHERE LEN(RTRIM(LTRIM(ISNULL(c.PAYVOUC,''))))>0""")
print(f'  CHCHQ.PAYVOUC matched to CHRHMS: {cur.fetchone()[0]}')

conn.close()
