# Deployment and operations

## On-premise install

1. Install Docker Engine and the Docker Compose plugin.
2. Copy this repository release and run `cp .env.example .env`.
3. Set unique values for `DB_PASSWORD`; the installer generates missing `APP_KEY` and replaces the example database/Redis passwords. Keep `APP_DEBUG=false`. Set `APP_REGISTRATION_ENABLED=false` after creating the first owner or use invitation onboarding. Browser clients use Sanctum's same-origin session cookie and CSRF flow.
4. Run `./ops/install-onprem.sh`. The app listens on port 8080; use a reverse proxy for TLS. Example Caddy configuration (Caddy obtains and renews certificates automatically):
   ```caddy
   engine.example.com {
       reverse_proxy 127.0.0.1:8080
   }
   ```
   Restrict host firewall access to ports 80/443 and do not expose PostgreSQL, Redis or the application port publicly.
5. For the first admin, use the registration API. The initial account creates an organization owner.

Use `docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml` for operations. Database and Redis ports are not published to the host. Uploaded files and database data use persistent volumes. The PHP-FPM image runs as the unprivileged `www-data` user; upload validation accepts TXT, MD, PDF and DOCX up to 50 MiB. Secrets belong only in the protected `.env`/secret manager; rotate them outside Git. Services use `restart: unless-stopped` and health checks.

## SaaS / managed services

Build and deploy the same `Dockerfile.prod` image with `docker-compose.prod.yml` and `docker-compose.saas.yml`. Supply managed PostgreSQL with pgvector, Redis, secrets, and object storage through the deployment environment. Set `FILESYSTEM_DISK=s3` and the AWS-compatible variables for object storage. Keep `APP_KEY` stable across replicas. In organization settings, configure deployment mode and cloud fallback explicitly. Set `APP_DEPLOYMENT_MODE=saas` for SaaS registrations: new tenants receive the configurable `trial` plan, default expiry and seat/cost quotas. Plan tiers, status, expiry and grace days are configured by an owner and recorded in the audit log; expired plans become read-only after the grace window. On-premise mode skips plan enforcement entirely. This repository does not connect a payment provider or provision managed services automatically.

## Development-only optional services

- MinIO: `docker compose --profile object-storage up -d minio`; create the local bucket in the MinIO console at port 9001 and configure `FILESYSTEM_DISK=s3`, `AWS_ENDPOINT=http://localhost:9000`, path-style addressing and local-only credentials in `.env`.
- Ollama: `docker compose --profile model-node up -d model-node`; pull a model in Ollama and configure an OpenAI-compatible endpoint for the app.

These profiles are for local development only and use non-production default credentials.

## Backup and restore

For on-premise, run `./ops/backup.sh`. It briefly stops application writes for a consistent PostgreSQL dump and local uploaded-file archive under `./backups`; services restart automatically afterward. Copy those files to protected off-host storage. Restore with `./ops/restore.sh database.sql files.tar.gz`. Restore replaces current data. Test recovery against an isolated deployment before relying on a backup. Managed database and object-store backups must use the provider's backup facilities.

## Update and rollback

Tag each release with an immutable version. Deploy using `./ops/deploy.sh 1.0.1`; this builds the versioned image, runs migrations, and recreates services. Roll back the application image with `./ops/rollback.sh 1.0.0`. Database migrations are not automatically reversed; use only backward-compatible migrations and restore a pre-update backup if schema rollback is required.

## Health and diagnosis

Use `GET /api/health/live` for liveness and `GET /api/health/ready` for database/Redis readiness; `/api/health` remains a readiness alias. Compose reports app, worker, and web-container health; a scheduler service runs configured retention jobs. Use `docker compose logs --tail=200 app worker nginx` to inspect service errors. The package exposes request logs, tenant-scoped operational metrics, and browser admin views. Plan expiry/grace checks and audit are implemented; payment-provider integration and practical production acceptance remain open.
