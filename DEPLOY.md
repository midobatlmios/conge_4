# Deploying to Laravel Cloud

Checklist for putting the app online for a real company. Steps marked
**(check)** depend on Laravel Cloud's current dashboard and plans: confirm them
there, since names and prices change.

## 0. Before you start

- Commit and push everything to GitHub (`main`).
- Decide the data region. The app stores personal data (salaries, CIN, CNSS
  numbers, documents). Choose an EU region. Moroccan law 09-08 generally
  requires a declaration to, or authorization from, the CNDP to send personal
  data abroad. Settle this with the company before real data goes in.

## 1. Create the application

1. In Laravel Cloud: **New application** → connect the GitHub repository → branch `main`.
2. Region: EU **(check)** which ones are offered.
3. PHP **8.4**. The lock file does not support PHP 8.5 yet.
4. Plan: Sandbox is fine to try things out. For a real company, use a paid plan
   so the app never hibernates **(check)** current pricing.

## 2. Resources

- **Database:** create a MySQL database and attach it to the environment.
  Cloud fills in the `DB_*` variables. Turn on backups and confirm how long
  they are kept **(check)** what the plan includes.
- **Bucket (required):** create a *private* bucket and attach it with the disk
  name **`uploads`**. All uploaded files go there: employee and leave documents,
  CVs, the company logo and Excel imports. Without it, every deploy erases them.
  Locally, the `uploads` disk is simply `storage/app/private`.
- **Scheduler:** turn it on (it runs `schedule:run` every minute). It sends the
  weekday morning reminders (`reminders:send`).
- **Queue worker:** not needed. Nothing is queued (`QUEUE_CONNECTION=sync`).

## 3. Environment variables

Copy `.env.production.example` into the environment's variables and fill in:

- `APP_URL`: the real `https://` address. Links in e-mails are built from it.
- `MAIL_USERNAME` / `MAIL_PASSWORD`: the Brevo SMTP login and key.
- `MAIL_FROM_ADDRESS`: a sender address verified in Brevo.
- `SENTRY_LARAVEL_DSN`: from a new Laravel project in sentry.io. Leave it empty
  to keep Sentry off.

## 4. Build and deploy commands

Build:

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Deploy:

```
php artisan migrate --force
```

Cloud runs the config, route and view caches itself **(check)**. If it doesn't,
add `php artisan optimize` to the deploy commands.

## 5. Domain

Add the custom domain in the environment, create the DNS record Cloud shows
you, and wait for the certificate (HTTPS is automatic).

## 6. First login

The database starts empty. Create the first administrator from the Cloud
command runner:

```
php artisan tinker --execute="App\Models\User::forceCreate(['name' => 'Admin', 'email' => 'admin@example.ma', 'password' => 'change-me', 'role' => 'admin', 'email_verified_at' => now()]);"
```

Then sign in, change the password, and fill in **Entreprise** (company
settings, sites), departments and holidays before importing employees.

> Never run `demo:reset` on the company's environment: it erases all data.
> It refuses to run when `APP_ENV=production`.

## 7. After going live

- Send a test e-mail (forgotten password) to check Brevo.
- Upload a document, deploy again, and check the document is still there.
- Trigger a test error and check it reaches Sentry.
- Check the next morning that the reminders were sent.

## A public demo (portfolio)

Create a second environment (Sandbox plan) with `APP_ENV=demo`, its own database
and bucket, and add a scheduled reset. Use a separate environment because
`demo:reset` refuses to run in production.
