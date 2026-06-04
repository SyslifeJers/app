CREATE TABLE IF NOT EXISTS checador_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    serial_no INT NOT NULL UNIQUE,
    employee_no VARCHAR(50) NOT NULL,
    nombre VARCHAR(255) NULL,
    fecha_hora DATETIME NOT NULL,
    door_no INT NULL,
    dispositivo VARCHAR(100) NULL,
    sucursal VARCHAR(100) NULL,
    fecha_sincronizacion DATETIME NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
