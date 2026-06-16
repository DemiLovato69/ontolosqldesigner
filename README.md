# OntoloSQL Designer

OntoloSQL Designer is a fork of SQL Designer focused on visually designing Foundry ontology definitions. It keeps the original drag-and-drop database canvas, SQL import/export, and multi-dialect schema support, while adding an `Ontology` diagram type that exports `.mts` modules for `@osdk/maker`.

The original project was a GUI SQL designer. This fork extends it into an ontology authoring workflow:

- Create standard SQL diagrams or Foundry ontology diagrams.
- Import SQL DDL into an ontology diagram and map SQL types to the closest Foundry data types.
- Export ontology diagrams as Maker `.mts` files.
- Export SQL diagrams as SQL, JSON, Laravel migrations, PNG, or ontology `.mts`.
- Add notes to tables and rows; ontology export maps them to Maker descriptions and SQL export emits them as comments.
- Manually verify users from the admin dashboard for local development without a mail server.

## Stack

| Layer | Technology |
| --- | --- |
| Frontend | Vue 3, Vue Flow, Vite |
| Backend | Laravel 11, Sanctum, Reverb |
| Database | PostgreSQL |
| Queue/cache | Redis |
| Ontology export | `@osdk/maker` and local ontology generator service |
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

Run the frontend build locally:

```bash
cd frontend
npm run build
```

The test suite covers ontology exports, SQL import/export, type mapping, manual admin verification, and diagram CRUD/sharing behavior.

## Notes On The Fork

This repository still contains original SQL Designer concepts and naming in several places, including the Docker Compose project name and some UI copy. The main behavior change is that ontology generation is now a first-class output path alongside SQL outputs.

## License

This project remains source-available under the original repository license. See [LICENSE](./LICENSE).
