# Entwickler-Checkliste

Die Liste setzt die Projektbeschreibung in umsetzbare Arbeitspakete um. Der aktuelle Stand ist ein statischer UI-Prototyp; Chat, Dokumentimport und Modellknoten verwenden noch Beispieldaten. Die ersten Backend-Bausteine sind ergänzt. Häkchen markieren im Repository implementierte Codepunkte; nicht ausgeführte Abnahmepunkte bleiben offen.

## Phase 1 — Lokaler vertikaler Prototyp

### 1. Projektgrundlage und Mandantentrennung

- [x] Laravel-Anwendung als Backend anlegen.
- [x] API-Token-Authentifizierung mit Laravel Sanctum, Registrierung, Login und Logout einrichten.
- [ ] Sanctum-Cookie-Authentifizierung für eine getrennte SPA einrichten.
- [x] Firmen, Nutzer, Mitgliedschaften, Standorte und Rollenbasis implementieren.
- [x] Firmen- und Dokumentrechte serverseitig prüfen; alle Firmenobjekte mandantengebunden abfragen.
- [x] Docker-Compose-Entwicklungsstack für App, Queue-Worker, PostgreSQL und Redis mit persistentem lokalem Dateispeicher einrichten.
- [ ] Separaten Objektspeicher-Dienst und optionalen Modellknoten als Compose-Dienste ergänzen.
- [x] Persistente Volumes für Datenbank und Quelldokumente konfigurieren.
- [x] Konfiguration über Umgebungsvariablen laden; keine Schlüssel oder Zugangsdaten ins Repository schreiben.
- [x] Datenbankschema und Migrationen für die unten aufgeführten Kernobjekte erstellen.

**Abnahmepunkt:** Eine Firmeninstanz startet lokal mit Docker; Nutzer können sich anmelden und sehen ausschließlich ihre Firma und deren Daten.

### 2. Modelladapter und Modellprofile

- [x] Provider-Schnittstelle für Chat und Embeddings definieren.
- [ ] Streaming-Schnittstelle und Streaming-Implementierung ergänzen.
- [x] Embedding-Schnittstelle bereitstellen.
- [ ] Transport-Schnittstelle für entfernte Modellknoten ergänzen.
- [x] Einen lokalen Modellserver über einen Adapter anbinden.
- [ ] Streaming- und nicht-streamende Antworten unterstützen.
- [x] Timeouts, begrenzte Retries und verständliche Providerfehler abbilden.
- [ ] Nutzungs- und Kostenlimits durchsetzen.
- [ ] Modellprofile und Provider-Konfiguration in der Admin-Oberfläche verwalten.
- [x] Modellzugriffe so abstrahieren, dass ein Wechsel des Providers keine Änderungen an der Chatlogik erfordert.

**Abnahmepunkt:** Laravel kann über ein Modellprofil mit einem lokalen Modell chatten; Provider können hinter derselben Schnittstelle ausgetauscht werden.

### 3. Chat, Speicherung und Audit

- [x] Konversationen und Nachrichten persistent speichern.
- [x] Versionierte API POST /api/v1/chat implementieren.
- [ ] Chat-UI an die API anbinden.
- [ ] Berechtigung für den ausgewählten Assistenten bei jeder Anfrage prüfen.
- [x] Pro Anfrage eine request_id und pro Agentenlauf eine run_id erzeugen.
- [x] Antwortstatus, Quellen und Modellmetadaten über die API zurückgeben.
- [ ] Tatsächliche Modellknoten-Metadaten an die UI zurückgeben.
- [x] Modell- und Nutzungsmetadaten speichern.
- [ ] Laufzeitmessung im Audit-Protokoll ergänzen.
- [x] Geheimnisse, Zugangsdaten und vollständige vertrauliche Prompts aus Logs ausschließen.
- [ ] Gesprächs- und Aufbewahrungsrichtlinien konfigurierbar machen.

**Abnahmepunkt:** Ein angemeldeter Nutzer kann mit einem konfigurierten Modell chatten; Verlauf und Auditdaten sind der richtigen Firma zugeordnet.

## Phase 2 — Unternehmenswissen (RAG)

### 4. Dokumentenimport und Suche

- [x] Upload und Speicherung von Quelldateien mit Firmenbezug, Hash und Versionsnummer implementieren.
- [x] Queue-Jobs für Textextraktion, Metadaten und Dokumentstatus einrichten.
- [x] Dokumente in nachvollziehbare Abschnitte (Chunks) aufteilen.
- [x] Embeddings über den Provider erzeugen und zusammen mit Zugriffsmetadaten in PostgreSQL/pgvector speichern.
- [x] Keyword- und Vektorsuche kombinieren.
- [x] Firmen- und Dokumentrechte (Organisation oder Dokumenteigentümer) bereits in der Suchabfrage durchsetzen.
- [x] Gruppenrechte und Gruppenmitgliedschaften in der Suchabfrage ergänzen.
- [x] Treffer mit Dokumentname und Seiten- oder Abschnittsangabe in den Modellkontext übergeben.
- [x] Quellen und Fundstellen in der Chat-API zurückgeben.
- [ ] Quellen und Fundstellen in der Chat-UI anzeigen.
- [x] Bei Dokumentänderung alte Chunks ersetzen; beim Löschen Quelldatei, Text und Vektoren entfernen.
- [x] Importfehler, erneute Verarbeitung und Statusanzeige abbilden.

**Abnahmepunkt:** Antworten verwenden nur für den Nutzer freigegebene Dokumentstellen und zeigen überprüfbare Quellen an.

## Phase 3 — Kontrollierter, schreibgeschützter Agent

### 5. Agent Runtime und Werkzeugregister

- [x] Agent-Definitionen versionieren und Zweck, Anweisungen, erlaubte Modelle, Wissensquellen, Werkzeuge, maximale Schritte, Timeout, Budget, Freigabe- und Aufbewahrungsrichtlinie speichern.
- [ ] Laufstatus queued, running, awaiting_approval, completed, failed und cancelled unterstützen.
- [x] Längere Läufe als Queue-Jobs ausführen und ihren Zustand wiederherstellbar speichern.
- [x] Werkzeugregister mit gespeichertem JSON-Schema und Parameterprüfung für search_knowledge implementieren.
- [x] Firmenmitgliedschaft unmittelbar vor jedem asynchronen Werkzeugaufruf erneut prüfen.
- [x] Nur registrierte, eng begrenzte Werkzeuge ausführen; keine freien Shell-Befehle, beliebigen HTTP-Aufrufe oder direkten Datenbankzugriffe bereitstellen.
- [x] search_knowledge(query) als erstes schreibgeschütztes Werkzeug implementieren.
- [x] Agentenlauf, Schritte und Fehler mit redigierten Metadaten protokollieren.
- [x] Maximale Schrittzahl und Laufzeit verbindlich erzwingen.
- [x] Wissensagent bereitstellen: freigegebene interne Dokumente suchen und Quellen nennen.
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

- [x] POST /api/v1/chat
- [x] POST /api/v1/agents/{agent}/runs
- [x] GET /api/v1/agent-runs/{run}
- [ ] POST /api/v1/agent-runs/{run}/approve
- [x] POST /api/v1/documents
- [ ] GET /api/v1/nodes
- [ ] POST /api/v1/nodes/enroll
- [ ] POST /api/v1/nodes/heartbeat

## Kernobjekte für die Datenbank

- [x] organizations, users, memberships, sites, nodes, model_profiles
- [x] assistants, assistant_versions, tools, tool_credentials
- [x] knowledge_sources, documents, document_chunks
- [x] conversations, messages, agent_runs, agent_run_events
- [x] approvals, audit_events

## Zielreihenfolge

1. Lokaler Prototyp: Laravel, Docker, ein Modell und einfacher Chat.
2. RAG: Dokumentimport, Rechteprüfung, Suche und Quellen.
3. Read-only-Agent: Werkzeugregister und Laufstatus.
4. Freigaben: Approval-UI, Audit und erste Schreibaktion.
5. Mehrere Nodes: Registrierung und regelbasiertes Routing.
6. SaaS und Betrieb: Mandantentrennung, Backups, Updates und Monitoring.
