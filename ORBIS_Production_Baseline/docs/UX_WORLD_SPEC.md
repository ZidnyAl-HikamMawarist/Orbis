# UX Specification — 3D World and Quick Dashboard

## 1. Experience principles

1. **Two equal paths:** spatial exploration and quick dashboard. Neither is a mandatory gateway to the other.
2. **World as context:** the map helps users understand where a problem is; detail panels and tables let them act.
3. **Preserve identity:** colorful low-poly buildings, roads, trees, small character, department-specific accent colors, navy translucent panels and cyan/gold highlights.
4. **Meaning beyond color:** every alert/status has text, icon or accessible label.
5. **Progressive disclosure:** avoid showing all metrics at once. Show summary first, then details on demand.
6. **Touch first-class:** controls must not require hover; interactive targets should be comfortably tappable.
7. **Graceful fallback:** dashboard remains available if WebGL fails or performance is poor.

## 2. World navigation

- Company view displays company HQ and available branches.
- Selecting a branch opens branch summary and allows transition into branch scene/context.
- Department landmarks show name, status and optional alert count.
- Selecting a landmark opens a detail panel with overview, tasks, team and projects tabs.
- “Back to map” returns to the prior world context and restores camera position where possible.
- Quick dashboard button opens a direct summary independent of current character position.
- Search results can navigate directly to a branch, department, project or task.
- World markers are visual affordances only; backend authorization controls all returned data and actions.

## 3. Desktop behavior

- Keyboard/mouse camera navigation must not conflict with page scrolling.
- Keep search, company context and profile access in the top bar.
- Sidebar may collapse; HUD cards can be hidden to maximize world area.
- Detail panel should not block essential camera controls and should support escape/back close.
- Provide keyboard-operable list of world locations alongside the canvas.

## 4. Mobile behavior

- Use a compact top bar with menu, search entry point, profile and quick dashboard access.
- Use a bottom sheet or full-screen detail panel; do not leave joystick/action controls active behind an opaque panel.
- Keep close/back controls sticky and visible while panel content scrolls.
- Respect safe-area insets and device viewport changes.
- Avoid fixed controls overlapping browser/system navigation bars.
- Virtual joystick must support pointer/touch cancellation and release outside its hit area.
- Sprint/action buttons must reset on `pointerup`, `pointercancel`, window blur and visibility change.
- Provide a non-joystick route to navigate branches/departments.
- At 360px width, no horizontal page overflow; detail tables should scroll within their own region or transform to stacked cards.
- Do not disable browser zoom.

## 5. Accessibility

- All core dashboard operations support keyboard interaction and visible focus.
- Use semantic buttons and form labels; don't use clickable `div` without keyboard behavior.
- Announce panel titles and relevant status changes where practical.
- Maintain sufficient contrast for text and status badges.
- Avoid relying on animation to communicate urgency; honor reduced-motion preferences.
- Canvas/world has a synchronized accessible list of locations and actions.
- Provide text alternatives for icon-only controls and tooltips where useful.

## 6. Performance and quality modes

- `low`: reduced shadows, capped pixel ratio, fewer decorative NPCs/cars, reduced animation.
- `balanced`: default target for general laptops/phones.
- `high`: enhanced shadows and detail for capable desktops.
- Proposed initial pixel ratio cap: 1.5 on mobile, 2.0 on desktop; verify by profiling.
- Stop render loop when hidden or when no animation/state change requires a frame, where feasible.
- Reuse geometries/materials for repeated low-poly objects.
- Use instancing for repeated static objects when it materially reduces draw calls.
- Avoid unnecessary DOM label updates every frame; update on camera/object changes or at a capped rate.
- Measure FPS, frame-time percentiles, draw calls, memory trend and time-to-interactive on agreed hardware.

## 7. UX acceptance checklist

- [ ] World landmarks are understandable without hover.
- [ ] All world entities also appear in an accessible list/search.
- [ ] Opening a detail panel does not leave conflicting movement controls active.
- [ ] Close/back restores prior context.
- [ ] Dashboard remains usable without WebGL.
- [ ] Layout passes at 360×800, 390×844, 768×1024 and 1440×900.
- [ ] No horizontal overflow outside intentionally scrollable regions.
- [ ] Touch cancellation does not leave character moving or sprinting.
- [ ] Reduced-motion setting is respected.
- [ ] Status meaning is communicated with text/icon, not color alone.
