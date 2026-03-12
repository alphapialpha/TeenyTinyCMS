---
slug: test-post-23
lang: de
title: Datum und Beitragssortierung
template: post
date: 2026-02-17
author: admin
tags: [cms, datum]
---

# Datum und Beitragssortierung

Blog-Beiträge sollten neueste zuerst erscheinen. Dafür ist das Datumsfeld wichtig.

## Das `date`-Feld

```yaml
date: 2026-02-17
```

ISO-8601-Format verwenden (`JJJJ-MM-TT`). Es sortiert alphabetisch genauso wie chronologisch — eine schöne Eigenschaft, die SQL-`ORDER BY date DESC` einfach funktionieren lässt.

## Was ohne Datum passiert

Beiträge ohne Datum werden in der Datenbank mit `NULL` gespeichert. Sie fallen beim Sortieren nach `date DESC` ans Ende der Liste.
