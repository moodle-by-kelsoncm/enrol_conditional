Backup, restore and privacy
===========================

Backup and restore
------------------

* The conditions are part of the enrolment method and go with every course backup.
* Files of the *File of usernames* conditions go with the backup and are restored in the new course.
* When users are included, the open suspension records go too, so the restored course can still reactivate the enrolments the plugin suspended.
* Restored on another site, the *Cohort* and *Course completed* conditions lose their cohort or course: they point to nothing and are not met until a teacher edits them. On the same site, they are kept.
* A *List from a query* condition keeps the list number; on another site it uses that site's list with the same number, if any.
* A condition tree that is no longer valid is kept as it is and keeps blocking self enrolment until it is edited.

Privacy
-------

* ``enrol_conditional`` stores, for each suspension made by the plugin, the enrolment, the action, the reason and the dates. These records are exported and deleted with the course or the user, through the Privacy API.
* ``enrolcond_userfile`` finds the users listed in the files of usernames: their data request exports the file names, and their deletion removes their line from the files.
* The other condition types store no personal data.
