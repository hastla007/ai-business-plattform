# Entwickler-Checkliste

Die Liste setzt die Projektbeschreibung in umsetzbare Arbeitspakete um. Der aktuelle Stand ist ein statischer UI-Prototyp; Chat, Dokumentimport und Modellknoten verwenden noch Beispieldaten. Die Backend-Aufgaben sind daher noch offen.

## Phase 1 — Lokaler vertikaler Prototyp

### 1. Projektgrundlage und Mandantentrennung

- [ ] Laravel-Anwendung als Backend anlegen.
- [ ] SPA/API-Authentifizierung einrichten, zum Beispiel mit Laravel Sanctum.
- [ ] Firmen, Nutzer, Mitgliedschaften, Standorte und Rollen implementieren.
- [ ] Firmen- und Dokumentrechte serverseitig prüfen; alle Firmenobjekte mandantengebunden abfragen.
- [ ] Docker-Compose-Entwicklungsstack für App, Queue-Worker, PostgreSQL, Redis, Objektspeicher und optionalen Modellknoten einrichten.
- [ ] Persistente Volumes für Datenbank und Quelldokumente konfigurieren.
- [ ] Konfiguration über Umgebungsvariablen laden; keine Schlüssel oder Zugangsdaten ins Repository schreiben.
- [ ] Datenbankschema und Migrationen für die unten aufgeführten Kernobjekte erstellen.

**Abnahmepunkt:** Eine Firmeninstanz startet lokal mit Docker; Nutzer können sich anmelden und sehen ausschließlich ihre Firma und deren Daten.

### 2. Modelladapter und Modellprofile

- [ ] Provider-Schnittstelle für Chat und Streaming definieren.
- [ ] Schnittstelle für Embeddings und für die Kommunikation mit Modellknoten definieren.
- [ ] Einen lokalen Modellserver über einen Adapter anbinden.
- [ ] Streaming- und nicht-streamende Antworten unterstützen.
- [ ] Timeouts, begrenzte Retries, Nutzungslimits und verständliche Providerfehler abbilden.
- [ ] Modellprofile und Provider-Konfiguration in der Admin-Oberfläche verwalten.
- [ ] Modellzugriffe so abstrahieren, dass ein Wechsel des Providers keine Änderungen an der Chatlogik erfordert.

**Abnahmepunkt:** Laravel kann über ein Modellprofil mit einem lokalen Modell chatten; Provider können hinter derselben Schnittstelle ausgetauscht werden.

### 3. Chat, Speicherung und Audit

- [ ] Konversationen und Nachrichten persistent speichern.
- [ ] Versionierte API POST /api/v1/chat und Chat-UI implementieren.
- [ ] Berechtigung für den ausgewählten Assistenten bei jeder Anfrage prüfen.
- [ ] Pro Anfrage eine request_id und pro Agentenlauf eine run_id erzeugen.
- [ ] Antwortstatus, Quellen sowie Modell- und Knotenmetadaten an die UI zurückgeben.
- [ ] Laufzeit und Nutzungsmetadaten protokollieren.
- [ ] Geheimnisse, Zugangsdaten und vollständige vertrauliche Prompts aus Logs ausschließen.
- [ ] Gesprächs- und Aufbewahrungsrichtlinien konfigurierbar machen.

**Abnahmepunkt:** Ein angemeldeter Nutzer kann mit einem konfigurierten Modell chatten; Verlauf und Auditdaten sind der richtigen Firma zugeordnet.

## Phase 2 — Unternehmenswissen (RAG)

### 4. Dokumentenimport und Suche

- [ ] Upload und Speicherung von Quelldateien mit Firmenbezug, Hash und Versionsnummer implementieren.
- [ ] Queue-Jobs für Textextraktion, Metadaten und Dokumentstatus einrichten.
- [ ] Dokumente in nachvollziehbare Abschnitte (Chunks) aufteilen.
- [ ] Embeddings über den Provider erzeugen und zusammen mit Zugriffsmetadaten in PostgreSQL/pgvector speichern.
- [ ] Keyword- und Vektorsuche kombinieren.
- [ ] Firmen-, Gruppen- und Dokumentrechte bereits in der Suchabfrage durchsetzen.
- [ ] Treffer mit Dokumentname und Seiten- oder Abschnittsangabe in den Modellkontext übergeben.
- [ ] Quellen und Fundstellen in der Chat-UI anzeigen.
- [ ] Bei Dokumentänderung alte Chunks ersetzen; beim Löschen Quelldatei, Text und Vektoren entfernen.
- [ ] Importfehler, erneute Verarbeitung und Statusanzeige abbilden.

**Abnahmepunkt:** Antworten verwenden nur für den Nutzer freigegebene Dokumentstellen und zeigen überprüfbare Quellen an.

## Phase 3 — Kontrollierter, schreibgeschützter Agent

### 5. Agent Runtime und Werkzeugregister

- [ ] Agent-Definitionen versionieren und Zweck, Anweisungen, erlaubte Modelle, Wissensquellen, Werkzeuge, maximale Schritte, Timeout, Budget, Freigabe- und Aufbewahrungsrichtlinie speichern.
- [ ] Laufstatus queued, running, awaiting_approval, completed, failed und cancelled unterstützen.
- [ ] Längere Läufe als Queue-Jobs ausführen und ihren Zustand wiederherstellbar speichern.
- [ ] Werkzeugregister mit validierten JSON-Schemas und Parameterprüfung implementieren.
- [ ] Nutzer- und Firmenberechtigungen vor jedem Werkzeugaufruf serverseitig prüfen.
- [ ] Nur registrierte, eng begrenzte Werkzeuge ausführen; keine freien Shell-Befehle, beliebigen HTTP-Aufrufe oder direkten Datenbankzugriffe bereitstellen.
- [ ] search_knowledge(query) als erstes schreibgeschütztes Werkzeug implementieren.
- [ ] Agentenlauf, Schritte und Fehler mit redigierten Metadaten protokollieren.
- [ ] Maximale Schrittzahl und Laufzeit verbindlich erzwingen.
- [ ] Wissensagent bereitstellen: freigegebene interne Dokumente suchen und Quellen nennen.
- [ ] Dokumentenagent bereitstellen: klassifizieren, Informationen extrahieren und Entwürfe erstellen.
- [ ] Ticket-Agent zunächst nur lesend betreiben und Antwortvorschläge erstellen.

**Abnahmepunkt:** Der Wissensagent recherchiert über freigegebene Dokumente und kann keine Daten oder externen Systeme ändern.

## Phase 4 — Freigaben und Schreibwerkzeuge

### 6. Menschliche Freigabe

- [ ] Freigabeansicht und Status awaiting_approval in API und UI implementieren.
- [ ] Freigabe an die konkrete Aktion und deren geprüfte Parameter binden.
- [ ] Nutzerrechte unmittelbar vor der Ausführung erneut prüfen.
- [ ] Schreibwerkzeuge wie create_ticket(...) oder send_email(...) erst nach Freigabe ausführen.
- [ ] Freigabe, Ablehnung und Ausführung revisionsfähig protokollieren.
- [ ] Doppelte Ausführung verhindern und abgelaufene Freigaben behandeln.

**Abnahmepunkt:** Eine freigabepflichtige Aktion wird nur nach konkreter Nutzerfreigabe und erneuter Rechteprüfung ausgeführt.

## Phase 5 — Modellknoten und Routing

### 7. Node-Verwaltung

- [ ] Node-Agent für Registrierung, Authentifizierung, Fähigkeiten und Heartbeats implementieren.
- [ ] Knoten mit Firma, Standort, Datenschutzklasse, Modell, Hardwareprofil, maximaler Parallelität, Status, Version und letzter Verbindung erfassen.
- [ ] Admin-Ansicht für Gesundheitsstatus, Modellprofile und letzte Verbindung ergänzen.
- [ ] Modell-Router nach Firmenrichtlinie, Standort, erforderlichem Modell, Datenschutzklasse und Auslastung entscheiden lassen.
- [ ] Für entfernte Standorte einen ausgehenden, authentifizierten und abgesicherten Kommunikationskanal verwenden; Modell-APIs nicht offen ins Internet stellen.
- [ ] Fallback auf lokale oder Cloud-Modelle nur nach expliziter Firmenrichtlinie erlauben.
- [ ] Jeden Mini-PC zunächst als eigenständigen Modellserver behandeln; Geräte nicht als gemeinsamen RAM-Pool modellieren.
- [ ] Optional lokale Embedding-Modelle je Knoten unterstützen.

**Abnahmepunkt:** Mehrere Modellknoten lassen sich registrieren und Anfragen werden nach festgelegten Firmenregeln geroutet.

## Phase 6 — Produktionspakete und Betrieb

### 8. Deployment und Betrieb

- [ ] Gemeinsame Compose-Basis und getrennte Produktions-Overrides für On-Premise und SaaS erstellen.
- [ ] Persistente Speicherung und Backup-/Restore-Verfahren dokumentieren und automatisieren.
- [ ] Versionierte Images, Updateverfahren und Rollback dokumentieren.
- [ ] Healthchecks, Monitoring und nachvollziehbare Fehlerdiagnose einrichten.
- [ ] Installation und Lizenzierung automatisieren.
- [ ] Mandantentrennung, Datenaufbewahrung und erlaubte Verarbeitungsorte je Deployment-Variante konfigurieren.
- [ ] Hybridbetrieb so gestalten, dass die Firma bewusst steuert, ob Fragen und Kontext die Kundenumgebung verlassen dürfen.

**Abnahmepunkt:** Dieselbe Produktversion ist reproduzierbar On-Premise und als SaaS installierbar; Daten bleiben nach Neustarts erhalten und Wiederherstellung ist beschrieben.

## API-Mindestumfang

- [ ] POST /api/v1/chat
- [ ] POST /api/v1/agents/{agent}/runs
- [ ] GET /api/v1/agent-runs/{run}
- [ ] POST /api/v1/agent-runs/{run}/approve
- [ ] POST /api/v1/documents
- [ ] GET /api/v1/nodes
- [ ] POST /api/v1/nodes/enroll
- [ ] POST /api/v1/nodes/heartbeat

## Kernobjekte für die Datenbank

- [ ] organizations, users, memberships, sites, nodes, model_profiles
- [ ] assistants, assistant_versions, tools, tool_credentials
- [ ] knowledge_sources, documents, document_chunks
- [ ] conversations, messages, agent_runs, agent_run_events
- [ ] approvals, audit_events

## Zielreihenfolge

1. Lokaler Prototyp: Laravel, Docker, ein Modell und einfacher Chat.
2. RAG: Dokumentimport, Rechteprüfung, Suche und Quellen.
3. Read-only-Agent: Werkzeugregister und Laufstatus.
4. Freigaben: Approval-UI, Audit und erste Schreibaktion.
5. Mehrere Nodes: Registrierung und regelbasiertes Routing.
6. SaaS und Betrieb: Mandantentrennung, Backups, Updates und Monitoring.
