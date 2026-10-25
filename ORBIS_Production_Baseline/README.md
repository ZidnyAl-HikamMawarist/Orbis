# ORBIS — Production-Oriented Specification Baseline

**Version:** 1.0.0-draft  
**Status:** Implementation baseline — NOT certified production-ready  
**Product direction:** Multi-tenant SaaS for retail and multi-branch businesses  
**Experience:** Three.js 3D isometric world + quick dashboard as equal primary paths  
**Proposed stack:** React + TypeScript + Three.js + Laravel 13.x + MySQL 8.x

## What this package is

This is a synchronized specification baseline derived from the chosen ORBIS scope and the current HTML/Three.js prototype direction. It replaces the earlier starter-pack assumptions that treated the world as 2D/Phaser.

It is intentionally **not labelled production-ready**. Production readiness requires implementation, review, automated tests, runtime profiling, security validation, deployment rehearsal, backup restore rehearsal, and sign-off against the acceptance criteria in this package.

## Documents

| Path | Purpose |
|---|---|
| `docs/PRD.md` | Product requirements, personas, scope, user journeys, acceptance criteria |
| `docs/ARCHITECTURE.md` | Runtime architecture, boundaries, deployment topology |
| `docs/UX_WORLD_SPEC.md` | 3D world behavior, desktop/mobile interaction and accessibility |
| `docs/API_CONTRACT.md` | Proposed API conventions and endpoint inventory |
| `docs/SECURITY.md` | Threat model, tenant isolation, authorization and data handling |
| `docs/TEST_PLAN.md` | Verification strategy and release gates |
| `docs/DEPLOYMENT.md` | Environment, release, observability, backup and recovery plan |
| `docs/DECISIONS.md` | Architectural decisions and assumptions |
| `database/ERD.mmd` | Mermaid entity relationship diagram |
| `database/schema.mysql.sql` | Initial MySQL 8 schema draft with keys, constraints and indexes |
| `database/DATA_DICTIONARY.md` | Table and domain field definitions |
| `traceability/REQUIREMENTS_MATRIX.csv` | Requirement → implementation area → verification mapping |
| `prototype/README.md` | Guidance for retaining the user's existing prototype |

## Non-negotiable implementation rules

1. Tenant ownership is enforced server-side on every tenant-owned resource. Never trust a client-supplied `company_id` as proof of access.
2. Laravel migrations become the canonical schema source. `schema.mysql.sql` is a review/reference artifact; do not independently maintain two conflicting schemas.
3. All production routes require authentication and explicit authorization. UI hiding is not authorization.
4. KPI definitions must be documented, deterministic, timezone-aware, and consistent between dashboard and reports.
5. The 3D world is a visualization/navigation layer, not the source of truth for business data.
6. The quick dashboard must remain usable without entering or navigating the 3D world.
7. The prototype currently uses demo data. Do not connect it to production data before tenant isolation, authorization, validation, and error handling are implemented and tested.

## Open decisions before implementation freeze

- Authentication strategy: first-party Laravel session/cookie for same-site app vs token-based clients.
- Billing, subscription plans, quotas, and payment provider: excluded from MVP unless commercial launch requires them.
- Exact KPI formulas and business calendar per tenant.
- File storage provider and retention policy for any future attachments.
- Target scale and service-level objectives.
- Data residency, privacy obligations, and contractual retention requirements.
- Whether users may belong to multiple companies in MVP (schema supports memberships).
- Whether branch-level roles differ from company-level roles (MVP uses company roles plus scoped membership permissions).

## Suggested implementation sequence

1. Confirm open decisions and KPI glossary.
2. Scaffold Laravel migrations from the approved ERD and schema.
3. Implement tenant membership, role/permission checks, and tenant-isolation tests before business modules.
4. Implement branches, departments, members, projects, tasks, status history, alerts, KPI snapshots and reports.
5. Implement API contract and frontend service layer.
6. Integrate the current Three.js scene through a React component boundary; keep world rendering separate from dashboard data logic.
7. Run test, performance, security and recovery gates before launch.

## Original prototype reference
The original HTML prototype and supplied screenshots are preserved in `reference/prototype/`. Review `reference/prototype/README.md` before implementation. Keep this reference directory unchanged.
