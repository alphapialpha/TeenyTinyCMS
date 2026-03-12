---
slug: test-post-16
lang: de
title: Git als Deployment-Werkzeug
template: post
date: 2026-02-24
author: admin
tags: [git, deployment]
---

# Git als Deployment-Werkzeug

Für kleine Websites braucht man keine CI/CD-Pipelines. Ein einfaches `git pull` und ein Rebuild reichen oft aus.

## Ein einfacher Workflow

1. Inhalte lokal bearbeiten
2. `git commit -am "Neuer Beitrag"`
3. `git push`
4. Auf dem Server: `git pull && php app/builder.php`

## Noch einfacher: Git Hooks

Einen `post-receive`-Hook auf dem Server einrichten, der den Builder nach jedem Push automatisch ausführt. Deployment per Commit.
