ALTER TABLE visitas ADD COLUMN IF NOT EXISTS operador_salida_id INT NULL;
ALTER TABLE visitas ADD INDEX IF NOT EXISTS idx_visitas_operador_salida (operador_salida_id);
ALTER TABLE visitas ADD CONSTRAINT fk_visitas_operador_salida FOREIGN KEY IF NOT EXISTS (operador_salida_id) REFERENCES usuarios(id);