# Accessibility and W3C-oriented UI baseline

The active Abhipraya screens are built to support WCAG 2.2 AA-oriented practices.

## Implemented

- Semantic `header`, `nav`, `main`, `aside`, `footer`, `form`, `label`, and table markup.
- Skip links and visible keyboard focus indicators.
- Native `<details>/<summary>` profile menu, usable with keyboard and assistive technology.
- Sidebar collapse control with an accessible name, `aria-controls`, `aria-pressed`, and persisted preference.
- Icon-only collapsed navigation retains accessible labels and hover labels.
- Facility picker uses combobox/listbox roles, `aria-expanded`, `aria-activedescendant`, Escape, Arrow Up/Down, and Enter support.
- Form instructions and error messages are associated with their fields or announced through live regions.
- Responsive reflow at narrow viewports, reduced-motion support, and Windows high-contrast (`forced-colors`) support.

## Validation before release

1. Validate each rendered page with the W3C Nu HTML Checker.
2. Test keyboard-only navigation: Tab, Shift+Tab, Enter, Space, Escape, and arrow keys.
3. Test with NVDA or another screen reader.
4. Check text/background contrast and focus appearance in light, dark, high-contrast, and mobile states.
5. Run an automated audit such as axe DevTools, then manually review findings.

This document records implementation intent; it is not a substitute for release-time browser and assistive-technology testing.
