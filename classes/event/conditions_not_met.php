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

namespace enrol_conditional\event;

/**
 * The conditions of a user enrolment stopped being met.
 *
 * Triggered once per episode when the action is "none", and before the removal when the action is "remove".
 * Suspensions trigger user_enrolment_suspended instead.
 *
 * @property-read array $other {
 *      - int enrolid: enrolment instance id.
 *      - string action: none or remove.
 *      - int stateid: id in enrol_conditional_state, 0 when there is none.
 * }
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conditions_not_met extends \core\event\base {
    /**
     * Creates the event.
     *
     * @param \stdClass $instance Enrolment instance.
     * @param \stdClass $ue User enrolment.
     * @param string $action none or remove.
     * @param int $stateid Id of the state record, 0 when there is none.
     * @return self
     */
    public static function create_from_enrolment(\stdClass $instance, \stdClass $ue, string $action, int $stateid = 0): self {
        return self::create([
            'objectid' => $ue->id,
            'courseid' => $instance->courseid,
            'context' => \context_course::instance($instance->courseid),
            'relateduserid' => $ue->userid,
            'other' => ['enrolid' => (int) $instance->id, 'action' => $action, 'stateid' => $stateid],
        ]);
    }

    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'user_enrolments';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventconditionsnotmet', 'enrol_conditional');
    }

    #[\Override]
    public function get_description() {
        return "The conditions of the enrolment with id '{$this->objectid}' of the user with id '{$this->relateduserid}' " .
            "in the course with id '{$this->courseid}' are no longer met. Action: '{$this->other['action']}'.";
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/user/index.php', ['id' => $this->courseid]);
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['enrolid']) || !isset($this->other['action']) || !isset($this->other['stateid'])) {
            throw new \coding_exception('The \'enrolid\', \'action\' and \'stateid\' values must be set in other.');
        }
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'user_enrolments', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    public static function get_other_mapping() {
        return [
            'enrolid' => ['db' => 'enrol', 'restore' => 'enrol'],
            'action' => \core\event\base::NOT_MAPPED,
            'stateid' => \core\event\base::NOT_MAPPED,
        ];
    }
}
