# ORBIS — End-to-End Test Matrix & Journey Reconciliation Report

## 1. Reconciliation of "6 vs. 10 Journeys" Claim

### 1.1 Root Cause & Discrepancy Explained
The discrepancy identified in the historical reports arises from conflating two different dimensions:
1. **10 Functional Product Journey Scenarios (Journeys A through J)**:
   The product requirements specification defines 10 distinct user stories/journeys:
   - **Journey A**: Anonymous unauthenticated visit & login
   - **Journey B**: Session persistence & tenant context
   - **Journey C**: Branch navigation & facility inspection
   - **Journey D**: Task creation & workflow state transitions
   - **Journey E**: Task drawer detail & operational comments
   - **Journey F**: Quick Dashboard metric indicators & KPI thresholds
   - **Journey G**: 3D Isometric campus visualization & camera controls
   - **Journey H**: 2D topological fallback toggle
   - **Journey I**: Cross-tenant data isolation & boundary checks
   - **Journey J**: Session termination & post-logout protection
2. **3 Compound Test Specifications in Playwright**:
   To minimize browser startup overhead and simulate realistic multi-step user workflows, Playwright bundles the 10 scenarios into 3 consolidated test specifications:
   - **Test Specification 1**: Covers Journeys A, B, and J (Auth, Reload Persistence, and Logout).
   - **Test Specification 2**: Covers Journeys C, D, and E (Branches, Task Lifecycle, Drawer, and Comments).
   - **Test Specification 3**: Covers Journeys F, G, and H (Quick Dashboard, 3D Campus, and 2D Fallback).
3. **6 Browser Test Executions**:
   Running the 3 compound test specifications across **2 target viewports** (Chromium Desktop 1280×720 and Chromium Mobile 390×844) results in **6 total automated browser test executions**.

---

## 2. Playwright E2E Execution Matrix

| Test Run ID | Compound Journey Name | Functional Scenarios Covered | Target Browser | Viewport Resolution | Execution Duration | Status | Evidence Artifact |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **E2E-01** | Journey A & B | A (Login), B (Session/Tenant), J (Logout) | Chromium Desktop | 1280 × 720 | 22.6s | **PASS** | `test-results/orbis-...-desktop` |
| **E2E-02** | Journey C & D | C (Branches), D (Tasks/Transitions), E (Comments) | Chromium Desktop | 1280 × 720 | 13.1s | **PASS** | `test-results/orbis-...-desktop` |
| **E2E-03** | Journey E & H | F (KPIs), G (3D World), H (2D Fallback) | Chromium Desktop | 1280 × 720 | 11.2s | **PASS** | `test-results/orbis-...-desktop` |
| **E2E-04** | Journey A & B | A (Login), B (Session/Tenant), J (Logout) | Chromium Mobile (Pixel 7) | 390 × 844 | 25.8s | **PASS** | `test-results/orbis-...-mobile` |
| **E2E-05** | Journey C & D | C (Branches), D (Tasks/Transitions), E (Comments) | Chromium Mobile (Pixel 7) | 390 × 844 | 13.2s | **PASS** | `test-results/orbis-...-mobile` |
| **E2E-06** | Journey E & H | F (KPIs), G (3D World), H (2D Fallback) | Chromium Mobile (Pixel 7) | 390 × 844 | 11.7s | **PASS** | `test-results/orbis-...-mobile` |

**Total Suite Duration**: 1.7 minutes  
**Worker Count**: 1 worker (serialized execution for database state predictability)  
**Retries**: 0 retries in final green run  
**Pass Rate**: **100% (6 of 6 passed)**
