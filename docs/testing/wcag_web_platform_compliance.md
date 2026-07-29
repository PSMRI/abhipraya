# WCAG and web platform compliance

## Target

Abhipraya should meet WCAG 2.2 AA expectations for public and administrator journeys where practical.

## Completed development-environment checks

| Check | Result | Evidence |
| --- | --- | --- |
| Semantic structure and labels | Pass | The tested pages expose one H1, banner, navigation, main and complementary landmarks, table headers, image alternative text, and labelled controls. |
| Mobile responsive layout | Pass | At a 390px viewport, the documentation testing page showed its menu control, used an off-canvas sidebar, and had no horizontal overflow. Administrator sign-in was also checked at 320px, 390px and 768px without horizontal overflow or unlabeled inputs. |
| Colour contrast and focus | Pass | Tested colour pairs meet WCAG AA contrast: body 15.26:1, paragraph text 7.71:1, links 6.65:1 and focus outline 6.31:1. The mobile menu control can receive focus. |
| NVDA screen-reader journey | Pass | On 2026-07-29, NVDA Speech Viewer with Firefox announced skip links, landmarks, headings, required fields, invalid-entry state, CAPTCHA instructions, login errors, buttons, links, comboboxes, tabs and alerts. The journey covered administrator sign-in, dashboard, QR Center, analytics, reports, CAPA and sign-out navigation. |
| Security-related web platform controls | Pass | The HTTPS administrator sign-in surface at `https://localhost` renders successfully. Development-environment responses include `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy` and `Cache-Control`. Protected-file requests return a generic unavailable-resource response without exposing file contents or IIS handler/configuration details. |

## Remaining validation

| Check | Status | Next step |
| --- | --- | --- |
| Keyboard-only public and administrator journeys | Partial | Complete manual keyboard-only journeys, including dropdown search, filters, touch targets, zoom/reflow, and visible focus on every interactive control. |
| Supported browser and device coverage | Partial | Repeat the completed journeys in the deployment owner's additional supported browser engines and devices. |

See [test results](test_results.md) for the detailed evidence record.
