# Security Audit

**Scope:** full source tree and Git history at the start of the 2026 modernization.
**Method:** manual code review, gitleaks on the full history, `composer audit`, PHPStan level 8, automated tests, a local HTTP smoke test, and probes against a local fake SMTP server.

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

## Findings in the rebuilt code

A second review of the 2.0.0 branch found and fixed these issues before release.

| ID | Severity | Finding | Status |
| --- | --- | --- | --- |
| REV-01 | Medium | Oversized or null-byte passwords skipped Argon2id for existing accounts, exposing valid usernames through response time | Fixed |
| REV-02 | Medium | A POST without `Content-Length` bypassed the body size limit | Fixed: returns 411 |
| REV-03 | Medium | An IPv6 client could evade per-address rate limits by rotating addresses within its /64 | Fixed |
| REV-04 | Medium | SMTP waits of up to 300 seconds could tie up PHP workers from a public form | Fixed: 10 second limit |
| REV-05 | Low | `display_errors` and `X-Powered-By` were left to the PHP defaults | Fixed in the application |
| REV-06 | Low | The development server's asset check revealed whether files outside the web root exist | Fixed |
| REV-07 | Low | HSTS `includeSubDomains` could force HTTPS on unrelated sibling hosts | Fixed: directive removed |
| REV-08 | Low | Viewing the submission list exposed names and emails without an audit event | Fixed |

## Secret scan

gitleaks reports two findings across the history and working tree. Both are the public RFC 6238 test secret used as a TOTP fixture in the test suite. No private keys or SMTP credentials appear in the history. Earlier revisions of the legacy files contained literal email addresses, including the former notification recipient. They are not reproduced here and are absent from the current tree. On 2026-11-24 the published history was rewritten to keep only the 2026 modernization commits, so those revisions are no longer part of any branch or tag.

## Controls added

| Area | Control |
| --- | --- |
| Authentication | Argon2id, TOTP with replay protection, single-use recovery codes, lockout |
| Sessions | Strict mode, HttpOnly, SameSite=Strict, Secure and `__Host-` in production, id rotation, timeouts |
| Data at rest | XChaCha20-Poly1305 with context binding, keys from the environment |
| Input | Server-side validation, email and phone formats, length limits, control character rejection |
| Output | Contextual escaping and a CSP that blocks scripts |
| Abuse | Rate limits with IPv6 /64 grouping, honeypot, declared body size limit |
| Mail | Fixed sender, TLS required, certificate verification left enabled, bounded SMTP wait |
| Supply chain | Locked dependencies, install scripts disabled, CI actions pinned by commit |

## Open items

| ID | Severity | Item |
| --- | --- | --- |
| OPEN-02 | Medium | Live SMTP delivery and the secure cookie configuration behind real TLS were not exercised. A local fake SMTP server confirmed that a server without STARTTLS and an untrusted certificate are both refused. |
| OPEN-03 | Medium | Rate limiting depends on a correct `REMOTE_ADDR` when deployed behind a proxy. |
| OPEN-04 | Low | Metadata such as timestamps and ciphertext length is not encrypted. |
| OPEN-05 | Low | Account lockout can be triggered deliberately to delay a legitimate admin for 15 minutes. |
| OPEN-06 | Low | No bulk re-encryption after key rotation and no TOTP reset command. |

## Resolved after the audit

| ID | Severity | Item | Resolution |
| --- | --- | --- | --- |
| OPEN-01 | High | The legacy code was on `master` and in Git history | Resolved on 2026-11-24: 2.0.0 was published on `master`, and the history rewrite removed the legacy code from every branch and tag. The legacy code must still not be deployed. |
| OPEN-07 | Low | Email addresses were in the history of the legacy files | Resolved on 2026-11-24 by the history rewrite. Old commits may stay reachable by direct SHA until GitHub purges unreferenced objects, so treat anything that was ever published as exposed. |
