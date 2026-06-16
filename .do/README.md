# DigitalOcean App Platform

This directory contains the App Platform spec for deploying OntoloSQL Designer without manually creating each component in the DigitalOcean UI.

## Apply The Spec

Create a new app:

```bash
doctl apps create --spec .do/app.yaml
```

Update an existing app:

```bash
doctl apps update APP_ID --spec .do/app.yaml
```

## Components

The spec defines:

- `ontolosqldesigner`: Nginx + PHP-FPM web service.
- `reverb`: Laravel Reverb WebSocket service mounted at `/app`.
- `diagram-worker`: diagram import/export queue worker.
- `queue-emails`: email queue worker.
- `migrate`: pre-deploy migration job.
- `snydiagram`: managed PostgreSQL database.

All PHP components build from `docker/app/Dockerfile` and run different commands from the same image.

Shared environment variables live in the top-level `envs` block so services, workers, and jobs inherit the same Laravel/database/storage/session/Reverb configuration. Component blocks should only need runtime-specific fields such as `run_command`, `http_port`, and routes.

## Required Edits

Before applying the spec, update these values in `.do/app.yaml`:

- `APP_KEY`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_ALLOWED_DOMAIN`
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_BUCKET`
- mail credentials and from address
- `APP_URL`, `REVERB_HOST`, `VITE_REVERB_HOST`, `SESSION_DOMAIN`, and `SANCTUM_STATEFUL_DOMAINS` if the domain is not `ontolosql-designer-kripb.ondigitalocean.app`
- the `databases` block only if your existing App Platform database component is not named `snydiagram`
- Git branch if production should deploy from a branch other than `master`

## Import Storage

App Platform components do not share local disk. The web component receives upload chunks, and the queue worker processes them later, so imports must use shared object storage.

The spec sets:

```env
IMPORTS_FILESYSTEM_DISK=s3
```

Configure the `AWS_*` values for a DigitalOcean Spaces bucket. Local development still defaults to the private local `imports` disk.

## Reverb Routing

The `reverb` service is routed at `/app` with `preserve_path_prefix: true`, matching Laravel Reverb/Pusher client URLs such as `/app/{key}`.
