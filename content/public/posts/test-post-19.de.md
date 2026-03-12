---
slug: test-post-19
lang: de
title: Medienverwaltung
template: post
date: 2026-02-21
author: admin
tags: [medien, dateien]
---

# Medienverwaltung

Bilder und Dateien liegen in `content/public/media/`. Sie werden über einen dedizierten Media-Handler ausgeliefert.

## Medien hinzufügen

Eine Datei in den Media-Ordner legen. Beim nächsten Rebuild wird sie in die Datenbank synchronisiert und ist verfügbar unter:

```
/media/public/mein-bild.jpg
```

## Medien in Beiträgen verwenden

```markdown
![Mein Bild](/media/public/mein-bild.jpg)
```

Einfaches Dateisystem. Keine Upload-Oberfläche nötig.
