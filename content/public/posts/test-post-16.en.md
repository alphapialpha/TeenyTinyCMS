---
slug: test-post-16
lang: en
title: Git as a Deployment Tool
template: post
date: 2026-02-24
author: admin
tags: [git, deployment]
---

# Git as a Deployment Tool

For small sites, you don't need CI/CD pipelines. A simple `git pull` and a rebuild is often enough.

## A Simple Workflow

1. Edit content locally
2. `git commit -am "New post"`
3. `git push`
4. On the server: `git pull && php app/builder.php`

## Even Simpler: Git Hooks

Set up a `post-receive` hook on your server to run the builder automatically after every push. Deploy by committing.
