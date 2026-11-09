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
 * Upgrade steps for enrol_conditional.
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin database.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_enrol_conditional_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026110900) {
        $table = new xmldb_table('enrol_conditional_state');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userenrolmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timesuspended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('uetimemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timereactivated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeclosed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('closereason', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userenrolmentid', XMLDB_KEY_FOREIGN, ['userenrolmentid'], 'user_enrolments', ['id']);
        $table->add_key('instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'enrol', ['id']);
        $table->add_index('userenrolmentid-timeclosed', XMLDB_INDEX_NOTUNIQUE, ['userenrolmentid', 'timeclosed']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026110900, 'enrol', 'conditional');
    }

    return true;
}
