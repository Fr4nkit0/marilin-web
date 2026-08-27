---
name: tailwind-design-system
description: >
  Best practices for Tailwind CSS v4, responsive layout tokens, UI components, states and accessibility.
---

# Tailwind CSS v4 & UI Design System

## 1. Reglas de Componentes y Estilizado
- **Tokens y Colores:** Usar la paleta estándar de Tailwind (`slate`/`zinc` para neutros, `indigo`/`blue` para acentos primarios, `emerald` para éxitos/estados activos, `rose` para errores/cancelaciones).
- **Dark Mode:** Todos los componentes de UI deben soportar modo oscuro de forma nativa usando variantes `dark:bg-gray-900`, `dark:text-gray-100`, `dark:border-gray-700`.
- **Estados Interactivos:** Siempre estilizar `hover:`, `focus:ring-2 focus:ring-offset-2`, `disabled:opacity-50 disabled:cursor-not-allowed`.

## 2. Layouts Responsivos
- Mobile-first: `w-full md:w-1/2 lg:w-1/3`.
- Tablas y Grillas: Envoltorios con `overflow-x-auto` para visualización en dispositivos móviles de repartidores.
