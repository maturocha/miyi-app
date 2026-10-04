-- F1-12: corregir pedidos con fecha cero ('0000-00-00').
-- PARA REVISION HUMANA. NO EJECUTAR SIN REVISION. Hacer backup antes.

-- 1) Diagnostico (hoy: 2 filas, ids 27823 y 27863, created_at 2022-02-02):
SELECT id, date, created_at FROM orders WHERE date = '0000-00-00';

-- 2) Correccion (descomentar tras revisar el diagnostico):
-- START TRANSACTION;
-- UPDATE orders
--    SET date = DATE(created_at)
--  WHERE date = '0000-00-00' AND created_at IS NOT NULL;
-- -- Verificar: debe ser 0 filas
-- SELECT id, date, created_at FROM orders WHERE date = '0000-00-00';
-- COMMIT;   -- o ROLLBACK;
