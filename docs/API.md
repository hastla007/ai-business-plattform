# API-Ergänzungen

Alle v1-Routen unter `/api` verwenden Sanctum oder ein API-Bearer-Token. Mandantenrouten benötigen `X-Organization-Id`; Antworten auf Fehler sind JSON mit HTTP-Status.

## Gesundheit

- `GET /api/health/live` — Prozess-Liveness, HTTP 200 wenn der Prozess läuft.
- `GET /api/health/ready` — Readiness inklusive PostgreSQL und Redis; HTTP 503 bei fehlender Abhängigkeit.
- `GET /api/health` — Rückwärtskompatibler Alias für Readiness.

## Dokumentquelle

`GET /api/v1/documents/{document}/source` liefert die Originaldatei als Attachment, wenn der angemeldete Nutzer den Dokumentumfang lesen darf. Fremde Organisationen und unberechtigte Gruppen-/Eigentümerzugriffe liefern 404. Beispiel:

```http
GET /api/v1/documents/42/source
X-Organization-Id: 7
Accept: application/octet-stream
```

## Embedding-Fähigkeiten

Der Node-Heartbeat kann `capabilities.embedding_models` (Modellnamen) und `capabilities.embedding_dimensions` melden. Der aktuelle PostgreSQL-Index akzeptiert ausschließlich 1536 Dimensionen. Nicht passende Modellantworten führen zum fehlgeschlagenen Indexlauf; es werden keine Vektoren anderer Dimension in den Index geschrieben.
