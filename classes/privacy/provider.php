<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace enrol_conditional\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_conditional\local\enforcer;

/**
 * Privacy provider: records of the actions taken when the conditions of an enrolment stopped being met.
 *
 * The records belong to the user of the enrolment and live in the course context.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(enforcer::TABLE, [
            'userenrolmentid' => 'privacy:metadata:state:userenrolmentid',
            'action' => 'privacy:metadata:state:action',
            'reason' => 'privacy:metadata:state:reason',
            'timesuspended' => 'privacy:metadata:state:timesuspended',
            'timereactivated' => 'privacy:metadata:state:timereactivated',
            'timeclosed' => 'privacy:metadata:state:timeclosed',
            'closereason' => 'privacy:metadata:state:closereason',
        ], 'privacy:metadata:state');
        return $collection;
    }

    /**
     * SQL that joins the state records to their enrolment, instance and course context.
     *
     * @return string
     */
    protected static function from_sql(): string {
        return "FROM {" . enforcer::TABLE . "} s
                JOIN {user_enrolments} ue ON ue.id = s.userenrolmentid
                JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = :enrolname
                JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel";
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT DISTINCT ctx.id " . self::from_sql() . " WHERE ue.userid = :userid",
            ['enrolname' => 'conditional', 'contextlevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT ue.userid " . self::from_sql() . " WHERE ctx.id = :contextid",
            ['enrolname' => 'conditional', 'contextlevel' => CONTEXT_COURSE, 'contextid' => $context->id]
        );
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $records = $DB->get_records_sql(
                "SELECT s.* " . self::from_sql() . " WHERE ue.userid = :userid AND ctx.id = :contextid ORDER BY s.id",
                ['enrolname' => 'conditional', 'contextlevel' => CONTEXT_COURSE, 'userid' => $userid,
                    'contextid' => $context->id]
            );
            if (!$records) {
                continue;
            }
            $data = [];
            foreach ($records as $record) {
                $data[] = (object) [
                    'action' => $record->action,
                    'reason' => $record->reason,
                    'timesuspended' => transform::datetime($record->timesuspended),
                    'timereactivated' => $record->timereactivated ? transform::datetime($record->timereactivated) : null,
                    'timeclosed' => $record->timeclosed ? transform::datetime($record->timeclosed) : null,
                    'closereason' => $record->closereason,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'enrol_conditional'), get_string('privacy:path:state', 'enrol_conditional')],
                (object) ['records' => $data]
            );
        }
    }

    /**
     * Deletes the state records selected by a WHERE clause over the from_sql() joins.
     *
     * @param string $where
     * @param array $params
     */
    protected static function delete_where(string $where, array $params): void {
        global $DB;
        $ids = $DB->get_fieldset_sql(
            "SELECT s.id " . self::from_sql() . " WHERE $where",
            $params + ['enrolname' => 'conditional', 'contextlevel' => CONTEXT_COURSE]
        );
        foreach (array_chunk($ids, 1000) as $chunk) {
            $DB->delete_records_list(enforcer::TABLE, 'id', $chunk);
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_course) {
            self::delete_where('ctx.id = :contextid', ['contextid' => $context->id]);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                self::delete_where('ctx.id = :contextid AND ue.userid = :userid', [
                    'contextid' => $context->id,
                    'userid' => $userid,
                ]);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        self::delete_where("ctx.id = :contextid AND ue.userid $insql", $params + ['contextid' => $context->id]);
    }
}
