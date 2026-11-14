# Propuesta de interfaz F-004

Mockup estático con datos sintéticos, sin conexión a Kanboard ni base de datos.
Abrir [index.html](index.html) en el navegador para escritorio y
[mobile.html](mobile.html) para un marco con viewport de 390 × 844 px.

Servidor local opcional desde la raíz:

```powershell
python -m http.server 18004 --bind 127.0.0.1 --directory harness/mockups/f004
```

Visitar http://127.0.0.1:18004/ y http://127.0.0.1:18004/mobile.html.
Detener con Ctrl+C. También se puede abrir el HTML directamente desde disco.
No desplegar este directorio: pertenece al harness, no al plugin productivo.

## Propuesta revisable
Navegación diaria: Inicio, Proyectos, Mi tiempo, Clientes y bolsas, Reportes.
Funciones nativas secundarias en Más herramientas, accesibles también al abrir
el menú móvil. Administración según rol. Sidebar azul profundo, acción principal
azul eléctrico, fondos neutros; logo oficial oscuro a resolución sin ampliación.
Tipografía del sistema, sin dependencias, fuentes externas ni animaciones.

Probar:
1. Inicio: métricas de trabajo propio, tareas, proyectos y acceso al registro de tiempo.
2. Proyectos: columnas verticales en móvil y opciones secundarias del proyecto.
3. Nueva tarea: título, proyecto y responsable primero; campos avanzados desplegables.
4. Mi tiempo: proyecto, subtarea, fecha trabajada, minutos y nota; estado pendiente.
5. Clientes y bolsas: formulario de cliente, bolsa con vigencia opcional y saldo.
6. Reportes: fechas y proyecto; trabajado, facturable y consumo separados, sin importes.
7. Controles superiores: estados normal/vacío/error/vencida y vistas miembro/líder/admin.

Los controles de vista/estado pertenecen exclusivamente a la demostración. Formularios
y botones solo muestran ejemplos en memoria; no guardan, consumen, exportan ni
autorizan operaciones reales. La seguridad del producto se implementa en servidor
y se prueba por objeto en las features correspondientes.

## Alcance de revisión
Comprobar visualmente escritorio, móvil, zoom 200%, tabulación, foco y diálogos.
Análisis estático de contraste y etiquetas en Test-F004Mockup.py. No sustituye
navegación real, lector de pantalla o auditoría WCAG completa. La integración del
navegador falló durante la sesión; esos resultados visuales permanecen pendientes.
La aprobación explícita de esta propuesta es condición de F-004 y dependencia de
F-005/F-006; ver ui-brand.md y prompts/ui-ux.md. No afirmar aprobación por silencio.
