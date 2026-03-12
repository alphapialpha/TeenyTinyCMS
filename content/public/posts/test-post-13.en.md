---
slug: test-post-13
lang: en
title: Static Sites vs Dynamic Sites
template: post
date: 2026-02-27
author: admin
tags: [architecture, performance]
---

# Static Sites vs Dynamic Sites

The debate is old but worth revisiting.

## Static

- Pre-rendered HTML served directly
- Blazing fast, no server-side processing per request
- Harder to update content dynamically

## Dynamic

- Generated on every request
- Easy to show real-time data
- More server resources required

## The Hybrid Sweet Spot

Pre-render what you can. Serve static files for 99% of requests. Use a small database for the 1% that needs it (admin, auth, search). That's the TeenyTinyCMS philosophy.
