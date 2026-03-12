---
slug: test-post-10
lang: en
title: Understanding URL Routing
template: post
date: 2026-03-02
author: admin
tags: [routing, php]
---

# Understanding URL Routing

A router takes an incoming URL and maps it to the right piece of content. It sounds complex — it isn't.

## URL Patterns

```
/en/             → homepage
/en/about        → page with slug "about"
/en/blog         → blog index
/en/blog/my-post → post with slug "my-post"
```

## The Router's Job

1. Parse the URL into segments
2. Determine lang, type, slug
3. Look up the cached file path in the DB
4. Include the file

That's it. No framework required.
