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

namespace enrol_conditional\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_conditional\local\enforcer;
use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of the privacy provider.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass[] Users. */
    protected array $users = [];

    /** @var \context_course[] Course contexts. */
    protected array $contexts = [];

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $plugin = enrol_get_plugin('conditional');
        $enforcer = new enforcer($plugin);
        $tree = json_encode(['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => [0]]]]]);
        for ($i = 0; $i < 2; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user();
        }
        for ($c = 0; $c < 2; $c++) {
            $course = $this->getDataGenerator()->create_course();
            $this->contexts[$c] = \context_course::instance($course->id);
            $id = $plugin->add_instance($course, [
                'status' => ENROL_INSTANCE_ENABLED,
                'customtext2' => $tree,
                'conditionaction' => enrol_conditional_plugin::ACTION_SUSPEND,
            ]);
            $instance = $DB->get_record('enrol', ['id' => $id]);
            foreach ($this->users as $user) {
                $plugin->enrol_user($instance, $user->id);
                $enforcer->check_user($instance, $user->id);
            }
        }
    }

    #[\Override]
    public function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('enrol_conditional'));
        $this->assertCount(1, $collection->get_collection());
    }

    public function test_contexts_and_users(): void {
        $contextids = provider::get_contexts_for_userid($this->users[0]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->contexts[0]->id, $this->contexts[1]->id], $contextids);

        $userlist = new userlist($this->contexts[0], 'enrol_conditional');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->users[0]->id, $this->users[1]->id], $userlist->get_userids());

        $userlist = new userlist(\context_system::instance(), 'enrol_conditional');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
    }

    public function test_export(): void {
        $contextlist = new approved_contextlist($this->users[0], 'enrol_conditional', [$this->contexts[0]->id]);
        provider::export_user_data($contextlist);
        $writer = writer::with_context($this->contexts[0]);
        $data = $writer->get_data([
            get_string('pluginname', 'enrol_conditional'),
            get_string('privacy:path:state', 'enrol_conditional'),
        ]);
        $this->assertCount(1, $data->records);
        $this->assertSame('suspend', $data->records[0]->action);
        $this->assertStringContainsString('Not a listed user', $data->records[0]->reason);
    }

    public function test_delete_for_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->contexts[0]);
        $this->assertEquals([$this->contexts[1]->id], provider::get_contexts_for_userid($this->users[0]->id)->get_contextids());
        $this->assertSame(2, $DB->count_records(enforcer::TABLE));
    }

    public function test_delete_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->users[0], 'enrol_conditional', [$this->contexts[0]->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertEquals([$this->contexts[1]->id], provider::get_contexts_for_userid($this->users[0]->id)->get_contextids());
        $this->assertSame(3, $DB->count_records(enforcer::TABLE));
    }

    public function test_delete_for_users(): void {
        global $DB;
        $userlist = new approved_userlist($this->contexts[1], 'enrol_conditional', [$this->users[1]->id]);
        provider::delete_data_for_users($userlist);
        $this->assertEquals([$this->contexts[0]->id], provider::get_contexts_for_userid($this->users[1]->id)->get_contextids());
        $this->assertSame(3, $DB->count_records(enforcer::TABLE));
    }
}
