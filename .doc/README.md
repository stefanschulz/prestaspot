# PrestaSpot Documentation

Developer-facing documentation for the PrestaSpot plugin. For end-user/setup instructions (shop URL, API key, shortcode/block usage), see the plugin's own [README.md](../prestaspot/README.md) instead — this directory is about how the plugin is built, not how to use it.

| Doc | Purpose |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | File structure, class responsibilities, settings reference, the two-tier config pattern, PrestaShop Webservice integration details |
| [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) | Local Docker test environment, coding conventions, how to add a new display option end-to-end, pre-commit checklist |
| [CHANGES.md](CHANGES.md) | Version history |

Start with **ARCHITECTURE.md** to understand what exists, then **DEVELOPER_GUIDE.md** for how to work on it.

This directory is intentionally lean — PrestaSpot is a small, young plugin. Expand it as the plugin grows rather than front-loading documentation for features that don't exist yet.
