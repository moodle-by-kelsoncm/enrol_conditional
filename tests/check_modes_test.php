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
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of when existing enrolments are re-checked: login, scheduled task and events.
 *
 * The fake condition is met when the user id is in config['userids'] and observes user_updated.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enforcer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(task\check_conditions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class check_modes_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * Creates an instance that suspends, in a new course.
     *
     * @param array $modes Form fields checkmode* set to 1.
     * @param int[] $allowed Users that meet the conditions.
     * @return \stdClass
     */
    protected function create_instance(array $modes, array $allowed = []): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $fields = [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'customtext2' => $this->tree($allowed),
            'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            'conditionreactivate' => 1,
        ];
        foreach ($modes as $mode) {
            $fields[$mode] = 1;
        }
        $id = $this->plugin->add_instance($course, $fields);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Tree that accepts the given users.
     *
     * @param int[] $allowed
     * @return string
     */
    protected function tree(array $allowed): string {
        return json_encode(['op' => 'and', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => array_map('intval', $allowed)]],
        ]]);
    }

    /**
     * Status of the enrolment of a user.
     *
     * @param \stdClass $instance
     * @param int $userid
     * @return int
     */
    protected function ue_status(\stdClass $instance, int $userid): int {
        global $DB;
        return (int) $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $userid], MUST_EXIST);
    }

    public function test_check_modes_fields(): void {
        global $DB;
        $instance = $this->create_instance(['checkmodelogin']);
        $this->assertEquals(
            enrol_conditional_plugin::CHECK_ENROL | enrol_conditional_plugin::CHECK_LOGIN,
            $instance->{enrol_conditional_plugin::FIELD_CHECKMODES}
        );

        $this->plugin->update_instance($instance, (object) [
            'checkmodelogin' => 0,
            'checkmodeperiodic' => 1,
            'expirynotify' => 0,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->assertFalse(enrol_conditional_plugin::has_check_mode($instance, enrol_conditional_plugin::CHECK_LOGIN));
        $this->assertTrue(enrol_conditional_plugin::has_check_mode($instance, enrol_conditional_plugin::CHECK_PERIODIC));
        $this->assertTrue(enrol_conditional_plugin::has_check_mode($instance, enrol_conditional_plugin::CHECK_ENROL));

        // Site defaults.
        set_config('checkmodeevents', 1, 'enrol_conditional');
        $this->assertSame(
            enrol_conditional_plugin::CHECK_ENROL | enrol_conditional_plugin::CHECK_EVENTS,
            enrol_conditional_plugin::get_check_modes(null)
        );
        $defaults = $this->plugin->get_instance_defaults();
        $this->assertSame(
            enrol_conditional_plugin::CHECK_ENROL | enrol_conditional_plugin::CHECK_EVENTS,
            $defaults[enrol_conditional_plugin::FIELD_CHECKMODES]
        );
    }

    public function test_login_checks_only_flagged_instances(): void {
        $user = $this->getDataGenerator()->create_user();
        $withlogin = $this->create_instance(['checkmodelogin']);
        $without = $this->create_instance(['checkmodeperiodic']);
        $this->plugin->enrol_user($withlogin, $user->id);
        $this->plugin->enrol_user($without, $user->id);
        $manualcourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $manualcourse->id);

        $this->plugin->sync_user_enrolments($user);

        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($withlogin, $user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($without, $user->id));
        $this->assertTrue(is_enrolled(\context_course::instance($manualcourse->id), $user, '', true));
    }

    public function test_login_through_core(): void {
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['checkmodelogin']);
        $this->plugin->enrol_user($instance, $user->id);

        enrol_check_plugins($user);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance, $user->id));
    }

    public function test_login_ignores_guest_and_disabled_instances(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['checkmodelogin']);
        $this->plugin->enrol_user($instance, $user->id);
        $this->plugin->update_status($instance, ENROL_INSTANCE_DISABLED);

        $this->plugin->sync_user_enrolments($user);
        $this->plugin->sync_user_enrolments(guest_user());
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));
        $this->assertSame(0, $DB->count_records(enforcer::TABLE));
    }

    public function test_periodic_batches(): void {
        $flagged = $this->create_instance(['checkmodeperiodic']);
        $other = $this->create_instance(['checkmodelogin']);
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
            $this->plugin->enrol_user($flagged, $users[$i]->id);
        }
        $this->plugin->enrol_user($other, $users[0]->id);
        $enforcer = new enforcer($this->plugin);

        $first = $enforcer->check_batch(2);
        $this->assertCount(2, $first);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, reset($first));
        $this->assertSame((int) array_key_last($first), (int) get_config('enrol_conditional', 'periodiccursor'));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($flagged, $users[2]->id));

        $second = $enforcer->check_batch(2);
        $this->assertCount(1, $second);
        $this->assertSame(0, (int) get_config('enrol_conditional', 'periodiccursor'));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($flagged, $users[2]->id));

        // The instance without the periodic mode is never touched; the cycle starts again.
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($other, $users[0]->id));
        $third = $enforcer->check_batch(10);
        $this->assertCount(3, $third);
        $this->assertSame([enforcer::OUTCOME_NOTHING], array_values(array_unique($third)));
    }

    public function test_periodic_task(): void {
        $instance = $this->create_instance(['checkmodeperiodic']);
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id);
        set_config('checkbatchsize', 10, 'enrol_conditional');

        $task = new task\check_conditions();
        $this->assertNotEmpty($task->get_name());
        $this->expectOutputRegex('/1 enrolments checked/');
        $task->execute();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance, $user->id));
    }

    public function test_periodic_task_with_plugin_disabled(): void {
        $instance = $this->create_instance(['checkmodeperiodic']);
        $user = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($instance, $user->id);
        \core\plugininfo\enrol::enable_plugin('conditional', false);

        $this->expectOutputRegex('/disabled/');
        (new task\check_conditions())->execute();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));
    }

    public function test_events_recheck_flagged_instances(): void {
        $user = $this->getDataGenerator()->create_user();
        $withevents = $this->create_instance(['checkmodeevents']);
        $without = $this->create_instance(['checkmodelogin']);
        $this->plugin->enrol_user($withevents, $user->id);
        $this->plugin->enrol_user($without, $user->id);

        $this->assertSame([(int) $withevents->id], enforcer::get_event_map()['core\event\user_updated']);

        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($withevents, $user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($without, $user->id));

        // Events not observed by the conditions change nothing.
        $other = $this->getDataGenerator()->create_user();
        $this->plugin->enrol_user($withevents, $other->id);
        \core\event\user_loggedout::create(['objectid' => $other->id, 'userid' => $other->id])->trigger();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($withevents, $other->id));
    }

    public function test_event_map_follows_instance_changes(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['checkmodelogin']);
        $this->plugin->enrol_user($instance, $user->id);

        $this->assertSame([], enforcer::get_event_map());
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));

        $this->plugin->update_instance($instance, (object) ['checkmodeevents' => 1, 'expirynotify' => 0]);
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance, $user->id));

        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
        $this->assertSame([], enforcer::get_event_map());
    }

    public function test_events_ignored_when_plugin_disabled(): void {
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['checkmodeevents']);
        $this->plugin->enrol_user($instance, $user->id);
        \core\plugininfo\enrol::enable_plugin('conditional', false);

        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));
    }

    public function test_cohort_condition_on_events(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $user = $this->getDataGenerator()->create_user();
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user->id);
        $instance = $this->create_instance(['checkmodeevents']);
        $tree = json_encode(['op' => 'and', 'children' => [
            ['type' => 'cohort', 'config' => ['cohortid' => (int) $cohort->id]],
        ]]);
        $this->plugin->update_instance($instance, (object) ['customtext2' => $tree, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->plugin->enrol_user($instance, $user->id);

        cohort_remove_member($cohort->id, $user->id);
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance, $user->id));

        cohort_add_member($cohort->id, $user->id);
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));
    }

    public function test_events_reactivate(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->create_instance(['checkmodeevents']);
        $this->plugin->enrol_user($instance, $user->id);
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance, $user->id));

        $DB->set_field('enrol', 'customtext2', $this->tree([$user->id]), ['id' => $instance->id]);
        \core\event\user_updated::create_from_userid($user->id)->trigger();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance, $user->id));
    }
}
