# 🥚 Egg Monitor

**A production and sales monitoring system with predictive analytics for commercial layer farms.**

Built for **SPC Farm Magalang** — 45 laying houses, ~92,000–101,000 Dekalb White hens — Egg Monitor replaces manual logbooks with a digital system that records daily production and sales, flags anomalies as they happen, and forecasts near-term output so the farm can plan ahead instead of reacting after the fact.

> Capstone project · BS Information Technology · Pampanga State Agricultural University

---

## Table of Contents
- [Overview](#overview)
- [Core Features](#core-features)
- [In Progress](#in-progress)
- [Tech Stack](#tech-stack)
- [Third-Party Integrations](#third-party-integrations)
- [System Architecture](#system-architecture)
- [Data Model Highlights](#data-model-highlights)
- [Getting Started](#getting-started)
- [Project Status](#project-status)
- [Recommended Additions](#recommended-additions)
- [Acknowledgments](#acknowledgments)

---

## Overview

Egg Monitor digitizes the daily workflow of a multi-building layer farm:

1. **Record** — daily production (per building, per flock), egg grading by size, sales, and farm expenses, replacing paper logbooks.
2. **Detect** — flag unusual drops or spikes in production against a building's own history and its peers.
3. **Forecast** — project near-term production using a regression model trained on flock age, production trend, and weather (THI).
4. **Report** — surface farm-wide and per-building performance — including estimated revenue, expenses, and net contribution — for decision-making.

The system is trained and validated on **real farm data**: ~5 years of logs and 1 month of inputted data

---

## Core Features

### Production Monitoring
- Daily entry per **building** (3 laying houses) with population, mortality, and eggs collected.
- **Egg grading by size**: Peewee, Small, Medium, Large, XLarge, Jumbo, plus non-sellable categories (No Value, No Weight, Dirty, Broken, Waste/Tapon).
- **Flock records** (batch traceability) tracking a flock from placement through cull, anchored to real flock age — including the 18-week transfer age from grower houses.
- Bulk import from the farm's existing Excel (.xls) logbook format, with dynamic parsing that adapts to the sheet's layout rather than relying on fixed cell positions.

### Sales Monitoring
- Daily sales entry by egg size, with a per-size pricing ladder that can be updated as farm prices change.
- Rate/remaining tracking per size so partial-day sales reconcile against production.

### Environmental Monitoring
- Daily weather (temperature, humidity, rainfall) synced from Open-Meteo for the farm's exact location, with a computed **THI (Temperature-Humidity Index)** and heat-stress comfort bands (Comfort / Mild / Moderate / Severe).
- Dashboard THI card + 14-day trend, and THI shown alongside production dips so heat-stress context is visible without leaving the page.
- THI is available as an optional forecast feature (behind a config toggle), with before/after MAPE logged so its effect is measured, not assumed.

### Expense Tracking
- Farm expenses across 8 categories — feed, vaccine, vitamins, medicine, restocking, electricity, manpower, other — recorded at their natural level (farm-wide or per-building).
- **Feed is tracked in kilograms**, not just bags or pesos, per the farm's own accounting need.
- Farm-wide costs (the common case — feed and vaccines come through a single farm-level supplier) are **allocated to buildings by population share** for per-building reporting, and recurring regimens (e.g. weekly vitamin dosing) are surfaced as an ongoing monthly cost rather than buried in a one-off purchase date.
- Editable settings (e.g. feed price, restocking cost) for figures the farm currently estimates rather than measures, so a future confirmed number is a one-field change.

### Per-Building Investment Dashboard
- Pick any building and see its current state, production-rate trend (with the farm's 80%/50% health bands drawn in and an optional THI overlay), active alerts, and a farm-wide leaderboard to compare buildings against each other.
- **Estimated Revenue Contribution** and **Estimated Net Contribution** per building over a 1/2/3-month window — attributed by the building's share of eggs produced and its population share of shared costs. Every allocated or attributed figure is explicitly labeled *"Estimated"* since eggs are physically pooled farm-wide before sale.
- A visibly disabled forecast panel marks where the forward simulator (see below) will plug in — no fabricated projections in the meantime.

### Predictive Analytics
- Linear regression forecast (PHP-ML) using day-index, **flock-age**, and optional **weather** as features — flock age and weather were each added only after testing showed they measurably reduced forecast error (MAPE); both effects are logged, not just claimed.
- **Anomaly detection**, two layers:
  - *Age-relative* — is this building underperforming for a flock of this age?
  - *Peer-deviation* — is this building underperforming relative to other buildings right now?
- Alerts are clustered (`flock_alerts`, `anomaly_alerts`) to avoid flooding the dashboard with repeat notifications for the same ongoing issue.

### Accounts & Access
- Standalone farm accounts (each farm's data is isolated), with Google OAuth as an alternative to a local password.
- Role-based permissions (admin / manager / staff) for data entry vs. management and financial views.
- Audit logging on data changes.

### Data Integrity
- Chunked bulk inserts inside DB transactions for large imports.
- Duplicate-safe writes (`insertOrIgnore`/`updateOrInsert` + unique constraints) — designed after an early incident where a slow network retry caused duplicate sales entries.
- Offline-capable PWA data entry for production and sales, syncing automatically on reconnect.
- Automated database backups (Spatie Laravel Backup) to Google Drive.

---

## In Progress

| # | Feature | Depends on | Status |
|---|---|---|---|
| 3F | **Environmental / weather module** — Open-Meteo integration, daily THI, dashboard trend card | — | ✅ Deployed |
| 3G | **Feed (kg) + expense capture** — feed, vitamins, vaccines, restocking, electricity, manpower, with farm-wide → per-building allocation | 3F | ✅ Deployed |
| 3H | **Per-building investment dashboard** — pick a building, see prod-rate trend, alerts, estimated revenue & expenses, net contribution | 3G | ✅ Deployed |
| 3I | **Forward simulator / forecast** — predict survival, egg output, and earnings per building 1–4 months out | 3H | ⏳ **Pending — Animal Science consultation** |

The forward simulator (3I) is the one remaining feature that depends on external expertise: it requires an age-driven survival curve, lay curve, and feed-intake curve for Dekalb White layers, which the project is obtaining through a consultation with the university's Animal Science department. Everything else — recording, describing, and detecting what has already happened — does not require that input and has already been built.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Database | MySQL |
| Frontend | Blade + Alpine.js + Tailwind CSS (Vite build) |
| Hosting | Railway |
| Delivery | Progressive Web App (PWA) — installable, usable on farm-floor mobile devices, offline-capable data entry with background sync |
| ML | PHP-ML (linear regression forecasting) |
| Reports | barryvdh/laravel-dompdf (PDF), Maatwebsite Excel (CSV/XLS import & export) |

---

## Third-Party Integrations

| Service | Purpose | Notes |
|---|---|---|
| **[Open-Meteo](https://open-meteo.com)** | Historical + forecast weather (temperature, humidity, precipitation) for THI calculation | Free, no API key required; Archive API for history, Forecast API for the last 14 days + 16-day outlook |
| **Google OAuth** (Laravel Socialite) | Optional sign-in alongside local farm accounts | |
| **Google Drive** (via `masbug/flysystem-google-drive-ext`) | Off-site storage target for automated database backups | |
| **Railway** | Application hosting & deployment | |

*No paid third-party APIs are currently required — this was a deliberate choice for a student capstone with no operating budget.*

---

## System Architecture

```
                 ┌─────────────────────┐
                 │   Farm Excel Logs    │
                 │  (.xls, per period)  │
                 └──────────┬───────────┘
                            │ dynamic import parser
                            ▼
 ┌───────────────────────────────────────────────────┐
 │                    MySQL Database                   │
 │  hen_batches (buildings) · building_daily · sales    │
 │  weather_daily · expenses · alerts · audit_logs      │
 └───────┬───────────────────┬──────────────┬──────────┘
         │                   │              │
         ▼                   ▼              ▼
 ┌───────────────┐   ┌───────────────┐   ┌─────────────────┐
 │ Anomaly        │   │ Forecast       │   │ Open-Meteo       │
 │ Detection      │   │ (PHP-ML)       │   │ Weather Sync     │
 │ (age + peer)   │   │ + age + THI    │   │ (daily, scheduled)│
 └───────┬────────┘   └───────┬────────┘   └────────┬─────────┘
         │                    │                      │
         └────────────────────┴──────────────────────┘
                              ▼
                 ┌─────────────────────────┐
                 │  Laravel Dashboard (PWA)  │
                 │  farm-wide + per-building │
                 │  + investment dashboard   │
                 └─────────────────────────┘
```

---

## Data Model Highlights

- A **"building"** in this system is a **hen batch** (`hen_batches` / `building_daily`) — there is no separate buildings table; each flock's lifecycle in a house is tracked as a batch from placement through cull.
- **Sales** are recorded farm-wide by egg size, since eggs are graded and pooled before sale (not traceable back to a single building after grading).
- **Expenses** can be building-specific (rare) or farm-wide (feed, vaccines — the common case), with farm-wide costs **allocated to buildings by population share**, and revenue **attributed to buildings by their share of eggs produced**, for per-building reporting. Allocated and attributed figures are explicitly labeled *"Estimated"* in the UI — this is a deliberate transparency choice, not a gap: eggs are physically mixed farm-wide before sale, so exact per-building revenue is not directly measurable from the data.
- **Weather** is a single daily row per date (one farm location), joined to production by date, with the record's `source` (`archive` vs `forecast`) tracking whether it's a finalized reading or a rolling short-range forecast that will later be overwritten by the real archive value.

---

## Getting Started

```bash
git clone <repo-url>
cd egg-monitor
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# configure DB credentials in .env
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

**Scheduled tasks** (weather sync, weekly forecast retrain) require Laravel's scheduler to be running in production:
```bash
php artisan schedule:work   # or a cron entry calling `php artisan schedule:run` every minute
```

**One-time weather backfill** (after first deploy):
```bash
php artisan weather:backfill
```

---

## Project Status

- ✅ Core production & sales monitoring — deployed
- ✅ Forecast + anomaly detection — deployed
- ✅ Standalone accounts — deployed
- ✅ 5-year synthetic historical backfill, validated against real data
- ✅ Weather / THI module (3F) — deployed
- ✅ Feed & expense capture (3G) — deployed
- ✅ Per-building investment dashboard (3H) — deployed
- ⏳ Forward simulator (3I) — pending Animal Science consultation

---

## Recommended Additions

A few things worth considering as the system matures, roughly in order of value-for-effort:

**Near-term, low effort**
- **Push/SMS/email alerts** for red-band anomalies (e.g., a building crossing the 50% cull-consideration threshold) so managers are notified without opening the dashboard. Twilio or a free SMS gateway would fit the no-budget constraint.
- **CSV/Excel export** of the new expense summary and investment dashboard views — farm managers will want to print or forward a table, not just view it on-screen.
- **A "data completeness" indicator** on the dashboard (e.g., "3 buildings missing today's entry") — useful given data entry is manual and happens on the farm floor.

**Medium-term**
- **Broader automated test coverage** for the importer and anomaly logic specifically — the importer in particular has been rebuilt several times after edge cases, which is exactly what regression tests are for. (The expense allocator, weather module, and investment dashboard now have feature/unit tests as of 3F–3H.)
- **API documentation** (e.g., Scribe or a simple OpenAPI spec) if the system is ever meant to expose data to another tool or a mobile companion app.
- **Tagalog/English UI toggle** — all farm-facing interviews and documentation for this project were conducted bilingually; the dashboard itself is currently English-only, which is a mismatch worth closing for the actual users (farm staff).
- **Formal MAPE/accuracy dashboard for the forecast itself** — MAPE is now logged for both the flock-age and weather features, but isn't surfaced anywhere a manager or evaluator can see forecast accuracy over time.

**Longer-term / post-thesis**
- **Multi-farm support**, if the system is ever offered beyond SPC Farm Magalang — the standalone-accounts architecture is a reasonable foundation for this already.
- **The forward simulator (3I)**, once the Animal Science formula is obtained — this is the centerpiece feature the IT-expert evaluation was built around, and the one most worth prioritizing the moment the blocking consultation is complete.

---

## Acknowledgments

- **SPC Farm Magalang** — for granting data access, facility visits, and ongoing interviews under a signed confidentiality agreement.
- **[IT Expert's name/title]** — system evaluation and feature recommendations.
- **Pampanga State Agricultural University**, Animal Science Department — biological/production formula consultation (in progress).

---

*Sections marked `[confirm: …]` should be filled in with exact details before submission (expert names/titles for the acknowledgments section).*
