# GitBook and HTML documentation publishing

## Main entry point

The primary documentation entry is:

```text
/docs/README.md
```

This route renders the GitBook-style documentation home page in HTML. It contains links to all documentation sections.

## Friendly HTML routes

Examples:

- `/docs/project-overview.md`
- `/docs/technical-architecture.md`
- `/docs/user-guide.md`
- `/docs/developer-guide.md`
- `/docs/security.md`
- `/docs/dpg-readiness.md`

The HTML renderer uses a safe allow-list of documents. Documentation files remain Markdown in the repository, while the browser displays them as HTML.

## Authoring rules

1. Add the Markdown page beneath `docs/`.
2. Add it to `docs/SUMMARY.md` and link it from `docs/README.md` when it is a main section. `SUMMARY.md` is the navigation source of truth: every listed page appears in the HTML documentation sidebar automatically.
3. Use the Markdown file's path below `docs/` as its HTML route; for example, `architecture/new-page.md` opens at `/docs/architecture/new-page.md`.
4. Use relative Markdown links for repository/GitBook readers.
5. Test the HTML page after reloading the selected web-server/PHP process when PHP or routing files change; recycle the IIS application pool only when using the supplied IIS reference deployment.

## Publishing check

- Open `/docs/README.md`.
- Verify every main link opens an HTML page.
- Confirm tables and code blocks render correctly.
- Check mobile layout and keyboard navigation.
- Ensure no documentation page exposes credentials, internal secrets, or personal data.
