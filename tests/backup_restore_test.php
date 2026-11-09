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
use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of backup and restore of conditional instances.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_enrol_conditional_plugin::class)]
final class backup_restore_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Backs up a course with users and restores it as a new course.
     *
     * @param int $courseid
     * @param bool $othersite Whether to simulate a backup from another site.
     * @return int Id of the new course.
     */
    protected function backup_and_restore(int $courseid, bool $othersite = false): int {
        global $CFG, $USER;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $courseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'test-' . $courseid . '-' . random_string(6);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();

        if ($othersite) {
            set_config('siteidentifier', random_string(32) . 'other');
        }
        $newcourseid = \restore_dbops::create_new_course('Restored', 'R' . random_string(4), 1);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Creates a course with a suspending instance and a cohort condition, and a suspended and an active user.
     *
     * @return array [course, instance, suspended user, active user, cohort]
     */
    protected function create_course(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $cohort = $this->getDataGenerator()->create_cohort();
        $suspended = $this->getDataGenerator()->create_user();
        $active = $this->getDataGenerator()->create_user();
        $tree = json_encode(['op' => 'or', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $active->id]]],
            ['type' => 'cohort', 'config' => ['cohortid' => (int) $cohort->id]],
        ]]);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext2' => $tree,
            'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            'conditionreactivate' => 1,
            'checkmodelogin' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->plugin->enrol_user($instance, $suspended->id);
        $this->plugin->enrol_user($instance, $active->id);
        (new enforcer($this->plugin))->check_user($instance, $suspended->id);
        return [$course, $instance, $suspended, $active, $cohort];
    }

    public function test_same_site(): void {
        global $DB;
        [$course, $instance, $suspended, $active, $cohort] = $this->create_course();

        $newcourseid = $this->backup_and_restore((int) $course->id);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);

        $this->assertSame($instance->customtext2, $restored->customtext2);
        $this->assertEquals($instance->customint7, $restored->customint7);
        $this->assertEquals($instance->customint8, $restored->customint8);

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $restored->id, 'userid' => $suspended->id], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $states = $DB->get_records(enforcer::TABLE, ['instanceid' => $restored->id]);
        $this->assertCount(1, $states);
        $state = reset($states);
        $this->assertEquals($ue->id, $state->userenrolmentid);
        $this->assertTrue(enforcer::owns($state, $ue));

        // The restored suspension still belongs to the plugin, so it is reactivated.
        require_once($GLOBALS["CFG"]->dirroot . "/cohort/lib.php");
        cohort_add_member($cohort->id, $suspended->id);
        $outcome = (new enforcer($this->plugin))->check_user($restored, $suspended->id);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $outcome);
    }

    public function test_other_site(): void {
        global $DB;
        [$course, , $suspended, $active] = $this->create_course();

        $newcourseid = $this->backup_and_restore((int) $course->id, true);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);

        $tree = json_decode($restored->customtext2, true);
        $this->assertSame(['userids' => [(int) $active->id]], $tree['children'][0]['config']);
        $this->assertSame(['cohortid' => 0], $tree['children'][1]['config']);
    }

    public function test_without_users(): void {
        global $DB, $CFG, $USER;
        [$course] = $this->create_course();
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(false);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $backupid = 'nousers-' . random_string(6);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $backupid);
        $bc->destroy();
        $newcourseid = \restore_dbops::create_new_course('Restored', 'RN' . random_string(4), 1);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('enrolments')->set_value(\backup::ENROL_ALWAYS);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $this->assertNotEmpty($restored->customtext2);
        $this->assertSame(0, $DB->count_records(enforcer::TABLE, ['instanceid' => $restored->id]));
    }

    public function test_invalid_tree_is_kept(): void {
        global $DB;
        [$course, $instance] = $this->create_course();
        $DB->set_field('enrol', 'customtext2', '{"op":"xor"}', ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore((int) $course->id);
        $restored = $DB->get_record('enrol', ['courseid' => $newcourseid, 'enrol' => 'conditional'], '*', MUST_EXIST);
        $this->assertSame('{"op":"xor"}', $restored->customtext2);
    }
}
