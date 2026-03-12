---
slug: test-post-04
lang: de
title: PHP 8 Features im Überblick
template: post
date: 2026-03-08
author: admin
tags: [php, entwicklung]
---

# PHP 8 Features im Überblick

PHP 8 hat die Sprache erheblich verbessert. Hier die wichtigsten Neuerungen.

## Benannte Argumente

```php
array_slice(array: $arr, offset: 2, length: 3);
```

## Match-Ausdrücke

```php
$result = match($status) {
    'active'   => 'grün',
    'inactive' => 'rot',
    default    => 'grau',
};
```

## Nullsafe-Operator

```php
$city = $user?->address?->city;
```

Modernes PHP macht Spaß.
