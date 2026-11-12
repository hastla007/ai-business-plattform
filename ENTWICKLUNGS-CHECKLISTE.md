# Entwickler-Checkliste

Stand: Implementierungen der Phasen 1–6 sowie Codearbeiten aus P0–P2. Code-Häkchen werden nach Implementierung gesetzt; reale Produktionsabnahmen bleiben separat offen. Häkchen bedeuten, dass der Code im Repository vorhanden ist; Laufzeit-, Sicherheits- und Deployment-Abnahmen bleiben separat offen.

## Phase 1 — Lokaler vertikaler Prototyp

### 1. Projektgrundlage und Mandantentrennung

- [x] Laravel-Anwendung als Backend anlegen.
- [x] API-Token-Authentifizierung mit Laravel Sanctum, Registrierung, Login und Logout einrichten.
- [x] Sanctum-Cookie-Authentifizierung für die SPA einrichten.
- [x] Firmen, Nutzer, Mitgliedschaften, Standorte und Rollenbasis implementieren.
- [x] Firmen- und Dokumentrechte serverseitig prüfen; alle Firmenobjekte mandantengebunden abfragen.
- [x] Docker-Compose-Entwicklungsstack für App, Queue-Worker, PostgreSQL und Redis mit persistentem lokalem Dateispeicher einrichten.
- [x] Separaten MinIO-Objektspeicher (`object-storage`-Profil) und optionalen Ollama-Modellknoten (`model-node`-Profil) als Entwicklungs-Compose-Dienste ergänzen.
- [x] Persistente Volumes für Datenbank und Quelldokumente konfigurieren.
- [x] Konfiguration über Umgebungsvariablen laden; keine Schlüssel oder Zugangsdaten ins Repository schreiben.
- [x] Datenbankschema und Migrationen für Kernobjekte erstellen.

**Abnahmepunkt:** Eine Firmeninstanz startet lokal mit Docker; Anmeldung, Isolation und Wiederherstellung wurden noch nicht abgenommen.

### 2. Modelladapter und Modellprofile

- [x] Provider-Schnittstelle für Chat und Embeddings definieren.
- [x] Streaming-Schnittstelle und Streaming-Implementierung ergänzen.
- [x] Embedding-Schnittstelle bereitstellen.
- [x] Transport-Schnittstelle für entfernte Modellknoten ergänzen.
- [x] Einen lokalen Modellserver über einen Adapter anbinden.
- [x] Streaming- und nicht-streamende Antworten unterstützen.
- [x] Timeouts, begrenzte Retries und verständliche Providerfehler abbilden.
- [x] Nutzungs- und Kostenlimits durchsetzen.
- [x] Modellprofile über eine Admin-API verwalten.
- [x] Modellprofile in der Admin-Oberfläche verwalten.
- [x] Modellzugriffe so abstrahieren, dass Provider hinter derselben Schnittstelle ausgetauscht werden.

### 3. Chat, Speicherung und Audit

- [x] Konversationen und Nachrichten persistent speichern.
- [x] Versionierte API POST /api/v1/chat implementieren.
- [x] Chat-UI an die API anbinden.
- [x] Berechtigung für den ausgewählten Assistenten bei jeder Anfrage prüfen.
- [x] Pro Anfrage eine request_id und pro Agentenlauf eine run_id erzeugen.
- [x] Antwortstatus, Quellen und Modellmetadaten über die API zurückgeben.
- [x] Tatsächliche Modellknoten-ID bei gerouteten Antworten zurückgeben.
- [x] Laufzeit- und Nutzungsmetadaten im Audit-Protokoll speichern.
- [x] Geheimnisse, Zugangsdaten und vollständige vertrauliche Prompts aus Logs ausschließen.
- [x] Aufbewahrungsfrist je Organisation konfigurierbar machen und Bereinigung planen.

### Phase 2 — Unternehmenswissen (RAG)

#### 4. Dokumentenimport und Suche

- [x] Upload und Speicherung von Quelldateien mit Firmenbezug, Hash und Versionsnummer implementieren.
- [x] Queue-Jobs für Textextraktion, Metadaten und Dokumentstatus einrichten.
- [x] Dokumente in nachvollziehbare Abschnitte (Chunks) aufteilen.
- [x] Embeddings über den Provider erzeugen und mit Zugriffsmetadaten in PostgreSQL/pgvector speichern.
- [x] Keyword- und Vektorsuche kombinieren.
- [x] Firmen- und Dokumentrechte bereits in der Suchabfrage durchsetzen.
- [x] Gruppenrechte und Gruppenmitgliedschaften in der Suchabfrage ergänzen.
- [x] Treffer mit Dokumentname und Seiten- oder Abschnittsangabe in den Modellkontext übergeben.
- [x] Quellen und Fundstellen in der Chat-API zurückgeben.
- [x] Quellen und Fundstellen in der Chat-UI anzeigen.
- [x] Bei Dokumentänderung alte Chunks ersetzen; beim Löschen Quelldatei, Text und Vektoren entfernen.
- [x] Importfehler, erneute Verarbeitung und Statusanzeige über die API abbilden.
- [x] Konfigurierbaren lokalen bzw. S3-kompatiblen Objektspeicher unterstützen.

### Phase 3 — Kontrollierter Agent

#### 5. Agent Runtime und Werkzeugregister

- [x] Agent-Definitionen versionieren und Zweck, Anweisungen, Modelle, Wissensquellen, Werkzeuge, Laufzeit und Freigaberichtlinie speichern.
- [x] Laufstatus queued, running, awaiting_approval, completed, failed und cancelled im Schema/API-Modell unterstützen.
- [x] Längere Läufe als Queue-Jobs ausführen und Zustand speichern.
- [x] Werkzeugregister mit JSON-Schema und Parameterprüfung für search_knowledge implementieren.
- [x] Firmenmitgliedschaft unmittelbar vor asynchronen Werkzeugaufrufen erneut prüfen.
- [x] Nur registrierte, eng begrenzte Werkzeuge ausführen.
- [x] search_knowledge(query) als schreibgeschütztes Werkzeug implementieren.
- [x] Agentenlauf, Schritte und redigierte Fehler-Metadaten protokollieren.
- [x] Maximale Schrittzahl und Laufzeit verbindlich erzwingen.
- [x] Wissensagent und Dokumentenagent bereitstellen.
- [x] Ticket-Agent zunächst nur lesend betreiben und Antwortvorschläge erstellen.

## Phase 4 — Freigaben und Schreibwerkzeuge

### 6. Menschliche Freigabe

- [x] Freigabeansicht in der UI implementieren.
- [x] Status awaiting_approval und Freigabe-/Ablehnungs-API implementieren.
- [x] Freigabe an konkrete Aktion und kanonisch gehashte Parameter binden.
- [x] Organisationsmitgliedschaft und Adminrolle unmittelbar vor Ausführung erneut prüfen.
- [x] Schreibwerkzeug create_ticket nur nach expliziter Freigabe ausführen.
- [x] Freigabe, Ablehnung, Ablauf und Ausführung in Agentenereignissen und Audit protokollieren.
- [x] Doppelte Ausführung über Zeilensperren, eindeutige Freigabe-ID und Idempotenzschlüssel verhindern.
- [x] Freigaben nach 15 Minuten ablaufen lassen und abgelehnte/abgelaufene Läufe beenden.

**Abnahmepunkt:** API-Pfad implementiert; UI- und Laufzeitabnahme offen.

## Phase 5 — Modellknoten und Routing

### 7. Node-Verwaltung

- [x] Node-Agent über einmalig ausgegebenes Token registrieren und authentifizieren.
- [x] Fähigkeiten, Heartbeats und Statuswechsel verarbeiten.
- [x] Knoten mit Firma, Standort, Datenschutzklasse, Modellfähigkeiten, Hardwareprofil, Parallelität, Version und letzter Verbindung erfassen.
- [x] Admin-API für Gesundheit, Modellprofile und letzte Verbindung bereitstellen.
- [x] Admin-Oberfläche für Knoten und Modellprofile ergänzen.
- [x] Modell-Router berücksichtigt Firma, Standort, Modell, Datenschutzklasse, Aktualität und Auslastung.
- [x] Remote-Kommunikation über ausgehendes Polling mit Node-Token; keine Modell-API wird öffentlich geöffnet.
- [x] Cloud-Fallback nur bei expliziter Organisationsrichtlinie zulassen.
- [x] Jeden Mini-PC als eigenständigen Modellserver behandeln.
- [x] Optionale lokale Embedding-Modelle je Knoten unterstützen.

**Abnahmepunkt:** Backend für Registrierung und Routing implementiert; Mehrknoten-Laufzeittest offen.

## Phase 6 — Produktionspakete und Betrieb

### 8. Deployment und Betrieb

- [x] Produktions-Compose-Basis und On-Premise-/SaaS-Overrides erstellen.
- [x] Persistente Speicherung sowie Backup-/Restore-Skripte und Anleitung ergänzen.
- [x] Versionierte Images, Updateverfahren und Rollback-Skript dokumentieren.
- [x] Health-Endpunkt, Container-Healthchecks und Fehlerdiagnose dokumentieren.
- [ ] Alertmanager-Empfänger mit Betriebszugangsdaten verbinden und Monitoring in der Zielumgebung praktisch abnehmen. Admin-Metriken, Prometheus-Probe-Regeln und Runbook sind im Repository vorhanden.
- [x] On-Prem-Installation automatisieren; SaaS-Testplan, Kontingente, Ablauf/Kulanz und Planprüfung ergänzen.
- [x] Mandantentrennung, Aufbewahrungsfrist und erlaubte Datenverarbeitung je Organisation konfigurierbar machen.
- [x] Hybrid-/Cloud-Fallback durch explizite Organisationsrichtlinie begrenzen.
- [ ] Produktionsinstallation, Backup-Restore und SaaS-Deployment praktisch abnehmen.

## API-Mindestumfang

- [x] POST /api/v1/chat
- [x] POST /api/v1/agents/{agent}/runs
- [x] GET /api/v1/agent-runs/{run}
- [x] POST /api/v1/agent-runs/{run}/approve
- [x] POST /api/v1/documents
- [x] GET /api/v1/nodes
- [x] POST /api/v1/nodes/enroll
- [x] POST /api/v1/nodes/heartbeat

## Kernobjekte

- [x] organizations, users, memberships, sites, nodes, model_profiles
- [x] assistants, assistant_versions, tools, tool_credentials
- [x] knowledge_sources, documents, document_chunks
- [x] conversations, messages, agent_runs, agent_run_events
- [x] approvals, audit_events
- [x] node_commands, tickets

## Noch offen

Die externen Abnahmen (verwalteter SaaS-Deploy, Upgrade aus veröffentlichter Vorversion, Restore mit echten Mandanten-/Dokument-/Vektordaten und Produktionsinstallation) sind in B04 offen, bis sie in der Zielumgebung nachgewiesen sind. Weitere Detailpunkte stehen im [Entwickler-Backlog](ENTWICKLUNGS-BACKLOG.md).
