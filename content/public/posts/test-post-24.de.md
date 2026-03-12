---
slug: test-post-24
lang: de
title: Der Build-Prozess erklärt
template: post
date: 2026-02-16
author: admin
tags: [cms, architektur]
---

# Der Build-Prozess erklärt

Jedes Mal, wenn man auf „Rebuild" klickt, durchläuft der Builder vier Phasen.

## Phase 0 — Bereinigen

DB-Zeilen und Cache-Dateien für nicht mehr vorhandene Inhalte entfernen.

## Phase 1 — Metadaten-Synchronisation

Front Matter jeder Markdown-Datei parsen und in die Datenbank einfügen. Läuft zuerst, damit der Blog-Index eine aktuelle Beiragsliste abfragen kann.

## Phase 2 — Rendern

Jede Datei mit ihrem Template rendern, in das Layout einbetten, als `.php`-Cache-Datei schreiben.

## Phase 3 — Tag-Seiten

Eine statische Tag-Index-Seite pro `(Tag, Sprache)`-Kombination generieren.

## Phase 4 — Blog-Pagination

`blog-page-2.php`, `blog-page-3.php` … basierend auf der aktuellen Beitragsanzahl generieren.
