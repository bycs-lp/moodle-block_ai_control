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
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;

/**
 * Provisioning webservice get_user for retrieving user information.
 *
 * @package   block_ai_control
 * @copyright 2024 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_aiconfig extends external_api {
    /**
     * Webservice function executing the requested operation.
     *
     * @param int $id The context id of the context the requested aiconfig is managing
     * @return stdClass the response object
     */
    public static function execute(int $id): stdClass {
        global $PAGE;
        ['id' => $id] = self::validate_parameters(self::execute_parameters(), ['id' => $id]);
        $context = \context::instance_by_id($id);
        self::validate_context($context);
        require_capability('block/ai_control:view', $context);

        $aiconfig = new aiconfig($id);
        $aiconfigdata = aiconfig_exporter::export_aiconfig_data($aiconfig);
        $exporter = new aiconfig_exporter($aiconfigdata, ['context' => $context, 'aiconfig' => $aiconfig]);
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
        return new external_function_parameters([
                'id' => new external_value(PARAM_INT, 'Context id of the context the AI config is managing', VALUE_REQUIRED),
        ]);
    }
}
