-- Script para crear tablas de reuniones internas
-- Ejecutar sobre la base de datos de la aplicación.

CREATE TABLE IF NOT EXISTS ReunionInterna (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    inicio DATETIME NOT NULL,
    fin DATETIME NOT NULL,
    creado_por INT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reunion_inicio (inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ReunionInternaPsicologo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    psicologo_id INT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reunion_psicologo (reunion_id, psicologo_id),
    INDEX idx_psicologo (psicologo_id),
    CONSTRAINT fk_reunion_interna FOREIGN KEY (reunion_id) REFERENCES ReunionInterna(id) ON DELETE CASCADE,
    CONSTRAINT fk_reunion_psicologo FOREIGN KEY (psicologo_id) REFERENCES Usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
