-- Fix all PostgreSQL sequences to match the actual max id in each table
-- Run this against chips_db_v3 after data migration

SET search_path TO chips_db_v3;

DO $$
DECLARE
    rec RECORD;
    max_val BIGINT;
BEGIN
    FOR rec IN
        SELECT 
            n.nspname AS schema_name,
            t.relname AS table_name,
            a.attname AS column_name,
            pg_get_serial_sequence(format('%I.%I', n.nspname, t.relname), a.attname::text) AS sequence_name
        FROM pg_class t
        JOIN pg_attribute a ON a.attrelid = t.oid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        WHERE t.relkind = 'r'
          AND a.attnum > 0
          AND NOT a.attisdropped
          AND pg_get_serial_sequence(format('%I.%I', n.nspname, t.relname), a.attname::text) IS NOT NULL
          AND n.nspname = 'chips_db_v3'
        ORDER BY t.relname
    LOOP
        EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I.%I', rec.column_name, rec.schema_name, rec.table_name) INTO max_val;
        
        IF max_val > 0 THEN
            EXECUTE format('SELECT setval(%L, %s)', rec.sequence_name, max_val);
            RAISE NOTICE 'Fixed: %.% sequence set to %', rec.table_name, rec.column_name, max_val;
        ELSE
            RAISE NOTICE 'Skipped: %.% (table empty)', rec.table_name, rec.column_name;
        END IF;
    END LOOP;
END $$;
