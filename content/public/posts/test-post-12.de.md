---
slug: test-post-12
lang: de
title: Template-Partials erklärt
template: post
date: 2026-02-28
author: admin
tags: [templates, theming]
---

# Template-Partials erklärt

Partials sind wiederverwendbare Template-Fragmente. Statt den gleichen Header-HTML in jedem Template zu wiederholen, schreibt man ihn einmal.

## Einen Partial verwenden

```php
render_partial('header', ['lang' => $lang]);
```

## Verfügbare Partials

- `header` — Seitentitel und obere Leiste
- `nav` — Hauptnavigation
- `footer` — Copyright und Impressum
- `post_teaser` — kompakte Beitragsvorschau
- `tag_list` — inline Tag-Links
- `language_switcher` — Links zu anderen Sprachversionen
