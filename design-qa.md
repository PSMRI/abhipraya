# Abhipraya UI design QA

## Comparison target

- Source visual truth: `C:\Users\manish_k\.codex\generated_images\019f4f2a-c587-7461-a761-a249447bf1ee\exec-838909e5-ed3f-42d1-864f-48215a851eb6.png` (user-selected visual direction 1).
- Intended implementation routes: `ui/dashboard.html`, `ui/qr/generate.html`, `ui/login.html`, and `ui/survey.php`.
- Intended viewport: desktop, 1440 × 1024, light theme, authenticated Main Administrator state.

## Evidence status

No controllable in-app browser surface is available in the current session, so a browser-rendered implementation screenshot and an evidence-based visual comparison could not be captured. PHP lint and JavaScript syntax checks passed for the QR changes, but these do not replace visual QA.

## Findings

- [P1] Visual comparison is blocked.
  Location: all migrated routes.
  Evidence: no browser-rendered implementation capture is available.
  Impact: spacing, typography, mobile navigation, facility-picker interaction, and dark-mode presentation cannot yet be verified against the selected source.
  Fix: open `http://localhost:92/ui/qr/generate.html` as a signed-in Main Administrator at 1440 × 1024, capture it, and compare it directly against the selected visual target. Test role 2 separately to confirm its facility picker is locked to the assigned facility.

## Required fidelity surfaces

- Fonts and typography: implemented with Inter / Segoe UI fallbacks; not browser verified.
- Spacing and layout rhythm: compact 248px sidebar and 64px header implemented; not browser verified.
- Colors and visual tokens: navy/blue reference tokens implemented; not browser verified.
- Image and asset fidelity: existing project SVG icons remain available; the QR preview currently uses an external temporary image endpoint and needs replacement with a bundled local QR encoder before production.
- Copy and content: QR workflow copy reflects facility and department JSON configuration; not browser verified.

## Implementation checklist

1. Capture and compare the signed-in QR page at desktop and mobile sizes.
2. Replace the temporary external QR preview with a bundled open-source QR encoder.
3. Migrate the remaining administration routes to the shared `app-shell.css` and the selected shell markup.
4. Verify keyboard use of the facility combobox, focus states, and contrast in light and dark themes.

final result: blocked
