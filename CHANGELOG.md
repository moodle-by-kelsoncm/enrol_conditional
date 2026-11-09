# Changelog

All notable changes to the Conditional self enrolment plugin will be documented in this file.

## 1.0.0 - 2026-11-09

### Added
- Plugin skeleton copied from `enrol_self` of Moodle 5.3 (`MOODLE_503_STABLE`, version 2026100500), renamed to `enrol_conditional`.
- The deprecated `enrol_self_enrol_form` class was not copied; the dynamic form `enrol_conditional\form\enrol_form` is used.
- Depends on `local_enrolconditions`, which declares the `enrolcond` subplugin type: Moodle only accepts subplugins under `mod`, `editor`, `tool` and `local` plugins.
- New courses do not get an instance of this method by default (`defaultenrol` is off).
- Action when a re-check finds that the conditions are no longer met: nothing (logged once per episode), suspend (default) or remove, plus automatic reactivation. Stored in `customint8` (action, plus 16 to reactivate).
- Table `enrol_conditional_state`: the plugin only reactivates enrolments that it suspended itself and that nobody changed since. Unknown results never suspend nor reactivate.
- Events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met`.
- The cohort restriction of `enrol_self` (`customint5`) was removed in favour of the `enrolcond_cohort` condition. The upgrade adds a cohort condition to the tree of instances that used it.
- Check modes per instance in `customint7` (bitmask): at enrolment (always), at login (`sync_user_enrolments`), periodically (scheduled task `check_conditions`, in batches of `checkbatchsize` that resume where the previous run stopped) and on the events observed by the conditions (catch-all observer with a cached map).
- Privacy API: records of `enrol_conditional_state` are exported and deleted with the course context.
- Backup and restore: open records of `enrol_conditional_state` go with the users, so restored suspensions can still be reactivated; condition trees from another site lose their cohort and course references. An invalid tree is kept and keeps blocking self enrolment.
- Behat: self enrolment allowed and blocked by the conditions (step `the course "..." has a conditional self enrolment method with conditions:`), and a Behat step in CI.
