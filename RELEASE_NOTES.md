# Release Notes: 2.0.0

**Status:** prepared, not tagged or published.

Version 2.0.0 is a complete security-focused rewrite of the 2013 contact form. It keeps the original form fields and adds encrypted storage and a protected admin area for reading submissions.

## Highlights

- XChaCha20-Poly1305 encryption of stored personal data, with keys from the environment.
- Argon2id passwords, TOTP two-factor authentication, and one-time recovery codes.
- CSRF protection, rate limiting, strict validation, and mail header injection protection.
- Maintained PHPMailer dependency in place of the bundled 1.73 copy.
- Automated tests, static analysis, CI, and a committed test report.

## Upgrade notes

There is no automated upgrade path because the original application stored nothing.

1. Install PHP 8.4 or later and point the web root at `frontend/public`.
2. Configure the environment from `.env.example` and generate a key.
3. Run `php bin/console db:migrate` and create an admin account.
4. Update any external integration that posts to the form to use the new English field names and a CSRF token.

## Known limitations

- SMTP delivery has not been tested against a live mail server.
- The `__Host-` secure cookie configuration has not been exercised behind real TLS.
- There is no bulk re-encryption command for key rotation.
- There is no self-service TOTP reset or admin removal command.
- No license has been selected for the project's own code.

Test evidence is in [reports/tests/](reports/tests/).
