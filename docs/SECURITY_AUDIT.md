# Security Audit

**Scope:** full source tree and Git history at the start of the 2026 modernization.
**Method:** manual code review, history scan for credentials and private keys, `composer audit`, PHPStan level 8, automated tests, and a local HTTP smoke test.

This is a self-review, not an independent penetration test.

## Findings in the legacy code

| ID | Severity | Finding | Status |
| --- | --- | --- | --- |
| LEG-01 | Critical | Unrestricted file upload handling into a local directory | Fixed: feature removed |
| LEG-02 | Critical | Bundled PHPMailer 1.73, long unsupported, with known vulnerabilities | Fixed: replaced |
| LEG-03 | High | Mail header injection through visitor-controlled sender fields | Fixed |
| LEG-04 | High | Reflected XSS in form values | Fixed: contextual escaping and CSP |
| LEG-05 | High | HTML injection in notification emails | Fixed: escaping |
| LEG-06 | High | No CSRF protection | Fixed |
| LEG-07 | Medium | No rate limiting or spam control | Fixed |
| LEG-08 | Medium | Validation limited to presence checks | Fixed |
| LEG-09 | Medium | Predictable file names from a non-cryptographic generator | Fixed: feature removed |
| LEG-10 | Medium | End-of-life runtime and short open tags | Fixed: PHP 8.4 |
| LEG-11 | Low | No security headers | Fixed |
| LEG-12 | Low | Custom field values overwrote one variable | Fixed |

## Secret scan

A pattern-based scan of the full history found no private keys or common API token formats, and no SMTP credentials appear in the legacy code. Earlier revisions of the legacy files do contain literal email addresses, including the former notification recipient. They are not reproduced here and are absent from the current tree, but they remain in Git history.

## Controls added

| Area | Control |
| --- | --- |
| Authentication | Argon2id, TOTP with replay protection, single-use recovery codes, lockout |
| Sessions | Strict mode, HttpOnly, SameSite=Strict, Secure and `__Host-` in production, id rotation, timeouts |
| Data at rest | XChaCha20-Poly1305 with context binding, keys from the environment |
| Input | Server-side allow-list validation, length limits, control character rejection |
| Output | Contextual escaping and a CSP that blocks scripts |
| Abuse | Rate limits, honeypot, body size limit |
| Mail | Fixed sender, TLS required, certificate verification left enabled |
| Supply chain | Locked dependencies, install scripts disabled, CI actions pinned by commit |

## Open items

| ID | Severity | Item |
| --- | --- | --- |
| OPEN-01 | High | The legacy code remains on `master` and in Git history until this branch is merged. It must not be deployed. |
| OPEN-02 | Medium | Live SMTP delivery and the secure cookie configuration behind TLS were not exercised. |
| OPEN-03 | Medium | Rate limiting depends on a correct `REMOTE_ADDR` when deployed behind a proxy. |
| OPEN-04 | Low | Metadata such as timestamps and ciphertext length is not encrypted. |
| OPEN-05 | Low | Account lockout can be triggered deliberately to delay a legitimate admin for 15 minutes. |
| OPEN-06 | Low | No bulk re-encryption after key rotation and no TOTP reset command. |
| OPEN-07 | Low | Email addresses remain in the history of the legacy files. Removing them would require a history rewrite, which is an owner decision. |
