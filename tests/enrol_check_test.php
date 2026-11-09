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

use core_external\external_api;
use enrol_conditional_external;
use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/enrol/conditional/externallib.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of the condition check when users enrol themselves.
 *
 * The fake condition is met when the user id is in config['userids'].
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_external::class)]
final class enrol_check_test extends \core_external\tests\externallib_testcase {
    /** @var enrol_conditional_plugin */
    protected enrol_conditional_plugin $plugin;

    /** @var \stdClass Course. */
    protected \stdClass $course;

    /** @var \stdClass User that meets the conditions. */
    protected \stdClass $allowed;

    /** @var \stdClass User that does not meet the conditions. */
    protected \stdClass $refused;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\enrol::enable_plugin('conditional', true);
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = enrol_get_plugin('conditional');
        $this->course = $this->getDataGenerator()->create_course();
        $this->allowed = $this->getDataGenerator()->create_user();
        $this->refused = $this->getDataGenerator()->create_user();
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an enabled instance with the given tree.
     *
     * @param string|null $tree JSON of the tree.
     * @return \stdClass
     */
    protected function create_instance(?string $tree): \stdClass {
        global $DB;
        $id = $this->plugin->add_instance($this->course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'customtext2' => $tree,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Tree that only the allowed user meets.
     *
     * @return string
     */
    protected function allowed_tree(): string {
        return json_encode(['op' => 'and', 'children' => [
            ['type' => 'fake', 'config' => ['userids' => [(int) $this->allowed->id]]],
        ]]);
    }

    public function test_without_conditions(): void {
        $instance = $this->create_instance(null);
        $this->setUser($this->refused);
        $this->assertTrue($this->plugin->can_self_enrol($instance));
    }

    public function test_conditions_met(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->allowed);
        $this->assertTrue($this->plugin->can_self_enrol($instance));
        $this->assertTrue($this->plugin->evaluate_conditions($instance, (int) $this->allowed->id)->is_met());

        $this->plugin->enrol_conditional($instance, (object) []);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $this->allowed));
    }

    public function test_conditions_not_met(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->refused);

        $status = $this->plugin->can_self_enrol($instance);
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('conditionsnotmet', 'enrol_conditional'), $status);
        $this->assertStringContainsString('Not a listed user', $status);
        $this->assertFalse($this->plugin->show_enrolme_link($instance));

        // The page shows the reason instead of the enrol button.
        $html = $this->plugin->enrol_page_hook($instance);
        $this->assertStringContainsString('Not a listed user', $html);
        $this->assertStringNotContainsString(get_string('enrolme', 'enrol_conditional'), $html);
    }

    public function test_enrol_method_checks_conditions_again(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->refused);
        try {
            $this->plugin->enrol_conditional($instance, (object) []);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('conditionsnotmetshort', $e->errorcode);
        }
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $this->refused));
    }

    public function test_unknown_type_refuses(): void {
        $instance = $this->create_instance('{"op":"and","children":[{"type":"missing","config":{}}]}');
        $this->setUser($this->allowed);
        $status = $this->plugin->can_self_enrol($instance);
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('failureunknown', 'local_enrolconditions'), $status);
    }

    public function test_unknown_type_or_met_branch_allows(): void {
        $tree = json_encode(['op' => 'or', 'children' => [
            ['type' => 'missing', 'config' => []],
            ['type' => 'fake', 'config' => ['userids' => [(int) $this->allowed->id]]],
        ]]);
        $instance = $this->create_instance($tree);
        $this->setUser($this->allowed);
        $this->assertTrue($this->plugin->can_self_enrol($instance));
    }

    public function test_invalid_stored_tree_refuses(): void {
        global $DB;
        $instance = $this->create_instance(null);
        $DB->set_field('enrol', 'customtext2', '{"op":"xor"}', ['id' => $instance->id]);
        $instance = $DB->get_record('enrol', ['id' => $instance->id]);
        $this->setUser($this->allowed);

        $status = $this->plugin->can_self_enrol($instance);
        $this->assertDebuggingCalled();
        $this->assertIsString($status);
        $this->assertStringContainsString(get_string('failureunknown', 'local_enrolconditions'), $status);
    }

    public function test_web_service_refuses_with_plain_text(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->refused);

        $result = enrol_conditional_external::enrol_user($this->course->id, '', $instance->id);
        $result = external_api::clean_returnvalue(enrol_conditional_external::enrol_user_returns(), $result);

        $this->assertFalse($result['status']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Not a listed user', $result['warnings'][0]['message']);
        $this->assertStringNotContainsString('<', $result['warnings'][0]['message']);
        $this->assertFalse(is_enrolled(\context_course::instance($this->course->id), $this->refused));
    }

    public function test_web_service_enrols_when_met(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->allowed);

        $result = enrol_conditional_external::enrol_user($this->course->id, '', $instance->id);
        $result = external_api::clean_returnvalue(enrol_conditional_external::enrol_user_returns(), $result);

        $this->assertTrue($result['status']);
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $this->allowed));
    }

    public function test_enrol_info_status(): void {
        $instance = $this->create_instance($this->allowed_tree());
        $this->setUser($this->refused);
        $this->assertIsString($this->plugin->get_enrol_info($instance)->status);
        $this->setUser($this->allowed);
        $this->assertTrue($this->plugin->get_enrol_info($instance)->status);
    }
}
