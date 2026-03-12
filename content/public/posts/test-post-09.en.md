---
slug: test-post-09
lang: en
title: Keeping Dependencies Minimal
template: post
date: 2026-03-03
author: admin
tags: [architecture, dependencies]
---

# Keeping Dependencies Minimal

Every dependency you add is a liability. It can go unmaintained, introduce security issues, or simply break on update.

## The Minimal Approach

Ask for each dependency:

> Can I implement this myself in under 100 lines?

If yes, do it. If no, evaluate carefully.

## What TeenyTinyCMS Uses

- Parsedown / ParsedownExtra for Markdown
- PDO for database access
- Nothing else

Less is more.
