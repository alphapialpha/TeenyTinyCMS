---
slug: test-post-05
lang: de
title: Caching-Strategien für kleine Websites
template: post
date: 2026-03-07
author: admin
tags: [performance, caching]
---

# Caching-Strategien für kleine Websites

Man braucht kein Redis oder Memcached für eine schnelle Website. Vorkompilierte PHP-Dateien sind überraschend effektiv.

## Statischer Datei-Cache

Inhalte einmal zur Build-Zeit rendern. Das Ergebnis bei jeder Anfrage ausliefern. Kein Datenbankzugriff, kein Template-Parsing.

Genau so funktioniert TeenyTinyCMS.

## Wann neu bauen?

- Ein neuer Beitrag wird veröffentlicht
- Eine bestehende Seite wird bearbeitet
- Konfiguration ändert sich
