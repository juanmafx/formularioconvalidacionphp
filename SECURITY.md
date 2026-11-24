# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | Yes |
| Earlier code | No |

Earlier code must not be deployed. See [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md).

## Reporting a vulnerability

Report vulnerabilities privately through GitHub's **Report a vulnerability** option on the repository's Security tab, if it is enabled, or by contacting the maintainer through the GitHub profile. Do not open a public issue. Please include the affected version, the impact, and the steps needed to reproduce it.

This is a personal project, so no response time is guaranteed.

## Operator responsibilities

- Serve only `frontend/public`, over TLS.
- Store the keyring and SMTP credentials outside the repository.
- Back up the keyring separately from the database.
- Run `composer audit` and apply dependency updates regularly.
- Schedule `php bin/console maintenance:purge`.
