---
slug: test-post-20
lang: en
title: Pagination Done Right
template: post
date: 2026-02-20
author: admin
tags: [ux, performance]
---

# Pagination Done Right

Pagination sounds boring. When it's missing, you notice. When it's bad, you curse it. When it's good, it's invisible.

## What Makes Good Pagination

- Clear current page indicator
- Working previous / next links
- Consistent URLs (`/blog/page/2` not `?p=2&offset=10`)
- The first page stays at `/blog` — no `/blog/page/1` redirect needed

## Static Pagination

Pre-generating each page at build time means zero database hits at serve time. The pagination links are hardcoded into the HTML. Fast and simple.
