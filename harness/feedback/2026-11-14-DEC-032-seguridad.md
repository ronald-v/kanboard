# DEC-032 ? Contenci?n y revisi?n de seguridad

Fecha: 2026-11-14T19:34:19-05:00. Feature: F-020.

Evidencia: captura del usuario de cuota MariaDB y revisi?n est?tica de las cuatro entradas HTTP.

Decisi?n: usuario aprob? el plan e indic? corregir y analizar, probar solo en copia aislada y actualizar los tableros manualmente. Permite excepciones m?nimas de n?cleo para contener fallos anteriores a plugins y confiar solo en proxies configurados. No permite despliegue ni pruebas contra producci?n.

Impacto: nueva F-020; estados anteriores intactos. No modificar vendor/libs, configuraci?n privada ni datos reales.

Siguiente: implementar, reproducir fallos con sint?ticos, retest y registrar l?mites de hosting.
