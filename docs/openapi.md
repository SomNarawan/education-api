# OpenAPI and Swagger UI

The API contract is OpenAPI 3.1 and covers every application route under `/api`.

## Generate the contract

```bash
composer openapi
```

This creates both formats:

- `storage/api-docs/api-docs.json`
- `storage/api-docs/api-docs.yaml`

The directory is generated and intentionally ignored by Git. Regenerate the files before handing the contract to an external system or publishing a release artifact.

## Open Swagger UI

Start Laravel, then open:

```text
http://localhost:8000/api/documentation
```

The UI can call protected endpoints after using **Authorize** with one of these schemes:

- `bearerAuth`: JWT returned by `POST /api/auth/portal-login` (enter the token without adding the `Bearer` prefix manually).
- `portalApiKey`: server-to-server key sent as `X-API-KEY` for `/api/portal-main-student/*` endpoints.

Public endpoints override the default JWT requirement in the contract.

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `L5_SWAGGER_CONST_HOST` | `/` | Server URL written into the contract. Use the deployed API origin when exporting to another team. |
| `L5_SWAGGER_GENERATE_ALWAYS` | `false` | Regenerate whenever Swagger UI is opened. Keep disabled in production and CI. |
| `L5_SWAGGER_GENERATE_YAML_COPY` | `true` | Generate YAML in addition to JSON. |
| `L5_FORMAT_TO_USE_FOR_DOCS` | `json` | Contract format loaded by Swagger UI. |
| `L5_SWAGGER_UI_PERSIST_AUTHORIZATION` | `false` | Persist credentials in the browser between reloads. |

Never place real tokens, API keys, or personal information in OpenAPI examples.

## Validate changes

```bash
php artisan test tests/Feature/OpenApi/OpenApiGenerationTest.php
```

The contract test generates the document, validates security and file media types, resolves every local `$ref`, and compares every Laravel API method/path with the OpenAPI paths. Adding or removing an API route without updating the contract fails the test with the missing operation.

When running the full suite locally, ensure `APP_KEY` exists in `.env.testing` or the environment. A process-local key can be used without changing project files:

```powershell
$env:APP_KEY = php artisan key:generate --show
php artisan test
```

## Maintenance layout

- `app/OpenApi/ApiDefinition.php`: API metadata, server, tags, and security schemes.
- `app/OpenApi/ApiOperation.php`: consistent operation defaults and path parameters.
- `app/OpenApi/Paths/`: operations grouped by domain.
- `app/OpenApi/Schemas/`: reusable request, response, and error schemas.
- `config/l5-swagger.php`: generation and Swagger UI configuration.
