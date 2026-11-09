Configuration
=============

Plugin settings
---------------

*Site administration > Plugins > Enrolments > Conditional self enrolment* has the settings of ``enrol_self`` plus:

When the conditions are no longer met
   Default action for new instances: *Nothing (only record it)*, *Suspend the enrolment* (default) or *Remove the enrolment*.

Reactivate when the conditions are met again
   Default for new instances. Off by default.

When the user logs in / Periodically, in the scheduled task / When something the conditions depend on changes
   Default check modes for new instances. All off by default: new instances only check the conditions when a user enrols.

Enrolments per periodic check
   Maximum number of enrolments re-checked by each run of the scheduled task (default 500). The next run continues where the previous one stopped.

New courses do not get an instance of this method by default (*Add instance to new courses* is off).

Managing the condition types
----------------------------

*Site administration > Plugins > Enrolments > Enrolment conditions > Manage conditions* lists the installed condition types. A disabled type is no longer offered to teachers; instances that already use it refuse new self enrolments, because the condition can no longer be checked, and never suspend anybody because of it.

Lists from a query
------------------

The *List from a query* condition only offers lists that an administrator defined in its settings page (*Manage conditions*, then *Settings* of *List from a query*):

Number of lists
   How many lists can be defined (default 3, at most 20).

Name and Query
   For each list. The name is shown to teachers and to users who do not meet the condition; an empty name disables the list. The query is a single ``SELECT`` (or ``WITH ... SELECT``) that returns a column named ``username``. Use ``{table}`` for table names; ``:courseid`` is the course of the enrolment method.

Keep results for
   How long the usernames returned by a query are reused before it runs again (default 5 minutes; 0 runs it on every check).

When the settings are saved, each query is checked and run once. Queries with ``;``, comments, statements that change data, ``INTO`` or functions that wait or read server files are refused.

.. warning::
   The queries run with the database permissions of Moodle and can read any table, including passwords and personal data. The checks catch common mistakes, not a malicious administrator. Review each query as you would review code.

Example, the users with a given institution:

.. code-block:: text

   SELECT username FROM {user} WHERE institution = 'IFRN' AND deleted = 0
