# RemitRova Backend — Phase 1

**Scope of this phase:** Laravel scaffolding + Paga Static NUBAN
(Collect API) integration — creating a persistent account for a
customer, receiving and verifying deposit webhooks, and crediting the
customer's NGN wallet ledger. Disbursement (Business API / Deposit To
Bank) is Phase 2, not included here.

## What's real vs. what still needs your hands

**I wrote every file in this project by hand** — I do not have PHP,
Composer, or a database available in the sandbox I work in, so none of
this has actually been executed yet. What I *did* do:

- Verified every file's syntax is well-formed (brace/parenthesis
  balance checked programmatically across all 21 files).
- **Rebuilt the hash-signing logic in Python and ran 8 targeted test
  cases against it** — covering the exact scenario Paga's docs
  contradicted themselves on (optional-field inclusion, the dynamic
  `x-paga-hash-parameters` verification, tamper detection, wrong-key
  rejection). All 8 passed. This is the closest verification possible
  without a live PHP environment, and it's the piece most worth
  trusting *carefully* rather than blindly, so I made a point of not
  skipping it.
- The actual PHP file (`app/Services/Payments/Paga/PagaHasher.php`)
  mirrors that Python logic method-for-method — but you should still
  run `php artisan test` yourself once this is on your droplet, and
  ideally do one real round-trip against Paga's sandbox before trusting
  it in earnest.

**What genuinely needs your hands, and can't be verified any other
way:** every step below. This has never been run.

## Step-by-step: getting this running on your DigitalOcean droplet

### 1. Provision the droplet
- Ubuntu 24.04 LTS, at least 2GB RAM (1GB will struggle once
  `composer install` and the queue worker are both running).
- Point a domain/subdomain at it (e.g. `api.remitrova.com`) once you
  have one — Paga's webhook needs a real HTTPS URL to call.

### 2. Install the stack
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server redis-server supervisor certbot python3-certbot-nginx \
  php8.3-fpm php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath php8.3-zip unzip git

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```
(`bcmath` specifically matters here — `Wallet::debitAtomically()` uses
`bccomp()` for exact decimal comparison, not float comparison.)

### 3. Get the code onto the server, install dependencies
```bash
cd /var/www
git clone <your-repo-url> remitrova-backend   # or upload this folder directly
cd remitrova-backend
composer install --no-dev --optimize-autoloader
```

### 4. Configure the environment
```bash
cp .env.example .env
php artisan key:generate
```
Then edit `.env` and fill in:
- Your real MySQL credentials (create the database + user first: `mysql -u root -p` → `CREATE DATABASE remitrova; CREATE USER 'remitrova'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON remitrova.* TO 'remitrova'@'localhost';`)
- `APP_URL` — your real domain
- `PAGA_PRINCIPAL`, `PAGA_SECRET_KEY`, `PAGA_HASH_KEY` — from Paga's sandbox credentials (the ones already shared: Principal `70AFEF25-...`, etc. — move these into `.env`, never commit them into the repo)

### 5. Run migrations
```bash
php artisan migrate
```
This creates all 5 tables: `customers`, `wallets`, `persistent_accounts`, `ledger_entries`, `webhook_events`.

### 6. Run the test suite — do this before anything else
```bash
composer install --dev   # if you skipped dev deps in step 3
php artisan test
```
If `PagaHasherTest` fails here, something is wrong — stop and
investigate before doing a real Paga sandbox call, since a broken hash
means every webhook will silently fail verification in production.

### 7. Register the webhook URL with Paga
Your webhook endpoint will be:
```
https://api.remitrova.com/api/webhooks/paga/persistent-account
```
This needs to be passed as `callbackUrl` on every `createPersistentAccount`
call (already wired up in `ProvisionsPersistentAccounts` via
`route('webhooks.paga.persistent-account')` — just make sure `APP_URL`
in `.env` is your real HTTPS domain so the generated URL is correct).

**Also confirm with Paga:** do they need this webhook URL
pre-registered/whitelisted on their side before it'll receive
callbacks, or does passing it as `callbackUrl` per-request suffice?
This wasn't asked in the earlier support thread — worth a quick
follow-up before assuming it "just works."

### 8. Set up the queue worker (this is not optional)
Webhook processing happens in `ProcessPersistentAccountDeposit`, which
runs on the queue, not inline. Without a worker running, deposits will
be verified and logged but **never actually credited to a wallet**.

Create `/etc/supervisor/conf.d/remitrova-worker.conf`:
```ini
[program:remitrova-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/remitrova-backend/artisan queue:work redis --sleep=3 --tries=5 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/remitrova-backend/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start remitrova-worker:*
```

### 9. Nginx + HTTPS
Standard Laravel Nginx config pointing document root at `public/`, then:
```bash
sudo certbot --nginx -d api.remitrova.com
```

### 10. Exclude the webhook route from CSRF
In `bootstrap/app.php` (Laravel 11's new structure), add to the
`withMiddleware` callback:
```php
$middleware->validateCsrfTokens(except: [
    'api/webhooks/paga/persistent-account',
]);
```
(If this project targets Laravel 10 instead, this goes in
`App\Http\Middleware\VerifyCsrfToken::class`'s `$except` array
instead — check which Laravel version actually gets installed and
adjust accordingly, since I specified `^11.0` in composer.json but
can't confirm what Packagist resolves to without running it.)

### 11. First real end-to-end test
1. Create a test customer + NGN wallet (via `php artisan tinker` or a seeder).
2. Call `POST /api/accounts/nuban` (authenticated) to provision a real
   sandbox NUBAN.
3. Use Paga's sandbox tools (or their Postman collection) to simulate
   a deposit to that NUBAN.
4. Confirm: the webhook arrives, hash verifies, a `webhook_events` row
   appears, the queue worker picks it up, a `ledger_entries` row is
   created, and the wallet's `balance` actually increases.
5. Check `storage/logs/laravel.log` and `worker.log` for anything
   unexpected along the way.

This is the point where we find out if any of the confirmed-but-
untested assumptions above (webhook registration, response field
names, etc.) need adjusting — expect at least one thing to need a
small fix on first real contact with their sandbox, that's normal.

## What's deliberately NOT in this phase

- **Currency conversion on deposit.** The investor demo shows an NGN
  deposit automatically converting to spendable PLN — this backend
  phase does not do that yet. `ProcessPersistentAccountDeposit` has a
  `TODO` marking exactly where that logic needs to go once we have a
  live FX rate source and have decided how the conversion itself gets
  ledgered (a debit from the receiving wallet, a credit to the
  spendable one). Don't assume this works until Phase 2 confirms it.
- **Disbursement (Business API / Deposit To Bank).** Phase 2.
- **Any customer-facing auth/registration flow.** Phase 1 assumes a
  `Customer` already exists (however that gets built) with an
  associated NGN `Wallet` — the signup flow itself isn't scoped here.
- **KYC verification enforcement.** `Customer::hasCompletedImtoKyc()`
  exists as a check, but nothing currently calls it to block an action —
  that needs wiring into wherever transfers get initiated, in Phase 2.

## File map

```
app/
  Http/Controllers/Api/PersistentAccountController.php   customer-facing NUBAN provisioning endpoint
  Http/Controllers/Webhooks/PagaPersistentAccountWebhookController.php  receives + verifies + queues
  Jobs/ProcessPersistentAccountDeposit.php                the actual wallet-crediting logic
  Models/{Customer,Wallet,PersistentAccount,LedgerEntry,WebhookEvent}.php
  Services/Payments/Paga/
    PagaHasher.php          hash build + verify (the critical-correctness file)
    PagaHashFields.php      confirmed field lists per endpoint
    PagaCollectClient.php   HTTP client for Collect API calls
    ProvisionsPersistentAccounts.php   orchestrates NUBAN creation end-to-end
  Providers/PagaServiceProvider.php   DI wiring
config/paga.php             all Paga config, reads from .env
database/migrations/        5 tables, in dependency order
routes/api.php
tests/Unit/PagaHasherTest.php
```
