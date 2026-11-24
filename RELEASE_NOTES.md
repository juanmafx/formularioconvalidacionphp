# Release Notes: 2.0.1

Released 2026-11-24.

This is a documentation-only patch release. There is no code behaviour change from 2.0.0.

## Changes

- The changelog and release notes now record 2.0.0 as released on 2026-11-24.
- The security audit marks OPEN-01 and OPEN-07 as resolved by the 2.0.0 publication and the 2026-11-24 history rewrite. Anything that was ever published should still be treated as exposed.
- The README records that the CI workflow ran and passed after publication.
- The third-party notice for the former bundled PHPMailer 1.73 copy no longer claims it is available in the Git history.

## Upgrade notes

No migration is needed from 2.0.0.

## Known limitations

- SMTP delivery has not been tested against a live mail server.
- The `__Host-` secure cookie configuration has not been exercised behind real TLS.
- There is no bulk re-encryption command for key rotation.
- There is no self-service TOTP reset or admin removal command.
- No license has been selected for the project's own code.
