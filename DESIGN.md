---
name: Marilin Web Design System
version: alpha
description: >
  A logistics and water/soda distribution management system built on a clean,
  high-contrast light canvas, slate-900 primary actions and an indigo accent. Designed for two
  audiences — office administrators managing routes, customers, and invoices on
  desktop, and delivery drivers confirming stops and inventory on mobile devices
  in truck cabs under direct sunlight.

colors:
  # ── Brand & Primary ──
  # Primary actions are slate-900 (SURFACE.md); indigo is the accent for links, focus and selection.
  primary: "#0f172a"
  primary-hover: "#1e293b"
  primary-pressed: "#020617"
  primary-soft: "#f1f5f9"
  on-primary: "#ffffff"
  accent: "#4f46e5"
  accent-hover: "#4338ca"
  accent-soft: "#e0e7ff"
  focus-ring: "#4f46e5"

  # ── Ink Hierarchy ──
  ink: "#0f172a"
  ink-secondary: "#334155"
  ink-muted: "#64748b"
  ink-subtle: "#94a3b8"

  # ── Surfaces & Canvas ──
  canvas: "#ffffff"
  canvas-soft: "#f8fafc"
  surface-1: "#f1f5f9"
  surface-2: "#e2e8f0"
  surface-3: "#cbd5e1"

  # ── Borders & Hairlines ──
  hairline: "#e2e8f0"
  hairline-strong: "#cbd5e1"
  hairline-input: "#94a3b8"
  hairline-input-focus: "#4f46e5"

  # ── Semantic Status ──
  success: "#10b981"
  success-soft: "#d1fae5"
  success-deep: "#065f46"
  warning: "#f59e0b"
  warning-soft: "#fef3c7"
  warning-deep: "#92400e"
  danger: "#e11d48"
  danger-soft: "#ffe4e6"
  danger-deep: "#9f1239"
  info: "#3b82f6"
  info-soft: "#dbeafe"
  info-deep: "#1e40af"

  # ── Product Category Tints (Notion-inspired) ──
  tint-agua: "#d1fae5"
  tint-agua-ink: "#065f46"
  tint-soda: "#dbeafe"
  tint-soda-ink: "#1e40af"
  tint-dispenser: "#fef3c7"
  tint-dispenser-ink: "#92400e"
  tint-facturacion: "#e0e7ff"
  tint-facturacion-ink: "#3730a3"

  # ── Dark Mode ──
  dark-canvas: "#0f172a"
  dark-surface-1: "#1e293b"
  dark-surface-2: "#334155"
  dark-hairline: "#334155"
  dark-ink: "#f1f5f9"
  dark-ink-muted: "#94a3b8"

typography:
  display-lg:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 30px
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: -0.5px
  display-md:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 24px
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: -0.3px
  display-sm:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 20px
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: -0.2px
  body-lg:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 1.5
  body-md:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.5
  body-md-strong:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.5
  body-sm:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.4
  body-tabular:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: -0.02em
    fontFeature: tnum
  caption:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 12px
    fontWeight: 500
    lineHeight: 1.4
  caption-mono:
    fontFamily: "ui-monospace, 'Cascadia Code', 'Fira Code', monospace"
    fontSize: 12px
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: 0.02em
  button-md:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 14px
    fontWeight: 500
    lineHeight: 1.0
  button-lg:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 16px
    fontWeight: 500
    lineHeight: 1.0
  eyebrow:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: 11px
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: 0.08em

spacing:
  xxs: 4px
  xs: 8px
  sm: 12px
  md: 16px
  lg: 24px
  xl: 32px
  xxl: 48px
  section: 64px

rounded:
  xs: 4px
  sm: 6px
  md: 8px
  lg: 12px
  xl: 16px
  pill: 9999px
  full: 9999px

components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    height: 40px
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.on-primary}"
  button-primary-pressed:
    backgroundColor: "{colors.primary-pressed}"
    textColor: "{colors.on-primary}"
  button-secondary:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    height: 40px
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button-md}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    height: 40px
  button-driver-action:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.button-lg}"
    rounded: "{rounded.lg}"
    padding: "14px 24px"
    height: 52px
  text-input:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
    height: 40px
  text-input-sm:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.sm}"
    padding: "6px 10px"
    height: 32px
  text-input-lg:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    typography: "{typography.body-lg}"
    rounded: "{rounded.md}"
    padding: "12px 14px"
    height: 48px
  text-input-focused:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
  card-base:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.lg}"
  stat-card:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.lg}"
    padding: "{spacing.md}"
  route-card:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.xl}"
    padding: "{spacing.lg}"
  badge-success:
    backgroundColor: "{colors.success-soft}"
    textColor: "{colors.success-deep}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-warning:
    backgroundColor: "{colors.warning-soft}"
    textColor: "{colors.warning-deep}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-danger:
    backgroundColor: "{colors.danger-soft}"
    textColor: "{colors.danger-deep}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-info:
    backgroundColor: "{colors.info-soft}"
    textColor: "{colors.info-deep}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-product-agua:
    backgroundColor: "{colors.tint-agua}"
    textColor: "{colors.tint-agua-ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-product-soda:
    backgroundColor: "{colors.tint-soda}"
    textColor: "{colors.tint-soda-ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  badge-product-dispenser:
    backgroundColor: "{colors.tint-dispenser}"
    textColor: "{colors.tint-dispenser-ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  pill-tab:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    typography: "{typography.body-md-strong}"
    rounded: "{rounded.pill}"
    padding: "6px 14px"
  pill-tab-active:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
  segmented-tab:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    typography: "{typography.body-md-strong}"
    padding: "8px 16px"
  segmented-tab-active:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
  data-table-header:
    backgroundColor: "{colors.canvas-soft}"
    typography: "{typography.caption-mono}"
    padding: "{spacing.sm} {spacing.md}"
  data-table-cell:
    backgroundColor: "{colors.canvas}"
    typography: "{typography.body-md}"
    padding: "{spacing.sm} {spacing.md}"
  search-bar:
    backgroundColor: "{colors.surface-1}"
    textColor: "{colors.ink}"
    typography: "{typography.body-md}"
    rounded: "{rounded.md}"
    padding: "10px 14px"
    height: 44px
  modal-card:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.xl}"
    padding: "{spacing.lg}"
  toast:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.canvas}"
    typography: "{typography.body-md}"
    rounded: "{rounded.lg}"
    padding: "{spacing.sm} {spacing.md}"
---

# Marilin Web Design System

## Overview

Marilin Web is a logistics management system for water and soda distribution. The interface serves **two distinct user profiles** with fundamentally different needs:

- **Admin / Dispatcher (Desktop):** Manages customer databases, delivery routes, truck inventories, invoicing, and accounts receivable. Needs high data density, fast filtering, and multi-column tables.
- **Delivery Driver (Mobile/Tablet):** Confirms deliveries, records bottle exchanges (full vs. empty returns), and collects payments. Operates from truck cabs under direct sunlight with wet or gloved hands.

The visual personality is **clean, professional, and operationally dense** — inspired by the surface clarity of Linear, the financial precision of Stripe, the categorical expressiveness of Notion, and the driver ergonomics of Uber.

The app runs on **Laravel 12 + Inertia.js v2 (React 18) + Tailwind CSS v4**.

## Colors

The palette is built on the standard Tailwind CSS v4 slate and indigo scales, ensuring consistent mapping between design tokens and utility classes.

### Ink Hierarchy
Text uses a 4-step ink hierarchy — never pure black (`#000000`). The deep navy-slate ink (`#0f172a`) provides high contrast without visual harshness:
- `ink` — Primary headings, titles, and key values
- `ink-secondary` — Body copy, descriptions, and secondary content
- `ink-muted` — Labels, placeholders, and metadata
- `ink-subtle` — Disabled text, timestamps, fine print

### Surface Ladder
Spatial hierarchy is communicated through surface stepping (not heavy drop shadows):
- `canvas` (`#ffffff`) — Default page background and card surfaces
- `canvas-soft` (`#f8fafc`) — Alternate background, table header rows
- `surface-1` (`#f1f5f9`) — Sidebar, search bars, embedded sections
- `surface-2` (`#e2e8f0`) — Dividers, pressed states
- `surface-3` (`#cbd5e1`) — Strong dividers, disabled backgrounds

### Semantic Status Colors
Each semantic status has **three tiers** for flexible application:
- **Base** — For icons and small indicators
- **Soft** — Light background tint for badges and banners
- **Deep** — High-contrast text paired with soft backgrounds

The primary brand color (indigo) is **strictly reserved** for primary CTAs, active focus rings, and navigation active states. It is never used decoratively.

### Product Category Tints
Inspired by Notion's pastel property system, product categories use soft tinted backgrounds with saturated text for instant visual recognition on truck manifests and packing slips:
- 🟢 **Agua/Bidones** → Mint tint
- 🔵 **Soda/Sifones** → Sky tint
- 🟡 **Dispensers/Equipos** → Amber tint
- 🟣 **Facturación** → Lavender tint

### Dark Mode
All components must support dark mode using Tailwind's `dark:` variant. The dark palette inverts the surface ladder while maintaining the same semantic status colors.

## Typography

The system uses **Inter** (or system UI fallback) as the sole font family, with weight and size changes communicating hierarchy rather than font switching.

### Hierarchy Rules
- **Display** sizes (20–30px, weight 600–700) are reserved for page titles and section headers. Negative letter-spacing (`-0.2px` to `-0.5px`) tightens large text for modern density.
- **Body** sizes (14–16px, weight 400–500) handle all running text, table cells, and form content.
- **Caption/Eyebrow** sizes (11–12px, weight 500–600) are used for metadata labels, table headers, and status indicators. Eyebrows use positive letter-spacing (`+0.08em`) for taxonomic separation.

### Tabular Figures (Mandatory)
All numeric data — bottle counts, siphon quantities, monetary amounts ($ARS), invoice totals, delivery timestamps — **must** use `font-variant-numeric: tabular-nums` (Tailwind class: `tabular-nums`). This prevents column misalignment in data tables and financial summaries.

### Monospace for Operational Headers
Data table column headers use `caption-mono` (monospace, 12px, uppercase, wide tracking) to distinguish structural metadata from content: `RUTA_ID`, `VEHÍCULO`, `QTY (20L)`, `SUBTOTAL`, `ESTADO`.

## Layout

### Responsive Strategy
Mobile-first design targeting three breakpoints:
- **Mobile** (`< 768px`): Single-column, full-width cards, bottom navigation. This is the driver's primary view.
- **Tablet** (`768px – 1024px`): Two-column layouts, sidebar collapses to hamburger.
- **Desktop** (`> 1024px`): Full admin dashboard with persistent sidebar, multi-column tables, and dispatch console.

### Dispatch Console Layout
The dispatch view uses a **multi-pane layout** inspired by Linear's surface ladder:
- Left pane (Routes list) on `canvas-soft`
- Center pane (Stop sequence) on `canvas`
- Right pane (Stop detail) on `canvas` with `surface-1` embedded sections

### Data Tables
- Tables are **always** wrapped in an `overflow-x-auto` container for mobile compatibility.
- Table headers use `canvas-soft` background with `caption-mono` typography.
- Row dividers use 1px `hairline` borders — never boxed card containers per row.
- Numeric columns are right-aligned with `tabular-nums`.

### Touch Target Standards
| Context | Minimum Height | Usage |
|---------|---------------|-------|
| Desktop admin | 32–40px | Standard inputs, buttons, table actions |
| Mobile general | 44px | All interactive elements |
| Driver cab mode | 52px | Stop confirmation, inventory +/- buttons |

### Map-Centric Views
For route visualization, the map component (Leaflet/Mapbox) fills the viewport. Route details appear as a **floating panel** (`rounded-xl`, Level 2 shadow, max-width 440px on desktop; bottom-sheet on mobile) overlaid on the map — inspired by Uber's request card pattern.

## Elevation & Depth

Elevation is communicated through **surface stepping and subtle stacked shadows**, not heavy blurred drop-shadows.

### Shadow Scale
- **Level 0 (Flat):** Default for 90% of components. Surface contrast and 1px hairlines provide structure.
- **Level 1 (Subtle):** `0 0 0 1px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.05)` — Standard cards and widgets.
- **Level 2 (Elevated):** `0 0 0 1px rgba(0,0,0,0.05), 0 4px 12px rgba(0,0,0,0.08)` — Floating panels, map overlays, modals.
- **Level 3 (Floating):** `0 2px 8px rgba(0,0,0,0.16)` — Floating action pills over maps.

### Hairline Focus
- Input borders at rest use `hairline` (`#e2e8f0`).
- Active focus shifts to a 2px ring in `focus-ring` (indigo), providing clear keyboard navigation visibility.

## Shapes

### Geometric Language
Two distinct radius scales serve different purposes:

- **Functional elements** (buttons, inputs, badges): `rounded-md` (8px) for rectangular controls. `rounded-full` (pill) exclusively for filter chips, status badges, and tab toggles.
- **Container elements** (cards, modals, panels): `rounded-lg` (12px) for standard cards. `rounded-xl` (16px) for prominent floating panels and modals.

### Rules
- Buttons use `rounded-md` (8px) — never pills. Exception: pill filter chips and category toggles.
- Status badges always use `rounded-full` (pill).
- Cards and panels use `rounded-lg` or `rounded-xl` depending on prominence.
- Nested elements should not have a border radius larger than their parent container.

## Components

### Buttons
| Variant | Usage | Height |
|---------|-------|--------|
| `button-primary` | Main conversion actions (Save, Create, Confirm) | 40px |
| `button-secondary` | Secondary actions (Cancel, Back, Filter) | 40px |
| `button-danger` | Destructive actions (Delete, Remove) | 40px |
| `button-driver-action` | Driver cab large actions (Confirmar Entrega) | 52px |

All buttons show `disabled:opacity-50 disabled:cursor-not-allowed` during form submission via Inertia's `processing` state.

### Forms
- Inputs have 3 sizes: `sm` (32px), `md` (40px, default), `lg` (48px, driver mode).
- Validation errors appear immediately below the input in `danger` color, `body-sm` typography.
- Use Inertia's `useForm` hook for all form submissions with automatic error handling.
- All `<input>` elements must have associated `<label>` tags for accessibility.

### Data Tables
- **Header row:** `canvas-soft` background, `caption-mono` text, uppercase.
- **Body rows:** `canvas` background, `body-md` text, separated by 1px `hairline`.
- **Selected rows:** `primary-soft` background tint.
- **Bulk action toolbar:** When rows are checked, the header transforms into an action bar showing count and available operations.
- **Counter badges in tabs:** Show dynamic counts inside filter tabs: `Pendientes (18)`.

### Navigation
- **Segmented Tabs:** Clean text tabs with 2px bottom border on active state. Used for major view switching (Listado, Mapa, Carga, Rendición).
- **Pill Tabs:** Floating pill toggles inside a `surface-1` container. Active tab renders as elevated white pill. Used for inline data filtering (Todos, Entregados, Ausentes).
- **Sidebar:** Active item uses a 3px left border indicator in `accent` (indigo), not a full background fill.

### Status Badges
Follow the **monochrome urgency principle**: most operational states are neutral. Color is reserved for exceptions.
- **Neutral states** (En Ruta, Pendiente, Completado): `canvas-soft` background with `ink-muted` text.
- **Attention states** (Stock Bajo, Pago Parcial): `warning-soft` + `warning-deep`.
- **Critical states** (Vencido, Rechazado, Stock Crítico): `danger-soft` + `danger-deep`.
- **Success exceptions** (Pagado en el acto): `success-soft` + `success-deep` — used sparingly.
- **Live indicator:** Pulsing dot (`w-2 h-2 rounded-full bg-primary animate-pulse`) next to "En Reparto" for real-time status.

### Search
A prominent search bar (`search-bar` component, 44px, `surface-1` background) in the dispatch header enables instant lookup by client name, address, phone, or contract number.

### Modals & Toasts
- Modals use `modal-card` (canvas background, `rounded-xl`, Level 2 shadow) with Headless UI.
- Toast notifications use dark ink background with white text, `rounded-lg`.

## Do's and Don'ts

### Do
- ✅ Wrap every data table in `overflow-x-auto` for mobile drivers
- ✅ Use `tabular-nums` on all numeric table columns and financial displays
- ✅ Use Inertia `<Link>` for all internal navigation (SPA without full reload)
- ✅ Use Headless UI for dialogs, menus, and dropdowns
- ✅ Provide `focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500` on all interactive elements
- ✅ Use semantic Tailwind classes mapped to design tokens — never arbitrary hex values in JSX
- ✅ Ensure all driver-facing buttons have `min-h-[52px]` on mobile
- ✅ Use 1px hairline borders for table row separation
- ✅ Write UI copy in **Sentence case** ("Confirmar entrega", not "CONFIRMAR ENTREGA")
- ✅ Use product category tints for instant visual recognition on manifests
- ✅ Test contrast ratios — minimum WCAG AA (4.5:1 for text, 3:1 for large text)

### Don't
- ❌ Use `#000000` pure black for text — use the slate ink hierarchy instead
- ❌ Use heavy blurred `shadow-lg` or `shadow-xl` — use the stacked shadow scale
- ❌ Hardcode hex colors directly in components — reference Tailwind theme tokens
- ❌ Use `alert()`, `confirm()`, or browser-native dialogs — use Headless UI modals
- ❌ Use the indigo accent decoratively — reserve it for links, focus and selected states only
- ❌ Color-code normal operational states (Pagado = green, Pendiente = yellow) — keep them neutral
- ❌ Use ALL CAPS for headings or button labels (except `eyebrow` / `caption-mono` labels)
- ❌ Apply `rounded-full` (pill) to standard action buttons — pills are for badges and filter chips
- ❌ Skip `<label>` tags on form inputs
- ❌ Create inline styles or custom CSS when a Tailwind utility exists
