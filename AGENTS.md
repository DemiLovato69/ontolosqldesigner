# Agent Context

## Project
- Repository: `ontolosqldesigner`
- Production app: OntoloSQL Designer
- Production URL: `https://ontolosql-designer-kripb.ondigitalocean.app`
- DigitalOcean App Platform app ID: `6cfe1bf5-b876-4a53-b962-5e2fcd9ca7a8`
- Current production release at time of writing: `v0.0.3`
- Production release commit at time of writing: `991020b Merge develop for v0.0.3`

## Branches And Deployment
- Production deploys from `master`.
- DigitalOcean App Platform has `deploy_on_push: true` for `master` on the web service, Reverb service, workers, and pre-deploy migration job.
- Pushing `master` triggers production deployment automatically.
- Do not run a DigitalOcean app spec update at the same time as a `master` push unless intentionally coordinating deployments.
- `develop` is the integration branch used before release.
- Feature branches may exist and may be actively used by humans or agents; inspect status before changing branches.
- Before release work, inspect:
  - `git status --short --branch`
  - `git diff --stat`
  - `git log --oneline -10`
  - `git fetch origin`

## DigitalOcean Specs
- `.do/app.yaml` is a tracked template and contains placeholder values. Do not apply it to production.
- `.do/app.production.yaml` is ignored and contains the live production spec. Treat it as sensitive; do not commit it and do not expose secret values.
- If a production spec update is explicitly required, use:
  - `doctl apps update 6cfe1bf5-b876-4a53-b962-5e2fcd9ca7a8 --spec .do/app.production.yaml`
- Normal code releases should not need `doctl apps update`; pushing `master` is enough.
- Useful read-only checks:
  - `doctl apps list`
  - `doctl apps list-deployments 6cfe1bf5-b876-4a53-b962-5e2fcd9ca7a8`
  - `doctl apps spec get 6cfe1bf5-b876-4a53-b962-5e2fcd9ca7a8`

## Release Workflow
1. Start from a clean tree.
2. Update local refs with `git fetch origin`.
3. Switch to `master`.
4. Merge `develop` into `master`.
5. Resolve conflicts carefully.
6. Run verification commands listed below.
7. Remove generated `frontend/public/build` artifacts after frontend builds.
8. Create an annotated tag, for example `git tag -a v0.0.4 -m "Release v0.0.4"`.
9. Push `master`.
10. Push the tag.
11. Monitor DigitalOcean until the deployment reaches `ACTIVE`.
12. Smoke test production with `curl -I https://ontolosql-designer-kripb.ondigitalocean.app`.

## Verification Commands
- Frontend build:
  - `npm run build` in `frontend`
- Backend service/unit tests:
  - `docker exec php php artisan test tests/Unit/Services/DiagramSqlServiceTest.php tests/Unit/Services/OntologyMakerServiceTest.php`
- Backend feature tests:
  - `docker exec php php artisan test tests/Feature/Api/V1`
  - `docker exec php php artisan test tests/Feature/DiagramControllerTest.php tests/Feature/DiagramChangelogControllerTest.php`
  - `docker exec php php artisan test tests/Feature/AuthControllerTest.php tests/Feature/BroadcastChannelAuthTest.php`
  - `docker exec php php artisan test tests/Feature/AdminFoundryControllerTest.php tests/Feature/Api/V1/Foundry/FoundryApiTest.php tests/Unit/Services/Foundry/FoundryHostConfigServiceTest.php`
- Maker runtime tests:
  - `npm test -- --runInBand` in `maker-runtime`
- Foundry runtime tests (Node bridge; install deps first, deps are gitignored):
  - `docker run --rm -v "$PWD/foundry-runtime":/app -w /app node:18-alpine sh -c "npm ci --omit=dev && npm test"`
- Remove generated frontend build output after local builds:
  - `rm -rf "frontend/public/build"`

## Local Development Notes
- Use Docker PHP commands. Host PHP may not be installed.
- Common PHP command pattern:
  - `docker exec php php artisan ...`
- Local app URL is usually `http://localhost:8080`.
- Vite dev server may use `http://localhost:5173` via `backend/public/hot`.
- If UI is stale after adding or renaming Vue components, hard refresh the browser and restart the Vite/node container if needed.
- Local Docker/Postgres previously hit disk pressure. If Postgres fails with a no-space error, check Docker disk usage and prune builders carefully.

## Auth And Security
- Auth uses Sanctum SPA cookie/session auth.
- Web auth uses Sanctum SPA cookie/session auth and must stay that way.
- Desktop API auth uses Google OAuth + PKCE + one-time grant + expiring Sanctum bearer tokens under `/api/v1`.
- Do not store bearer tokens in browser `localStorage` or convert the web SPA to bearer-token auth.
- Native desktop clients should store bearer tokens in OS/native secure storage.
- Desktop token abilities currently include `desktop`, `diagrams:read`, `diagrams:write`, `diagrams:delete`, `imports:write`, `exports:read`, `sharing:write`, `changelog:read`, `changelog:write`, `presence:read`, `presence:write`, `foundry:connect`, `foundry:read`, and `tokens:manage`.
- Foundry abilities: `foundry:read` for status/browse/read endpoints, `foundry:connect` for OAuth/token connect and disconnect. SPA cookie sessions pass ability checks via Sanctum `TransientToken`, so `/api/v1/foundry/*` serves both web SPA and desktop clients.
- Foundry access tokens and OAuth client secrets are stored encrypted (Eloquent `encrypted` casts) and never returned to clients or logged. The Node bridge receives access tokens over stdin only.
- Import permission requires write access.
- Export permission requires read access.
- Frontend should show Import for writable viewers and Export for viewers with read access.
- Static routes must stay before dynamic `/{diagram}` routes.
- Sanctum cookie-auth tests need a first-party `referer`.

## Desktop API V1
- API spec lives at `openapi.json` and documents the `/api/v1` desktop API.
- V1 routes are registered from `backend/routes/api_v1.php`, required by `backend/routes/api.php`.
- V1 uses `auth:sanctum`, `track.seen`, and Sanctum ability middleware aliases registered in `backend/bootstrap/app.php`.
- Desktop OAuth config lives in `backend/config/services.php`:
  - `services.google.desktop_redirect`
  - `services.desktop_oauth.redirect_uri`
  - `services.desktop_oauth.grant_ttl_seconds`
- Desktop OAuth implementation files:
  - `backend/app/Services/GoogleOAuthService.php`
  - `backend/app/Services/DesktopOAuthGrantService.php`
  - `backend/app/Http/Controllers/Api/V1/Auth/OAuthController.php`
  - `backend/app/Http/Controllers/Api/V1/Auth/TokenController.php`
- Desktop realtime endpoints:
  - `POST /api/v1/broadcasting/auth`
  - `GET /api/v1/realtime/config`
- Bearer Reverb auth reuses `backend/routes/channels.php`; bearer tokens need `presence:read` for read channels and `presence:write` for writer channels.
- Current v1 endpoints intentionally reuse existing web diagram controllers/resources where possible.
- V1 route tests live in `backend/tests/Feature/Api/V1`.

## Production Storage
- App Platform components do not share local disk.
- Production imports require shared S3-compatible storage.
- Production uses `IMPORTS_FILESYSTEM_DISK=s3` with DigitalOcean Spaces-compatible settings.
- Local development defaults to private local `imports` disk.

## Current Feature Architecture

### Ontology As Code Metadata
- OAC metadata is persisted on `diagrams` JSON columns:
  - `interfaces`
  - `interface_link_constraints`
  - `custom_actions`
  - `shared_property_types`
- Migration:
  - `backend/database/migrations/2026_06_16_000004_add_ontology_metadata_to_diagrams_table.php`
- Backup JSON version is `2`.
- Backup v2 includes:
  - `interfaces`
  - `interfaceLinkConstraints`
  - `customActions`
  - `sharedPropertyTypes`
- v1 backup imports remain accepted with missing metadata defaulting to `[]`.
- OAC Maker export supports:
  - `defineSharedPropertyType`
  - `defineInterface`
  - `defineInterfaceLinkConstraint`
  - `defineAction`
  - object `implementsInterfaces`
- Maker runtime importer allows and preserves the OAC metadata helpers.

### Reference Tables
- Reference tables are visual/reference-only schema elements stored in the existing Vue Flow schema.
- They are not separate backend tables.
- Reference table markers:
  - `type: 'table'`
  - `data.tableKind = 'reference'`
  - `data.reference = true`
  - `data.exportable = false` when applicable
- Reference rows store JSON Schema-oriented metadata:
  - `data.reference = true`
  - `data.jsonSchemaType`
  - `data.jsonSchema`
  - mapped `data.sqlType`
  - nullability/index/type metadata
- Reference table titlebar opacity is intended to be `25%`.
- Reference table style is defined in `frontend/src/services/TableActions.js`.
- Reference table color changes should preserve dashed purple reference border and use alpha `0.25`.
- Manual reference table creation is in the diagram toolbar beside Add Table.
- Reference JSON import lives under the pipeline dropdown, not the right sidebar.
- Import Reference JSON opens `frontend/src/components/Modal/ReferenceJsonImportModal.vue` and supports paste or `.json` upload.
- Reference JSON schema expectations:
  - JSON Schema draft-07 object schema
  - `title` becomes reference table name
  - `properties` become reference rows/columns
  - import appends/updates reference tables and preserves missing rows by default

### Reference Links
- Links connected to reference rows/tables are visual-only reference links.
- Reference link markers:
  - `data.linkKind = 'reference'`
  - `data.exportable = false`
- Reference links are dashed purple.
- Reference links open a delete-only relationship popover.
- Real table-to-real table links must remain real/exportable relationship links:
  - `data.linkKind = 'relationship'`
  - `data.exportable = true`

### Pipeline Transforms
- Pipeline transform nodes are visual-only/non-exportable.
- Transform node marker:
  - `type: 'pipeline-transform'`
  - `data.exportable = false`
  - `data.sourceRowIds`
  - `data.targetRowIds`
- Transform edges are dashed orange and visual-only/non-exportable.
- Transform edge markers:
  - `type: 'transform'`
  - `data.linkKind = 'transform'`
  - `data.exportable = false`
- Pipeline dropdown options are icon-only:
  - `pipe-plus`: Add Pipeline
  - `pipe-json`: Import Reference JSON
- Pipeline transform labels are editable by single-clicking the transform title.
- Transform label updates use a transform-specific path that preserves spaces.
- Row multi-select uses Cmd/Ctrl/Shift-click; selected rows can be attached to pipeline transforms.
- Attach selected rows is the reliable manual workflow; drag/drop linking to transform nodes has historically been unreliable.

### View Filters
- View filters are visual-only local UI state.
- They persist in `localStorage`, scoped per demo/share token/diagram.
- They are not saved to diagram schema, not synced to collaborators, not included in backup JSON, and not part of undo/redo.
- Filter controls live in the left table sidebar bottom section.
- Filters include:
  - Reference Tables
  - Reference Links
  - Pipelines
  - Pipeline Links
- Each table in the left list has an eye toggle for visual visibility.
- The table list also has a Hide all / Show all control.
- Runtime Vue Flow elements may use `hidden: true` or `hidden: false`; save/sync/load paths must strip `hidden` via `stripViewOnlySchema()`.
- Do not persist `hidden` flags to the backend.
- Avoid forcing Vue Flow remounts for visibility changes, because that resets camera position/zoom.

### Foundry Platform Integration
- Read-only Palantir Foundry access for ontology diagrams via the `@osdk/foundry` Platform SDK.
- The SDK runs in the `foundry-runtime` Node bridge (`foundry-runtime/foundry.mjs` dispatcher + `sdk.mjs` adapter), invoked by Laravel `FoundryRuntimeClient` over stdin; tokens are never in argv/logs.
- Resolution rule: `authenticated user + ontology diagram + diagram host -> that user's Foundry connection for that host`. Collaborators never reuse the owner's token.
- Per-diagram host is owner-only (`DiagramPolicy::manageFoundry`); connecting and reads require diagram read access (`DiagramPolicy::viewFoundry`).
- Hybrid hosts: owners may enter any HTTPS host, but connecting requires an admin-configured host (DB table `foundry_host_configs`, managed at `/admin/foundry`, or env `FOUNDRY_HOSTS_JSON`) or `FOUNDRY_ALLOW_CUSTOM_HOSTS=true` with a custom-host OAuth client. Enabled DB hosts take precedence over the env map.
- Two auth methods: delegated OAuth (Authorization Code + PKCE) and pasted Foundry token (`FOUNDRY_ALLOW_TOKEN_AUTH`, default true). Token auth works for hosts without an OAuth client.
- Persisted tables: `foundry_connections` (per user+host, encrypted access/refresh tokens, `auth_type` oauth|token) and `diagram_foundry_configs` (host + default project/folder/ontology RIDs).
- Foundry config lives in `backend/config/foundry.php` (env-driven); migrations `2026_06_27_000000..000003`.
- Imported datasets become Foundry-linked reference tables: row `data.referenceSource.importedFrom = 'foundry-dataset'` with `datasetRid`, `datasetName`, `host`, `syncedAt`. Reference import upserts by `datasetRid`, so re-import/sync refreshes the same table (non-destructive; missing columns are preserved).
- Frontend: connection settings in `frontend/src/components/Diagram/FoundryPanel.vue` (right sidebar); browsing in `frontend/src/components/Modal/FoundryBrowserModal.vue` (opened from the top toolbar globe) with `FoundryTreeNode.vue`; schema→JSON Schema conversion in `frontend/src/services/foundryImport.js`; API calls in `frontend/src/services/Foundry.js`.
- Foundry `Resource.type` values are uppercase namespaced constants (e.g. `COMPASS_FOLDER`, `FOUNDRY_DATASET`); spaces are `ri.compass.main.folder.*` and `Folders.children` accepts a space RID.
- Local dev: `foundry-runtime` is bind-mounted into the `php` service in `docker-compose.yml`; production bakes it at `/opt/ontolosql-foundry` via `docker/app/Dockerfile`. `config('foundry.runtime.script')` prefers the mounted path and falls back to `/opt`.

## Export Rules
- SQL export and Maker export must exclude visual-only/reference/pipeline elements:
  - reference tables
  - rows under reference tables
  - reference links
  - pipeline transform nodes
  - transform edges
  - any `data.exportable === false` items
- Backup JSON must remain lossless and preserve visual-only reference/transform elements.
- Export filtering lives primarily in:
  - `backend/app/Services/DiagramSqlService.php`
  - `backend/app/Services/OntologyMakerService.php`
- Backup preservation is tested in:
  - `backend/tests/Unit/Services/DiagramSqlServiceTest.php`
- Maker export filtering is tested in:
  - `backend/tests/Unit/Services/OntologyMakerServiceTest.php`

## Important Files
- `backend/app/Services/DiagramSqlService.php`: SQL export, backup JSON creation/import, visual-only filtering.
- `backend/app/Services/OntologyMakerService.php`: Maker export/import and OAC metadata export.
- `backend/app/Services/GoogleOAuthService.php`: shared Google Workspace domain validation and Google user linking.
- `backend/app/Services/DesktopOAuthGrantService.php`: desktop OAuth PKCE request/grant storage and one-time code exchange.
- `backend/app/Http/Controllers/Api/V1/Auth/OAuthController.php`: desktop Google OAuth authorize/callback/token exchange.
- `backend/app/Http/Controllers/Api/V1/Auth/TokenController.php`: desktop bearer token me/list/revoke endpoints.
- `backend/app/Http/Controllers/Api/V1/RealtimeConfigController.php`: desktop Reverb client config endpoint.
- `backend/routes/api_v1.php`: versioned desktop API route definitions and ability middleware.
- `openapi.json`: OpenAPI 3.1 spec for `/api/v1` (includes the Foundry endpoints and `foundry_*` error contract).
- `maker-runtime/import-maker.mjs`: Maker `.mts` importer.
- `backend/config/foundry.php`: Foundry hybrid-host/OAuth/token/runtime config (env-driven).
- `backend/app/Services/Foundry/`: `FoundryHostConfigService` (host normalize + DB/env host resolution), `FoundryOAuthStateService` (PKCE state), `FoundryOAuthClient` (Foundry OAuth HTTP), `FoundryConnectionService` (per-user tokens, refresh, status), `FoundryRuntimeClient` (Node bridge), `FoundryPlatformService` (read ops scoped by diagram).
- `backend/app/Http/Controllers/Api/V1/Foundry/`: `DiagramFoundryConfigController`, `FoundryConnectionController` (hosts/connections/oauth/token/status), `FoundryResourceController` (spaces/folders/ontologies/datasets/files/search).
- `backend/app/Http/Controllers/AdminFoundryController.php` + `backend/resources/views/admin/foundry.blade.php`: admin host management at `/admin/foundry`.
- `foundry-runtime/foundry.mjs` + `sdk.mjs`: Node dispatcher + `@osdk/foundry` adapter (commit `package-lock.json`; deps are gitignored).
- `frontend/src/services/Foundry.js`, `frontend/src/services/foundryImport.js`: Foundry API client and dataset-schema→JSON Schema converter.
- `frontend/src/components/Diagram/FoundryPanel.vue`, `frontend/src/components/Modal/FoundryBrowserModal.vue`, `frontend/src/components/Modal/FoundryTreeNode.vue`: connection panel, browser modal, recursive tree.
- `frontend/src/components/Diagram/Diagram.vue`: main editor, Vue Flow wiring, view filters, sidebar, modals.
- `frontend/src/components/Diagram/DiagramHeader.vue`: diagram toolbar actions.
- `frontend/src/components/Diagram/DiagramRightSidebar.vue`: OAC metadata lists and changelog.
- `frontend/src/components/Diagram/PipelineTransformNode.vue`: pipeline transform node UI.
- `frontend/src/components/TransformEdge.vue`: transform edge renderer.
- `frontend/src/components/RowNode.vue`: row UI and multi-select.
- `frontend/src/components/Diagram/TableNode.vue`: table UI and reference badge/styling.
- `frontend/src/components/Modal/ReferenceJsonImportModal.vue`: reference JSON paste/upload modal.
- `frontend/src/components/Modal/RelationshipModal.vue`: relationship/reference/transform popover.
- `frontend/src/components/SvgIcon.vue`: inline custom icons.
- `frontend/src/services/TableActions.js`: table/row/edge helpers, reference table import helpers and styles.
- `frontend/src/composables/useSchemaActions.js`: schema mutation logic, reference/pipeline creation, row selection, transform attach.
- `frontend/src/composables/useAppHeaderActions.js`: shared header actions for Import/Export/Save and title/status.
- `.do/app.yaml`: tracked App Platform template; do not apply to production without replacing placeholders.
- `.do/app.production.yaml`: ignored live production spec; sensitive and not committed.

## Recent Release Notes
- `v0.0.3` merged `develop` into `master` and deployed successfully.
- Deployment ID for `v0.0.3`: `cd2225d3-d96d-4b6f-92ce-2bad138363ba`.
- Production smoke test returned HTTP 200 after deploy.
- `DiagramHeader.vue` had a known merge collision between master and develop; the release resolved in favor of develop because import/export/save moved to the main header while toolbar gained reference/pipeline controls.

## Coding Conventions For Agents
- Prefer small, targeted changes.
- Preserve existing UI patterns unless explicitly doing design work.
- Use `apply_patch` for manual edits.
- Do not revert user changes unless explicitly requested.
- Do not commit, tag, push, or deploy unless explicitly requested.
- Always inspect status/diff/log before committing or releasing.
- Keep generated `frontend/public/build` out of the working tree after local builds.
- Avoid adding compatibility code unless there is a concrete persisted-data, shipped-behavior, or external-consumer need.
