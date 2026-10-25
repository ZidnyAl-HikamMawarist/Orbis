# Prototype Reference Package

This directory preserves the original ORBIS HTML prototype and the screenshots supplied for review. Treat these files as reference material, not as the production application. Do not overwrite them while implementing the new app.

## Files
- `index.html` — original Three.js prototype supplied by the product owner.
- `screenshots/` — supplied visual reference screenshots.

## Agent instructions
1. Inspect the HTML structure, interactions, Three.js scene setup, camera, lighting, character/environment construction, responsive CSS, and external dependencies.
2. Run it in a browser if feasible and report any runtime/network issues.
3. Record which behaviors are implemented, simulated, or missing.
4. Use screenshots and the running prototype to preserve the established visual identity.
5. Do not blindly copy the prototype into production. Extract reusable visual/interaction requirements, then implement maintainable components in the approved app architecture.
6. Do not modify these reference files; copy only what is needed into application source.

## External dependencies
The prototype may load libraries or resources from external URLs. Verify connectivity and dependency versions before relying on them. A locally stored HTML file does not guarantee fully offline operation.
