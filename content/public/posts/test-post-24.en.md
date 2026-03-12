---
slug: test-post-24
lang: en
title: The Build Process Explained
template: post
date: 2026-02-16
author: admin
tags: [cms, architecture]
---

# The Build Process Explained

Every time you hit "Rebuild", the builder runs through four passes.

## Pass 0 — Prune

Remove DB rows and cache files for content that no longer exists on disk.

## Pass 1 — Metadata Sync

Parse every Markdown file's front matter and upsert it into the database. This runs first so the blog index can query an up-to-date post list.

## Pass 2 — Render

Render each file using its template, wrap in the layout, write the result as a `.php` cache file.

## Pass 3 — Tag Pages

Generate one static tag index page per `(tag, language)` combination.

## Pass 4 — Blog Pagination

Generate `blog-page-2.php`, `blog-page-3.php`, … based on current post count.
