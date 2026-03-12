---
slug: test-post-12
lang: en
title: Template Partials Explained
template: post
date: 2026-02-28
author: admin
tags: [templates, theming]
---

# Template Partials Explained

Partials are reusable template fragments. Instead of repeating the same header HTML in every template, you write it once.

## Using a Partial

```php
render_partial('header', ['lang' => $lang]);
```

## Available Partials

- `header` — site title and top bar
- `nav` — main navigation
- `footer` — copyright and credits
- `post_teaser` — compact post preview
- `tag_list` — inline list of tag links
- `language_switcher` — links to other language versions

Keep partials small and focused.
