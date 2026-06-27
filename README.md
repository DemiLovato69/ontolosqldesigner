# OntoloSQL Designer

OntoloSQL Designer is a fork of SQL Designer focused on visually designing Foundry ontology definitions. It keeps the original drag-and-drop database canvas, SQL import/export, and multi-dialect schema support, while adding an `Ontology` diagram type that exports `.mts` modules for `@osdk/maker`.

The original project was a GUI SQL designer. This fork extends it into an ontology authoring workflow:

- Create standard SQL diagrams or Foundry ontology diagrams.
- Import SQL DDL into an ontology diagram and map SQL types to the closest Foundry data types.
- Export ontology diagrams as Maker `.mts` files.
- Export SQL diagrams as SQL, JSON, Laravel migrations, PNG, or ontology `.mts`.
- Add notes to tables and rows; ontology export maps them to Maker descriptions and SQL export emits them as comments.
- Manually verify users from the admin dashboard for local development without a mail server.
- Use the versioned `/api/v1` backend API from native desktop clients with Google OAuth, PKCE, expiring Sanctum bearer tokens, and Reverb presence authorization.
- Connect ontology diagrams to a Palantir Foundry stack (delegated per-user OAuth or a pasted Foundry token), browse spaces/folders/datasets and ontologies, and import dataset schemas as reference tables that can be re-synced from Foundry.

## Stack

| Layer | Technology |
| --- | --- |
| Frontend | Vue 3, Vue Flow, Vite |
| Backend | Laravel 11, Sanctum, Reverb |
| Database | PostgreSQL |
| Queue/cache | Redis |
| Ontology export | `@osdk/maker` and local ontology generator service |
| Foundry Platform | `@osdk/foundry` + `@osdk/client` via a Node bridge (`foundry-runtime`) |
| Dev runtime | Docker Compose, Nginx, PHP-FPM, Node |

## Local Development

Prerequisites:

- Docker Desktop
- GNU Make
- Git submodules initialized if you need the bundled `ontolosql` Rust CLI reference

Start from a clean checkout:

```bash
cp backend/.env.example backend/.env
make install
```

Open the app at:

```text
http://localhost:8080
```

Vite runs on `http://localhost:5173`, but Nginx at `8080` is the normal entry point.

Google login requires:

```dotenv
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_ALLOWED_DOMAIN=company.com
APP_URL=http://localhost:8080
```

Set the Google OAuth redirect URI to `${APP_URL}/auth/google/callback`. Use an Internal consent screen for Google Workspace projects.

The desktop companion API also uses Google OAuth. Configure a backend Google redirect URI and the native app callback URI:

```dotenv
GOOGLE_DESKTOP_REDIRECT_URI=${APP_URL}/api/v1/auth/oauth/google/callback
DESKTOP_OAUTH_REDIRECT_URI=ontolosql://oauth/callback
DESKTOP_OAUTH_GRANT_TTL_SECONDS=300
```

Set the additional Google OAuth redirect URI to `${APP_URL}/api/v1/auth/oauth/google/callback`.

Foundry Platform integration is optional and only applies to ontology diagrams. It is configured per host:

```dotenv
FOUNDRY_ALLOW_CUSTOM_HOSTS=false
FOUNDRY_ALLOW_TOKEN_AUTH=true
FOUNDRY_DEFAULT_SCOPES="api:read-data offline_access"
FOUNDRY_REDIRECT_URI=${APP_URL}/api/v1/foundry/oauth/callback
FOUNDRY_HOSTS_JSON={}
```

Admins manage approved Foundry hosts and OAuth clients at `http://localhost:8080/admin/foundry` (stored in the database) or via the `FOUNDRY_HOSTS_JSON` env map. Register `${APP_URL}/api/v1/foundry/oauth/callback` as an allowed redirect URI on each Foundry OAuth client. The Foundry SDK runs in the `foundry-runtime` Node bridge, which is baked into the production image and mounted into the local `php` container.

Useful commands:

```bash
make up
make down
make test
docker compose -p snydiagram logs --tail=120 php nginx node queue
```

The admin dashboard is available at:

```text
http://localhost:8080/admin
```

Use the admin user configured in the local database/env. The dashboard includes manual account verification so local development does not require SMTP setup.

## Desktop API

The web app keeps using Sanctum SPA cookie/session authentication. Native desktop clients should use the versioned API under `/api/v1` with `Authorization: Bearer <token>`.

The desktop OAuth flow is:

1. Generate a PKCE verifier/challenge and state in the native app.
2. Open `GET /api/v1/auth/oauth/google/authorize` in the system browser with `state`, `code_challenge`, `code_challenge_method=S256`, `device_name`, and `redirect_uri`.
3. Google redirects back to `/api/v1/auth/oauth/google/callback`.
4. The backend validates the Google Workspace domain and redirects to `DESKTOP_OAUTH_REDIRECT_URI` with a one-time `code` and original `state`.
5. Exchange the one-time code with `POST /api/v1/auth/oauth/google/token` and the PKCE `code_verifier`.
6. Store the returned expiring bearer token in native secure storage, not browser `localStorage`.

Desktop token abilities currently include:

- `desktop`
- `diagrams:read`
- `diagrams:write`
- `diagrams:delete`
- `imports:write`
- `exports:read`
- `sharing:write`
- `changelog:read`
- `changelog:write`
- `presence:read`
- `presence:write`
- `foundry:connect`
- `foundry:read`
- `foundry:llm`
- `tokens:manage`

The v1 API exposes owned, shared, and public/library diagrams; diagram CRUD; share/invite/visitor management; raw and chunked imports; export jobs and direct backup/migration/ontology exports; changelog entries; token management; Reverb auth/config endpoints; and the Foundry integration (host/connection management, OAuth and token connections, and read-only spaces/folders/datasets/files/ontologies scoped per ontology diagram).

The OpenAPI 3.1 spec is available in [`openapi.json`](./openapi.json). It documents the `/api/v1` desktop API surface and bearer-token security model, including the Foundry endpoints and the `foundry_*` error contract.

## Foundry Platform Integration

Ontology diagrams can connect to a Palantir Foundry stack to browse and import data. All Foundry access is read-only and runs server-side; Foundry tokens never reach the browser or desktop client.

Model:

- **Per-diagram host (owner only):** the diagram owner sets the Foundry host in the right-sidebar Foundry panel.
- **Per-user connection:** each user connects their own Foundry account, so collaborators never reuse the owner's access. Resolution is `authenticated user + ontology diagram + diagram host -> that user's connection for that host`.
- **Hybrid hosts:** owners may enter any HTTPS host, but connecting requires an admin-configured host (managed at `/admin/foundry` or via `FOUNDRY_HOSTS_JSON`) or an explicitly enabled custom-host OAuth client.
- **Two auth methods:** delegated OAuth (Authorization Code + PKCE) or a pasted Foundry personal/service token (`FOUNDRY_ALLOW_TOKEN_AUTH`). Token auth works even for hosts without an OAuth client.

Usage:

- Connect in the right sidebar (host + Connect/Token), then open the **Foundry Browser** from the top toolbar (globe icon).
- The browser has an ontology dropdown (default first), a path bar, an OS-style tree on the left, and a searchable contents list on the right.
- Import a dataset to create a Foundry-linked reference table. Linked tables show a refresh button in their title bar and can be re-synced individually, or all at once via **Sync linked**.

The Foundry SDK (`@osdk/foundry`) runs in the `foundry-runtime` Node bridge invoked by Laravel; access tokens are passed over stdin and never logged.

**Troubleshooting browsing:** if listing spaces/folders/ontologies or reading datasets fails, the cause is logged server-side at `error` level (so it appears in the platform's runtime logs even with `LOG_LEVEL=error`), including the operation, exit code, and the Node bridge's stderr. A `foundry_access_denied` result almost always means the connected account/token is missing the required Platform read scopes — set `FOUNDRY_DEFAULT_SCOPES` to include the filesystem/ontologies/datasets read scopes your stack uses, ensure the OAuth client grants them, and reconnect.

### Diagram Agent (Foundry AIP)

Ontology diagrams have an AI assistant powered by Foundry's OpenAI-compatible LLM proxy (AIP). It reasons over the full diagram and proposes structured edits you review before applying.

- **Open it** from the sparkles icon in the top toolbar (next to the Foundry globe).
- **Selectable models:** admins allowlist Foundry AIP models at `/admin/foundry`; users pick one per session.
- **Full-diagram context:** the agent receives all tables, columns, relationships, pipeline transforms, and ontology metadata (Vue Flow runtime/view-only state is stripped).
- **Review then apply:** the model returns an allowlisted patch (add/update tables, columns, relationships, and ontology metadata; delete/rename only when you opt in). Nothing is applied or saved automatically — you apply the patch and then Save as usual.
- **Shared, archivable sessions:** prompts and responses are stored (encrypted) and visible to diagram collaborators; sessions are archived, not deleted.

Server-side only: calls use the requesting user's own Foundry token (`foundry:llm` ability), so collaborators never reuse the owner's access. Enabled by default (`FOUNDRY_LLM_ENABLED=true`); it still needs AIP enabled on the stack and at least one model configured in `/admin/foundry`. Set `FOUNDRY_LLM_ENABLED=false` to turn it off.

## Ontology Workflow

1. Log in and create a new diagram.
2. Choose `Ontology` as the diagram type.
3. Add tables and rows, or import existing SQL DDL.
4. Imported SQL types are normalized to Foundry-friendly types such as `STRING`, `LONG`, `DECIMAL(12,4)`, `TIMESTAMP`, `ATTACHMENT`, and ontology `ENUM(...)`.
5. Add table or row notes with the note buttons on the canvas.
6. Export the diagram as `.mts`.

Ontology exports include:

- `defineObject` definitions
- `defineLink` definitions for relationships
- `defineValueType` definitions for enum-like values
- Maker `description` fields from table and row notes
- Foundry data type mapping for the canvas type palette

## SQL Workflow

Non-ontology diagrams retain the original SQL behavior:

- Create tables and rows visually.
- Import SQL DDL into the canvas.
- Export SQL for MySQL, PostgreSQL, SQLite, Oracle, SQL Server, and MS Access.
- Export JSON, Laravel migrations, and PNG.

Table and row notes are exported as SQL `--` comments. Dialects that support native inline column comments, such as MySQL, also keep those native comments.

## Testing

Run the backend test suite in Docker:

```bash
make test
```

Run the focused desktop API tests:

```bash
docker exec php php artisan test tests/Feature/Api/V1
```

Run the Foundry Node bridge tests:

```bash
docker run --rm -v "$PWD/foundry-runtime":/app -w /app node:18-alpine npm ci --omit=dev && \
docker run --rm -v "$PWD/foundry-runtime":/app -w /app node:18-alpine npm test
```

Run the frontend build locally:

```bash
cd frontend
npm run build
```

The test suite covers ontology exports, SQL import/export, type mapping, manual admin verification, diagram CRUD/sharing behavior, desktop OAuth/token handling, v1 bearer-token diagram access, Reverb auth, and the Foundry integration (host config, per-user connections, OAuth/token auth, and read-only browsing via a mocked runtime).

## Notes On The Fork

This repository still contains original SQL Designer concepts and naming in several places, including the Docker Compose project name and some UI copy. The main behavior change is that ontology generation is now a first-class output path alongside SQL outputs.

## License

This project remains source-available under the original repository license. See [LICENSE](./LICENSE).
