# Coding standards and conventions

## PHP

- Use PHP strict types in new API entry files.
- Use prepared statements or repository parameter binding for database access.
- Validate input before processing it.
- Return standard JSON through the shared response helper.
- Keep authentication, CSRF, and scope checks on the server.
- Do not expose exception traces, database details, credentials, or personal data to the browser.

## JavaScript and UI

- Use the shared API client and do not duplicate authentication logic in page scripts.
- Do not create inline styles or inline scripts; CSP blocks them.
- Use external CSS classes for dynamic visual states.
- Use labelled controls, keyboard focus, and text/icon cues in addition to colour.
- Keep Home, Analytics, and Reports consistent by consuming the same analytics definitions.

## JSON and analytical semantics

- Every analytical question needs `report_type`, stable value mapping, and an `indicator_key`.
- Icons are presentation only; analytics must use report type and configured semantics.
- Ratings use a defined scale; binary/availability questions use configured favourable values.
- Preserve version and schema hash on submitted responses.

## Naming

- Use lowercase hyphenated browser routes and documentation slugs.
- Use PHP class names in `PascalCase` and methods in `camelCase`.
- Use database field names only through a repository or well-scoped module query.
- Prefer clear business names such as `facilityNin`, `departmentId`, `surveyVersion`, and `indicatorKey`.
