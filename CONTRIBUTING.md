# Contributing / Contribuindo

## Workflow

1. Open (or pick) an issue and work on a branch named `issues/<number>`.
2. Follow the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle): every PHP, JavaScript, CSS and Mustache file starts with the GPL boilerplate.
3. Add or update PHPUnit tests in `tests/` for every change of behaviour.
4. JavaScript lives in `amd/src` as ES6 modules; rebuild `amd/build` with `npx grunt amd --root=public/enrol/conditional` in a Moodle 5.3 checkout and commit both.
5. Language strings go only to `lang/en/enrol_conditional.php`; translations come from AMOS.
6. Update `CHANGELOG.md` (section `## <release> - <date>` for the next release) and the documentation in `docs/en` and `docs/pt_BR`.
7. Commit messages follow Conventional Commits in Brazilian Portuguese: `tipo(escopo): [TAG] Descrição`, with *Motivo*, *Impacto* and *Arquivos/Mudanças* in the body.

## Checks before pushing

Run the CI workflow locally with [`act`](https://github.com/nektos/act):

```bash
act -W .github/workflows/ci.yml --matrix moodle-branch:MOODLE_503_STABLE --matrix database:pgsql
```

It runs `moodle-plugin-ci`: PHP lint, code checker and PHPDoc (no warnings allowed), validation, upgrade savepoints, Mustache lint, grunt (ESLint, stylelint and the `amd/build` check) and PHPUnit. The documentation is checked by `.github/workflows/docs.yml` (`sphinx-build -W`).

## Releasing

1. Bump `$plugin->version` and `$plugin->release` in `version.php` and add the release section to `CHANGELOG.md`.
2. With CI green on `main`, run the **Prepare release** workflow. It builds `enrol_conditional-<release>.zip` and a draft release with the changelog as notes.
3. Review the draft and publish it: the tag `v<release>` is created on publication. **Verify published release** checks that the attached ZIP matches the tagged code.
4. Upload the ZIP to the Moodle Marketplace.
