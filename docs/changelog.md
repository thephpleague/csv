---
layout: default
title: Changelog
---

# Changelog

All Notable changes to `Csv` will be documented in this file

{% for release in site.github.releases %}
## {{ release.name }}
{{ release.body | replace:'```':'~~~' | markdownify }}
{% endfor %}