# formularioconvalidacionphp

A security-focused PHP 8.4 contact form with server-side validation, encrypted storage, and an admin area protected by a password and TOTP two-factor authentication.

> **Project history:** originally created in 2013, modernized in 2026.

## Purpose

The project answers a small but easy-to-get-wrong question: how should a public contact form handle personal data? A contact form accepts anonymous input, sends email, and stores names, addresses, and phone numbers, so it touches injection, spam, credential, and privacy risks at once.

## Why this project exists

It began as a single-file PHP form that emailed its contents through a bundled mailer. Version 2 keeps the same form fields and rebuilds everything around them as a compact reference for doing this job safely without a framework.

## What it does

- Renders a contact form and validates every field on the server.
- Encrypts each accepted submission with XChaCha20-Poly1305 before it is written to SQLite.
- Optionally notifies an inbox through TLS-only SMTP.
- Provides an admin area to read submissions, protected by Argon2id passwords, TOTP, one-time recovery codes, lockout, and an audit trail.
- Applies CSRF protection, rate limiting, a honeypot, and strict security headers.

## Architecture

```text
frontend/
  public/          Web root: front controller and static assets
  templates/       Escaped server-rendered views
backend/
  src/             Application modules
  tests/           Unit and functional test suites
bin/console        Operator commands
db/schema.sql      Database schema
docs/              Architecture and security audit
reports/tests/     Committed output from a real test run
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for module responsibilities, request flows, and the recommended deployment.

## Quick start

Requirements: PHP 8.4 (the version the test suite runs on) with `mbstring`, `pdo_sqlite`, `session`, `sodium`, and Argon2 support, plus Composer 2. Running the tests also needs `dom`, `xml`, and `xmlwriter`.

```bash
composer install --no-scripts --no-plugins
cp .env.example .env && chmod 600 .env
php bin/console keys:generate k1
```

Paste the printed value into `APP_KEYRING` in `.env`, then:

```bash
set -a; . ./.env; set +a
php bin/console db:migrate
read -rs ADMIN_PASSWORD
printf '%s\n' "$ADMIN_PASSWORD" | php bin/console admin:create operator
unset ADMIN_PASSWORD
APP_SECURE_COOKIES=false php -S 127.0.0.1:8080 -t frontend/public frontend/public/index.php
```

`admin:create` prints the TOTP enrollment URI and ten recovery codes **once**. Add the URI to an authenticator app and store the codes offline. Disabling secure cookies is only appropriate for this local HTTP server.

## Usage guide

| Task | How |
| --- | --- |
| Submit the form | Open `/` |
| Read submissions | Open `/admin/login`, enter the password, then a TOTP or recovery code |
| Create another admin | `php bin/console admin:create <username>` with the password on standard input |
| Generate a new key | `php bin/console keys:generate <id>` |
| Apply retention | `php bin/console maintenance:purge`, typically from a daily scheduled job |

### Rotating the encryption key

Append the new entry to `APP_KEYRING` and set `APP_ACTIVE_KEY_ID` to it. New records use the new key and older records stay readable. There is no bulk re-encryption command, so retired keys must stay in the keyring until the records they protect are purged.

## Configuration

All configuration comes from environment variables. Invalid or unsafe values stop the application from starting.

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_KEYRING` | required | Comma-separated `id:base64` 32-byte keys |
| `APP_ACTIVE_KEY_ID` | required | Key id used for new encryption |
| `APP_SECURE_COOKIES` | `true` | Secure `__Host-` session cookie and HSTS |
| `DB_PATH` | `var/contact.sqlite` | SQLite database path |
| `RETENTION_DAYS` | `365` | Age at which `maintenance:purge` deletes records |
| `ARGON_MEMORY_KIB` | `65536` | Argon2id memory, minimum 19456 |
| `ARGON_TIME_COST` | `3` | Argon2id iterations, minimum 2 |
| `ARGON_THREADS` | `1` | Argon2id parallelism |
| `MAIL_ENABLED` | `false` | Enables SMTP notifications |
| `MAIL_HOST`, `MAIL_PORT` | none, `587` | SMTP server |
| `MAIL_ENCRYPTION` | `starttls` | `starttls` or `smtps`; plaintext is rejected |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | empty | SMTP credentials |
| `MAIL_FROM`, `MAIL_TO` | none | Fixed sender and recipient |
| `MAIL_INCLUDE_CONTENT` | `false` | Includes submission content in the email |

By default the notification email contains only a reference id, so personal data stays encrypted in the database. Set `MAIL_INCLUDE_CONTENT=true` to restore the original behavior of emailing the full message.

## Database

[db/schema.sql](db/schema.sql) defines the SQLite schema: admin users, recovery codes, encrypted submissions, rate limit buckets, and audit events. Apply it with `php bin/console db:migrate`.

## Testing

```bash
composer test
composer analyse
```

If your local PHP lacks the required extensions, run the suite in the official image with networking disabled:

```bash
docker run --rm --network none -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.4-cli vendor/bin/phpunit
```

The output of the last recorded run is in [reports/tests/](reports/tests/). CI runs dependency audit, PHPStan, and PHPUnit on every push and pull request.

## Security notes

- Serve only `frontend/public` and terminate TLS in front of the application.
- Keep `.env`, the database, and backups out of the web root. Losing the keyring makes stored submissions unrecoverable.
- Rate limiting uses `REMOTE_ADDR`. Behind a reverse proxy, configure the web server to restore the real client address, or all visitors will share one limit.
- File uploads were removed. The legacy code handled them unsafely and the form never exposed an upload field.

See [SECURITY.md](SECURITY.md) and [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md).

## Credits and license

The original version bundled PHPMailer 1.73 by Brent R. Matzelle (LGPL). It has been replaced by the maintained [PHPMailer](https://github.com/PHPMailer/PHPMailer) package. Third-party notices are in [NOTICE.md](NOTICE.md).

The `LICENSE` file in this repository is empty, so no license has been selected for the project's own code yet.
