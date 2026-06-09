# Documentacion del checador Hikvision

## Equipo

- Modelo: `DS-K1T320MFWX-B`
- IP actual: `192.168.0.184`
- Usuario actual: `admin`
- Contrasena actual: `H.246810`
- Numero de serie: pendiente de documentar fisicamente o desde la interfaz del equipo
- Sucursal default: `Puebla Centro`

## Archivos principales

- App C#: `EventosChecador/EventosChecador/Program.cs`
- Accesos C#: `EventosChecador/EventosChecador/accesos.json`
- API PHP: `api/checador/subir_log.php`
- Tabla MySQL: `checador_eventos`
- Migracion tabla eventos: `database/migrations/20260603_000000_create_checador_eventos_table.sql`
- Migracion usuarios: `database/migrations/20260603_000001_add_id_checador_to_usuarios.sql`
- Pantalla eventos usuario: `Usuarios/checador_eventos.php`

## Configuracion C#

El archivo `accesos.json` contiene los accesos y datos principales del equipo:

```json
{
  "ip": "192.168.0.184",
  "usuario": "admin",
  "password": "H.246810",
  "equipo": "DS-K1T320MFWX-B",
  "sucursal": "Puebla Centro",
  "urlApi": "https://app.clinicacerene.com/api/checador/subir_log.php"
}
```

La app tambien permite sobrescribir valores usando variables de entorno:

- `HIKVISION_IP`
- `HIKVISION_USER`
- `HIKVISION_PASSWORD`
- `CHECADOR_API_URL`
- `CHECADOR_EQUIPO`
- `CHECADOR_SUCURSAL`
- `CHECADOR_INICIO`
- `CHECADOR_FIN`

## Protocolo de consulta al checador

El equipo se consulta por HTTP usando Hikvision ISAPI con autenticacion Digest.

Endpoint usado:

```text
POST http://{IP}/ISAPI/AccessControl/AcsEvent?format=json
```

Payload enviado al checador:

```json
{
  "AcsEventCond": {
    "searchID": "<guid>",
    "searchResultPosition": 0,
    "maxResults": 100,
    "major": 5,
    "minor": 38,
    "startTime": "2026-06-03T00:00:00-06:00",
    "endTime": "2026-06-03T23:59:59-06:00"
  }
}
```

Campos usados desde la respuesta ISAPI:

- `serialNo`: identificador unico del evento, se usa para evitar duplicados
- `employeeNoString` o `employeeNo`: ID de empleado en el checador
- `name`: nombre registrado en el checador
- `time`: fecha y hora del evento
- `doorNo`: puerta relacionada al evento

## Flujo de sincronizacion

1. La app C# consulta eventos del Hikvision por ISAPI.
2. Filtra eventos validos con `serialNo`, empleado y fecha/hora.
3. Guarda un archivo JSON local en `logs/pendientes`.
4. Envia los JSON pendientes por `POST` a la API PHP.
5. Si la API responde correctamente, mueve el archivo a `logs/enviados`.
6. La API PHP inserta en MySQL usando `mysqli` y prepared statements.
7. MySQL evita duplicados por `checador_eventos.serial_no UNIQUE`.

## JSON enviado desde C# hacia PHP

```json
{
  "fechaSincronizacion": "2026-06-04T08:00:00",
  "equipo": "DS-K1T320MFWX-B",
  "sucursal": "Puebla Centro",
  "eventos": [
    {
      "serialNo": 8992,
      "empleado": "117",
      "nombre": "JErs",
      "fechaHora": "2026-06-03T15:13:57-06:00",
      "doorNo": 1
    }
  ]
}
```

## Base de datos

Tabla de eventos:

```sql
CREATE TABLE checador_eventos (
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
```

Relacion con usuarios:

```text
Usuarios.id_checador = checador_eventos.employee_no
```

Columna agregada a usuarios:

```sql
ALTER TABLE Usuarios
    ADD COLUMN id_checador VARCHAR(50) NULL AFTER correo;

CREATE INDEX idx_usuarios_id_checador ON Usuarios (id_checador);
```

Al crear un usuario nuevo, si no se captura manualmente `id_checador`, el sistema asigna automaticamente el `id` generado del usuario.

## Pantalla de consulta

Desde `Usuarios/index.php`, el nombre del usuario abre:

```text
Usuarios/checador_eventos.php?id=<id_usuario>
```

La pantalla muestra:

- Resumen diario de asistencia
- Entrada del dia: primera checada registrada del dia
- Salida del dia: ultima checada registrada del dia
- Estado `Completo` si hay entrada y salida en dia pasado
- Estado `Falto checar salida` si solo hay una checada en dia pasado
- Estado `Dia en curso` o `Dia en curso, salida pendiente` si es hoy
- Listado completo de eventos crudos

## API PHP

Endpoint:

```text
POST /api/checador/subir_log.php
```

Caracteristicas:

- Compatible con PHP antiguo, sin `declare(strict_types=1)` ni tipos nullable
- Usa `mysqli`
- Usa prepared statements
- Usa `INSERT IGNORE` para evitar duplicados por `serial_no`
- Devuelve conteo de recibidos, insertados, duplicados y omitidos

Respuesta ejemplo:

```json
{
  "ok": true,
  "recibidos": 1,
  "insertados": 1,
  "duplicados": 0,
  "omitidos": []
}
```

## Notas de mantenimiento

- Si cambia la IP del checador, modificar `accesos.json` o usar `HIKVISION_IP`.
- Si cambia la contrasena, modificar `accesos.json` o usar `HIKVISION_PASSWORD`.
- Si cambia la URL del servidor, modificar `urlApi` en `accesos.json`.
- Si se reinstala o reemplaza el checador, documentar aqui el numero de serie nuevo.
- Verificar que `serial_no` siga siendo unico por evento antes de cambiar la estrategia de deduplicacion.
- Mantener sincronizado `Usuarios.id_checador` con el ID usado en el equipo Hikvision.
