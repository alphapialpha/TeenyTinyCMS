---
slug: test-post-19
lang: en
title: Media Management
template: post
date: 2026-02-21
author: admin
tags: [media, files]
---

# Media Management

Images and files live in `content/public/media/`. They're served via a dedicated media handler.

## Adding Media

Drop a file into the media folder. The next rebuild will sync it to the database and make it available at:

```
/media/public/your-image.jpg
```

## Using Media in Posts

```markdown
![My image](/media/public/my-image.jpg)
```

Simple file system. No upload UI needed unless you want one.
