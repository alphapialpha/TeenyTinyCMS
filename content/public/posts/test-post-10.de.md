---
slug: test-post-10
lang: de
title: URL-Routing verstehen
template: post
date: 2026-03-02
author: admin
tags: [routing, php]
---

# URL-Routing verstehen

Ein Router nimmt eine eingehende URL und ordnet sie dem richtigen Inhalt zu. Klingt komplex — ist es nicht.

## URL-Muster

```
/de/             → Startseite
/de/about        → Seite mit Slug "about"
/de/blog         → Blog-Index
/de/blog/mein-post → Beitrag mit Slug "mein-post"
```

## Die Aufgabe des Routers

1. URL in Segmente zerlegen
2. Sprache, Typ, Slug bestimmen
3. Den Cache-Dateipfad in der DB nachschlagen
4. Die Datei einbinden

Das war's. Kein Framework nötig.
