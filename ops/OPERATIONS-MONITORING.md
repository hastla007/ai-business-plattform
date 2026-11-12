# Betriebsmetriken, Dashboards und Alarmierung

## Dashboard

Die Admin-API `GET /api/v1/admin/metrics` und der Bereich „Betriebsmetriken“ zeigen den Zeitraum der letzten 24 Stunden: Chat-Aufrufe, p95-Chat-Latenz, fehlgeschlagene Agentenläufe, älteste Queue-Nachricht, stündliche Token-/Kostenwerte, Nutzung pro Nutzer und Modellprofil, verbleibendes Tagesbudget und abgewiesene Limit-Requests. Der reguläre `GET /api/v1/metrics` bleibt auf aggregierte Organisationswerte begrenzt.

## Alarmgrenzen

- **Readiness fehlgeschlagen:** `GET /api/health/ready` nicht 200 für 2 Minuten. Prüfe PostgreSQL und Redis, danach App-Logs und Verbindungen.
- **Liveness fehlgeschlagen:** `GET /api/health/live` nicht 200 für 1 Minute. Container neu starten und Deploy-/OOM-Ereignisse prüfen.
- **Chat-p95 über 5 Sekunden für 10 Minuten:** Provider-/Node-Auslastung, Modellprofil, Queue-Laufzeiten und Redis prüfen.
- **Queue älter als 5 Minuten:** Workerstatus, Queue-Namen und fehlgeschlagene Jobs prüfen; Jobs nach Behebung kontrolliert erneut ausführen.
- **Node offline oder Providerfehler:** Heartbeat, Node-Command-Ablauf, Netzwerk und explizite Cloud-Fallback-Richtlinie prüfen.
- **429-Limit-Ablehnungen steigen:** Admin prüft Nutzer-/Profilverbrauch und angelegte Budgets; Limits nur nach Kosten-/Kapazitätsentscheidung anpassen.

## Diagnose und Wiederherstellung

1. `docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml ps` ausführen und Healthstatus prüfen.
2. `docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml logs --since=15m app worker scheduler nginx postgres redis` prüfen. Request-ID aus dem HTTP-Log mit API-Antwort abgleichen; Prompts und Secrets werden nicht protokolliert.
3. Bei PostgreSQL-/Redis-Ausfall zuerst Dienstverfügbarkeit und Zugangsdaten korrigieren. Nach Wiederherstellung Readiness und Queue prüfen.
4. Bei Provider-/Node-Ausfall Modellprofil und Node-Heartbeat prüfen. Cloud-Fallback bleibt durch die Organisationsrichtlinie begrenzt.
5. Vor Restore ein Backup sichern, Restore in isolierter Instanz durchführen und danach Anmeldung, Dokumentensuche, Chatverlauf und Queue prüfen.

Die Zahlen sind Startwerte für ein Pilotdeployment. Betreiber müssen sie gegen SLO, Providerverträge und tatsächliche Last anpassen.
