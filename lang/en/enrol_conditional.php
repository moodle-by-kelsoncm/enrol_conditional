<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'enrol_conditional', language 'en'.
 *
 * @package    enrol_conditional
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cannotenrolreason'] = 'You cannot enrol yourself in this course: {$a}';
$string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['canntenrolearly'] = 'You cannot enrol yet; enrolment starts on {$a}.';
$string['canntenrollate'] = 'You cannot enrol any more, since enrolment ended on {$a}.';
$string['cachedef_observedevents'] = 'Events observed by the conditions of the instances that re-check on events';
$string['checkbatchsize'] = 'Enrolments per periodic check';
$string['checkbatchsize_desc'] = 'Maximum number of enrolments re-checked by each run of the scheduled task. The next run continues where the previous one stopped.';
$string['checkconditionstask'] = 'Re-check the conditions of conditional self enrolments';
$string['checkmodeenrol'] = 'When the user enrols (always)';
$string['checkmodeevents'] = 'When something the conditions depend on changes';
$string['checkmodeevents_desc'] = 'Re-check by default when an event observed by the conditions happens.';
$string['checkmodelogin'] = 'When the user logs in';
$string['checkmodelogin_desc'] = 'Re-check by default the enrolments of users when they log in.';
$string['checkmodeperiodic'] = 'Periodically, in the scheduled task';
$string['checkmodeperiodic_desc'] = 'Re-check by default the enrolments in the scheduled task.';
$string['checkmodes'] = 'Check the conditions';
$string['checkmodes_help'] = 'The conditions are always checked when a user enrols. Existing enrolments can also be re-checked:

* When the user logs in.
* Periodically, by the scheduled task, in batches.
* When something the conditions depend on changes, for example the user profile or a cohort membership. Each condition type declares the events it depends on.

When a re-check finds that the conditions are no longer met, the action below is applied.';
$string['conditionaction'] = 'When the conditions are no longer met';
$string['conditionaction_desc'] = 'Default action when a re-check finds that an enrolled user no longer meets the conditions.';
$string['conditionaction_help'] = 'What happens to an existing enrolment when a re-check finds that the user no longer meets the conditions.

* Nothing: the plugin only records it in the logs, once until the conditions are met again.
* Suspend: the enrolment is suspended.
* Remove: the user is unenrolled. Some user data in the course may be lost.

When the plugin cannot evaluate a condition (for example, its type was uninstalled), it never changes the enrolment.

Changing this setting does not undo what was already done: enrolments suspended by the plugin stay suspended until the user meets the conditions again and reactivation is enabled.';
$string['conditionactionnone'] = 'Nothing (only record it)';
$string['conditionactionremove'] = 'Remove the enrolment';
$string['conditionactionsuspend'] = 'Suspend the enrolment';
$string['conditional:config'] = 'Configure self enrol instances';
$string['conditional:enrolself'] = 'Self enrol in course';
$string['conditional:holdkey'] = 'Appear as the self enrolment key holder';
$string['conditional:manage'] = 'Manage enrolled users';
$string['conditional:unenrol'] = 'Unenrol users from course';
$string['conditional:unenrolself'] = 'Unenrol self from the course';
$string['conditionreactivate'] = 'Reactivate when the conditions are met again';
$string['conditionreactivate_desc'] = 'Reactivate by default the enrolments suspended by this plugin when the conditions are met again.';
$string['conditionreactivate_help'] = 'If enabled, enrolments that this plugin suspended are reactivated when the user meets the conditions again. Removing all the conditions counts as meeting them.

Suspensions made by other people or processes are never reactivated. If someone else reactivates an enrolment that the plugin suspended, or changes it directly in the database, the plugin no longer reactivates it. Changing only its dates, while it stays suspended, keeps it.';
$string['conditions'] = 'Conditions';
$string['conditions_help'] = 'Users can only self enrol when they meet these conditions. Combine conditions in groups that require all or any of their items, and mark a condition or group as "Must not be met" to invert it. Without conditions, anybody who can self enrol is accepted.';
$string['conditionsnotmet'] = 'You cannot enrol yourself in this course because:';
$string['conditionsnotmetshort'] = 'You do not meet the conditions to enrol yourself in this course.';
$string['confirmbulkdeleteenrolment'] = 'Are you sure you want to delete these user enrolments?';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during self enrolment';
$string['deleteselectedusers'] = 'Delete selected user enrolments';
$string['editselectedusers'] = 'Edit selected user enrolments';
$string['enroledas'] = '{$a->enrol} as \'{$a->role}\'';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can enrol themselves until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolkeyrequired'] = 'An enrolment key will be required.';
$string['enrolme'] = 'Enrol me';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user enrols themselves. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can enrol themselves from this date onward only.';
$string['eventconditionsnotmet'] = 'Enrolment conditions no longer met';
$string['eventuserenrolmentreactivated'] = 'User enrolment reactivated by conditions';
$string['eventuserenrolmentsuspended'] = 'User enrolment suspended by conditions';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['expiryinactivemessageenrolledbody'] = 'Hi {$a->user},

Your enrolment in the course {$a->course} expires on {$a->timeend} as you have not accessed it in the last {$a->inactivetime} days.

To keep your enrolment active, log in and access <a href="{$a->url}">{$a->course}</a> before {$a->timeend}.';
$string['expiryinactivemessageenrolledsubject'] = 'Your enrolment is expiring: {$a->course}';
$string['expirymessageenrolledbody'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->course}\' is due to expire on {$a->timeend}.

If you need help, please contact {$a->enroller}.';
$string['expirymessageenrolledsubject'] = 'Self enrolment expiry notification';
$string['expirymessageenrollerbody'] = 'Self enrolment in the course \'{$a->course}\' will expire within the next {$a->threshold} for the following users:

{$a->users}

To extend their enrolment, go to {$a->extendurl}';
$string['expirymessageenrollersubject'] = 'Self enrolment expiry notification';
$string['expirynotifyall'] = 'Teacher and enrolled user';
$string['expirynotifyenroller'] = 'Teacher only';
$string['groupkey'] = 'Use group enrolment keys';
$string['groupkey_desc'] = 'Use group enrolment keys by default.';
$string['groupkey_help'] = 'In addition to restricting access to the course to only those who know the key, use of group enrolment keys means users are automatically added to groups when they enrol in the course.

Note: An enrolment key for the course must be specified in the self enrolment settings as well as group enrolment keys in the group settings.';
$string['keyholder'] = 'You should have received this enrolment key from:';
$string['longtimenosee'] = 'Unenrol inactive after';
$string['longtimenosee_help'] = 'If users haven\'t accessed a course for a long time, then they are automatically unenrolled. This parameter specifies that time limit.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can self enrol. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to self-enrol was already reached.';
$string['messageprovider:expiry_notification'] = 'Self enrolment expiry notifications';
$string['newenrols'] = 'Allow new self enrolments';
$string['newenrols_desc'] = 'Allow users to self enrol into new courses by default.';
$string['newenrols_help'] = 'This setting determines whether a user can enrol into this course.';
$string['nopassword'] = 'No enrolment key required.';
$string['password'] = 'Enrolment key';
$string['password_help'] = 'An enrolment key enables access to the course to be restricted to only those who know the key.

If the field is left blank, any user may enrol in the course.

If an enrolment key is specified, any user attempting to enrol in the course will be required to supply the key. Note that a user only needs to supply the enrolment key ONCE, when they enrol in the course.';
$string['passwordinvalid'] = 'Incorrect enrolment key, please try again';
$string['passwordinvalidhint'] = 'That enrolment key was incorrect, please try again<br />
(Here\'s a hint - it starts with \'{$a}\')';
$string['passwordmatchesgroupkey'] = 'This enrolment key is already used as a group enrolment key.';
$string['pluginname'] = 'Conditional self enrolment';
$string['pluginname_desc'] = 'The conditional self enrolment plugin allows users to choose which courses they want to participate in, as long as they meet the conditions configured for each enrolment method. The courses may be protected by an enrolment key. Internally the enrolment is done via the manual enrolment plugin which has to be enabled in the same course.';
$string['privacy:metadata'] = 'The Self enrolment plugin does not store any personal data.';
$string['requirepassword'] = 'Require enrolment key';
$string['requirepassword_desc'] = 'Require enrolment key in new courses and prevent removing of enrolment key from existing courses.';
$string['role'] = 'Default assigned role';
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = 'When a user self enrols in the course, they may be sent a welcome message email. If sent from the course contact (by default the teacher), and more than one user has this role, the email is sent from the first user to be assigned the role.';
$string['sendexpirynotificationstask'] = "Self enrolment send expiry notifications task";
$string['showhint'] = 'Show hint';
$string['showhint_desc'] = 'Show first letter of the guest access key.';
$string['status'] = 'Keep current self enrolments active';
$string['status_desc'] = 'Enable self enrolment method in new courses.';
$string['status_help'] = 'If set to No, any existing participants who enrolled themselves into the course will no longer have access.';
$string['syncenrolmentstask'] = 'Synchronise self enrolments task';
$string['unenrol'] = 'Unenrol user';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unenroluser'] = 'Do you really want to unenrol "{$a->user}" from course "{$a->course}"?';
$string['unenrolusers'] = 'Unenrol users';
$string['usepasswordpolicy'] = 'Use password policy';
$string['usepasswordpolicy_desc'] = 'Use standard password policy for enrolment keys.';
