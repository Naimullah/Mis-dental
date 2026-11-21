# Laravel React App — Windows Setup

This project is based on the [official Laravel React starter kit](https://github.com/laravel/react-starter-kit), commit `012f424` (November 10, 2026).

## Included

- Laravel 13 (PHP 8.3+)
- React 19 and TypeScript
- Inertia.js 3
- Tailwind CSS 4 and shadcn/ui
- Vite Plus and Laravel Wayfinder
- Login, registration, password reset, email verification, user settings, two-factor authentication/passkey support
- SQLite default database (can switch to PostgreSQL or MySQL)

## 1. Requirements

Install **PHP 8.3 or newer**, **Composer 2**, and **Node.js 24**. Verify in PowerShell:

```powershell
php -v
composer --version
node -v
npm -v
```

## 2. Install and run

Extract this archive, then open PowerShell inside the `laravel-react-app` folder.

```powershell
composer install
Copy-Item .env.example .env
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan key:generate
php artisan migrate
npm ci
npm run build
composer run dev
```

Open **http://localhost:8000**. If `database\database.sqlite` already exists, don't overwrite it.

For continued development, `composer run dev` runs Laravel and the frontend together. Alternatively run `php artisan serve` and `npm run dev` in separate terminals.

With the default `MAIL_MAILER=log`, verification emails are recorded in `storage/logs/laravel.log`, not delivered. Configure an SMTP service or local mail catcher to test email delivery.

## 3. Add shadcn components

The project already includes the shadcn/ui configuration and many components. To add another:

```powershell
npx shadcn@latest add switch
```

Example in a React page:

```tsx
import { Switch } from '@/components/ui/switch';
```

## 4. Main folders

| Purpose           | Folder or file                |
| ----------------- | ----------------------------- |
| Laravel routing   | `routes/web.php`              |
| React pages       | `resources/js/pages/`         |
| Components        | `resources/js/components/`    |
| shadcn components | `resources/js/components/ui/` |
| Page layouts      | `resources/js/layouts/`       |
| Tailwind styling  | `resources/css/app.css`       |
| Database          | `database/migrations/`        |
| Local settings    | `.env`                        |

**Note:** Laravel Wayfinder generates `resources/js/routes/` and `resources/js/actions/` from PHP routes. PHP and Composer dependencies must be installed before `npm run build` or `npm run types:check`. Those generated folders are intentionally excluded from the source archive.

This is a starter project, not a deployed application. Keep `.env` private; never commit real passwords or application keys.
