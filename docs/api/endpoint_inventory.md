# Endpoint inventory

| Method | Endpoint | Authentication | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/auth/captcha` | Public | Get the login CAPTCHA challenge. |
| POST | `/api/v1/auth/login` | Public | Create an administrator session. |
| POST | `/api/v1/auth/logout` | Session + CSRF | End the current session. |
| GET | `/api/v1/auth/me` | Session | Read current user and scope. |
| GET/POST | `/api/v1/auth/profile` | Session; POST uses CSRF | Read or update permitted profile fields. |
| POST | `/api/v1/auth/change-password` | Session + CSRF | Change the signed-in user password. |
| GET | `/api/v1/auth/csrf` | Session | Obtain CSRF token data. |
| GET | `/api/v1/qr` | Session | List QR records in permitted scope. |
| POST | `/api/v1/qr/generate` | Session + CSRF | Generate a permitted department QR record/poster context. |
| GET | `/api/v1/analytics/summary` | Session | Return scope-aware Home and Analytics data. |
| GET | `/api/v1/responses` | Session | List anonymous response records in permitted scope. |
| GET | `/api/v1/responses/view` | Session | View one permitted response. |
| GET | `/api/v1/responses/export` | Session | Export permitted response data. |
| GET/POST | `/api/v1/capa/actions` | Session; POST uses CSRF | Read or record CAPA actions. |
| GET | `/api/v1/public-survey/resolve` | Public | Resolve QR facility, department, and survey context. |
| GET | `/api/v1/public-survey/questions` | Public | Load validated active survey questions. |
| POST | `/api/v1/public-survey/location` | Public | Validate location where configured. |
| POST | `/api/v1/public-survey/submit` | Public | Submit anonymous validated feedback. |

All administrator endpoints must enforce facility scope on the server. A browser-supplied facility NIN is never sufficient authority.
