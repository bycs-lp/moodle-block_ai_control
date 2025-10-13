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

namespace block_ai_control\event;

use block_ai_control\local\aiconfig;

/**
 * The ai_control_config_changed event.
 *
 * @package    block_ai_control
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_control_config_changed extends \core\event\base {
    /**
     * Init function for this event, setting some basic attributes.
     */
    protected function init() {
        $this->data['objecttable'] = 'block_ai_control_config';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the lang string of the event's name.
     *
     * @return string the localized name of the event
     */
    public static function get_name(): string {
        return get_string('ai_control_config_changed', 'block_ai_control');
    }

    /**
     * Gets the localized description of the event.
     *
     * @return string the description string
     */
    public function get_description(): string {
        return get_string('ai_control_config_changed_desc', 'block_ai_control');
    }

    /**
     * Creates the event with the proper information.
     *
     * @param \context $context The context of the AI control center
     * @param aiconfig $aiconfig The aiconfig object that contains the AI control center config
     * @param int $aiconfigid The id of the AI config object in the table block_ai_control_config
     */
    public static function create_from_data(\context $context, aiconfig $aiconfig, int $aiconfigid): \core\event\base {
        global $USER;
        $data = [
                'objectid' => $aiconfigid,
                'relateduserid' => $USER->id,
                'contextid' => $context->id,
                'other' => [
                        'enabled' => $aiconfig->is_enabled(),
                        'expiresat' => $aiconfig->get_expiresat(),
                        'enabledpurposes' => implode(',', $aiconfig->get_enabledpurposes()),
                ],
        ];

        return self::create($data);
    }
}
