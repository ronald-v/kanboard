# F-015 · Ajustes de login y registro de doble factor

Fecha: 2026-11-14T13:31:02-05:00. Rama: feat/refactor.
Ejecutor único, consulta de prompts ui-ux/dev/testing/security; sin delegación.
Autorización: solicitud actual y DEC-028. Árbol limpio al inicio.

## Causa y cambio mínimo

El selector nativo `input[type="text"]:not(.input-addon-field)` conserva 300px
por su especificidad; el password sí recibía el ancho completo de la tarjeta.
FormHelper agrega un span required después del input: al ocupar este todo el
ancho, el span salta de línea. Se fuerza el mismo ancho para inputs de acceso
y se colocan los marcadores en la fila de etiquetas mediante grid, preservando
el helper y los atributos required/autocomplete. Se retira el span de flecha.

El login acumulaba padding de 40px, tarjeta de 38px, márgenes de marca/seguridad
y pie; el body nativo también añade 10px inferiores. Ahora el contenedor usa
100dvh (100vh como alternativa), padding compacto y flex shrink en la tarjeta.
Se elimina el padding del body solo cuando su hijo directo es rv-auth.
El registro MFA dispone QR y controles en columnas sobre 600px, una columna
debajo; el QR móvil tiene 180px. Tarjeta con overflow-y auto permite consultar
errores, clave manual o controles a zoom alto sin exceder el alto del contenedor.
No se oculta contenido ni se cambia el flujo de autenticación.

## Comprobaciones observadas

- `php -l plugins/RvSoftware/Template/auth/login.php`: exit 0.
- `php -l plugins/RvSoftware/Template/auth/mfa.php`: exit 0.
- `php .runtime/check_auth_templates.php`: exit 0, seis renders sintéticos
  usando FormHelper real, sin app/common.php ni base de datos. Login, alta MFA,
  verificación MFA, cada uno con y sin error; escape conservado.
- `python .runtime/inspect_auth_markup.py`: exit 0. Etiquetas equilibradas en
  las seis ramas, un form POST con CSRF por vista, required/autocomplete,
  ausencia de valor password, dos marcadores y wrappers del login, botón sin
  flecha, columnas solo en alta MFA. Scripts temporales ignorados en .runtime.
- `git diff --check`: exit 0; avisos normales LF/CRLF.
- `powershell -NoProfile -File harness/scripts/Test-Harness.ps1`: exit 0,
  18 features, 29 casos, 13 prompts, 10 escenarios; consistencia estática.

## Límites y recuperación

Chrome no permite inspección: crear pestaña devuelve `Cannot read properties of
undefined (reading 'timeoutMs')`; seleccionar una pestaña de revisión previa
devuelve sesión ya ocupada; seleccionar la pestaña local actual vuelve a fallar
con timeoutMs. Tres intentos con variación de condición, sin repetir. No hay
capturas nuevas ni medidas de scroll, teclado o zoom observadas en navegador.
El sondeo opcional de tinycss2 falla por módulo ausente; no se instala dependencia
para este ajuste ni se atribuye validación de CSS a ese intento.

No se arrancó Kanboard, ni se consultó/cambió data/config.php, ni se conectó la
MariaDB del usuario. Su servidor local queda en su estado previo. Los checks
son de sintaxis/HTML, no prueban el ajuste visual final ni autenticación completa.
F-015 conserva pendiente la revisión visual de estas vistas y de la matriz
anterior; F-012 sin cambios. Recuperar revirtiendo solo los tres archivos de
presentación modificados. El ZIP preview anterior no contiene este ajuste;
no se generó paquete nuevo ni despliegue.
