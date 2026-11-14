# Desarrollo PHP y simplificación

Aplicar al desarrollo autorizado de PHP, plantillas, servicios y refactorizaciones.
Leer el código y las convenciones reales antes de diseñar. Mantener MVC, Pimple,
PicoDB/PDO y extensión nativa. Principios KISS, YAGNI, DRY y responsabilidad única
orientan decisiones; no obligan a introducir capas ni reescribir código estable.

## Cambio mínimo verificable
- Delimitar comportamiento que debe conservarse, entradas UI/API/CLI, permisos,
  efectos en datos, consumidores y recuperación. Refactorizar por pasos equivalentes.
- Funciones con una tarea: 5–20 líneas orientativas; revisar las mayores de 20.
  Tope por defecto 40 líneas de cuerpo sin blancos/comentarios para código nuevo
  o modificado. Justificar excepciones concretas; no comprimir ni editar terceros
  ni reformatear código ajeno para cumplir una métrica.
- Dependencias explícitas, errores nativos útiles, validación en límites y escape
  al presentar. No introducir tipos/sintaxis incompatibles con el runtime acordado.
- Tests de comportamiento donde aporten: un cambio trivial reversible no necesita
  tests que repitan su implementación. Sintaxis no acredita integración.

## Retirar componentes
Inventariar uso y dependencias en rutas, ACL, API, CLI, cron, plantillas, assets,
configuración, Composer/autoload, migraciones, pruebas y licencias. Distinguir
ocultar, deshabilitar por configuración y eliminar. Ausencia en el menú no demuestra
que algo sea prescindible. Conservar capacidades nativas útiles y datos históricos.
No eliminar motores, lenguajes, conectores ni configuraciones por inferencia.
La selección de componentes requiere alcance explícito y evaluación de consumidores.
No editar vendor/ o libs/: si una dependencia debe retirarse, evaluar manifiesto,
lock, build y compatibilidad en un cambio autorizado. Restaurar código no restaura datos.

## Configuración e integraciones
Consultar constantes nativas y precedencia sin mostrar valores privados. SMTP u
otras integraciones requieren destino de prueba y credenciales gestionadas fuera
de Git/evidencias; una conexión exitosa no demuestra entrega. No enviar correo sin
autorización de destinatario y acción. No arrancar la app para leer configuración.
