# Changelog

All notable changes to the Conditional self enrolment plugin will be documented in this file.

## 0.1.0 - Unreleased

### Added
- Plugin skeleton copied from `enrol_self` of Moodle 5.3 (`MOODLE_503_STABLE`, version 2026100500), renamed to `enrol_conditional`.
- The deprecated `enrol_self_enrol_form` class was not copied; the dynamic form `enrol_conditional\form\enrol_form` is used.
- New courses do not get an instance of this method by default (`defaultenrol` is off).
