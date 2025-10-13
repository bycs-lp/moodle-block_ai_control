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

namespace block_ai_control\local;

use block_ai_control\event\ai_control_config_changed;
use context;

/**
 * Wrapper for a config record in the block_ai_control_config table.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiconfig {
    /** @var int Constant representing that the AI tools are enabled in this context */
    const ENABLED = 1;

    /** @var int Constant representing that the AI tools are disabled in this context */
    const DISABLED = 0;

    /** @var context The context of the object which this config is applied */
    private context $context;

    /** @var bool Variable to store the current enabled/disabled state for the current context */
    private bool $enabled = false;

    /** @var int Variable to store the current expiry timestamp until which the AI tools are enabled for this context */
    private int $expiresat = 0;

    /** @var array Array to store the currently enabled purposes for the current context */
    private array $enabledpurposes = [];

    /**
     * Simple constructor for this config object.
     *
     * It sets the context id this object is handling and tries to load the database record if it exists yet.
     *
     * @param int $contextid The context id of the context being managed
     */
    public function __construct(int $contextid) {
        $context = \context::instance_by_id($contextid);
        if ($context->contextlevel !== CONTEXT_COURSE) {
            throw new \coding_exception('Context level of context id ' . $contextid . ' is not CONTEXT_COURSE. '
                    . 'This is not supported by this plugin (yet).');
        }
        $this->context = $context;
        $this->load_from_db();
    }

    /**
     * Loads the database record into the object and returns it.
     *
     * If no record exists the object will just recognize this fact.
     *
     * @return \stdClass|false the record or false if no record found
     */
    public function load_from_db(): \stdClass|false {
        global $DB;

        $record = $DB->get_record('block_ai_control_config', ['contextid' => $this->context->id]);
        if ($record) {
            $this->enabled = !empty($record->enabled);
            $this->expiresat = !empty($record->expiresat) ? intval($record->expiresat) : 0;
            $this->enabledpurposes = !empty($record->enabledpurposes) ? explode(';', $record->enabledpurposes) : [];
        }
        return $record;
    }

    /**
     * Checks if the config record already exists.
     *
     * If no record has been loaded yet, this method will try to load a record.
     *
     * @return bool true if the record exists, false otherwise.
     */
    public function record_exists(): bool {
        return $this->load_from_db() !== false;
    }

    /**
     * Use this method to persist the {@see \block_ai_control\local\config} object to the database.
     *
     * This method will overwrite the current record in the database with the values in the object.
     */
    public function store(): void {
        global $DB;
        $clock = \core\di::get(\core\clock::class);
        $currentrecord = $DB->get_record('block_ai_control_config', ['contextid' => $this->context->id]);
        $recordexists = $currentrecord !== false;
        $record = new \stdClass();

        $record->contextid = $this->context->id;
        $record->enabled = $this->enabled ? self::ENABLED : self::DISABLED;
        // Set default to 90 minutes.
        $record->expiresat = $this->expiresat !== 0 ? $this->expiresat : $clock->time() + 90 * MINSECS;
        $record->enabledpurposes = implode(';', $this->enabledpurposes);
        $record->timemodified = $clock->time();

        if ($recordexists) {
            $record->id = $currentrecord->id;
            $DB->update_record('block_ai_control_config', $record);
        } else {
            $record->id = $DB->insert_record('block_ai_control_config', $record);
        }
        $configchangedevent = ai_control_config_changed::create_from_data($this->context, $this, $record->id);
        $configchangedevent->add_record_snapshot('block_ai_control_config', $record);
        $configchangedevent->trigger();
    }

    /**
     * Deletes the configuration from the config table.
     *
     * Will be called when block instance is being deleted.
     */
    public function delete(): void {
        global $DB;
        $DB->delete_records('block_ai_control_config', ['contextid' => $this->context->id]);
    }

    /**
     * Getter to retrieve the context.
     *
     * @return context the context on which this config is operating
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Determines if the AI tools are enabled in this course.
     *
     * Will also respect the expiry time.
     *
     * @return bool true if AI tools are enabled for this course, false otherwise
     */
    public function is_enabled(): bool {
        $clock = \core\di::get(\core\clock::class);
        return !empty($this->enabled) && ($clock->time() < $this->expiresat || $this->expiresat === -1);
    }

    /**
     * Setter for the enabled state.
     *
     * CARE: For the AI tools to be available also the expiry time needs to be set properly.
     *
     * @param bool $enabled true if the AI tools should be enabled in this context, false otherwise
     */
    public function set_enabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    /**
     * Getter for the expiry time.
     *
     * If this time is smaller than the current time, access to the AI tools in this context will be restricted.
     *
     * @return int the expiry time as unix timestamp, or -1 if the activation will not expire
     */
    public function get_expiresat(): int {
        return $this->expiresat;
    }

    /**
     * Setter for the expiry time.
     *
     * After this time has been reached the AI tools will not be accessible anymore.
     *
     * @param int $expiresat the unix timestamp at which the activation of the AI tools in this context is disabled again,
     *  set -1 for infinite access
     */
    public function set_expiresat(int $expiresat): void {
        $this->expiresat = $expiresat;
    }

    /**
     * Getter for the enabled purposes.
     *
     * @return array of purpose string identifiers that are enabled in the context this config object is handling
     */
    public function get_enabledpurposes(): array {
        return $this->enabledpurposes;
    }

    /**
     * Setter for the enabled purposes in this course.
     *
     * @param array $enabledpurposes Array of string identifiers of purposes enabled in this course.
     */
    public function set_enabledpurposes(array $enabledpurposes): void {
        foreach ($enabledpurposes as $enabledpurpose) {
            if (!in_array($enabledpurpose, \local_ai_manager\base_purpose::get_all_purposes())) {
                throw new \coding_exception('Purpose ' . $enabledpurpose . ' is not valid or not enabled.');
            }
        }
        $this->enabledpurposes = $enabledpurposes;
    }

    /**
     * Helper function to enable a specific purpose.
     *
     * @param string $purpose the purpose identifier of the purpose which should be enabled
     */
    public function enable_purpose(string $purpose): void {
        if (!in_array($purpose, $this->enabledpurposes)) {
            $this->set_enabledpurposes([...$this->get_enabledpurposes(), $purpose]);
        }
    }

    /**
     * Helper function to disable a specific purpose.
     *
     * @param string $purpose the purpose identifier of the purpose which should be disabled
     */
    public function disable_purpose(string $purpose): void {
        if (in_array($purpose, $this->enabledpurposes)) {
            $this->set_enabledpurposes(array_values(array_diff($this->get_enabledpurposes(), [$purpose])));
        }
    }
}
