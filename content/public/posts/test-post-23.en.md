---
slug: test-post-23
lang: en
title: Dates and Sorting Posts
template: post
date: 2026-02-17
author: admin
tags: [cms, dates]
---

# Dates and Sorting Posts

Blog posts should appear newest first. To make that work, the date field matters.

## The `date` Field

```yaml
date: 2026-02-17
```

Use ISO 8601 format (`YYYY-MM-DD`). It sorts alphabetically the same way it sorts chronologically — a nice property that makes SQL `ORDER BY date DESC` just work.

## What Happens Without a Date

Posts without a date are stored with `NULL` in the database. They fall to the end of the list when sorting by `date DESC`.
