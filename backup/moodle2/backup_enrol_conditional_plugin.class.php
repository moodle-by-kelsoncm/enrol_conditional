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

/**
 * Backup of enrol_conditional data.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds the open records of enrol_conditional_state to the enrolment instance, when users are included.
 *
 * Only open records matter: they are what allows the plugin to reactivate the enrolments it suspended.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_enrol_conditional_plugin extends backup_enrol_plugin {
    /**
     * Defines the structure appended to the enrol element.
     *
     * @return backup_plugin_element
     */
    protected function define_enrol_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $states = new backup_nested_element('states');
        $state = new backup_nested_element('state', ['id'], ['userid', 'action', 'reason', 'timesuspended']);
        $plugin->add_child($states);
        $states->add_child($state);

        if ($this->task->get_setting_value('users')) {
            $state->set_source_sql(
                "SELECT s.id, ue.userid, s.action, s.reason, s.timesuspended
                   FROM {enrol_conditional_state} s
                   JOIN {user_enrolments} ue ON ue.id = s.userenrolmentid
                  WHERE s.instanceid = ? AND s.timeclosed IS NULL",
                [backup::VAR_PARENTID]
            );
            $state->annotate_ids('user', 'userid');
        }
        return $plugin;
    }
}
