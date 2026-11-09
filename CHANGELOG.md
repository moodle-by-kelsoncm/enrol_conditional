# Changelog

All notable changes to the Conditional self enrolment plugin will be documented in this file.

## 0.1.0 - Unreleased

### Added
- Plugin skeleton copied from `enrol_self` of Moodle 5.3 (`MOODLE_503_STABLE`, version 2026100500), renamed to `enrol_conditional`.
- The deprecated `enrol_self_enrol_form` class was not copied; the dynamic form `enrol_conditional\form\enrol_form` is used.
- Depends on `local_enrolconditions`, which declares the `enrolcond` subplugin type: Moodle only accepts subplugins under `mod`, `editor`, `tool` and `local` plugins.
- New courses do not get an instance of this method by default (`defaultenrol` is off).
- Action when a re-check finds that the conditions are no longer met: nothing (logged once per episode), suspend (default) or remove, plus automatic reactivation. Stored in `customint8` (action, plus 16 to reactivate).
- Table `enrol_conditional_state`: the plugin only reactivates enrolments that it suspended itself and that nobody changed since. Unknown results never suspend nor reactivate.
- Events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met`.
- Check modes per instance in `customint7` (bitmask): at enrolment (always), at login (`sync_user_enrolments`), periodically (scheduled task `check_conditions`, in batches of `checkbatchsize` that resume where the previous run stopped) and on the events observed by the conditions (catch-all observer with a cached map).
