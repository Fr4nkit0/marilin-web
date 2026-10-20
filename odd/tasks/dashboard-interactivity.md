# Dashboard interactivity

Objective: make the web dashboard interactive without changing its composition, and drop the "Base Central" sidebar card.
Branch: feat/1-mobile-sync. Delivery: ask-on-risk. Tests: Pest (`./vendor/bin/sail artisan test`), `npm run build` (tsc).

## Specs

- S1 KPI cards open a modal with the records behind each figure: "Ventas del día" (each sale: customer, driver, time, amount), "Cobranza Recaudada" (each payment split by cash and transfer), "Paradas de Ruta" (each driver's stops and their status: pending, finished, rescheduled), "Movimiento Envases" (each container movement).
- S2 A date selector in the header to see any day.
- S3 A driver row is clickable and opens that driver's cash close for that day.
- S4 Quick actions work: "Asignar ruta" goes to Despacho y Rutas, "Cierre de caja" goes to the cash close.
- S5 Automatic refresh every 30 seconds that reloads only the numbers, without reloading the page, and shows "Actualizado hace X".
- S6 The "Sistema" card shows real data: each driver's last sync from the app, with an alert when one has gone too long without syncing. Assumption: "last sync" is the last API request made with the driver's token (`personal_access_tokens.last_used_at`); "too long" is more than 30 minutes while the driver's route is in progress.
- S7 "quitar la card del navbar que dice base central".

## Tasks

- [x] T1 S7 — inline — 584bb12
- [x] T2 S2 S3 S4 — inline — b0730e6
- [x] T3 S1 — inline — 8219906
- [x] T4 S5 S6 — inline — 6ba5354

## Log

- L1 (2026-10-20) User: "bueno tenemos que hacer algo para que el dashbord sea mas interactivo porque ahora no no lo es como esta compuesto y organizado me gusta pero hay que agregarle interactividad". After the 6-point proposal: "si avanza ademas agregale algo mas quitar la card del navbar que dice base central".
- L2 Routes: no writer trigger (single repo, sequential units sharing Dashboard.tsx), all inline.
- L3 (2026-10-20) Done. Evidence: `tests/Feature/DashboardTest.php` (3 tests: requested day, details per card, sync status) and full suite 165 passed; `npm run build` (tsc) ok. Sync time is sent as ISO 8601 so the browser computes "hace X" in its own timezone. Not checked visually in a browser.
