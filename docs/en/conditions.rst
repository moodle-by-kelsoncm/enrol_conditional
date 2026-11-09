Condition types
===============

All types are subplugins of ``local_enrolconditions`` (folder ``local/enrolconditions/condition``).

Date (``enrolcond_date``)
   Met from a date and time (*From*), until a date and time (*Until*) or between both.

User profile (``enrolcond_profile``)
   Compares a standard or custom profile field with a value: *is equal to*, *contains*, *does not contain*, *starts with*, *ends with*, *is empty* or *is not empty*. Re-checked when the user profile is updated.

Cohort (``enrolcond_cohort``)
   Met by the members of a cohort available in the course context. Re-checked when members are added to or removed from a cohort. Replaces the cohort restriction of ``enrol_self``: the upgrade from an earlier version turns that setting into a cohort condition.

Course completed (``enrolcond_coursecompleted``)
   Met by the users who completed another course. Re-checked when a course is completed.

List of usernames (``enrolcond_userlist``)
   Usernames typed in the condition, one per line or separated by spaces, commas or semicolons; case is ignored. At most 5,000 usernames.

File of usernames (``enrolcond_userfile``)
   A CSV or text file with the usernames in the first column (a first line ``username`` is a header). The file is kept in the course and the teacher replaces it to update the list. At most 200,000 usernames. A daily task deletes files no condition uses any more, after one day. If the file is lost, the condition is unknown.

List from a query (``enrolcond_sqlquery``)
   One of the lists defined by an administrator (see :doc:`configuration`). Teachers choose a list by its name and never see the query. The result is cached; a query that fails is unknown. Inside an open database transaction the query is not run: without a cached result the condition is unknown until it is checked outside one, for example at login or periodically.

Conditions from other plugins
-----------------------------

Any plugin can provide conditions through the hook ``\local_enrolconditions\hook\collect_conditions``, with classes that extend ``\local_enrolconditions\condition\base``. See the README of ``local_enrolconditions``.
