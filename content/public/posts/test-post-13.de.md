---
slug: test-post-13
lang: de
title: Statisch vs. dynamisch
template: post
date: 2026-02-27
author: admin
tags: [architektur, performance]
---

# Statisch vs. dynamisch

Die Debatte ist alt, aber es lohnt sich, sie neu zu betrachten.

## Statisch

- Vorgerendertes HTML wird direkt ausgeliefert
- Blitzschnell, keine serverseitige Verarbeitung pro Anfrage
- Dynamische Inhalte schwerer umsetzbar

## Dynamisch

- Bei jeder Anfrage generiert
- Echtzeit-Daten leicht möglich
- Mehr Serverressourcen erforderlich

## Der hybride Mittelweg

Vorrendern, was möglich ist. 99 % der Anfragen mit statischen Dateien bedienen. Eine kleine Datenbank für die 1 % nutzen, die sie brauchen. Das ist die TeenyTinyCMS-Philosophie.
