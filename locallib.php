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
 * Self enrol plugin implementation.
 *
 * @package    enrol_conditional
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Check if the given password match a group enrolment key in the specified course.
 *
 * @param  int $courseid            course id
 * @param  string $enrolpassword    enrolment password
 * @return bool                     True if match
 * @since  Moodle 3.0
 */
function enrol_conditional_check_group_enrolment_key($courseid, $enrolpassword) {
    global $DB;

    $found = false;
    $groups = $DB->get_records('groups', ['courseid' => $courseid], 'id ASC', 'id, enrolmentkey');

    foreach ($groups as $group) {
        if (empty($group->enrolmentkey)) {
            continue;
        }
        if ($group->enrolmentkey === $enrolpassword) {
            $found = true;
            break;
        }
    }
    return $found;
}
