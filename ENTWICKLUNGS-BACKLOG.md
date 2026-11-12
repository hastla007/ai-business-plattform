# Entwickler-Backlog

Dieser Backlog konkretisiert die offenen Punkte aus der [Entwickler-Checkliste](ENTWICKLUNGS-CHECKLISTE.md). Aufgaben bleiben offen, bis Code, Dokumentation und die jeweils genannte Abnahme vorliegen. Ein Backlog-Punkt kann als eigenes Issue oder Pull Request umgesetzt werden.

## P0 — Vor einem Pilotbetrieb

### B01 — CI und reproduzierbare Installation

- [ ] CI-Workflow für Composer-Installation, PHP-Syntaxprüfung, Frontend-Prüfung und Migration gegen PostgreSQL mit pgvector ergänzen.
- [ ] Lockfile für Composer-Abhängigkeiten erzeugen und im Image verwenden.
- [ ] Frische Installation aus leerer Datenbank und Update von der vorherigen Version als CI-Jobs abbilden.
- [ ] Produktions-Compose-Konfiguration mit einer Compose-Validierung prüfen.

**Abnahme:** CI wird bei Pull Requests ausgeführt; ein frischer Clone kann mit dokumentierten Schritten gebaut und migriert werden.

### B02 — Mandanten- und Rechteprüfung absichern

- [ ] Feature-Tests für Zugriffe über fremde Organisations-IDs anlegen: Chatverlauf, Agentenläufe, Dokumente, Gruppen, Tickets, Freigaben, Modellprofile und Nodes.
- [ ] Gruppen- und Eigentümerrechte für Anzeigen, Ändern und Löschen von Dokumenten mit erlaubten und verweigerten Fällen abdecken.
- [ ] Rechte erneut prüfen, wenn eine Mitgliedschaft zwischen Job-Einreihung und Job-Ausführung entzogen wird.
- [ ] Assistenten-ACLs und Prüfung des angeforderten Assistenten in Chat- und Agenten-APIs implementieren.
- [ ] Öffentliche Registrierung für SaaS durch Einladungen oder einen expliziten Deployment-Schalter begrenzen.

**Abnahme:** Negativtests zeigen für jeden mandantenübergreifenden Zugriff eine Ablehnung; berechtigte Gruppenmitglieder erhalten nur freigegebene Dokumente.

### B03 — Freigaben und Schreibaktion Ende zu Ende prüfen

- [ ] API-Tests für Vorschlag, Anzeige, Freigabe, Ablehnung, Ablauf, Wiederholung und parallele Freigabeentscheidungen ergänzen.
- [ ] Freigabeansicht mit Aktion, vollständigen Parametern, Antragsteller, Ablaufzeit und Ergebnis bauen.
- [ ] Vorschlag und Entscheidung auf dieselbe gespeicherte Parameterstruktur beziehen; bei Änderung muss die Freigabe ungültig werden.
- [ ] Test nachweisen, dass ohne Adminfreigabe kein Ticket entsteht und wiederholte Requests kein zweites Ticket anlegen.
- [ ] Prüfen, dass abgelaufene Freigaben im UI als abgelaufen erscheinen und der Agentenlauf beendet ist.

**Abnahme:** Ein automatisierter Ablauf erstellt genau ein Ticket nach gültiger Adminfreigabe; Ablehnung, Ablauf und Replay erzeugen keines.

### B04 — Produktionsinstallation und Wiederherstellung abnehmen

- [ ] On-Premise-Installation in einer leeren Umgebung inklusive Schlüsselgenerierung, Migration und Healthcheck durchführen.
- [ ] Backup und Restore auf einer isolierten Instanz durchführen und Datenbank, Quelldateien sowie Vektoren vergleichen.
- [ ] SaaS-Variante mit verwalteter PostgreSQL/pgvector-, Redis- und S3-kompatibler Speicherung deployen.
- [ ] Sichere TLS-Konfiguration, Upload-Grenzen, Container-Benutzer, Secrets und Wiederanlauf nach Neustart dokumentieren.
- [ ] Versionsmigration und Rollback mit einer echten Vorversion durchspielen; Rollbackgrenzen der Datenbankmigration festhalten.

**Abnahme:** Wiederhergestellte Instanz kann sich anmelden, Dokumente suchen und ein Chatgespräch fortsetzen; Healthchecks sind nach Neustart grün.

## P1 — Bedienbare Kernanwendung

### B05 — Anmeldung und Organisationsauswahl im Frontend

- [ ] Login- und Registrierungsansicht an die API anbinden.
- [ ] Sichere Sitzung und Logout umsetzen; Token nicht in ungeschütztem Browser-Speicher persistieren.
- [ ] Organisationsauswahl und Rollenanzeige für Nutzer mit mehreren Mitgliedschaften ergänzen.
- [ ] Lade-, Fehler-, leere und abgelaufene Sitzungzustände gestalten.

**Abnahme:** Nutzer können sich anmelden, Organisation wechseln, abmelden und nach Sitzungsablauf sicher erneut anmelden.

### B06 — Chat und Quellen im Frontend

- [ ] Chat-UI mit POST /api/v1/chat und Conversation-ID verbinden.
- [ ] Verlauf laden und zwischen Konversationen wechseln.
- [ ] Quellen als anklickbare Fundstellen mit Dokumentname und Abschnitt anzeigen.
- [ ] Modellstatus, Fehler und Wiederholen-Aktion darstellen.
- [ ] Tastaturbedienung und mobile Layouts ergänzen.

**Abnahme:** Eine Unterhaltung bleibt nach Neuladen verfügbar; jede angezeigte Quelle verweist auf eine tatsächlich zurückgegebene Fundstelle.

### B07 — Dokumente und RAG-Rechte im Frontend

- [ ] Upload für erlaubte Formate mit Fortschritt, Status und verständlicher Fehlermeldung umsetzen.
- [ ] Zugriffsumfang Organisation, Gruppe oder Eigentümer nur gemäß Rolle auswählbar machen.
- [ ] Dokumente filtern, Status anzeigen, erneut verarbeiten und löschen.
- [ ] Gruppenrechte in der UI sichtbar machen, ohne Dokumentinhalte unberechtigten Nutzern offenzulegen.

**Abnahme:** Nutzer können ein Dokument hochladen, Indexstatus verfolgen und die Berechtigung in einer Suche nachvollziehen.

### B08 — Admin-Ansichten für Knoten, Modelle und Gruppen

- [ ] Modellprofile erstellen, bearbeiten, aktivieren und einem Node zuordnen.
- [ ] Node-Liste mit Status, letztem Heartbeat, Standort, Fähigkeiten und Auslastung bauen.
- [ ] Enroll-Token einmalig anzeigen und danach maskieren; Node deaktivieren können.
- [ ] Gruppen und Mitgliedschaften verwalten.
- [ ] Organisationsrichtlinien für Datenschutzklasse, Cloud-Fallback und Datenaufbewahrung bearbeiten.

**Abnahme:** Ein Admin kann einen Node anmelden, ein Modellprofil zuweisen und anhand der Oberfläche den Routing-Status prüfen.

### B09 — Freigabeoberfläche

- [ ] Wartende Aktionen nach Organisation und Status anzeigen.
- [ ] Parameter unverändert und vollständig vor Freigabe darstellen.
- [ ] Freigeben und Ablehnen mit Bestätigung sowie Ergebnis-/Fehleranzeige anbieten.
- [ ] Statusänderungen nach Ablauf oder gleichzeitiger Entscheidung aktualisieren.

**Abnahme:** Die UI zeigt denselben Aktionshash/Parameterstand wie die API und kann keine Aktion außerhalb der eigenen Organisation entscheiden.

## P2 — Agenten und Modellfunktionen

### B10 — Ticket-Agent als Read-only-Agent

- [ ] Ticket-Agent definieren, der nur freigegebene Dokumente durchsucht und Ticketentwürfe erzeugt.
- [ ] Entwurf und Quellen im Agentenlauf speichern.
- [ ] Explizite Aktion „Ticket vorschlagen“ an den Freigabefluss anschließen.
- [ ] Keine Ticket-Änderung direkt aus dem Agentenmodell zulassen.

**Abnahme:** Ohne Freigabe bleibt der Entwurf unverändert und es wird kein Ticket angelegt.

### B11 — Streaming

- [ ] Streaming-Methoden im Provider-Vertrag ergänzen.
- [ ] Server-Sent Events mit Request-ID, Token-Chunks, Quellen und terminalem Status bereitstellen.
- [ ] Abbruch durch Client und Provider-Fehler behandeln.
- [ ] Frontend-Streaming mit Wiederverbindung oder klarer Fehlermeldung umsetzen.

**Abnahme:** Streaming- und Standardantworten liefern denselben finalen Inhalt; Abbruch hinterlässt keine halben Nachrichten.

### B12 — Nutzungs- und Kostenlimits

- [ ] Eingabe-/Ausgabetoken und Embedding-Nutzung pro Nutzer, Organisation, Profil und Lauf speichern.
- [ ] Budgets und Limits pro Modellprofil validieren und vor Modellaufruf prüfen.
- [ ] Verhalten bei erreichtem Limit festlegen: blockieren oder nur nach Adminfreigabe fortsetzen.
- [ ] Adminübersicht für Verbrauch, Budget und abgewiesene Requests bauen.

**Abnahme:** Testläufe oberhalb des Limits werden vor einem kostenpflichtigen Provideraufruf abgewiesen und im Audit nachvollziehbar erfasst.

### B13 — Lokale Embeddings pro Node

- [ ] Node-Fähigkeitsmodell um Embedding-Modelle und Dimensionen ergänzen.
- [ ] Router für Embeddings getrennt vom Chat-Modell routen lassen.
- [ ] Modell-/Dimensionskompatibilität mit bestehenden Vektoren prüfen.
- [ ] Re-Embedding und Indexwechsel ohne gemischte Vektordimensionen unterstützen.

**Abnahme:** Dokumente können mit einem lokal registrierten Embedding-Modell indexiert werden; inkompatible Vektoren werden abgewiesen.

## P2 — Betrieb und Support

### B14 — Monitoring und Diagnose

- [ ] Strukturierte Logs mit Request-ID, Organisations-ID und Agentenlauf-ID vereinheitlichen; keine Prompts oder Secrets protokollieren.
- [ ] Metriken für API-Latenz, Queue-Alter, Fehler, Node-Heartbeat und Provider-Nutzung ergänzen.
- [ ] Readiness- und Liveness-Probes voneinander trennen.
- [ ] Alarmgrenzen und Runbook für Datenbank-, Redis-, Provider- und Node-Ausfälle dokumentieren.

**Abnahme:** Ein simulierter Ausfall ist anhand von Metriken/Logs erkennbar und das Runbook führt zu einer klaren Wiederherstellung.

### B15 — SaaS-Onboarding und Lizenzierung

- [ ] Mandantenanlage, Einladungen, Rollen und Kontingente für SaaS-Deployment implementieren.
- [ ] Lizenz- oder Planstatus serverseitig prüfen und Änderungen revisionsfähig protokollieren.
- [ ] Ablauf, Kulanzphase und Sperrverhalten für abgelaufene Pläne festlegen.
- [ ] Keine Lizenzprüfung in On-Premise-Installationen erzwingen, sofern die Produktentscheidung Offlinebetrieb vorsieht.

**Abnahme:** Neue Organisationen können gemäß Plan angelegt werden; ein Planwechsel aktualisiert Kontingente ohne Mandantenüberschneidung.

## Gemeinsame Definition of Done

- [ ] Jede API-Änderung hat dokumentierte Request-/Response-Beispiele und Fehlercodes.
- [ ] Jede Berechtigungsänderung hat positive und negative Tests.
- [ ] Migrationen funktionieren auf leerer Datenbank und mit einer unterstützten Vorversion.
- [ ] Keine Secrets, personenbezogenen Inhalte oder vollständigen Prompts in Logs oder Commits.
- [ ] Die Entwickler-Checkliste wird erst nach Code und Abnahme aktualisiert.
