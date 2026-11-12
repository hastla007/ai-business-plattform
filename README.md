# AI Business Plattform

Klickbarer UI-Prototyp und Laravel-Backend-MVP für einen lokalen Firmenassistenten mit kontrolliertem Unternehmenswissen.

## Starten

1. Docker und Docker Compose installieren.
2. Kopie von .env.example als .env anlegen.
3. Einen Schlüssel mit docker compose run --rm app php artisan key:generate erzeugen.
4. docker compose up --build ausführen.
5. API unter http://localhost:8000/api/v1, Oberfläche unter http://localhost:8000/index.html öffnen.

Das Compose-Profil startet Laravel, Queue-Worker, PostgreSQL mit pgvector und Redis. Für Modellaufrufe AI_BASE_URL auf einen erreichbaren OpenAI-kompatiblen lokalen Endpunkt setzen.

## API-Kurzstart

- POST /api/v1/register: name, email, password (mindestens 12 Zeichen), organization_name
- POST /api/v1/login: email und password
- Bei geschützten Routen Bearer-Token und X-Organization-Id mitsenden.
- POST /api/v1/documents: multipart-Datei (txt, md, pdf oder docx)
- POST /api/v1/chat: message und optional conversation_id
- POST /api/v1/agents/{id}/runs: input; Status über GET /api/v1/agent-runs/{id} abfragen

## Stand und Grenzen

Die Checkliste markiert umgesetzte Backend-Arbeitspakete. Streaming, Admin-Oberfläche für Modellprofile, Gruppenrechte, gesonderter Objektspeicher, Node-Routing, SaaS-/On-Prem-Produktionshärtung und Abnahmetests sind noch offen. Der Chat-UI-Prototyp ist noch nicht mit diesen API-Routen verbunden. Compose verwendet Entwicklungswerte; Zugangsdaten und Schlüssel vor produktivem Betrieb ändern.

## Entwickler-Checkliste

Siehe [ENTWICKLUNGS-CHECKLISTE.md](ENTWICKLUNGS-CHECKLISTE.md).