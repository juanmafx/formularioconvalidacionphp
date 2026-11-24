# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

### Added

- Admin area with Argon2id passwords, TOTP two-factor authentication, one-time recovery codes, account lockout, session timeouts, and an audit log.
- Authenticated encryption of stored submissions and TOTP secrets, with key rotation support for new records.
- CSRF protection, rate limiting, honeypot, strict server-side validation, and security headers.
- SQLite schema, operator console, retention purge, unit and functional tests, static analysis, and CI.
- Project documentation, security policy, and security audit.

### Changed

- **Breaking:** requires PHP 8.4 or 8.5.
- **Breaking:** restructured into `frontend/` and `backend/`. The web root is now `frontend/public`.
- **Breaking:** form field names, identifiers, and all UI text are now in English.
- **Breaking:** configuration moved to environment variables.
- **Breaking:** notification emails omit submission content unless `MAIL_INCLUDE_CONTENT=true`.
- Email is sent from a fixed address, with the visitor only in `Reply-To`.
- Phone is now enforced as required, matching the form's documented behavior.

### Removed

- Bundled PHPMailer 1.73, replaced by the maintained Composer package.
- Unsafe, unused file upload handling.
- Non-English README sections.

### Fixed

- Reflected and email HTML injection.
- Mail header injection through visitor-controlled sender fields.
- Custom fields two and three no longer overwrite a single variable.
