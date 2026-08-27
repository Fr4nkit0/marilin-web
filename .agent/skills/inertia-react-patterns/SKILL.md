---
name: inertia-react-patterns
description: >
  Patterns for Inertia.js v2 with React 18, TypeScript, and Tailwind CSS.
---

# Inertia.js + React + TypeScript Conventions

## 1. Page Components
- Las páginas viven en `resources/js/Pages/<Domain>/<Action>.tsx` (ej. `Pages/Customers/Index.tsx`).
- Tipar siempre las props recibidas desde Laravel usando interfaces TypeScript (`interface IndexProps extends PageProps {...}`).

## 2. Form Handling
- Usar siempre el hook `useForm` de `@inertiajs/react`:
```tsx
const { data, setData, post, processing, errors } = useForm({
    name: '',
    phone_number: '',
});
```

## 3. Layouts & Navigation
- Envolver páginas en `<AuthenticatedLayout>` para vistas protegidas.
- Usar el componente `<Link href={route('...')} />` para navegación SPA sin recargar la página.
