# AI Business Plattform

Ein erster klickbarer Produktprototyp für einen lokalen Firmenassistenten mit kontrolliertem Unternehmenswissen.

## Starten

Die App ist als einzelne statische Datei umgesetzt. Öffne `index.html` direkt im Browser oder starte einen lokalen Webserver, zum Beispiel:

```bash
python3 -m http.server 8000
```

Danach `http://localhost:8000` öffnen.

## Enthalten

- Workspace-Übersicht mit Aktivität, Dokumenten und Agenten
- Assistenten-Ansicht mit Chat-Demo
- Wissensbasis mit Suche und lokalem Datei-Upload-Demo
- Agentenübersicht mit Leserechten und Freigabe-Hinweisen
- Modellknoten-Status für lokale Verarbeitung
- Responsive Layout für Desktop und Mobilgeräte

## Entwickler-Checkliste

Die umsetzbaren Arbeitspakete, API-Aufteilung und Abnahmepunkte stehen in der [Entwickler-Checkliste](ENTWICKLUNGS-CHECKLISTE.md).

## Prototyp-Status

Die Oberfläche nutzt Beispieldaten. Chat, Dokumentimport, Indexierung, Anmeldung, Mandantentrennung, Modelladapter, Agentenlaufzeit und Audit-Protokoll sind noch nicht mit einem Backend verbunden.
