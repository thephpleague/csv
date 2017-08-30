---
layout: default
title: Changelog
redirect_from: /changelog/
---

# Changelog

All Notable changes to `Csv` will be documented in this file

{% for release in site.github.releases %}
## {{ release.name }} - {{ release.published_at | date: "%Y-%m-%d" }}
{{ release.body | replace:'```':'~~~' | markdownify }}
{% endfor %}