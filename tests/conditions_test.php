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

use enrol_conditional_plugin;
use local_enrolconditions\condition\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/enrol/conditional/lib.php');
require_once($CFG->dirroot . '/local/enrolconditions/tests/fixtures/fake_condition.php');

/**
 * Tests of how the instance stores and validates its condition tree.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enrol_conditional_plugin::class)]
final class conditions_test extends \advanced_testcase {
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
     * JSON of a tree with one fake condition.
     *
     * @param int[] $userids
     * @return string
     */
    protected static function tree(array $userids): string {
        return json_encode(['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => $userids]]]]);
    }

    public function test_add_instance_normalises_tree(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, ['customtext2' => self::tree([3])]);

        $stored = $DB->get_field('enrol', 'customtext2', ['id' => $id]);
        $this->assertSame(
            [
                'op' => 'and',
                'negate' => false,
                'children' => [['type' => 'fake', 'negate' => false, 'config' => ['userids' => [3]]]],
            ],
            json_decode($stored, true)
        );

        // An empty tree is stored as null.
        $id = $this->plugin->add_instance($course, ['customtext2' => '{"op":"and","children":[]}']);
        $this->assertNull($DB->get_field('enrol', 'customtext2', ['id' => $id]));
    }

    public function test_add_instance_rejects_invalid_structure(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\local_enrolconditions\invalid_tree_exception::class);
        $this->plugin->add_instance($course, ['customtext2' => '{"op":"xor"}']);
    }

    public function test_update_instance_keeps_tree_when_not_sent(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, ['customtext2' => self::tree([3])]);
        $instance = $DB->get_record('enrol', ['id' => $id]);

        $data = (object) ['expirynotify' => 0, 'customint6' => 1, 'status' => ENROL_INSTANCE_ENABLED];
        $this->plugin->update_instance($instance, $data);
        $this->assertSame($instance->customtext2, $DB->get_field('enrol', 'customtext2', ['id' => $id]));

        $instance = $DB->get_record('enrol', ['id' => $id]);
        $data->customtext2 = '';
        $this->plugin->update_instance($instance, $data);
        $this->assertEmpty($DB->get_field('enrol', 'customtext2', ['id' => $id]));
    }

    public function test_validate_conditions(): void {
        global $DB;
        $this->assertNull($this->plugin->validate_conditions(''));
        $this->assertNull($this->plugin->validate_conditions(self::tree([1])));
        $this->assertNotNull($this->plugin->validate_conditions('{'));
        $this->assertNotNull($this->plugin->validate_conditions(self::tree([])));

        // A type that is not available is refused, unless the saved tree already uses it.
        $missing = '{"op":"and","children":[{"type":"missing","config":{}}]}';
        $this->assertNotNull($this->plugin->validate_conditions($missing));
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, ['customtext2' => $missing]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $this->assertNull($this->plugin->validate_conditions($missing, $instance));
    }

    public function test_edit_form_has_editor(): void {
        global $DB, $PAGE;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $id = $this->plugin->add_instance($course, ['customtext2' => self::tree([3])]);
        $instance = $DB->get_record('enrol', ['id' => $id]);
        $context = \context_course::instance($course->id);
        $PAGE->set_url('/enrol/editinstance.php', ['courseid' => $course->id, 'id' => $id, 'type' => 'conditional']);
        $PAGE->set_context($context);

        $mform = new \MoodleQuickForm('test', 'post', '');
        $this->plugin->edit_instance_form($instance, $mform, $context);

        $this->assertTrue($mform->elementExists('customtext2'));
        $this->assertTrue($mform->elementExists('customtext2_editor'));
    }
}
