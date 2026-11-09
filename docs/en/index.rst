moodle-enrol_conditional
========================

**Self enrolment that depends on conditions you choose: dates, profile fields, cohorts, completed courses or lists of usernames.**

Versão em português: `Português (Brasil) <../pt-br/index.html>`_.

*Conditional self enrolment* is a copy of the core self enrolment method (``enrol_self``) in which users can only enrol themselves when they meet the conditions of the enrolment method. Teachers combine the conditions in groups that require all or any of their items, and can invert a condition or a group. The method can also check the conditions again after the enrolment and suspend or remove enrolments that no longer meet them.

The conditions come from the companion plugin `local_enrolconditions <https://github.com/moodle-by-kelsoncm/local_enrolconditions>`_, as ``enrolcond`` subplugins. Other plugins can add their own conditions.

Main features
-------------

* Everything ``enrol_self`` offers: enrolment key, group keys, enrolment period, maximum enrolled users, welcome message and the web service.
* Seven condition types: date, user profile field, cohort, completed course, typed list of usernames, file of usernames and lists defined by administrators as database queries.
* Groups with *All of these* or *Any of these*, nested, each item can be marked *Must not be met*.
* Users who cannot enrol see which conditions they do not meet.
* Re-checks at login, periodically or when something the conditions depend on changes, with three actions: only record it, suspend or remove the enrolment.
* Automatic reactivation that only touches the enrolments this plugin suspended itself, never a suspension made by a person or by another plugin.
* Events, Privacy API and backup and restore of the conditions and of the plugin's own suspensions.

.. toctree::
   :maxdepth: 2
   :caption: Contents

   installation
   configuration
   usage
   conditions
   rechecks
   backup_privacy
