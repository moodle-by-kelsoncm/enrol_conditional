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
 * A bulk operation for the manual enrolment plugin to edit selected users.
 *
 * @package enrol_conditional
 * @copyright 2018 Farhan Karmali
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A bulk operation for the manual enrolment plugin to edit selected users.
 *
 * @package enrol_conditional
 * @copyright 2018 Farhan Karmali
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_conditional_editselectedusers_operation extends enrol_bulk_enrolment_operation {
    /**
     * Returns the title to display for this bulk operation.
     *
     * @return string
     */
    public function get_title() {
        return get_string('editselectedusers', 'enrol_conditional');
    }

    /**
     * Returns the identifier for this bulk operation. This is the key used when the plugin
     * returns an array containing all of the bulk operations it supports.
     */
    public function get_identifier() {
        return 'editselectedusers';
    }

    /**
     * Processes the bulk operation request for the given userids with the provided properties.
     *
     * @param course_enrolment_manager $manager
     * @param array $users
     * @param stdClass $properties The data returned by the form.
     */
    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        if (!has_capability("enrol/conditional:manage", $manager->get_context())) {
            return false;
        }

        // Get all of the user enrolments.
        $instances = [];
        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                if (!array_key_exists($enrolment->id, $instances)) {
                    $instances[$enrolment->id] = $enrolment;
                }
            }
        }

        // Check that each instance is manageable by the current user.
        foreach ($instances as $instance) {
            if (!$this->plugin->allow_manage($instance)) {
                return false;
            }
        }

        // Collect the known properties; null keeps the stored value.
        $status = null;
        if ($properties->status == ENROL_USER_ACTIVE || $properties->status == ENROL_USER_SUSPENDED) {
            $status = (int)$properties->status;
        }
        $timestart = empty($properties->timestart) ? null : (int)$properties->timestart;
        $timeend = empty($properties->timeend) ? null : (int)$properties->timeend;
        if ($status === null && $timestart === null && $timeend === null) {
            return true;
        }

        // The enrolment API updates each record, sets modifierid and timemodified and triggers the event.
        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                $this->plugin->update_user_enrol($enrolment->enrolmentinstance, $user->id, $status, $timestart, $timeend);
            }
        }

        // Delete cached course contacts for this course because they may be affected.
        cache::make('core', 'coursecontacts')->delete($manager->get_context()->instanceid);
        return true;
    }

    /**
     * Returns a enrol_bulk_enrolment_operation extension form to be used
     * in collecting required information for this operation to be processed.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_conditional_editselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        return new enrol_conditional_editselectedusers_form($defaultaction, $defaultcustomdata);
    }
}
