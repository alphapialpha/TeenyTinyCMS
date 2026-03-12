---
slug: test-post-18
lang: de
title: Ein Theme wählen
template: post
date: 2026-02-22
author: admin
tags: [theming, design]
---

# Ein Theme wählen

Das aktive Theme steuert jeden visuellen Aspekt der Website. Das Theme zu wechseln ist eine einzige Konfigurationsänderung.

## Theme-Struktur

```
themes/
  mein-theme/
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

## Theme wechseln

In `config/config.php`:

```php
'active_theme' => 'mein-theme',
```

Rebuild ausführen und das neue Theme gilt sofort für alle Seiten.
