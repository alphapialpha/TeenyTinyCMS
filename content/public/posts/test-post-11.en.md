---
slug: test-post-11
lang: en
title: Tagging Your Content
template: post
date: 2026-03-01
author: admin
tags: [tags, organization]
---

# Tagging Your Content

Tags are a simple way to group related posts without the complexity of categories.

## How Tags Work Here

Add a `tags` list to your front matter:

```yaml
tags: [php, tutorial, beginner]
```

The builder syncs them to the database and generates a static tag index page for each one.

## Tag URLs

```
/en/tag/php
/en/tag/tutorial
```

Each is a fully static page, built at compile time.
