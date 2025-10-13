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

namespace block_ai_control\external;

use block_ai_control\local\aiconfig;
use core\external\exporter;
use local_ai_manager\base_purpose;
use renderer_base;

/**
 * Exporter class for the purpose object.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purpose_exporter extends exporter {
    /**
     * Definition of basic properties.
     *
     * @return array of properties
     */
    protected static function define_properties(): array {
        return [
                'id' => [
                        'type' => PARAM_ALPHA,
                ],
                'allowed' => [
                        'type' => PARAM_BOOL,
                ],
        ];
    }

    /**
     * Definition of additional properties.
     *
     * @return array of additional properties
     */
    protected static function define_other_properties(): array {
        return [
                'name' => [
                        'type' => PARAM_ALPHANUM,
                ],
                'displayname' => [
                        'type' => PARAM_TEXT,
                ],
        ];
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array array of related objects
     */
    protected static function define_related(): array {
        return [
                'context' => 'context',
                'purpose' => 'local_ai_manager\\base_purpose',
        ];
    }

    /**
     * Calculates the additional data.
     *
     * @param renderer_base $output the renderer to use
     * @return array array of additional data
     */
    protected function get_other_values(renderer_base $output): array {
        return [
                'name' => $this->related['purpose']->get_plugin_name(),
                'displayname' => get_string('pluginname', 'aipurpose_' . $this->related['purpose']->get_plugin_name()),
        ];
    }

    /**
     * Helper function to extract the needed data array for the exporter based on an idmgroup object.
     *
     * @param aiconfig $aiconfig The config object to use
     * @param base_purpose $purpose The purpose object to export the data from
     * @return array data to inject into the exporter
     */
    public static function export_purpose_data(aiconfig $aiconfig, base_purpose $purpose): array {
        return [
                'id' => $purpose->get_plugin_name(),
                'allowed' => in_array($purpose->get_plugin_name(), $aiconfig->get_enabledpurposes()),
        ];
    }
}
