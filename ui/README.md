# Abhipraya UI

The UI is organised by feature module. Shared, framework-free resources remain in
`assets/`; each user-facing page belongs to its feature folder.

```text
ui/
├── assets/                 Shared CSS, JavaScript, images, icons and themes
├── pages/
│   ├── auth/               Administrator sign-in and future recovery pages
│   ├── dashboard/          Role-aware administration overview
│   ├── account/            Profile and account-security pages
│   ├── qr/                 Department QR generation and management
│   └── public-survey/      Anonymous respondent feedback journey
├── login.html              Compatibility redirect to pages/auth/login.html
├── dashboard.html          Compatibility redirect to pages/dashboard/index.html
├── change-password.html    Compatibility redirect to pages/account/security.html
└── survey.php              Compatibility redirect to pages/public-survey/survey.php
```

Feature modules must not duplicate shared styles or scripts. Put reusable UI
behaviour in `assets/js/` and reusable visual rules in `assets/css/`.

The existing top-level URLs are retained only for bookmarked links and printed
QR codes. New links should use the appropriate `pages/<module>/` location.
