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
 * Behat steps of enrol_conditional.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\PyStringNode;

/**
 * Behat steps of enrol_conditional.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_conditional extends behat_base {
    /**
     * Adds an enabled conditional self enrolment method to a course, with a condition tree.
     *
     * Cohorts can be referred to by idnumber with "cohortidnumber" instead of "cohortid" in the configuration.
     *
     * @Given /^the course "(?P<shortname_string>(?:[^"]|\\")*)" has a conditional self enrolment method with conditions:$/
     * @param string $shortname
     * @param PyStringNode $json Condition tree.
     */
    public function the_course_has_a_conditional_self_enrolment_method_with_conditions(
        string $shortname,
        PyStringNode $json
    ): void {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $tree = json_decode($json->getRaw(), true);
        if (!is_array($tree)) {
            throw new \Behat\Mink\Exception\ExpectationException('Invalid condition tree JSON', $this->getSession());
        }
        $tree = $this->resolve_references($tree);
        $plugin = enrol_get_plugin('conditional');
        $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint6' => 1,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student']),
            'customtext2' => json_encode($tree),
        ]);
    }

    /**
     * Replaces cohort idnumbers by ids in a tree.
     *
     * @param array $node
     * @return array
     */
    protected function resolve_references(array $node): array {
        global $DB;
        if (isset($node['config']['cohortidnumber'])) {
            $node['config'] = ['cohortid' => (int) $DB->get_field(
                'cohort',
                'id',
                ['idnumber' => $node['config']['cohortidnumber']],
                MUST_EXIST
            )];
        }
        if (isset($node['children'])) {
            $node['children'] = array_map(fn(array $child): array => $this->resolve_references($child), $node['children']);
        }
        return $node;
    }
}
