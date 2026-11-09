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
 * A user enrolment was suspended because its conditions are no longer met.
 *
 * @property-read array $other {
 *      - int enrolid: enrolment instance id.
 *      - int stateid: id in enrol_conditional_state.
 * }
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_enrolment_suspended extends \core\event\base {
    /**
     * Creates the event.
     *
     * @param \stdClass $instance Enrolment instance.
     * @param \stdClass $ue User enrolment.
     * @param int $stateid Id of the state record.
     * @return self
     */
    public static function create_from_state(\stdClass $instance, \stdClass $ue, int $stateid): self {
        return self::create([
            'objectid' => $ue->id,
            'courseid' => $instance->courseid,
            'context' => \context_course::instance($instance->courseid),
            'relateduserid' => $ue->userid,
            'other' => ['enrolid' => (int) $instance->id, 'stateid' => $stateid],
        ]);
    }

    #[\Override]
    protected function init() {
        $this->data['objecttable'] = 'user_enrolments';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventuserenrolmentsuspended', 'enrol_conditional');
    }

    #[\Override]
    public function get_description() {
        return "The enrolment with id '{$this->objectid}' of the user with id '{$this->relateduserid}' in the course " .
            "with id '{$this->courseid}' was suspended because its conditions are no longer met.";
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
        if (!isset($this->other['enrolid']) || !isset($this->other['stateid'])) {
            throw new \coding_exception('The \'enrolid\' and \'stateid\' values must be set in other.');
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
            'stateid' => \core\event\base::NOT_MAPPED,
        ];
    }
}
