# Security policy

## Reporting a vulnerability

Report security issues privately to **security@simtabi.com**. Do not open a public issue for a suspected vulnerability.

Include the affected version, a description of the issue and, where you can, a minimal reproduction. You can expect an acknowledgement within three working days and a substantive reply within ten.

GitHub private vulnerability reporting will be offered as the preferred channel once this repository is published and the feature is switched on. Until then, email is the only channel.

## Supported versions

While the package is pre-1.0, only the latest tagged release receives security fixes.

## Scope notes

This package runs Artisan commands from a web request, so its security surface is the point of it. The full threat model is in [docs/security.md](docs/security.md). In brief:

- **Nothing runs without the application's say-so.** The panel is off by default, reachable only in the `local` environment, and every Gate ability denies until the application defines it. The checks run in a fixed order and configuration cannot remove them.
- **One endpoint runs commands.** It is POST-only, CSRF-protected, rate-limited, validated against the command's own definition, authorized per command and per input, and locked per command. Quick actions and history reruns only pre-fill a form.
- **Destructive commands** need the command name typed back and a password confirmation made within the timeout.
- **Output is data, not markup.** It is sent as text segments and rendered with `textContent`, under a CSP that allows no inline script and no `eval`.
- **Redaction is best effort.** Known secret values, `NAME=value` lines and URL credentials are masked in output and in the audit trail. A command that prints secrets in another shape should be denied rather than relied on to be masked; `config:show` is forbidden by default for that reason.
- **Failures never leak detail.** An exception reaches the browser as a run id, in every environment, and is reported to the application's exception handler with its cause intact.

Reports about the upstream projects this package derives from should go to those projects.
