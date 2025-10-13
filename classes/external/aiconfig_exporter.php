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
use core_date;
use local_ai_manager\base_purpose;
use renderer_base;

/**
 * Exporter class for the aiconfig object.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiconfig_exporter extends exporter {
    /**
     * Definition of basic properties.
     *
     * @return array of properties
     */
    protected static function define_properties(): array {
        return [
            'id' => [
                'type' => PARAM_INT,
            ],
            'enabled' => [
                'type' => PARAM_BOOL,
            ],
            'expiresat' => [
                'type' => PARAM_INT,
            ],
            'purposes' => [
                'type' => purpose_exporter::read_properties_definition(),
                'multiple' => true,
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
            'expiresatFormatted' => [
                'type' => PARAM_TEXT,
            ],
            'infoText' => [
                'type' => PARAM_RAW,
            ],
            'showviewprompts' => [
                'type' => PARAM_BOOL,
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
            'aiconfig' => 'block_ai_control\\local\\aiconfig',
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
            'expiresatFormatted' => userdate(
                $this->related['aiconfig']->get_expiresat(),
                get_string('strftimedatetime', 'langconfig')
            ),
            'infoText' => get_config('block_ai_control', 'infotext'),
            'showviewprompts' => has_capability('local/ai_manager:viewprompts', $this->related['context']),
        ];
    }

    /**
     * Helper function to extract the needed data array for the exporter based on an idmgroup object.
     *
     * @param aiconfig $aiconfig the aiconfig object to export
     * @return array data to inject into the exporter
     */
    public static function export_aiconfig_data(aiconfig $aiconfig): array {
        global $PAGE;
        $purposes = [];
        foreach (base_purpose::get_all_purposes() as $purpose) {
            $purposeobject =
                \core\di::get(\local_ai_manager\local\connector_factory::class)->get_purpose_by_purpose_string($purpose);
            $purposedata = purpose_exporter::export_purpose_data($aiconfig, $purposeobject);
            $purposeexporter =
                new purpose_exporter($purposedata, ['context' => $aiconfig->get_context(), 'purpose' => $purposeobject]);
            $output = $PAGE->get_renderer('core');
            $purposes[] = $purposeexporter->export($output);
        }

        return [
            'id' => $aiconfig->get_context()->id,
            'enabled' => $aiconfig->is_enabled(),
            'expiresat' => $aiconfig->get_expiresat(),
            'purposes' => $purposes,
        ];
    }
}
