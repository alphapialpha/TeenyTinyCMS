---
slug: test-post-05
lang: en
title: Caching Strategies for Small Sites
template: post
date: 2026-03-07
author: admin
tags: [performance, caching]
---

# Caching Strategies for Small Sites

You don't need Redis or Memcached to make a fast site. Pre-compiled PHP files are surprisingly effective.

## Static File Cache

Render your content once at build time. Serve the result on every request. No database hit, no template parsing.

This is exactly how TeenyTinyCMS works.

## When to Rebuild

- A new post is published
- An existing page is edited
- Configuration changes

Keep the build fast and rebuilding is cheap.
