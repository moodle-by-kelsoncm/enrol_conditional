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

namespace enrol_conditional\local;

use enrol_conditional\event\conditions_not_met;
use enrol_conditional\event\user_enrolment_reactivated;
use enrol_conditional\event\user_enrolment_suspended;
use enrol_conditional_plugin;
use local_enrolconditions\output\failures;
use local_enrolconditions\tree_result;
use stdClass;

/**
 * Re-checks the conditions of existing enrolments and applies the configured action.
 *
 * Rules:
 * - An unknown result (unknown condition type, invalid tree) never changes anything.
 * - The plugin only reactivates enrolments that it suspended itself, recorded in
 *   enrol_conditional_state, and only while nobody else changed the enrolment since.
 * - The action "none" only records the failure once per episode (met, then not met).
 *
 * @package    enrol_conditional
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforcer {
    /** @var string State table. */
    public const TABLE = 'enrol_conditional_state';

    /** @var string Nothing changed. */
    public const OUTCOME_NOTHING = 'nothing';
    /** @var string The result is unknown, so nothing changed. */
    public const OUTCOME_UNKNOWN = 'unknown';
    /** @var string The failure was recorded (action none). */
    public const OUTCOME_RECORDED = 'recorded';
    /** @var string The enrolment was suspended. */
    public const OUTCOME_SUSPENDED = 'suspended';
    /** @var string The enrolment was removed. */
    public const OUTCOME_REMOVED = 'removed';
    /** @var string The enrolment was reactivated. */
    public const OUTCOME_REACTIVATED = 'reactivated';
    /** @var string A recorded failure was closed because the conditions are met again. */
    public const OUTCOME_RESOLVED = 'resolved';

    /** @var string State action: only recorded. */
    public const STATE_NONE = 'none';
    /** @var string State action: suspended by the plugin. */
    public const STATE_SUSPEND = 'suspend';

    /** @var string Closed because the plugin reactivated the enrolment. */
    public const CLOSE_REACTIVATED = 'reactivated';
    /** @var string Closed because the conditions are met again. */
    public const CLOSE_MET = 'met';
    /** @var string Closed because someone else changed the enrolment. */
    public const CLOSE_EXTERNAL = 'external';
    /** @var string Closed because the configured action changed. */
    public const CLOSE_SUPERSEDED = 'superseded';

    /** @var bool True while the enforcer itself is changing an enrolment. */
    protected static bool $acting = false;

    /** @var enrol_conditional_plugin The enrolment plugin. */
    protected enrol_conditional_plugin $plugin;

    /**
     * Constructor.
     *
     * @param enrol_conditional_plugin|null $plugin Defaults to the installed plugin.
     */
    public function __construct(?enrol_conditional_plugin $plugin = null) {
        $this->plugin = $plugin ?? enrol_get_plugin('conditional');
    }

    /**
     * Whether the enforcer is changing an enrolment right now.
     *
     * Observers use it to tell the plugin's own changes from changes made by others.
     *
     * @return bool
     */
    public static function is_acting(): bool {
        return self::$acting;
    }

    /**
     * Re-checks the enrolment of a user in an instance.
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $userid
     * @return string One of the OUTCOME_ constants.
     */
    public function check_user(stdClass $instance, int $userid): string {
        global $DB;
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$ue) {
            return self::OUTCOME_NOTHING;
        }
        return $this->check_user_enrolment($instance, $ue);
    }

    /**
     * Re-checks one user enrolment.
     *
     * @param stdClass $instance Enrolment instance.
     * @param stdClass $ue User enrolment record.
     * @return string One of the OUTCOME_ constants.
     */
    public function check_user_enrolment(stdClass $instance, stdClass $ue): string {
        if ($instance->enrol !== 'conditional' || (int) $ue->enrolid !== (int) $instance->id) {
            throw new \coding_exception('The user enrolment does not belong to this conditional instance.');
        }
        if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            return self::OUTCOME_NOTHING;
        }

        $result = $this->plugin->evaluate_conditions($instance, (int) $ue->userid);
        if ($result !== null && $result->is_unknown()) {
            return self::OUTCOME_UNKNOWN;
        }
        $open = $this->get_open_state((int) $ue->id);
        if ($result === null || $result->is_met()) {
            return $this->handle_met($instance, $ue, $open);
        }
        return $this->handle_not_met($instance, $ue, $open, $result);
    }

    /**
     * Open state record of a user enrolment.
     *
     * @param int $ueid
     * @return stdClass|null
     */
    public function get_open_state(int $ueid): ?stdClass {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE,
            'userenrolmentid = :ueid AND timeclosed IS NULL',
            ['ueid' => $ueid],
            'id DESC'
        );
        return $records ? reset($records) : null;
    }

    /**
     * Whether the plugin still owns the state of the enrolment.
     *
     * A suspension is owned while the enrolment is suspended and nobody changed it since.
     *
     * @param stdClass $state
     * @param stdClass $ue
     * @return bool
     */
    public static function owns(stdClass $state, stdClass $ue): bool {
        if ($state->action === self::STATE_NONE) {
            return true;
        }
        return (int) $ue->status === ENROL_USER_SUSPENDED
            && (int) $ue->timemodified === (int) $state->uetimemodified;
    }

    /**
     * Closes a state record.
     *
     * @param stdClass $state
     * @param string $reason One of the CLOSE_ constants.
     */
    public static function close(stdClass $state, string $reason): void {
        global $DB;
        $update = (object) [
            'id' => $state->id,
            'timeclosed' => time(),
            'closereason' => $reason,
        ];
        if ($reason === self::CLOSE_REACTIVATED) {
            $update->timereactivated = $update->timeclosed;
        }
        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Applies the rules when the conditions are met (or there are none).
     *
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass|null $open
     * @return string
     */
    protected function handle_met(stdClass $instance, stdClass $ue, ?stdClass $open): string {
        if (!$open) {
            return self::OUTCOME_NOTHING;
        }
        if ($open->action === self::STATE_NONE) {
            self::close($open, self::CLOSE_MET);
            return self::OUTCOME_RESOLVED;
        }
        if (!self::owns($open, $ue)) {
            // Someone else changed the enrolment after the plugin suspended it: it is not ours any more.
            self::close($open, self::CLOSE_EXTERNAL);
            return self::OUTCOME_NOTHING;
        }
        if (!enrol_conditional_plugin::get_reactivate($instance)) {
            // Kept open, so the enrolment can still be reactivated if the setting is enabled later.
            return self::OUTCOME_NOTHING;
        }

        $this->update_status($instance, $ue, ENROL_USER_ACTIVE);
        self::close($open, self::CLOSE_REACTIVATED);
        user_enrolment_reactivated::create_from_state($instance, $ue, (int) $open->id)->trigger();
        return self::OUTCOME_REACTIVATED;
    }

    /**
     * Applies the configured action when the conditions are not met.
     *
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass|null $open
     * @param tree_result $result
     * @return string
     */
    protected function handle_not_met(stdClass $instance, stdClass $ue, ?stdClass $open, tree_result $result): string {
        global $DB;
        $action = enrol_conditional_plugin::get_condition_action($instance);

        if ($open) {
            $owned = self::owns($open, $ue);
            $same = ($open->action === self::STATE_SUSPEND && $action === enrol_conditional_plugin::ACTION_SUSPEND)
                || ($open->action === self::STATE_NONE && $action === enrol_conditional_plugin::ACTION_NONE);
            // An owned suspension stays when the action becomes "none": the plugin does not reactivate on failure.
            if ($owned && ($same || $action === enrol_conditional_plugin::ACTION_NONE)) {
                return self::OUTCOME_NOTHING;
            }
            self::close($open, $owned ? self::CLOSE_SUPERSEDED : self::CLOSE_EXTERNAL);
        }

        $reason = trim((new failures($result, ''))->to_text());

        if ($action === enrol_conditional_plugin::ACTION_REMOVE) {
            conditions_not_met::create_from_enrolment($instance, $ue, 'remove')->trigger();
            self::$acting = true;
            try {
                $this->plugin->unenrol_user($instance, (int) $ue->userid);
            } finally {
                self::$acting = false;
            }
            return self::OUTCOME_REMOVED;
        }

        if ($action === enrol_conditional_plugin::ACTION_NONE) {
            $stateid = $this->insert_state($instance, $ue, self::STATE_NONE, $reason);
            conditions_not_met::create_from_enrolment($instance, $ue, 'none', $stateid)->trigger();
            return self::OUTCOME_RECORDED;
        }

        if ((int) $ue->status === ENROL_USER_SUSPENDED) {
            // Suspended by someone else: the plugin does not take it over.
            return self::OUTCOME_NOTHING;
        }
        $this->update_status($instance, $ue, ENROL_USER_SUSPENDED);
        $ue = $DB->get_record('user_enrolments', ['id' => $ue->id], '*', MUST_EXIST);
        $stateid = $this->insert_state($instance, $ue, self::STATE_SUSPEND, $reason);
        user_enrolment_suspended::create_from_state($instance, $ue, $stateid)->trigger();
        return self::OUTCOME_SUSPENDED;
    }

    /**
     * Changes the status of an enrolment, marking the change as the plugin's own.
     *
     * @param stdClass $instance
     * @param stdClass $ue
     * @param int $status
     */
    protected function update_status(stdClass $instance, stdClass $ue, int $status): void {
        self::$acting = true;
        try {
            $this->plugin->update_user_enrol($instance, (int) $ue->userid, $status);
        } finally {
            self::$acting = false;
        }
    }

    /**
     * Inserts an open state record.
     *
     * @param stdClass $instance
     * @param stdClass $ue User enrolment, as it is after the action.
     * @param string $action One of the STATE_ constants.
     * @param string $reason
     * @return int Id of the record.
     */
    protected function insert_state(stdClass $instance, stdClass $ue, string $action, string $reason): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'userenrolmentid' => $ue->id,
            'instanceid' => $instance->id,
            'action' => $action,
            'reason' => $reason,
            'timesuspended' => time(),
            'uetimemodified' => (int) $ue->timemodified,
            'timereactivated' => null,
            'timeclosed' => null,
            'closereason' => null,
        ]);
    }
}
