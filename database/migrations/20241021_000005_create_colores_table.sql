CREATE TABLE IF NOT EXISTS colores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    codigo_hex CHAR(7) NOT NULL,
    UNIQUE KEY colores_nombre_unique (nombre),
    UNIQUE KEY colores_codigo_hex_unique (codigo_hex)
);

ALTER TABLE Usuarios
    ADD COLUMN color_id INT NULL;

ALTER TABLE Usuarios
    ADD CONSTRAINT fk_usuarios_color
        FOREIGN KEY (color_id) REFERENCES colores (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL;

INSERT INTO colores (nombre, codigo_hex) VALUES
    ('Azul Cielo', '#ADD8E6'),
    ('Lavanda Suave', '#E6E6FA'),
    ('Verde Menta', '#B2F2BB'),
    ('Durazno Pastel', '#FFDAB9'),
    ('Amarillo Crema', '#FFFACD'),
    ('Rosa Empolvado', '#F8C8DC'),
    ('Celeste Pastel', '#B5E2FA'),
    ('Verde Salvia', '#CDE7BE'),
    ('Lila Pastel', '#D8B4FE'),
    ('Aqua Suave', '#B2EBF2')
ON DUPLICATE KEY UPDATE
    codigo_hex = VALUES(codigo_hex);
