# API de Citas

Este directorio expone un punto de acceso REST sencillo para administrar citas mediante peticiones HTTP.

## Endpoint principal

- **URL**: `/api/citas.php`
- **Formatos soportados**: JSON
- **Métodos permitidos**: `GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`

## Reportes de analítica

- **URL**: `/api/analitica.php`
- **Formatos soportados**: JSON
- **Métodos permitidos**: `GET`, `OPTIONS`
- **Parámetros comunes**:
  - `fecha_inicio` (opcional): fecha/hora mínima en formato válido (`2024-01-01`, `2024-01-01 10:00`, etc).
  - `fecha_fin` (opcional): fecha/hora máxima (exclusiva) en formato válido.

### Resumen básico de citas

- **`GET /api/analitica.php?reporte=citas_basico`**
- Devuelve conteos totales de citas por estatus (canceladas, programadas, reprogramadas y completadas).

### Resumen básico de clientes

- **`GET /api/analitica.php?reporte=clientes_basico`**
- Devuelve el total de pacientes y citas por cliente/tutor, incluyendo cancelaciones y completadas.

### Cancelaciones frecuentes

- **`GET /api/analitica.php?reporte=cancelaciones_frecuentes`**
- Parámetros opcionales:
  - `min_cancelaciones` (default: 2)
  - `limit` (default: 20)
- Devuelve pacientes con cancelaciones frecuentes y la última cancelación registrada.

### Prospectos para promoción

- **`GET /api/analitica.php?reporte=prospectos_promocion`**
- Parámetros opcionales:
  - `min_completadas` (default: 5)
  - `max_canceladas` (default: 1)
  - `limit` (default: 20)
- Devuelve pacientes con alto número de citas completadas y pocas cancelaciones.

## Operaciones

### Obtener citas

- **`GET /api/citas.php`**: devuelve la lista completa de citas ordenadas por fecha programada descendente.
- **`GET /api/citas.php?id=123`**: devuelve la cita con el identificador indicado.

### Crear citas (modo individual o masivo)

- **`POST /api/citas.php`**
- **Cuerpo JSON requerido (individual)**:
  ```json
  {
    "paciente_id": 10,
    "psicologo_id": 4,
    "creado_por": 2,
    "programado": "2024-12-01 10:30:00",
    "costo": 800.0,
    "tipo": "consulta",
    "estatus": 2
  }
  ```
- También se aceptan colecciones para sincronizaciones offline:
  - Como arreglo JSON: `[ { ... }, { ... } ]`
  - Como objeto con la clave `citas`:
    ```json
    {
      "creado_por": 2,
      "citas": [
        {
          "paciente_id": 10,
          "psicologo_id": 4,
          "programado": "2024-12-01T10:30:00-06:00",
          "costo": 800.0,
          "tipo": "consulta"
        },
        {
          "paciente_id": 14,
          "psicologo_id": 5,
          "programado": "2024-12-01 12:00:00",
          "costo": 600.0,
          "tipo": "valoracion"
        }
      ]
    }
    ```
    Los campos definidos fuera del arreglo (por ejemplo `creado_por`) se aplican como valores por defecto a cada cita que no lo incluya. La respuesta incluirá un arreglo en `data` y un `count` con el total procesado.
- `estatus` es opcional; por defecto se usa `2` (programada).

### Actualizar citas

- **`PUT /api/citas.php?id=123`**: actualiza una única cita existente enviando solo los campos a modificar (`paciente_id`, `psicologo_id`, `programado`, `costo`, `estatus`, `tipo`) y, opcionalmente, `usuario_id` para fines de registro.
- **`PUT /api/citas.php`** (sin parámetro `id`): permite actualizar varias citas en una sola petición. Cada elemento debe incluir el identificador de la cita en la propiedad `id`. Se admiten los mismos formatos que para la creación masiva, incluyendo valores comunes a nivel raíz.
  ```json
  {
    "usuario_id": 9,
    "citas": [
      {
        "id": 101,
        "programado": "2024-12-02 09:00:00",
        "estatus": 2
      },
      {
        "id": 108,
        "paciente_id": 15,
        "tipo": "sesion de seguimiento"
      }
    ]
  }
  ```
  La respuesta regresa los registros modificados y un `count` con el total de citas afectadas. Si se detectan conflictos (por ejemplo, horarios duplicados para el mismo paciente) la transacción se revierte y se devuelve el error correspondiente.

### Eliminar una cita

- **`DELETE /api/citas.php?id=123`**
- **Cuerpo JSON opcional**: `{ "usuario_id": 2 }` para registrar en la bitácora quién realizó la acción.

## Respuestas

Las respuestas exitosas incluyen un objeto `data` con la cita solicitada o una propiedad `message` en operaciones de eliminación. Las respuestas de error contienen una propiedad `error` describiendo el problema.
