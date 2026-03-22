DO $$
DECLARE
    seq_rec RECORD;
    max_val BIGINT;
    cur_val BIGINT;
    tbl_name TEXT;
BEGIN
    SET search_path TO chips_db_v3;
    FOR seq_rec IN
        SELECT s.relname AS seq_name
        FROM pg_class s
        JOIN pg_namespace n ON n.oid = s.relnamespace
        WHERE s.relkind = 'S' AND n.nspname = 'chips_db_v3' AND s.relname LIKE 'cb_%'
        ORDER BY s.relname
    LOOP
        tbl_name := replace(seq_rec.seq_name, '_id_seq', '');
        EXECUTE format('SELECT COALESCE(MAX(id), 0) FROM %I', tbl_name) INTO max_val;
        EXECUTE format('SELECT last_value FROM %I', seq_rec.seq_name) INTO cur_val;
        IF max_val > cur_val THEN
            EXECUTE format('SELECT setval(''%I'', %s)', seq_rec.seq_name, max_val);
            RAISE NOTICE 'FIXED: % seq was % now %', tbl_name, cur_val, max_val;
        ELSE
            RAISE NOTICE 'OK: % seq=% max_id=%', tbl_name, cur_val, max_val;
        END IF;
    END LOOP;
END $$;
