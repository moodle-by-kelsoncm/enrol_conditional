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

namespace enrol_conditional;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Tests for the bulk operation that edits the selected user enrolments.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson da Costa Medeiros <kelsoncm@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\enrol_conditional_editselectedusers_operation::class)]
final class editselectedusers_operation_test extends \advanced_testcase {
    /** @var \stdClass Course of the tests. */
    private \stdClass $course;

    /** @var \stdClass Enrolment instance of this method in the course. */
    private \stdClass $instance;

    /** @var \enrol_conditional_plugin The enrolment plugin. */
    private \enrol_conditional_plugin $plugin;

    /** @var \course_enrolment_manager Enrolment manager of the course. */
    private \course_enrolment_manager $manager;

    /**
     * Creates a course with an enabled instance of this method.
     */
    protected function setUp(): void {
        global $DB, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        $this->plugin = enrol_get_plugin('conditional');
        $this->course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($this->course, ['status' => ENROL_INSTANCE_ENABLED]);
        $this->instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->manager = new \course_enrolment_manager($PAGE, $this->course);
    }

    /**
     * Enrols users and returns them in the shape the bulk operations receive.
     *
     * @param int $count Number of users.
     * @return array Users with their enrolments of this instance.
     */
    private function enrol_users(int $count): array {
        global $DB;
        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($this->instance, $user->id, null, 100, 200);
            $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $user->id]);
            $enrolment->enrolmentinstance = $this->instance;
            $user->enrolments = [$enrolment->id => $enrolment];
            $users[$user->id] = $user;
        }
        return $users;
    }

    /**
     * Returns the operation for the course.
     *
     * @return \enrol_conditional_editselectedusers_operation
     */
    private function get_operation(): \enrol_conditional_editselectedusers_operation {
        return new \enrol_conditional_editselectedusers_operation($this->manager, $this->plugin);
    }

    /**
     * The selected enrolments get the new values and one event each.
     */
    public function test_process_updates_enrolments(): void {
        global $DB, $USER;
        $users = $this->enrol_users(2);
        $properties = (object)['status' => ENROL_USER_SUSPENDED, 'timestart' => 0, 'timeend' => 5000];

        $sink = $this->redirectEvents();
        $this->assertTrue($this->get_operation()->process($this->manager, $users, $properties));
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \core\event\user_enrolment_updated);
        $sink->close();

        $this->assertCount(2, $events);
        foreach ($users as $user) {
            $record = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $user->id]);
            $this->assertEquals(ENROL_USER_SUSPENDED, $record->status);
            $this->assertEquals(100, $record->timestart);
            $this->assertEquals(5000, $record->timeend);
            $this->assertEquals($USER->id, $record->modifierid);
        }
    }

    /**
     * Without any value to change, nothing is stored and no event is triggered.
     */
    public function test_process_without_changes(): void {
        global $DB;
        $users = $this->enrol_users(1);
        $properties = (object)['status' => -1, 'timestart' => 0, 'timeend' => 0];

        $sink = $this->redirectEvents();
        $this->assertTrue($this->get_operation()->process($this->manager, $users, $properties));
        $this->assertCount(0, $sink->get_events());
        $sink->close();

        $user = reset($users);
        $record = $DB->get_record('user_enrolments', ['enrolid' => $this->instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $record->status);
        $this->assertEquals(200, $record->timeend);
    }
}
