Installation
============

Requirements: Moodle 5.3 LTS and `local_enrolconditions <https://github.com/moodle-by-kelsoncm/local_enrolconditions>`_, which provides the conditions.

#. Copy ``local_enrolconditions`` to ``public/local/enrolconditions``.
#. Copy this plugin to ``public/enrol/conditional``.
#. Visit *Site administration > Notifications* and finish the installation.
#. Enable the method in *Site administration > Plugins > Enrolments > Manage enrol plugins*.

New courses do not get an instance of this method by default. Add it in each course under *Participants > Enrolment methods*, or turn on *Add instance to new courses* in the plugin settings.
