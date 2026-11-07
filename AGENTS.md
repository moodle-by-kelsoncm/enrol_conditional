# enrol_conditional - Agent Guidelines

Enrolment method copied from `enrol_self` (Moodle 5.3) where self enrolment depends on pluggable conditions (`enrolcond` subplugins). Supports Moodle 5.3 LTS only.

## Rules

- Conditions apply only to enrolments made by this method (including its own web service). Manual, cohort and other methods are out of scope.
- Only reactivate enrolments that this plugin suspended (table `enrol_conditional_state`).
- Only `lang/en` is shipped. Do not add other language folders.
- Validate with `act` (see `CONTRIBUTING.md`) before pushing: code checker and PHPDoc run with zero warnings allowed.
