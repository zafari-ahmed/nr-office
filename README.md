# NR Associates Office

Internal office management system for NR Associates: projects and follow-ups, quotations and invoices,
a log for documents sent outside the system, expenses, inventory, employees and the site-visit rotation.

Built with Laravel 12, MySQL, Blade, Tailwind CSS 4, Alpine.js and Chart.js.

- Module-wise plan: [docs/DEVELOPMENT_PLAN.md](docs/DEVELOPMENT_PLAN.md)
- Database design: [docs/DATABASE.md](docs/DATABASE.md)

## Setup

```bash
cp .env.example .env            # set DB_DATABASE / DB_USERNAME / DB_PASSWORD for MySQL
composer install
php artisan key:generate
php artisan migrate --seed      # tables + sample data
php artisan storage:link
npm install && npm run build
php artisan serve
```

Sign in with `admin@nrassociates.pk` / `password` (change it under Settings → Users & roles).
Other sample logins: `kamran@nrassociates.pk` (manager), `accounts@nrassociates.pk` (accounts), `ahmed@nrassociates.pk` (staff).

For a clean production database without sample data, run `php artisan migrate --seeder=SettingsSeeder`
and create the first admin with `php artisan tinker`.

### Daily reminders

Add the scheduler to cron so overdue invoices, follow-ups, site returns and low stock are flagged every morning at 08:00:

```
* * * * * cd /path/to/nr-office && php artisan schedule:run >> /dev/null 2>&1
```

Run once by hand with `php artisan nr:daily-reminders`.

## Tests

```bash
php artisan test
```
