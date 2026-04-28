# ADMINOS Feed Manager

Product catalog management with B2B partner feeds and B2C marketplace feeds, distributed as an ADMINOS module.

## What it provides

- A central `Product` entity with everything needed for both inbound (supplier feeds) and outbound (marketplace / partner feeds) flows
- Filament resources for catalog editing and partner / supplier configuration
- Output endpoints — partner-token-authenticated B2B feed and hash-authenticated B2C marketplace feed
- Inbound feed importers for the most common Czech / Slovak schemas (Heuréka, Google Shopping, Shoptet, custom)

## Status

Pre-stable preview. Public APIs marked `@api` follow SemVer; everything else may change without notice between `0.x` releases. The first published version (`0.1.0-alpha.x`) ships only the catalog scaffold; partner / marketplace feeds and supplier ingest land in subsequent releases.

## Installation

In an ADMINOS skeleton project:

```bash
composer require adminos/feedmanager
php artisan migrate
```

The module's service provider is auto-discovered via Laravel's `extra.laravel.providers` mechanism. To make its Filament resources visible in your admin panel, add the plugin to your `AdminPanelProvider`:

```php
use Adminos\Modules\Feedmanager\Filament\FeedmanagerPlugin;

$panel->plugin(FeedmanagerPlugin::make());
```

## Requirements

- PHP 8.3+
- Laravel 13+
- Filament 4+
- `adminos/core` ^0.1.0-alpha.2

## License

Proprietary. See [LICENSE](LICENSE). Copyright © Rekoj.cz.

## Issues and pull requests

This repository is a **read-only mirror** generated from the [`AdminosCZ/adminos`](https://github.com/AdminosCZ/adminos) monorepo by a subtree-split GitHub Action. Pull requests and issues opened here cannot be merged. File them against the monorepo instead:

- Issues: https://github.com/AdminosCZ/adminos/issues
- Pull requests: https://github.com/AdminosCZ/adminos/pulls
