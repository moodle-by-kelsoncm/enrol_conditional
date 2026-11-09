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

namespace enrol_conditional;

use enrol_conditional\local\enforcer;

/**
 * Observers that keep enrol_conditional_state consistent.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Someone else changed an enrolment: the plugin no longer owns its suspension.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        global $DB;
        if (($event->other['enrol'] ?? '') !== 'conditional' || enforcer::is_acting()) {
            return;
        }
        $states = $DB->get_records_select(
            enforcer::TABLE,
            'userenrolmentid = :ueid AND action = :action AND timeclosed IS NULL',
            ['ueid' => $event->objectid, 'action' => enforcer::STATE_SUSPEND]
        );
        foreach ($states as $state) {
            enforcer::close($state, enforcer::CLOSE_EXTERNAL);
        }
    }

    /**
     * The enrolment no longer exists, so its records go too.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB;
        if (($event->other['enrol'] ?? '') !== 'conditional') {
            return;
        }
        $DB->delete_records(enforcer::TABLE, ['userenrolmentid' => $event->objectid]);
    }
}
