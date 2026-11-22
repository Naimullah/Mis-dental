# Laravel + React Starter Kit

## Introduction

Our React starter kit provides a robust, modern starting point for building Laravel applications with a React frontend using [Inertia](https://inertiajs.com).

Inertia allows you to build modern, single-page React applications using classic server-side routing and controllers. This lets you enjoy the frontend power of React combined with the incredible backend productivity of Laravel and lightning-fast Vite compilation.

This React starter kit utilizes React 19, TypeScript, Tailwind, and the [shadcn/ui](https://ui.shadcn.com) and [radix-ui](https://www.radix-ui.com) component libraries.

## Official Documentation

Documentation for all Laravel starter kits can be found on the [Laravel website](https://laravel.com/docs/starter-kits).

## Contributing

Thank you for considering contributing to our starter kit! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

All contributions to the Starter Kits from now on should be made through [Maestro](https://github.com/laravel/maestro).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## License

The Laravel + React starter kit is open-sourced software licensed under the MIT license.

## Permission catalog

Permission definitions live in `config/permissions.php`. After applying the permissions migration, preview the catalog changes with `php artisan permissions:sync`. An authorized deployment operator can apply the reviewed changes with `php artisan permissions:sync --apply`. The command retains unlisted definitions and reports their codes.

Set `PERMISSION_CATALOG_VIEWER_IDS` to a comma-separated list of approved user IDs to grant read access under **Platform Admin → Security → Permissions**. This allowlist defaults to empty. Apply the normal configuration cache refresh after updating this setting.

Set `PERMISSION_TRANSLATION_MAINTAINER_IDS` to grant approved users access to the page and its translation editor. This allowlist defaults to empty. Maintainers can save translations in active languages and remove existing translations in any language.

Set `PERMISSION_DEFINITION_MAINTAINER_IDS` to grant approved platform users access to create, edit, and delete custom permissions. Use codes beginning with `custom.`. Codes stay fixed after creation. Source-controlled definitions are managed through catalog sync. Remove a custom permission's translations before deleting it. The account allowlists control access independently of custom permission records.

## Platform user accounts

Set `PLATFORM_ACCOUNT_MAINTAINER_IDS` to a comma-separated list of approved platform user IDs, then refresh the configuration cache. The list defaults to empty. Account changes are recorded in the application log with the actor ID, account ID, and action.
