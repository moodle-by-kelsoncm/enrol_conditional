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
    /** @var bool[] Users being re-checked by any_event(), to avoid loops. */
    protected static array $running = [];

    /**
     * Re-checks the enrolments of the user of an event observed by the conditions (CHECK_EVENTS).
     *
     * Observes every event, so it returns early unless the event is in the cached map.
     *
     * @param \core\event\base $event
     */
    public static function any_event(\core\event\base $event): void {
        global $CFG;
        if (
            !empty($CFG->upgraderunning) || during_initial_install() || enforcer::is_acting()
                || !enrol_is_enabled('conditional')
        ) {
            return;
        }
        $map = enforcer::get_event_map();
        $instanceids = $map[ltrim($event->eventname, '\\')] ?? [];
        if (!$instanceids) {
            return;
        }
        $userid = (int) ($event->relateduserid ?: $event->userid);
        if ($userid <= 0 || isguestuser($userid) || !empty(self::$running[$userid])) {
            return;
        }
        self::$running[$userid] = true;
        try {
            (new enforcer())->check_user_in_instances($userid, $instanceids);
        } finally {
            unset(self::$running[$userid]);
        }
    }

    /** @var int[] Open suspensions (state id by user enrolment id) that stay owned after the update in progress. */
    protected static array $keep = [];

    /**
     * Open suspensions of a user enrolment.
     *
     * @param int $ueid
     * @return \stdClass[]
     */
    protected static function open_suspensions(int $ueid): array {
        global $DB;
        return $DB->get_records_select(
            enforcer::TABLE,
            'userenrolmentid = :ueid AND action = :action AND timeclosed IS NULL',
            ['ueid' => $ueid, 'action' => enforcer::STATE_SUSPEND]
        );
    }

    /**
     * Someone else is about to change an enrolment suspended by the plugin.
     *
     * The record still has the previous timemodified here. The plugin keeps the suspension only when it still
     * owned it and the enrolment stays suspended (for example, only its dates change).
     *
     * @param \core_enrol\hook\before_user_enrolment_updated $hook
     */
    public static function before_user_enrolment_updated(\core_enrol\hook\before_user_enrolment_updated $hook): void {
        $ue = $hook->userenrolmentinstance;
        if ($hook->enrolinstance->enrol !== 'conditional' || enforcer::is_acting()) {
            return;
        }
        unset(self::$keep[(int) $ue->id]);
        foreach (self::open_suspensions((int) $ue->id) as $state) {
            $stillowned = (int) $ue->status === ENROL_USER_SUSPENDED
                && (int) $ue->timemodified === (int) $state->uetimemodified;
            if ($stillowned) {
                self::$keep[(int) $ue->id] = (int) $state->id;
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
        }
    }

    /**
     * Someone else changed an enrolment suspended by the plugin.
     *
     * Suspensions kept by before_user_enrolment_updated() get the new timemodified. Any other open
     * suspension is no longer owned.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        global $DB;
        if (($event->other['enrol'] ?? '') !== 'conditional' || enforcer::is_acting()) {
            return;
        }
        $ueid = (int) $event->objectid;
        $keep = self::$keep[$ueid] ?? null;
        unset(self::$keep[$ueid]);
        $ue = $DB->get_record('user_enrolments', ['id' => $ueid]);
        foreach (self::open_suspensions($ueid) as $state) {
            if ($ue && enforcer::owns($state, $ue)) {
                // Unchanged since the plugin acted: events triggered inside other observers are delivered
                // later, so this may be the plugin's own suspension.
                continue;
            }
            if ($ue && (int) $state->id === $keep && (int) $ue->status === ENROL_USER_SUSPENDED) {
                $DB->set_field(enforcer::TABLE, 'uetimemodified', $ue->timemodified, ['id' => $state->id]);
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
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
