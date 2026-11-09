Usage
=====

Adding the method to a course
-----------------------------

#. In the course, go to *Participants > Enrolment methods* and add *Conditional self enrolment*.
#. Fill in the usual self enrolment settings (enrolment key, period, maximum users...).
#. Under *Conditions*, build the conditions (see below).
#. Choose when the conditions are checked again and what happens when an enrolled user no longer meets them (see :doc:`rechecks`).
#. Save. Users who meet the conditions see the *Enrol me* button on the course page.

The condition editor
--------------------

The conditions form a tree. The root is a group:

* *Add condition* opens a form for the chosen *Condition type*.
* *Add group* adds a group inside the current one, up to the maximum depth.
* *How to combine the items of this group*: *All of these* (AND) or *Any of these* (OR).
* *Must not be met* inverts a condition or a group (NOT).
* *Edit* and *Remove* change or delete an item.

Without conditions, anybody who can self enrol in the course may enrol, as in ``enrol_self``. The editor needs JavaScript; without it, the saved conditions are kept unchanged.

What users see
--------------

A user who meets the conditions enrols as with ``enrol_self``. A user who does not meet them sees *You cannot enrol yourself in this course because:* followed by the conditions not met, for example "You have completed the course Introduction" or "You must meet at least one of the following:".

When a condition cannot be checked (a disabled condition type, a missing file, a failing query), its result is *unknown*. Unknown results block new self enrolments (*A condition can no longer be checked. Contact the course teacher.*) but never suspend, remove or reactivate an existing enrolment.

Web service
-----------

The self enrolment web service of the method (``enrol_conditional_enrol_user``) checks the same conditions.
