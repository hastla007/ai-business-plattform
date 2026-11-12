# Deployment and operations

## On-premise install

1. Install Docker Engine and the Docker Compose plugin.
2. Copy this repository release and run `cp .env.example .env`.
3. Set unique values for `DB_PASSWORD`; the installer generates missing `APP_KEY` and `REDIS_PASSWORD`. Keep `APP_DEBUG=false`.
4. Run `./ops/install-onprem.sh`. The app listens on port 8080; put TLS termination in front of it.
5. For the first admin, use the registration API. The initial account creates an organization owner.

Use `docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml` for operations. Database and Redis ports are not published to the host. Uploaded files and database data use persistent volumes.

## SaaS / managed services

Build and deploy the same `Dockerfile.prod` image with `docker-compose.prod.yml` and `docker-compose.saas.yml`. Supply managed PostgreSQL with pgvector, Redis, secrets, and object storage through the deployment environment. Set `FILESYSTEM_DISK=s3` and the AWS-compatible variables for object storage. Keep `APP_KEY` stable across replicas. In organization settings, configure deployment mode and cloud fallback explicitly.

## Backup and restore

For on-premise, run `./ops/backup.sh`. It briefly stops application writes for a consistent PostgreSQL dump and local uploaded-file archive under `./backups`; services restart automatically afterward. Copy those files to protected off-host storage. Restore with `./ops/restore.sh database.sql files.tar.gz`. Restore replaces current data. Test recovery against an isolated deployment before relying on a backup. Managed database and object-store backups must use the provider's backup facilities.

## Update and rollback

Tag each release with an immutable version. Deploy using `./ops/deploy.sh 1.0.1`; this builds the versioned image, runs migrations, and recreates services. Roll back the application image with `./ops/rollback.sh 1.0.0`. Database migrations are not automatically reversed; use only backward-compatible migrations and restore a pre-update backup if schema rollback is required.

## Health and diagnosis

The HTTP probe is `GET /api/health`. Compose reports app, worker, and web-container health; a scheduler service runs configured retention jobs. Use `docker compose logs --tail=200 app worker nginx` to inspect service errors. The current package exposes health and structured application logs; metrics dashboards, alert routing, automated license provisioning, and browser admin views are not included yet.
