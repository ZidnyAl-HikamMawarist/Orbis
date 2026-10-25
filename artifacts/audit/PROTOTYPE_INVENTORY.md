# ORBIS — Prototype Visual & Interaction Inventory

**Document:** `artifacts/audit/PROTOTYPE_INVENTORY.md`  
**Assessment Date:** 2026-10-25  
**Prototype Inspected:** `reference/prototype/index.html` (113,137 bytes, 2,589 lines)  
**Reference Screenshots:** `reference/prototype/screenshots/1000587270.jpg` through `1000587273.jpg`  
**Runtime Execution Status:** **VERIFIED** — Executed and tested via local HTTP server (`http://127.0.0.1:8088/index.html`) using browser automation and live viewport profiling (Desktop 1920×1080 and Mobile 412×915).

---

## 1. Visual Design & Aesthetic Identity

The ORBIS prototype establishes a distinct, memorable aesthetic combining an isometric low-poly 3D business environment with a sleek dark navy glassmorphic control dashboard.

### 1.1 Color Palette & Design Tokens
- **Backgrounds:**
  - Deep Cosmos Canvas: `#061325` (`--bg`)
  - Deep Navy Base: `#081a33` (`--navy`)
  - Translucent Glassmorphic Panel: `rgba(9, 27, 52, 0.92)` with `backdrop-filter: blur(12px..14px)`
  - Secondary Solid Panels: `#0a203d` (`--panel-solid`), `#102c52` (`--panel2`)
- **Accents & Borders:**
  - Electric Cyan: `#35d8ff` (`--cyan`) — focus rings, player ring, primary badges, ties
  - Royal Blue: `#2575fc` (`--blue`) — primary CTA gradients, active states
  - Amber Gold: `#f5b342` (`--gold`), `#c9861b` (`--gold-dark`) — Quick Dashboard CTA, travel badges, warning status
  - Bright Red / Crimson: `#ff5b79` (`--red`) — danger pills, overdue count badges, alert pings
  - Emerald Green: `#2ce0a3` (`--green`) — operational pills, completion progress gradients
  - Deep Lavender / Purple: `#7c69ef` (`--purple`) — brand cube accent, IT building trim
  - Line Gradients: `rgba(66, 121, 179, 0.38)` (`--line`), `rgba(88, 199, 255, 0.65)` (`--line-bright`)
- **Typography:**
  - Font Stack: `'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif`
  - Hierarchy: Clean geometric sans, 10px–12.5px micro-labels, 18px section titles, 20px–24px metric callouts.
- **Iconography:**
  - System uses native Unicode Emoji glyphs (🏛️, 💻, 👥, 💰, 📦, 🚀, ⚡, 🗺️, 🔔, ⚙️, 🧑🏻‍💼, 🧑🏻‍🚀) embedded inside hexagon badges, avatar pills, and sidebar items.

### 1.2 Three.js 3D World Construction
- **Camera & Projection:**
  - Type: `THREE.OrthographicCamera(-17, 17, 17, -17, -100, 200)`
  - Isometric Position: Offset `(26, 25, 26)` from camera target `(0, 0, 0)` looking downward at standard 35.264° / 45° isometric angles.
  - Frustum Size: Dynamic zoom recalculation `frustumSize = 34 / state.zoom`, maintaining true isometric orthographic projection without perspective distortion.
- **Lighting & Atmosphere:**
  - Sky/Background: Deep blue clear color `#134e7a`
  - Ambient Light: `THREE.AmbientLight(0xd9ecff, 0.72)`
  - Directional Sunlight: `THREE.DirectionalLight(0xfff5e0, 0.92)` at position `(28, 42, 20)` with 1024×1024 PCFSoftShadowMap (`dShadow = 32`).
  - Hemisphere Bounce: `THREE.HemisphereLight(0x74d2ff, 0x1e3c2f, 0.3)`
- **Campus Environment Elements (Sub-view 0: Explore World):**
  - **Island Base:** Stone foundation block `44 × 1.0 × 42` (`#d8c8a8`), topped with grass turf slab `42.6 × 0.25 × 40.6` (`#5ec669`).
  - **Roads & Plaza:** Perimeter loop and intersecting avenues in asphalt grey (`#415268`) with sidewalk kerbs (`#cbd6e2`) and dashed white street stripes (`#f4f8ff`). Central plaza features a stone terrace and an active multi-tiered fountain (`#d7e5f5`) with expanding animated translucent ring waves.
  - **Landscaping & Props:** 13 procedural trees (cylindrical trunks `#7c4e2d` with stacked dodecahedral green foliage or palm fronds on Bali), 4 parked low-poly cars, 4 dynamic moving cars looping along the perimeter, and 6 animated walking campus NPCs.
  - **Player Character:** Procedural low-poly business figure in a navy suit (`#1b4b91`), cyan shirt/tie (`#35d8ff`), animated alternating box legs, peach box head with hair and dark eyes, and a pulsing cyan selection ring at ground level.
- **Architectural Buildings (Departments):**
  1. **Executive HQ (0, -2):** Tall glass skyscraper (`7.4 × 6.4 × 9.5`), deep blue core (`#1f6fd1`), white architectural side fins, horizontal floor bands, entrance canopy, and a rotating, bobbing cyan ORBIS cube on the roof.
  2. **IT Department (-12, -10):** Lavender/purple trim (`#6c5ce7`), mid-rise campus structure (`6.2 × 5.4 × 6.8`), multi-row glass window strips, rooftop HVAC units, and glowing doorway.
  3. **HR Department (11, -11):** Mint/emerald trim (`#1dd1a1`), mid-rise geometry (`6.0 × 5.2 × 6.0`).
  4. **Finance Department (13, 3):** Amber gold trim (`#f5b342`), mid-rise geometry (`6.0 × 5.2 × 6.4`).
  5. **Operations Department (11, 13):** Crimson/pink trim (`#ff5b79`), mid-rise geometry (`6.4 × 5.5 × 5.6`).
  6. **Sales & Marketing (-13, 8):** Bright cyan trim (`#27d7ff`), mid-rise geometry (`6.0 × 5.2 × 5.8`).

### 1.3 Sub-view 1: Department Interior Office (Cutaway 3D Room)
- Isometric cutaway room with open front/right viewing angles.
- Floor slab (`18 × 0.4 × 16`, `#dce6f2`) with department-accented carpet inlay (`10 × 0.05 × 8`).
- Back and left cutaway walls (`#f2f7ff`) mounting a glowing Kanban wall screen and server racks / filing cabinets.
- 4 office workstations with wooden desks (`#c89d6c`), dual glowing widescreen monitors (`#35d8ff`), and seated team member figures.
- Cyan glowing circular exit portal pad at `(6.2, 5.5)` with floating "Exit to Campus" marker.

### 1.4 Sub-view 2: Branch Archipelago Map (3D Island Chain)
- Expansive ocean plane (`#0e6b96`) hosting 4 distinct island atolls:
  - **Jakarta Headquarters:** `(-15, -6)`, lush grass, cyan accent towers.
  - **Surabaya Branch:** `(14, -8)`, gold accent towers, warning status indicators.
  - **Bandung Branch:** `(-8, 13)`, purple accent towers.
  - **Bali Branch:** `(16, 12)`, palm trees, tropical green atoll, emerald accent towers.
- Elevated 3D parabolic arch glowing dots connecting HQ to each regional branch island.
- Fully animated cruise ship / ferry traveling along the curve between islands during branch switching.

---

## 2. Actual Implemented Interactions (Verified in Browser)

| Interaction | Trigger / Control | Runtime Verified Behavior |
|---|---|---|
| **Character Movement (Keyboard)** | `W`, `A`, `S`, `D` or Arrow Keys | Character moves smoothly aligned to isometric screen axes (45° projected XZ). Legs animate with sine-wave walk cycle. Character rotates to movement vector. |
| **Character Sprint** | `Shift` key or Mobile `⚡ RUN` button | Movement speed scales from 7.8 to 12.5 units/s. |
| **Point-and-Click Movement** | Left Click / Tap on Ground | Raycaster intersects ground meshes (`grass`, `roads`, `plaza`), sets `state.walkTarget`, and navigates character smoothly to the target position. |
| **Minimap Radar Navigation** | Left Click on Minimap Canvas | Maps canvas pixel coordinates to world bounds and issues a movement target command (`state.walkTarget`). |
| **Zoom Control** | Mouse Wheel / Minimap `+`/`−` buttons | Adjusts `state.zoom` between `0.65×` and `2.1×`, updating camera orthographic projection matrix. |
| **Department Selection (3D)** | Click on building mesh or floating hex badge | Smoothly switches scene from `campus` to `interior`, spawns 3D office room, repositions character, and opens the Department Detail Card overlay. |
| **Proximity Interaction** | Walk within 6.2m of building / 2.5m of exit | Shows floating animated `[E]` key prompt over player. Pressing `E` or mobile `E ACTION` opens department interior or exits back to campus. |
| **Department Tabs** | Click `Overview`, `Tasks`, `Team`, `Projects` | Switches active card tab, rendering KPI progress bars, avatar stacks, task table, team cards, or project roadmaps. |
| **Interactive Task Status Cycling**| Click any row in Department Task Table | Cycles task status sequentially (`Overdue` → `In Progress` → `On Track`) and displays a confirmation toast notification. |
| **Add Task (Inline Prompt)** | Click `+ Add Task` button | Prompts user via browser `prompt()`, adds new task to in-memory array, and re-renders table. |
| **Archipelago Branch Navigation** | Topnav `Branches`, Sidenav `Branches Map`, or `M` key | Switches scene to 3D Archipelago, displays floating island labels, branch list card, and branch summary bar. |
| **Branch Travel Animation** | Click `⛴️ Travel Here` button | Triggers 2.4-second smooth parametric translation and rotation of `travelShip` along archipelago coordinates, shows countdown banner, and switches campus world to target branch on arrival. |
| **Quick Dashboard Modal** | Sidenav Gold Button, Mobile `📊 DASH`, or `ESC` | Displays full-screen modal overlay with blur backdrop, containing KPI cards, SVG trend curves, task donut chart, alerts, and branch comparison table. |
| **Quick Dashboard Tabs** | Click `Overview`, `Tasks`, `Finance`, `Sales`, `Operations` | Dynamically swaps modal content between cross-department task master table, financial summary, and departmental metrics. |
| **Dashboard Widget Customization** | Click `⚙️ Customize` in Quick Dashboard | Toggles customization mode banner with pills to show/hide KPI cards, charts, and table. |
| **Global Search** | Type in Topbar `#globalSearch` | Filters departments, tasks, and branches; clicking any result navigates directly to that department 3D room or branch island. |
| **Workspace / Company Switching** | Click Sidenav `#companySwitchBtn` | Cycles between `PT. Nusantara Maju Bersama` and `ORBIS Global Logistics Tbk`, updating sidebar badges and metrics. |
| **Role Switching Dropdown** | Click Profile Box → Select Role | Switches role label (`Owner` vs `Branch Manager`), updating header display and toast message. |
| **HUD Minimization** | Click `✕` on Minimap Card | Hides right HUD cards and displays floating `🗺️ Show HUD` restore button. |
| **Mobile Virtual Joystick** | Touch / Pointer Drag on `#joystickZone` | Calculates normalized polar vector `(dx, dy)`, translates knob position, and drives character movement isometrically. |
| **Mobile Sidebar Drawer** | Click Hamburger Button `☰` | Slides sidebar drawer from `left: -270px` to `left: 0` with dark backdrop. |

---

## 3. Simulated Behaviors & Prototype Mockups

The prototype creates a compelling demonstration, but several features are purely simulated on the client:

1. **In-Memory Volatile State:** All companies, branches, departments, tasks, projects, and activities are hardcoded in static JS arrays (`COMPANIES`, `BRANCHES`, `DEPARTMENTS`, `ACTIVITIES`). No network calls or persistence exist.
2. **Mock Role Switching:** Selecting "Branch Manager" in the profile dropdown only modifies the DOM text (`#userRoleLabel`); it enforces no authorization constraints or data scoping.
3. **Simulated SVG Sparklines & Donut Charts:** The charts in the Quick Dashboard are hardcoded static SVG paths (`<path d="M0 35 Q35 28...">`) with hardcoded dasharrays; they do not calculate or aggregate live business data.
4. **Mock Company Switching:** Changing companies updates company name and total revenue strings in the sidebar, but leaves the exact same department buildings, tasks, and branch structures active.
5. **Primitive `prompt()` Dialog:** Adding a task triggers the browser's native modal `prompt()`, assigning the first member of the department without validation, dates, or database transactions.
6. **Hardcoded Alerts:** The recent activity list is a fixed list of 5 static mock entries (`Task Overdue`, `New Comment`, `Branch Alert`, etc.).
7. **Simulated Archipelago Travel:** Branch travel simply moves a ship mesh across coordinates and resets the campus branch name; the 3D campus geometry is identical for all branches except for a grass color swap and palm trees on Bali.

---

## 4. Dependencies & Asset Audit

| Dependency / Asset | Source / URL | Version | Security & Operational Assessment |
|---|---|---|---|
| **Three.js** | `https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js` | `r128` (Released May 2021) | **High Risk for Production.** Loaded via unpinned public CDN without Subresource Integrity (SRI) hash. Version r128 is significantly outdated compared to current Three.js releases (`r160+`). Must be bundled as a managed npm package (`three` + `@types/three`). |
| **Font: Inter** | System font stack fallback (`'Inter', ui-sans-serif...`) | System / CSS | Safe; no external font downloads blocking render. |
| **Icons & Emojis** | Native OS Unicode Emojis | Native | Can render inconsistently across Windows, macOS, Android, and Linux. Production should utilize calibrated SVG icon sets (e.g., Lucide / Heroicons). |
| **Local Images** | `reference/prototype/screenshots/*.jpg` | Local files | Preservation reference; not referenced directly by the runtime prototype HTML. |

---

## 5. Limitations & Technical Debt in Prototype

1. **Monolithic Single File:** All 2,589 lines of HTML, CSS, Three.js setup, scene building, mock data, and DOM event listeners reside in a single `index.html` file.
2. **CSS Class Typo (`. joy-active`):** At line 91, `. joy-active` has a space between the dot and class name, causing `.joy-active` styles to fail to apply as intended.
3. **Missing WebGL Fallback:** If WebGL fails to initialize or the browser encounters a WebGL context loss (`webglcontextlost`), the page remains blank without an informative error message or automatic redirect to the Quick Dashboard.
4. **Viewport Zoom Restriction:** `<meta name="viewport" content="...maximum-scale=1,user-scalable=no"/>` violates WCAG accessibility guidelines (WCAG 1.4.4 Resize Text) by preventing low-vision users from pinch-zooming.
5. **Event Handling & Touch Bleed:** On mobile devices, interacting with open overlay cards or the sidebar can accidentally trigger touch raycasting and pointer events on the underlying Three.js canvas.
6. **Uncapped Device Pixel Ratio:** Line 897 uses `Math.min(window.devicePixelRatio || 1, 2)`. While capped at 2, mobile devices with high fill rates can experience thermal throttling without a low-power mode.
7. **Lack of Lifecycle Cleanup:** The animation loop runs unconditionally via `requestAnimationFrame` even when tabs are hidden or when modals cover the entire screen, leading to battery drain.
8. **No Server-Side Validation or Tenant Scoping:** The prototype completely lacks authentication, CSRF protection, tenant isolation, or role-based access control.

---

## 6. Behavior-to-Component Traceability Mapping

The following table maps every significant prototype visual element and behavior to its target production architecture component, Laravel API endpoint, and backlog epic:

| Prototype Feature / Behavior | Prototype Lines | Target Production Component | Backend API / Service | Backlog Epic |
|---|---|---|---|---|
| **3D Campus Scene & Buildings** | 890–1286 | `apps/web/src/world/CampusScene.tsx` | `GET /api/v1/world/overview`<br>`GET /api/v1/world/branches/{id}` | Epic 4 (ORBIS Experience) |
| **Isometric Camera & Lighting** | 902–937 | `apps/web/src/world/IsometricCamera.ts` | Stored in `world_configs` table | Epic 4 |
| **Floating HTML Hex Badges** | 1447–1529 | `apps/web/src/world/WorldLabelsOverlay.tsx` | `GET /api/v1/world/overview` (summaries only) | Epic 4 |
| **Character Movement & Raycasting**| 958–1021, 2458–2532 | `apps/web/src/world/PlayerController.ts` | Client-only spatial navigation layer | Epic 4 |
| **Department Interior 3D Room** | 1288–1372 | `apps/web/src/world/DepartmentInteriorScene.tsx` | `GET /api/v1/world/departments/{id}` | Epic 4 |
| **Department Detail Floating Card**| 1632–1745 | `apps/web/src/features/departments/DepartmentDetailModal.tsx` | `GET /api/v1/departments/{id}`<br>`GET /api/v1/tasks?department_id={id}` | Epic 2 (Organization & Work) |
| **Task Status Cycling & Mutation** | 1694–1702 | `apps/web/src/features/tasks/TaskStatusDropdown.tsx` | `POST /api/v1/tasks/{id}/transitions` | Epic 2 |
| **Task Creation Dialog** | 1704–1714 | `apps/web/src/features/tasks/CreateTaskModal.tsx` | `POST /api/v1/tasks` | Epic 2 |
| **Archipelago 3D Branch Map** | 1374–1445 | `apps/web/src/world/ArchipelagoScene.tsx` | `GET /api/v1/branches` | Epic 4 |
| **Inter-Branch Travel Transition** | 1799–1833 | `apps/web/src/world/BranchTravelController.ts` | `POST /api/v1/companies/{id}/switch` (if multi-company) or branch filter | Epic 4 |
| **Quick Dashboard Fullscreen Modal**| 1836–2024 | `apps/web/src/features/dashboard/QuickDashboard.tsx` | `GET /api/v1/dashboard/summary`<br>`GET /api/v1/kpis` | Epic 3 (Monitoring) |
| **Minimap Radar Display** | 2029–2080 | `apps/web/src/world/MinimapRadar.tsx` | Synchronized with 3D scene entity coordinates | Epic 4 |
| **Global Search Autocomplete** | 2315–2355 | `apps/web/src/features/search/GlobalSearchBar.tsx` | `GET /api/v1/search?q={query}` (allowlisted tenant-scoped query) | Epic 3 |
| **Tenant / Company Switcher** | 2228–2232 | `apps/web/src/features/auth/CompanySwitcher.tsx` | `POST /api/v1/companies/{id}/switch` | Epic 1 (Secure Foundation) |
| **Recent Activity & Alerts Feed** | 1590–1615 | `apps/web/src/features/alerts/ActivityFeed.tsx` | `GET /api/v1/alerts`<br>`GET /api/v1/audit-events` | Epic 3 |
| **Mobile Virtual Gamepad & Drawer**| 2153–2208 | `apps/web/src/components/mobile/VirtualJoystick.tsx` | Client UI touch handler | Epic 4 |
| **WebGL Fallback View** | *Missing in prototype* | `apps/web/src/world/WebGLFallback.tsx` | Renders direct Quick Dashboard and 2D Campus Grid when WebGL is unavailable | Epic 4 |

---

## 7. Reusable Implementation Principles

1. **Strict Decoupling:** The Three.js canvas must remain purely a visual viewport. Business records, authentication state, and mutations must reside in React state / TanStack Query, connecting to Laravel REST endpoints.
2. **Asset Packaging:** Replace CDN Three.js r128 with npm package `three@^0.165.0` (or pinned modern release) bundled with Vite/TypeScript.
3. **Responsive Sizing & Clipping:** Maintain the 360px viewport threshold; ensure the Quick Dashboard and Department overlays utilize responsive drawers/bottom-sheets on screens under 820px without blocking viewport scrolling.
4. **Accessible Non-3D Route:** Every landmark accessible in the 3D world must have an identical, fully keyboard-operable counterpart in the Quick Dashboard and Topbar search.
