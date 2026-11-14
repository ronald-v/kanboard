# UI/UX e identidad

## Activos oficiales recibidos
Directorio externo autorizado de origen:
`C:\Users\ronal\OneDrive - Universidad de Cundinamarca\Escritorio\Proyectos\RvSoftware\Logos\2026`.
Colores-Corporativos.png: 748x166; Horizontal-Claro.png: 298x55;
Horizontal-Oscuro.png: 450x84. Inspeccionados en diagnóstico; no copiados al producto.
No ampliar logos pequeños hasta perder legibilidad. Si hace falta una versión
mayor/vectorial solicitarla en F-004; estos activos permiten preparar la propuesta.

| Token propuesto | Color oficial | Uso propuesto, no aprobado |
| --- | --- | --- |
| deep-blue | #0B1F3A | Texto principal/cabecera |
| electric-blue | #0057FF | Acción principal y enlaces |
| cyan | #00D4FF | Acentos, no texto pequeño sin contraste verificado |
| green | #00A651 | Éxito/progreso con etiqueta adicional |
| neutral | #F5F7FA | Fondo |

La paleta/logos son confirmados; usos, tipografía y layout son propuestas.
Evaluar contraste de combinaciones, foco visible, teclado, etiquetas, zoom y
pantallas pequeñas. No comunicar estado solo con color. No debilitar CSP ni cargar
recursos externos innecesarios. Sin animaciones que entorpezcan uso diario.

## Navegación propuesta para F-004
Inicio; Proyectos/tablero; Mi tiempo; Clientes y bolsas; Reportes. Administración
y funciones avanzadas en acceso secundario según permisos. No eliminar capacidades
sin revisar dependencias. CSS no sustituye permisos del servidor.

Vista representativa para aprobación antes de implementar:

```text
RV Software                      Usuario / opciones
Inicio | Proyectos | Mi tiempo | Clientes y bolsas | Reportes
Proyectos activos   Próximos vencimientos   Bloqueos   Horas del período
Mis tareas: proyecto, tarea, responsable, vencimiento y estado
[Abrir tablero] [Registrar tiempo]
Actividad relevante / estados vacíos y errores comprensibles
```

Formularios: tarea con título/proyecto/responsable primero y campos avanzados
desplegables; tiempo con tarea/subtarea, fecha, duración y nota; campos comerciales
solo al aprobar reglas. Inicio no mostrará métricas comerciales inexistentes.
F-004 debe entregar mockup revisable y registrar aprobación explícita. Este wireframe
es dirección de diseño, no aprobación ni evidencia de usabilidad ejecutada.

Implementación futura mediante hooks layout y overrides selectivos de cabecera,
dashboard y tablero. Inventariar cada plantilla reemplazada para futuras actualizaciones.
