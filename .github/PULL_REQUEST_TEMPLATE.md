# Summary

<!-- What changes, and why. The why matters more. -->

## Checklist

- [ ] `composer lint` passes (Pint, PHPStan, Rector)
- [ ] `composer test` passes
- [ ] `npm test` passes, if `resources/js` changed
- [ ] `npm run build` was run and `resources/dist` committed, if `resources/js`, `resources/css` or a view changed
- [ ] `CHANGELOG.md` has an entry under `## [Unreleased]`, if this is user-facing

## Notes for the reviewer

<!--
If this touches authorization, the command policy, risk classification or the
execute endpoint, say so here and name the test that pins the new behaviour.
Those are the parts of the package whose failure mode is "anyone can run
db:wipe", so they get read twice.
-->
