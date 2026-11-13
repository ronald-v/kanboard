# DEC-015: continuar sin aprobaciones visuales adicionales

Fecha: 2026-11-12T21:49:32-05:00.
Feature: F-006; alcance de continuidad F-007..F-012.
Evidencia: el usuario reporta superposición del menú sobre Inicio/Proyectos y
estilos incoherentes en Nuevo proyecto y modales de proyecto/tarea/subtarea.
Confirma que campos avanzados se ven. Añade: «Desarrolla todas las features, te
autorizo a que avances, tambien puedes sacar capturas para ver visualmente como
se ve, no debes esperar a que yo te de aprobaciones visuales» y luego «continua».

Decisión: corregir los problemas concretos y continuar el ciclo secuencial sin
pedir ni esperar nuevas aprobaciones visuales. Esta autorización no convierte
pruebas fallidas en aprobadas ni autoriza despliegue productivo. Mantener la
integración Chrome elegida en DEC-011; capturas permitidas si está operativa.

Impacto: F-006 se retoma. La causa estática del solapamiento es content-box:
sidebar 240px + padding horizontal 40px ocupa 280px, con contenido a 260px.
Fijar border-box y unificar estilos nativos por CSS acotado al plugin. Conservar
campos, permisos, CSRF y comportamiento nativo; sin esperar revisión del usuario.

Siguiente acción: comprobaciones pertinentes, evidencia con límites reales,
commit dev feature 006 y push; después F-007 en feat/007. Si Chrome falla,
registrar esa limitación sin atribuir capturas ni validación visual inexistentes.
