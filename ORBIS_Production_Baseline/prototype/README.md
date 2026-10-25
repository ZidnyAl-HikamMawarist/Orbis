# Existing ORBIS Prototype — Preservation Notes

The supplied `index.html` prototype is the visual and interaction reference for the next iteration. Keep it; do not replace the world with a generic dashboard or redesign its palette without a validated reason.

## Preserve
- Colorful low-poly/isometric buildings, roads, trees, characters and department markers.
- Dark navy interface with cyan/blue/gold accents and readable status pills.
- World landmark selection and department detail views.
- Quick dashboard, map and navigation controls.
- Distinctive ORBIS identity and playful spatial context.

## Improve incrementally
- Separate scene engine, UI components, mock data and API adapter.
- Fix touch cancellation and movement controls behind open panels.
- Keep panel close/back controls visible on small screens.
- Remove viewport zoom restriction.
- Fix `. joy-active` selector typo to `.joy-active` and confirm matching class logic.
- Cap pixel ratio by quality mode; measure rather than assume performance.
- Pin Three.js version through a reproducible package/build setup.
- Add WebGL failure/context-loss fallback.
- Do not allow demo data or client-side role labels to become a security boundary.

## Migration strategy
1. Capture baseline screenshots and interaction tests.
2. Extract scene and data definitions without changing visuals.
3. Mount world renderer from React.
4. Replace hard-coded demo records with an API adapter incrementally.
5. Validate the same user journeys against the real backend.
