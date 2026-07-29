# OpenAPI and Postman

## API contract files

| File | Purpose |
| --- | --- |
| [Rendered OpenAPI specification](../openapi-reference.md) | Read the API YAML as an HTML documentation page. |
| [Rendered Postman collection](../postman-collection-reference.md) | Read the collection JSON as an HTML documentation page. |
| [OpenAPI YAML download](openapi.yaml) | Machine-readable contract for documentation, validation, client generation, and tool import. |
| [Postman JSON download](postman_collection.json) | Importable request collection for development-environment API checks. |

## Use the OpenAPI definition

Open `docs/api/openapi.yaml` in an OpenAPI-compatible editor/viewer or import it into an API tool. The definition documents public-survey, authentication, boundaries, QR, analytics, responses, and CAPA endpoints using the standard Abhipraya response envelope.

Update the definition whenever an allow-listed API route, request field, response field, authentication rule, or status code changes. Treat `api/routes.php` and the OpenAPI definition as a paired review item.

## Use Postman safely

1. Import `docs/api/postman_collection.json` into Postman.
2. Set `baseUrl` to the development-environment or UAT address, for example `http://localhost`.
3. Enter a non-production `username`, `password`, the current `captcha` answer, and `surveyRef` only in the active environment.
4. Run **Get CAPTCHA**, then **Login**. Postman retains the session cookie for later requests when cookie handling is enabled.
5. Run **Get CSRF token** and copy the returned value to `csrfToken` before testing a protected write endpoint.

Never store production passwords, session cookies, CSRF tokens, database credentials, Memurai credentials, or private URLs in the collection file or a shared cloud workspace.

## Related documentation

- [API specifications](api_specifications.md)
- [Access control](access_control.md)
- [Developer guide](../developer-guide.md)
