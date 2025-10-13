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

/**
 * Webservice to get the current AI config for a given context.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_control\external;

use block_ai_control\local\aiconfig;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use stdClass;

/**
 * Webservice to update the current AI config for a given context.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_aiconfig extends external_api {
    /**
     * Webservice function executing the requested operation.
     *
     * @param array $aiconfig The aiconfig object to update
     * @return stdClass the response object
     */
    public static function execute(array $aiconfig): stdClass {
        global $PAGE;
        ['aiconfig' => $aiconfig] = self::validate_parameters(self::execute_parameters(), ['aiconfig' => $aiconfig]);
        $context = \context::instance_by_id($aiconfig['id']);
        self::validate_context($context);
        require_capability('block/ai_control:control', $context);

        $aiconfigobject = new aiconfig($aiconfig['id']);
        $aiconfigobject->set_enabled($aiconfig['enabled']);
        $aiconfigobject->set_expiresat($aiconfig['expiresat']);
        $allowedpurposes = [];
        foreach ($aiconfig['purposes'] as $purposeinfo) {
            if ($purposeinfo['allowed']) {
                $allowedpurposes[] = $purposeinfo['id'];
            }
        }
        $aiconfigobject->set_enabledpurposes($allowedpurposes);
        $aiconfigobject->store();

        $aiconfigdata = aiconfig_exporter::export_aiconfig_data($aiconfigobject);
        $exporter = new aiconfig_exporter($aiconfigdata, ['context' => $context, 'aiconfig' => $aiconfigobject]);
        $output = $PAGE->get_renderer('core');
        return $exporter->export($output);
    }

    /**
     * Return parameters definition for this webservice function.
     *
     * It will be a response object containing a code, message and a result attribute (if there is a result).
     *
     * @return external_single_structure the response object
     */
    public static function execute_returns(): external_single_structure {
        return aiconfig_exporter::get_read_structure();
    }

    /**
     * Parameters definition for this webservice function.
     *
     * @return external_function_parameters the external function parameters
     */
    public static function execute_parameters(): external_function_parameters {
        $aiupdatestructure = aiconfig_exporter::get_update_structure();
        unset($aiupdatestructure->keys['purposes']);
        $aiupdatestructure->keys['purposes'] = new external_multiple_structure(purpose_exporter::get_update_structure());
        return new external_function_parameters([
                'aiconfig' => $aiupdatestructure,
        ]);
    }
}
