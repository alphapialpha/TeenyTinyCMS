---
slug: test-post-14
lang: en
title: Handling 404 Pages Gracefully
template: post
date: 2026-02-26
author: admin
tags: [ux, routing]
---

# Handling 404 Pages Gracefully

A 404 page doesn't have to be an embarrassing dead end. It's an opportunity.

## What a Good 404 Does

- Confirms the page wasn't found (obviously)
- Offers navigation back to somewhere useful
- Maintains the site's look and feel

## In TeenyTinyCMS

Create a `404.en.md` page with `slug: 404`. The router will automatically serve it when no matching content is found, with a proper `HTTP 404` status header.
