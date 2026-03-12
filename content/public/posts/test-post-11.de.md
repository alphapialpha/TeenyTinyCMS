---
slug: test-post-11
lang: de
title: Inhalte mit Tags strukturieren
template: post
date: 2026-03-01
author: admin
tags: [tags, organisation]
---

# Inhalte mit Tags strukturieren

Tags sind eine einfache Möglichkeit, verwandte Beiträge zu gruppieren — ohne die Komplexität von Kategorien.

## So funktionieren Tags hier

Tags im Front Matter als Liste angeben:

```yaml
tags: [php, tutorial, einsteiger]
```

Der Builder synchronisiert sie mit der Datenbank und generiert für jeden Tag eine statische Index-Seite.

## Tag-URLs

```
/de/tag/php
/de/tag/tutorial
```

Jede ist eine vollständig statische Seite, zur Build-Zeit erstellt.
