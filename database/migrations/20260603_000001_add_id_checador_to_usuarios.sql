ALTER TABLE Usuarios
    ADD COLUMN id_checador VARCHAR(50) NULL AFTER correo;

CREATE INDEX idx_usuarios_id_checador ON Usuarios (id_checador);
