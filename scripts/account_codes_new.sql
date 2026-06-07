

WITH numbered AS (
    SELECT
        ctid,
        (ROW_NUMBER() OVER (ORDER BY account_code) + 1000)::text AS new_code
    FROM sys_gl_chart_accounts_master
)
UPDATE sys_gl_chart_accounts_master t
SET account_code = numbered.new_code
FROM numbered
WHERE t.ctid = numbered.ctid; 




