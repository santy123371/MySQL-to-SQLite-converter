# SQL a SQLite Converter

Este proyecto es una interfaz web para convertir archivos MySQL `.sql` a bases de datos SQLite `.db`.

## Qué hace

- Permite subir un archivo `.sql` generado por MySQL
- Convierte la definición de tablas y las sentencias `INSERT` a un formato compatible con SQLite
- Genera un archivo `.db` descargable
- El archivo convertido se elimina automáticamente después de la descarga o tras una hora de inactividad

## Requisitos

- PHP 7.4+ instalado
- Servidor web con soporte PHP (XAMPP, WAMP, Laragon, Apache + PHP, etc.)

## Instalación rápida

1. Copia la carpeta `converter` dentro de tu carpeta pública de servidor web, por ejemplo `htdocs` en XAMPP.
2. Asegúrate de que el directorio `upload/` exista y tenga permisos de escritura para el servidor web.
3. Abre el navegador y visita:

   ```
   http://localhost/converter/index.php
   ```

## Uso

1. En la página web, selecciona un archivo `.sql` válido.
2. Haz clic en el botón para convertir.
3. Si la conversión es exitosa, se mostrará un enlace para descargar el archivo `.db`.

## Archivos principales

- `index.php` - interfaz web y controlador principal
- `converter.php` - motor de conversión MySQL → SQLite
- `download.php` - descarga segura del archivo generado
- `debug.php` - página auxiliar para revisión y pruebas
- `upload/` - carpeta temporal para almacenar bases de datos generadas

## Limitaciones

- Admite principalmente sentencias `CREATE TABLE`, `INSERT INTO` y tipos comunes de MySQL.
- No convierte automáticamente todas las construcciones avanzadas de MySQL como `ALTER TABLE`, `CREATE VIEW`, `TRIGGERS`, o procedimientos almacenados.
- Solo acepta archivos con extensión `.sql` y tamaño máximo de 10 MB.

## Seguridad

- La descarga se realiza mediante `download.php` y evita recorridos de directorio.
- Los archivos temporales se limpian automáticamente tras una hora.

## Consejos

- Si necesitas soporte para una sintaxis SQL específica, revisa `converter.php` y agrega nuevas reglas de conversión.
- Usa una copia de tu archivo `.sql` original antes de convertir, ya que el comportamiento depende de la estructura SQL.
