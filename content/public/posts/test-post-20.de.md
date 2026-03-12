---
slug: test-post-20
lang: de
title: Pagination richtig gemacht
template: post
date: 2026-02-20
author: admin
tags: [ux, performance]
---

# Pagination richtig gemacht

Pagination klingt langweilig. Wenn sie fehlt, merkt man es. Wenn sie schlecht ist, flucht man. Wenn sie gut ist, ist sie unsichtbar.

## Was gute Pagination ausmacht

- Deutliche Anzeige der aktuellen Seite
- Funktionierende Zurück-/Weiter-Links
- Konsistente URLs (`/blog/page/2` statt `?p=2&offset=10`)
- Die erste Seite bleibt bei `/blog` — kein `/blog/page/1`-Redirect nötig

## Statische Pagination

Jede Seite zur Build-Zeit vorzugenerieren bedeutet null Datenbankzugriffe bei der Auslieferung. Die Pagination-Links sind fest im HTML kodiert. Schnell und einfach.
