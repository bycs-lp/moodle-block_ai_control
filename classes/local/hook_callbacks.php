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

use core\clock;
use core_course\hook\after_form_definition_after_data;
use core_course\hook\after_form_submission;
use local_ai_manager\ai_manager_utils;
use local_ai_manager\hook\additional_user_restriction;

/**
 * Hook listener callbacks.
 *
 * @package    block_ai_control
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add a checkbox to add an ai_control block.
     *
     * @param \core_course\hook\after_form_definition $hook
     */
    public static function handle_after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $configmanager = \core\di::get(\local_ai_manager\local\config_manager::class);
        if ($configmanager->is_tenant_enabled()) {
            $mform = $hook->mform;
            ai_manager_utils::add_ai_tools_category_to_mform($mform);
            $mform->addElement(
                'checkbox',
                'addaicontrol',
                get_string('pluginname', 'block_ai_control'),
                get_string('addblockinstance', 'block_ai_control')
            );
            $mform->addHelpButton('addaicontrol', 'addblockinstance', 'block_ai_control');
            $mform->setDefault('addaicontrol', 0);
        }
    }

    /**
     * Check for form setting to add/remove ai_control block and handle accordlingly.
     *
     * @param after_form_submission $hook the hook to retrieve the data from.
     */
    public static function handle_after_form_submission(after_form_submission $hook): void {
        global $DB;
        // Get form data.
        $data = $hook->get_data();

        $clock = \core\di::get(clock::class);

        $courseid = $data->id;
        $coursecontextid = \context_course::instance($courseid)->id;

        $existingblockinstance = $DB->get_record(
            'block_instances',
            ['blockname' => 'ai_control', 'parentcontextid' => $coursecontextid]
        );

        if (!empty($data->addaicontrol)) {
            if (!$existingblockinstance) {
                // Add block instance.
                $newinstance = new \stdClass();
                $newinstance->blockname = 'ai_control';
                $newinstance->parentcontextid = $coursecontextid;
                // We want to make the block usable for single activity courses as well, so display in subcontexts.
                $newinstance->showinsubcontexts = 1;
                $newinstance->pagetypepattern = '*';
                $newinstance->subpagepattern = null;
                $newinstance->defaultregion = 'side-pre';
                $newinstance->defaultweight = 1;
                $newinstance->configdata = '';
                $newinstance->timecreated = $clock->time();
                $newinstance->timemodified = $newinstance->timecreated;
                $newinstance->id = $DB->insert_record('block_instances', $newinstance);
                $aiconfig = new aiconfig($coursecontextid);
                $aiconfig->store();
            }
        } else {
            // If tenant is not allowed, $data->addaicontrol will be empty,
            // so an existing instance will be deleted by following lines.
            if ($existingblockinstance) {
                // Remove block instance.
                blocks_delete_instance($existingblockinstance);
            }
        }
    }

    /**
     * Check if block instance is present and set addaichat form setting.
     *
     * @param after_form_definition_after_data $hook
     */
    public static function handle_after_form_definition_after_data(after_form_definition_after_data $hook): void {
        global $DB;

        // Get form data.
        $mform = $hook->mform;
        $formwrapper = $hook->formwrapper;
        if (!empty($formwrapper->get_course()->id)) {
            $courseid = $formwrapper->get_course()->id;
            $coursecontextid = \context_course::instance($courseid)->id;

            $blockinstance = $DB->get_record(
                'block_instances',
                ['blockname' => 'ai_control', 'parentcontextid' => $coursecontextid]
            );
            if ($blockinstance) {
                // Block present, so set checkbox accordingly.
                $mform->setDefault('addaicontrol', 1);
            }
        }
    }

    /**
     * Hook callback for the additional_user_restriction hook.
     *
     * This callback basically applies the config set by the block_ai_control to the ai_manager. That means that this function
     * makes a request to the AI manager fail if the AI config of block_ai_control in the current course restricts this.
     *
     * @param additional_user_restriction $hook the hook object
     */
    public static function handle_additional_user_restriction(additional_user_restriction $hook): void {
        $coursecontext = ai_manager_utils::find_closest_parent_course_context($hook->get_context());
        if (is_null($coursecontext)) {
            // We could not find a usable course context, so we do not do anything.
            return;
        }
        $aiconfig = new aiconfig($coursecontext->id);

        $message = null;
        if (!$aiconfig->record_exists() || !$aiconfig->is_enabled()) {
            $message = get_string('noaiincourse', 'block_ai_control');
        } else if (!in_array($hook->get_purpose()->get_plugin_name(), $aiconfig->get_enabledpurposes())) {
            $message = get_string('notallowedincourse', 'block_ai_control', $hook->get_purpose()->get_plugin_name());
        }

        if ($message !== null && !has_capability('block/ai_control:control', $coursecontext)) {
            $hook->set_access_allowed(false, 403, $message);
        }
    }
}
