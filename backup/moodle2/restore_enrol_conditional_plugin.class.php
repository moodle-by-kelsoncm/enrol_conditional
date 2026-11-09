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
 * Restore of enrol_conditional data.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_conditional\local\enforcer;

/**
 * Restores the open records of enrol_conditional_state.
 *
 * A record is restored only when the restored enrolment still matches it (suspended, for a suspension) and has
 * no open record yet. Its uetimemodified takes the restored enrolment, so the plugin keeps owning the suspension.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_enrol_conditional_plugin extends restore_enrol_plugin {
    /**
     * Paths of the structure appended to the enrol element.
     *
     * @return restore_path_element[]
     */
    protected function define_enrol_plugin_structure() {
        return [
            new restore_path_element('enrol_conditional_state', $this->connectionpoint->get_path() . '/states/state'),
        ];
    }

    /**
     * Restores the files of usernames, keeping their item ids, which the condition trees refer to.
     */
    public function after_execute_enrol() {
        $this->add_related_files('enrolcond_userfile', 'usernames', null);
    }

    /**
     * Restores one state record. User enrolments come before it in the backup.
     *
     * @param array|stdClass $data
     */
    public function process_enrol_conditional_state($data) {
        global $DB;
        $data = (object) $data;
        $enrolid = $this->get_new_parentid('enrol');
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$enrolid || !$userid) {
            return;
        }
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrolid, 'userid' => $userid]);
        if (!$ue) {
            return;
        }
        if ($data->action === enforcer::STATE_SUSPEND && (int) $ue->status !== ENROL_USER_SUSPENDED) {
            return;
        }
        if (!in_array($data->action, [enforcer::STATE_SUSPEND, enforcer::STATE_NONE], true)) {
            return;
        }
        if ($DB->record_exists_select(enforcer::TABLE, 'userenrolmentid = ? AND timeclosed IS NULL', [$ue->id])) {
            return;
        }
        $DB->insert_record(enforcer::TABLE, (object) [
            'userenrolmentid' => $ue->id,
            'instanceid' => $enrolid,
            'action' => $data->action,
            'reason' => $data->reason,
            'timesuspended' => (int) $data->timesuspended,
            'uetimemodified' => (int) $ue->timemodified,
            'timereactivated' => null,
            'timeclosed' => null,
            'closereason' => null,
        ]);
    }
}
