---
slug: test-post-09
lang: de
title: Abhängigkeiten minimal halten
template: post
date: 2026-03-03
author: admin
tags: [architektur, abhängigkeiten]
---

# Abhängigkeiten minimal halten

Jede Abhängigkeit ist eine Verbindlichkeit. Sie kann nicht mehr gepflegt werden, Sicherheitsprobleme einführen oder einfach beim Update brechen.

## Der minimale Ansatz

Für jede Abhängigkeit fragen:

> Kann ich das selbst in unter 100 Zeilen implementieren?

Wenn ja: selbst machen. Wenn nein: sorgfältig abwägen.

## Was TeenyTinyCMS verwendet

- Parsedown / ParsedownExtra für Markdown
- PDO für Datenbankzugriff
- Sonst nichts

Weniger ist mehr.
