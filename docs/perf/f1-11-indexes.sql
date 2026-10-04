-- F1-11: indices de performance para orders y account_entries.
-- NO EJECUTAR SIN REVISION HUMANA.
-- En produccion el camino normal es `php artisan migrate` (migracion
-- 2026_10_04_100000_add_performance_indexes_to_orders_and_account_entries).
-- Este SQL es solo una alternativa manual (si se corre a mano, la migracion
-- fallaria luego por indices duplicados: no mezclar ambos caminos).
--
-- ANTES: verificar que no existan para no duplicar:
--   SHOW INDEX FROM orders;
--   SHOW INDEX FROM account_entries;
-- DESPUES:
--   ANALYZE TABLE orders, account_entries;

CREATE INDEX orders_user_date_id_idx ON orders (id_user, date, id);
CREATE INDEX orders_date_id_idx ON orders (date, id);
CREATE INDEX account_entries_type_dir_occurred_idx ON account_entries (type, direction, occurred_at);
CREATE INDEX account_entries_occurred_idx ON account_entries (occurred_at);

-- Rollback manual:
-- DROP INDEX orders_user_date_id_idx ON orders;
-- DROP INDEX orders_date_id_idx ON orders;
-- DROP INDEX account_entries_type_dir_occurred_idx ON account_entries;
-- DROP INDEX account_entries_occurred_idx ON account_entries;
