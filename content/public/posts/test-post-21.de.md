---
slug: test-post-21
lang: de
title: Sicherheitsgrundlagen für PHP-Apps
template: post
date: 2026-02-19
author: admin
tags: [sicherheit, php]
---

# Sicherheitsgrundlagen für PHP-Apps

Man muss kein Sicherheitsexperte sein, um die häufigsten Fehler zu vermeiden.

## Ausgabe escapen

Benutzerkontrollierte Daten immer escapen, bevor sie in HTML ausgegeben werden:

```php
echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

## Parametrisierte Abfragen

SQL-Strings nie durch Verkettung aufbauen:

```php
// Schlecht
$db->query("SELECT * FROM users WHERE name = '$name'");

// Gut
$stmt = $db->prepare("SELECT * FROM users WHERE name = :name");
$stmt->execute([':name' => $name]);
```

## Session-Sicherheit

Session-ID nach dem Login neu generieren. Sessions kurzlebig halten.
