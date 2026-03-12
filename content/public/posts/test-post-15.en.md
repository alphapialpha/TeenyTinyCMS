---
slug: test-post-15
lang: en
title: Writing Readable Code
template: post
date: 2026-02-25
author: admin
tags: [bestpractices, development]
---

# Writing Readable Code

Code is read far more often than it is written. Optimize for the reader.

## Naming Things

Good names are the most important thing in readable code. A variable called `$total_pages` needs no comment. A variable called `$tp` requires explanation.

## Short Functions

If a function doesn't fit on one screen, it's doing too much. Split it.

## Comments

Comment the *why*, not the *what*. The code already says what it does.

```php
// Use ceil() so a partial last page still gets its own page slot
$total_pages = (int) ceil($total_posts / $per_page);
```
