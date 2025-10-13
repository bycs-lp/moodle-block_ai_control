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
 * Settings for the block_ai_control plugin.
 *
 * @package    block_ai_control
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add(
        'blocksettings',
        new admin_category(
            'block_ai_control_settings',
            new lang_string('pluginname', 'block_ai_control')
        )
    );

    if ($ADMIN->fulltree) {
        $settings->add(
            new admin_setting_confightmleditor(
                'block_ai_control/infotext',
                new lang_string('infotext', 'block_ai_control'),
                new lang_string('infotextdesc', 'block_ai_control'),
                '',
            )
        );
    }
}
