# AI Business Plattform

Laravel-Backend und angebundene Weboberfläche für einen Firmenassistenten mit kontrolliertem Unternehmenswissen.

## Starten

1. Docker und Docker Compose installieren.
2. Kopie von `.env.example` als `.env` anlegen. Öffentliche Registrierung ist standardmäßig aus; für die initiale lokale Einrichtung `APP_REGISTRATION_ENABLED=true` setzen und nach Anlage des ersten Owners wieder auf `false` stellen.
3. Einen Schlüssel mit `php artisan key:generate` erzeugen und in `.env` eintragen.
4. docker compose up --build ausführen.
5. Oberfläche und API unter der in Compose konfigurierten URL öffnen.

Das Compose-Profil startet Laravel, Queue-Worker, PostgreSQL mit pgvector und Redis. Für Modellaufrufe AI_BASE_URL auf einen erreichbaren OpenAI-kompatiblen lokalen Endpunkt setzen.

## API-Kurzstart

- POST /api/v1/register: name, email, password (mindestens 12 Zeichen), organization_name
- POST /api/v1/login: email und password
- Die Weboberfläche nutzt Sanctum-Cookies und CSRF-Schutz. API-Clients können weiterhin Bearer-Token und `X-Organization-Id` verwenden.
- Gruppenverwaltung: GET/POST /api/v1/groups sowie PUT/DELETE /api/v1/groups/{group}/members/{user} für Owner und Admins.
- POST /api/v1/documents: multipart-Datei (txt, md, pdf oder docx)
- POST /api/v1/chat: message und optional conversation_id
- POST /api/v1/agents/{id}/runs: input; Status über GET /api/v1/agent-runs/{id} abfragen

## Stand und Grenzen

Die Weboberfläche umfasst Login/Registrierung, Chat mit Quellen und Verlauf, Dokumente, Agenten, Freigaben sowie Adminbereiche für Modelle, Nodes, Gruppen, Mitglieder und Nutzungsmetriken. Serverseitiges SSE-Streaming, Organisations-/Nutzer-/Profilbudgets, Einladungen und Feature-Tests sind implementiert. PHP-/Datenbanktests und Produktionsabnahmen müssen noch in CI bzw. einer Deployment-Umgebung verifiziert werden. Alarmierung, Lizenzierung und ein Composer-Lockfile bleiben offen. Die Entwicklungs-Compose-Datei ist für lokale Entwicklung; für On-Premise/SaaS siehe [DEPLOYMENT.md](DEPLOYMENT.md).

## Entwickler-Checkliste

Siehe [ENTWICKLUNGS-CHECKLISTE.md](ENTWICKLUNGS-CHECKLISTE.md) und den priorisierten [Entwickler-Backlog](ENTWICKLUNGS-BACKLOG.md).

## Phases 4–6 implementation

The repository includes guarded approval APIs for ticket creation, token-authenticated outbound-polling model nodes, profile routing, optional S3-compatible document storage, and production Compose files. See [ENTWICKLUNGS-CHECKLISTE.md](ENTWICKLUNGS-CHECKLISTE.md) for exact implementation status and [DEPLOYMENT.md](DEPLOYMENT.md) for install, backup/restore, update, and rollback instructions.

Production deploys require a configured `.env`; do not commit it. The web/API service listens on port 8080 and uses a managed TLS proxy in production. Browser admin/chat/approval screens, metrics/alerting, license automation, and runtime acceptance checks remain open.
