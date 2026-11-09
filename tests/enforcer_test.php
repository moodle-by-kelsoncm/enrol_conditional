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
 * Tests of the re-check of existing enrolments and of the actions taken.
 *
 * The fake condition is met when the user id is in config['userids'].
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enforcer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_suspended::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_reactivated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\conditions_not_met::class)]
final class enforcer_test extends \advanced_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \stdClass Enrolled user. */
    protected \stdClass $user;

    /** @var enforcer */
    protected enforcer $enforcer;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
        $this->enforcer = new enforcer($this->plugin);
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an instance whose tree accepts the given users, and enrols the test user.
     *
     * @param int $action One of the ACTION_ constants.
     * @param bool $reactivate
     * @param int[] $allowed Users that meet the conditions.
     * @return \stdClass
     */
    protected function create_instance(int $action, bool $reactivate, array $allowed): \stdClass {
        global $DB;
        $id = $this->plugin->add_instance($this->course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'customtext2' => $this->tree($allowed),
            'conditionaction' => $action,
            'conditionreactivate' => (int) $reactivate,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($instance, $this->user->id);
        return $instance;
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
     * Changes who meets the conditions and returns the fresh instance.
     *
     * @param \stdClass $instance
     * @param int[] $allowed
     * @return \stdClass
     */
    protected function set_allowed(\stdClass $instance, array $allowed): \stdClass {
        global $DB;
        $DB->set_field('enrol', 'customtext2', $this->tree($allowed), ['id' => $instance->id]);
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Current user enrolment of the test user.
     *
     * @param \stdClass $instance
     * @return \stdClass|false
     */
    protected function ue(\stdClass $instance) {
        global $DB;
        return $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $this->user->id]);
    }

    /**
     * State records of an instance, oldest first.
     *
     * @param \stdClass $instance
     * @return \stdClass[]
     */
    protected function states(\stdClass $instance): array {
        global $DB;
        return array_values($DB->get_records(enforcer::TABLE, ['instanceid' => $instance->id], 'id ASC'));
    }

    /**
     * Suspends the enrolment as another person would, with a later timemodified.
     *
     * @param \stdClass $instance
     * @param int $status
     */
    protected function change_by_someone_else(\stdClass $instance, int $status): void {
        $this->waitForSecond();
        $this->plugin->update_user_enrol($instance, $this->user->id, $status);
    }

    public function test_met_changes_nothing(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);
        $this->assertSame([], $this->states($instance));
    }

    public function test_not_met_suspends(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $sink = $this->redirectEvents();
        $outcome = $this->enforcer->check_user($instance, $this->user->id);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof event\user_enrolment_suspended
        ));
        $sink->close();

        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $outcome);
        $ue = $this->ue($instance);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);
        $states = $this->states($instance);
        $this->assertCount(1, $states);
        $this->assertSame(enforcer::STATE_SUSPEND, $states[0]->action);
        $this->assertNull($states[0]->timeclosed);
        $this->assertEquals($ue->timemodified, $states[0]->uetimemodified);
        $this->assertStringContainsString('Not a listed user', $states[0]->reason);

        $this->assertCount(1, $events);
        $this->assertEquals($ue->id, $events[0]->objectid);
        $this->assertEquals($this->user->id, $events[0]->relateduserid);
        $this->assertEquals($states[0]->id, $events[0]->other['stateid']);
        $this->assertNotEmpty($events[0]->get_name());
        $this->assertNotEmpty($events[0]->get_description());

        // A second check changes nothing.
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertCount(1, $this->states($instance));
    }

    public function test_reactivates_own_suspension(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $instance = $this->set_allowed($instance, [$this->user->id]);

        $sink = $this->redirectEvents();
        $outcome = $this->enforcer->check_user($instance, $this->user->id);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof event\user_enrolment_reactivated
        ));
        $sink->close();

        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $outcome);
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);
        $state = $this->states($instance)[0];
        $this->assertSame(enforcer::CLOSE_REACTIVATED, $state->closereason);
        $this->assertNotNull($state->timereactivated);
        $this->assertCount(1, $events);
        $this->assertNotEmpty($events[0]->get_description());
    }

    public function test_without_reactivation_keeps_suspended_until_enabled(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, false, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $instance = $this->set_allowed($instance, [$this->user->id]);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
        $this->assertNull($this->states($instance)[0]->timeclosed);

        $this->plugin->update_instance($instance, (object) ['conditionreactivate' => 1, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);
    }

    public function test_does_not_reactivate_suspension_by_someone_else(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, [$this->user->id]);
        $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_SUSPENDED);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);

        // Not met either: the plugin does not take the suspension over.
        $instance = $this->set_allowed($instance, []);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
        $this->assertSame([], $this->states($instance));
    }

    public function test_does_not_reactivate_after_someone_else_resuspended(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);

        // A teacher reactivates and later suspends the user again.
        $this->change_by_someone_else($instance, ENROL_USER_ACTIVE);
        $state = $this->states($instance)[0];
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $state->closereason);
        $this->change_by_someone_else($instance, ENROL_USER_SUSPENDED);

        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
    }

    public function test_does_not_reactivate_after_change_without_event(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);

        // Another routine changed the record directly, without triggering events.
        $ue = $this->ue($instance);
        $DB->set_field('user_enrolments', 'timemodified', $ue->timemodified + 10, ['id' => $ue->id]);

        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $this->states($instance)[0]->closereason);
    }

    public function test_date_change_by_someone_else_keeps_ownership(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);

        // A teacher only extends the enrolment, which stays suspended.
        $this->waitForSecond();
        $this->plugin->update_user_enrol($instance, $this->user->id, null, null, time() + WEEKSECS);
        $state = $this->states($instance)[0];
        $this->assertNull($state->timeclosed);
        $this->assertEquals($this->ue($instance)->timemodified, $state->uetimemodified);

        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
    }

    public function test_date_change_after_change_without_event_does_not_restore_ownership(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);

        // Another routine changed the record directly, then a teacher changes its dates.
        $ue = $this->ue($instance);
        $DB->set_field('user_enrolments', 'timemodified', $ue->timemodified - 10, ['id' => $ue->id]);
        $this->plugin->update_user_enrol($instance, $this->user->id, null, null, time() + WEEKSECS);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $this->states($instance)[0]->closereason);

        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
    }

    public function test_suspends_again_after_external_reactivation(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->change_by_someone_else($instance, ENROL_USER_ACTIVE);

        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $states = $this->states($instance);
        $this->assertCount(2, $states);
        $this->assertSame(enforcer::CLOSE_EXTERNAL, $states[0]->closereason);
        $this->assertNull($states[1]->timeclosed);
    }

    public function test_action_none_records_once_per_episode(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_NONE, true, []);
        $sink = $this->redirectEvents();

        $this->assertSame(enforcer::OUTCOME_RECORDED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);

        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_RESOLVED, $this->enforcer->check_user($instance, $this->user->id));
        $instance = $this->set_allowed($instance, []);
        $this->assertSame(enforcer::OUTCOME_RECORDED, $this->enforcer->check_user($instance, $this->user->id));

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\conditions_not_met));
        $sink->close();
        $this->assertCount(2, $events);
        $this->assertSame('none', $events[0]->other['action']);
        $this->assertNotEmpty($events[0]->get_description());

        $states = $this->states($instance);
        $this->assertCount(2, $states);
        $this->assertSame(enforcer::CLOSE_MET, $states[0]->closereason);
        $this->assertSame(enforcer::STATE_NONE, $states[1]->action);
    }

    public function test_action_changed_from_none_to_suspend(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_NONE, true, []);
        $this->enforcer->check_user($instance, $this->user->id);

        $this->plugin->update_instance($instance, (object) ['conditionaction' => 1, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $states = $this->states($instance);
        $this->assertSame(enforcer::CLOSE_SUPERSEDED, $states[0]->closereason);
        $this->assertSame(enforcer::STATE_SUSPEND, $states[1]->action);
    }

    public function test_suspension_kept_when_action_becomes_none(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->plugin->update_instance($instance, (object) ['conditionaction' => 0, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);

        // Still owned, so it is reactivated when the conditions are met.
        $instance = $this->set_allowed($instance, [$this->user->id]);
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
    }

    public function test_action_remove(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_REMOVE, true, []);
        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($instance, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\conditions_not_met));
        $sink->close();

        $this->assertFalse($this->ue($instance));
        $this->assertCount(1, $events);
        $this->assertSame('remove', $events[0]->other['action']);
        $this->assertSame([], $this->states($instance));
    }

    public function test_remove_after_own_suspension(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->plugin->update_instance($instance, (object) ['conditionaction' => 2, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertFalse($this->ue($instance));
        $this->assertSame([], $this->states($instance));
    }

    public function test_unknown_result_never_suspends(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $DB->set_field('enrol', 'customtext2', '{"op":"and","children":[{"type":"missing","config":{}}]}', ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);

        $DB->set_field('enrol', 'customtext2', '{"op":"xor"}', ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertDebuggingCalled();
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);
        $this->assertSame([], $this->states($instance));
    }

    public function test_unknown_result_never_reactivates(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $DB->set_field('enrol', 'customtext2', '{"op":"and","children":[{"type":"missing","config":{}}]}', ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_SUSPENDED, $this->ue($instance)->status);
        $this->assertNull($this->states($instance)[0]->timeclosed);
    }

    public function test_removed_conditions_reactivate(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $DB->set_field('enrol', 'customtext2', '', ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
    }

    public function test_disabled_instance_is_ignored(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertEquals(ENROL_USER_ACTIVE, $this->ue($instance)->status);
    }

    public function test_user_not_enrolled(): void {
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $other->id));
    }

    public function test_other_instance_rejected(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $manual = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $this->expectException(\coding_exception::class);
        $this->enforcer->check_user_enrolment($manual, $this->ue($instance));
    }

    public function test_cleanup_on_unenrol_and_instance_deletion(): void {
        global $DB;
        $instance = $this->create_instance(enrol_conditional_plugin::ACTION_SUSPEND, true, []);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->plugin->unenrol_user($instance, $this->user->id);
        $this->assertSame([], $this->states($instance));

        $this->plugin->enrol_user($instance, $this->user->id);
        $this->enforcer->check_user($instance, $this->user->id);
        $this->assertCount(1, $this->states($instance));
        $this->plugin->delete_instance($instance);
        $this->assertSame(0, $DB->count_records(enforcer::TABLE));
    }

    public function test_action_fields(): void {
        global $DB;
        set_config('conditionaction', enrol_conditional_plugin::ACTION_NONE, 'enrol_conditional');
        set_config('conditionreactivate', 0, 'enrol_conditional');
        $id = $this->plugin->add_instance($this->course, ['status' => ENROL_INSTANCE_ENABLED]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->assertSame(enrol_conditional_plugin::ACTION_NONE, enrol_conditional_plugin::get_condition_action($instance));
        $this->assertFalse(enrol_conditional_plugin::get_reactivate($instance));

        $this->plugin->update_instance($instance, (object) ['conditionaction' => 2, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->assertSame(enrol_conditional_plugin::ACTION_REMOVE, enrol_conditional_plugin::get_condition_action($instance));
        $this->assertFalse(enrol_conditional_plugin::get_reactivate($instance));

        $this->plugin->update_instance($instance, (object) ['conditionreactivate' => 1, 'expirynotify' => 0]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->assertSame(enrol_conditional_plugin::ACTION_REMOVE, enrol_conditional_plugin::get_condition_action($instance));
        $this->assertTrue(enrol_conditional_plugin::get_reactivate($instance));
        $this->assertEquals(
            enrol_conditional_plugin::ACTION_REMOVE | enrol_conditional_plugin::FLAG_REACTIVATE,
            $instance->{enrol_conditional_plugin::FIELD_ACTION}
        );

        // Instances from before the setting existed use the site defaults.
        $instance->{enrol_conditional_plugin::FIELD_ACTION} = null;
        $this->assertSame(enrol_conditional_plugin::ACTION_NONE, enrol_conditional_plugin::get_condition_action($instance));

        // Invalid stored values fall back to suspend.
        $instance->{enrol_conditional_plugin::FIELD_ACTION} = 7;
        $this->assertSame(enrol_conditional_plugin::ACTION_SUSPEND, enrol_conditional_plugin::get_condition_action($instance));
    }

    public function test_action_validation(): void {
        $this->setAdminUser();
        $instance = (object) array_merge(
            $this->plugin->get_instance_defaults(),
            ['id' => null, 'courseid' => $this->course->id, 'enrol' => 'conditional']
        );
        $context = \context_course::instance($this->course->id);
        $data = [
            'status' => ENROL_INSTANCE_ENABLED,
            'password' => '',
            'conditionaction' => 9,
            'conditionreactivate' => 1,
        ];
        $errors = $this->plugin->edit_instance_validation($data, [], $instance, $context);
        $this->assertArrayHasKey('conditionaction', $errors);

        $data['conditionaction'] = enrol_conditional_plugin::ACTION_REMOVE;
        $errors = $this->plugin->edit_instance_validation($data, [], $instance, $context);
        $this->assertArrayNotHasKey('conditionaction', $errors);
    }
}
