---
slug: test-post-18
lang: en
title: Choosing a Theme
template: post
date: 2026-02-22
author: admin
tags: [theming, design]
---

# Choosing a Theme

The active theme controls every visual aspect of the site. Switching themes is a single config change.

## Theme Structure

```
themes/
  my-theme/
    assets/
      css/app.css
      js/app.js
    templates/
      layout.php
      page_template.php
      post_template.php
      blog_template.php
      …
```

## Switching Themes

In `config/config.php`:

```php
'active_theme' => 'my-theme',
```

Rebuild and the new theme applies to every page instantly.
