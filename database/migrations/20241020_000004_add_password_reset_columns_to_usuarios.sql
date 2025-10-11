ALTER TABLE Usuarios
    ADD COLUMN reset_token VARCHAR(255) NULL AFTER token,
    ADD COLUMN reset_token_expiration DATETIME NULL AFTER reset_token;
