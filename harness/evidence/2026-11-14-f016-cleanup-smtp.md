# F-016 · Recorte y SMTP preparado

Fecha: 2026-11-14T11:10:00-05:00. Implementación local, sin despliegue ni envío real.

Política de servidor desactiva Currency, Plugins, OAuth externo, feeds/calendarios,
conectores/tareas externas, compartir público e integraciones. About redirige a
configuración útil. Menús de administración/perfil retirados o sustituidos. Español
como único idioma visible; motores y traducciones internos se conservan para
compatibilidad y pruebas. No se borran tablas/historia ni se edita vendor/libs.

data/config.php privado recibió include de config/projecta.php. SMTP usa variables
PROJECTA_SMTP_* y TLS, con edición UI deshabilitada; no cae en mail()/sendmail.
Sin remitente/host/TLS no se encola ni se conecta. Timeout SMTP 5 segundos y
validación de certificado obligatoria. Identidad del remitente: Projecta.
Se generó clave local estable MFA en la configuración privada ignorada, con
preferencia de PROJECTA_KEY del entorno: evita perder acceso al iniciar desde la
configuración ya elegida. No se expuso ni versionó. Respaldar dicha configuración.
El ejemplo productivo usa únicamente variables de entorno.

Pruebas: php -l data/config.php PASS, sin arranque ni conexión real.
RvSoftwarePolicyTest SQLite: 2 tests/17 aserciones PASS: rutas desactivadas,
funciones retenidas, configuración y SMTP incompleto sin fallback/envío.
Test-ProjectaViews.py sobre HTTP 18100: 31 vistas/modalidades responden sin errores
PHP, metadatos/icono Projecta, proyecto activo, guardado de configuración y seis
rutas directas retiradas devuelven 404. PASS. JSON por vista en .runtime/projecta.
Es verificación HTTP, no aprobación visual (F-015 continúa blocked).

SMTP queda configurado/preparado, no acreditado como entrega de correo. No hay
buzón/contraseña/destinatario real ni autorización de envío; no se declara probado
un servidor externo. No se ejecutó receptor TLS sintético; se verificó el bloqueo
de envíos sin configurar. Esta limitación se conserva expresamente.

Recuperación: retirar únicamente include/bloque local añadido y revertir cambios
propios; conservar la clave para descifrar factores existentes. No revertir a un
perfil que permita bypass de MFA sin migración explícita. Artefacto sigue excluyendo
configuración privada, harness, pruebas, Git y datos. Revalidar ZIP integrado.
