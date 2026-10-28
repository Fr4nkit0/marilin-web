# Surface Brief: The Premium SaaS

This document records the visual direction for the Marilin Web redesign, aiming for a high-end, premium B2B SaaS aesthetic (inspired by Vercel, Stripe, and Linear).

## The Direction
**The Premium SaaS**
* **Lineage:** Modern, high-performance, polished web applications.
* **Thesis:** Elegance through subtle shadows, generous radiuses, crisp typography, and glassmorphism.

## Core Rules

### 1. Viewport & Layout
A floating, breathable interface that feels like a native app.
* **Cards & Panels:** Content sits on floating white cards (`bg-white`) over a very subtle off-white background (`bg-slate-50` or `bg-gray-50/50`).
* **Headers:** Glassmorphic headers (`bg-white/80 backdrop-blur-md border-b border-gray-200/50`).

### 2. Materials & Typography
* **Borders & Corners:** Use large radiuses (`rounded-xl`, `rounded-2xl`) for containers, and `rounded-lg` for buttons and inputs.
* **Borders:** Extremely subtle, delicate borders (`border border-gray-200/60`).
* **Shadows:** Use `shadow-sm` for resting cards, and `hover:shadow-md` for interactive elements.
* **Typography:** High contrast for primary text (`text-slate-900`), and soft, legible gray for secondary text (`text-slate-500`).

### 3. Palette
A pristine, optimistic, and highly professional palette:
* **Backgrounds:** `#f8fafc` (Slate 50) for the app body, `#ffffff` (White) for floating cards.
* **Primary Actions:** Water blue `#0284c7` (Sky 600, hover Sky 700) for primary buttons, links, focus and selection (chosen 2026-10-28 over Slate 900). Previously considered: a sleek, deep brand color like `#0f172a` (Slate 900) for primary buttons (Vercel style), or a vibrant `#4f46e5` (Indigo 600) for Stripe style. We will use a sleek Slate 900 (`bg-slate-900 hover:bg-slate-800 text-white`) for a super premium monochromatic look with blue/indigo accents.
* **Secondary Actions:** `bg-white text-slate-700 border border-slate-200 hover:bg-slate-50`.
* **Badges:** Soft pills (`bg-slate-100 text-slate-700 rounded-full`, or `bg-emerald-50 text-emerald-700`).

## Risks to Manage
* **Blandness:** A completely clean UI can feel empty. We must ensure typography scale (large headers, small labels) and padding are perfectly dialed in to create structure without borders.

---
*Contract recorded prior to code implementation.*
