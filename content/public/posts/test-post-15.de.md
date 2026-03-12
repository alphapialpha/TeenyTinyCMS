---
slug: test-post-15
lang: de
title: Lesbaren Code schreiben
template: post
date: 2026-02-25
author: admin
tags: [bestpractices, entwicklung]
---

# Lesbaren Code schreiben

Code wird viel häufiger gelesen als geschrieben. Für den Leser optimieren.

## Dinge benennen

Gute Namen sind das Wichtigste für lesbaren Code. Eine Variable namens `$total_pages` braucht keinen Kommentar. Eine Variable namens `$tp` erfordert Erklärung.

## Kurze Funktionen

Wenn eine Funktion nicht auf einen Bildschirm passt, macht sie zu viel. Aufteilen.

## Kommentare

Das *Warum* kommentieren, nicht das *Was*. Der Code sagt bereits, was er tut.

```php
// ceil() damit eine teilweise letzte Seite noch ihren eigenen Slot bekommt
$total_pages = (int) ceil($total_posts / $per_page);
```
