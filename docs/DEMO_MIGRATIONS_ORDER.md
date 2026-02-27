# Orden de ejecución de migraciones demo (Version20250223100000–00013)

Doctrine Migrations ejecuta las clases por **orden alfabético de nombre de clase**. El sufijo `_validation` hace que cada validación se ejecute **justo después** de su migración correspondiente.

## Orden de ejecución (28 pasos)

| # | Clase | Tipo | Descripción |
|---|-------|------|-------------|
| 1 | Version20250223100000 | Migración | Crear tabla `kit_item` con columna `id` |
| 2 | Version20250223100000_validation | Validación | Comprobar que `kit_item` existe con `id` y PK |
| 3 | Version20250223100001 | Migración | Crear tabla `kit_example` (todos los tipos de columna) |
| 4 | Version20250223100001_validation | Validación | Comprobar que `kit_example` existe con `id` y PK (skip si se borra en 00004) |
| 5 | Version20250223100002 | Migración | Crear `kit_user` y añadir columna `user_id` a `kit_item` |
| 6 | Version20250223100002_validation | Validación | Comprobar que `kit_user` existe y `kit_item.user_id` existe (skip si se borran después) |
| 7 | Version20250223100003 | Migración | Añadir FK `kit_item.user_id` → `kit_user.id` (+ índice) |
| 8 | Version20250223100003_validation | Validación | Si existe FK `fk_kit_item_user_id`, OK; si no (p. ej. tras 00005), skip |
| 9 | Version20250223100004 | Migración | Drop tabla `kit_example` |
| 10 | Version20250223100004_validation | Validación | Comprobar que `kit_example` no existe |
| 11 | Version20250223100005 | Migración | Drop índice y FK en `kit_item` (user_id) |
| 12 | Version20250223100005_validation | Validación | Comprobar que FK e índice ya no existen (skip en SQLite) |
| 13 | Version20250223100006 | Migración | Drop tabla `kit_user` (en SQLite: recrear `kit_item` sin FK antes) |
| 14 | Version20250223100006_validation | Validación | Comprobar que `kit_user` no existe |
| 15 | Version20250223100007 | Migración | Drop columna `user_id` de `kit_item` |
| 16 | Version20250223100007_validation | Validación | Comprobar que `kit_item.user_id` no existe |
| 17 | Version20250223100008 | Migración | Renombrar `kit_example.col_string` → `col_title` (no-op si tabla no existe) |
| 18 | Version20250223100008_validation | Validación | Si tabla existe: debe existir `col_title` y no `col_string` (skip si tabla no existe) |
| 19 | Version20250223100009 | Migración | Modificar `kit_example.col_string_nullable` longitud 100→200 (si tabla no existe, puede crear tabla mínima) |
| 20 | Version20250223100009_validation | Validación | Si tabla existe: debe existir `col_string_nullable` (skip si tabla no existe) |
| 21 | Version20250223100010 | Migración | Añadir índice en `col_title` y unique en `col_guid` en `kit_example` (no-op si tabla/columnas faltan) |
| 22 | Version20250223100010_validation | Validación | Si tabla tiene `col_title` y `col_guid`: comprobar índice y unique (skip si tabla o columnas faltan) |
| 23 | Version20250223100011 | Migración | Crear tabla `kit_pk_demo` (id, code, PK(id)) |
| 24 | Version20250223100011_validation | Validación | Comprobar que `kit_pk_demo` existe con columnas `id`, `code` y PK |
| 25 | Version20250223100012 | Migración | Drop primary key en `kit_pk_demo` |
| 26 | Version20250223100012_validation | Validación | Comprobar que `kit_pk_demo` no tiene PK (skip en SQLite) |
| 27 | Version20250223100013 | Migración | Añadir primary key en `kit_pk_demo` (columna `code`) |
| 28 | Version20250223100013_validation | Validación | Comprobar que `kit_pk_demo` tiene PK (skip en SQLite; no comprueba que sea sobre `code`) |

## Resumen: ¿cada validación comprueba lo que toca?

| Migración | Acción principal | Validación correspondiente | ¿Coincide? |
|-----------|------------------|----------------------------|-------------|
| 00000 | CREATE TABLE kit_item (id, PK) | Tabla existe, columna id, tiene PK | ✅ |
| 00001 | CREATE TABLE kit_example (todas las columnas) | Tabla existe, id, PK (skip si tabla borrada en 00004) | ✅ |
| 00002 | CREATE kit_user + ADD COLUMN user_id en kit_item | kit_user existe, kit_item.user_id existe (skip si borrados) | ✅ |
| 00003 | ADD FK user_id → kit_user.id | Si FK existe → OK; si no (p. ej. 00005) → skip (nunca lanza) | ✅ (soft) |
| 00004 | DROP TABLE kit_example | Tabla no existe | ✅ |
| 00005 | DROP FK + DROP INDEX en kit_item | FK e índice no existen (skip SQLite) | ✅ |
| 00006 | DROP TABLE kit_user | Tabla no existe | ✅ |
| 00007 | DROP COLUMN user_id en kit_item | Columna user_id no existe | ✅ |
| 00008 | RENAME col_string → col_title en kit_example | Si tabla existe: col_title sí, col_string no (skip si no tabla) | ✅ |
| 00009 | MODIFY col_string_nullable length 200 | Si tabla existe: col_string_nullable existe (no comprueba length) | ✅ |
| 00010 | ADD INDEX col_title, UNIQUE col_guid en kit_example | Si tabla y columnas: índice en col_title y unique en col_guid (skip si faltan) | ✅ |
| 00011 | CREATE TABLE kit_pk_demo (id, code, PK(id)) | Tabla existe, id, code, PK | ✅ |
| 00012 | DROP PRIMARY KEY kit_pk_demo | Tabla sin PK (skip SQLite) | ✅ |
| 00013 | ADD PRIMARY KEY (code) en kit_pk_demo | Tabla tiene PK (skip SQLite; no verifica columna `code`) | ✅ (PK existe) |

## Dependencias entre migraciones (orden correcto)

- **00001** debe ir antes de 00004 (crea kit_example que 00004 borra).
- **00002** crea kit_user y user_id en kit_item; **00003** añade la FK (debe ir después de 00002).
- **00005** quita FK e índice de kit_item; **00006** borra kit_user (debe ir después de 00005 para que no quede FK huérfana).
- **00007** quita user_id de kit_item (después de 00006).
- **00008, 00009, 00010** operan sobre kit_example; en el flujo estándar la tabla fue borrada en 00004, por eso 00008/00010 suelen ser no-op y 00009 puede crear una tabla mínima; las validaciones hacen skip cuando la tabla o columnas no existen.
- **00011** crea kit_pk_demo; **00012** quita la PK; **00013** añade PK en `code` (orden fijo).

Para imprimir esta tabla en consola desde el demo: `make list-migrations` (o `php scripts/list-migrations-order.php`).
