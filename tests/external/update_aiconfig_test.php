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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the update_aiconfig external function.
 *
 * @package    block_ai_control
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(update_aiconfig::class)]
final class update_aiconfig_test extends \advanced_testcase {
    /**
     * Creates a course with an ai_control block instance and an enrolled editing teacher.
     *
     * @return array the course context, the block context and the editing teacher role id
     */
    private function setup_course_with_block(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $blockid = $DB->insert_record('block_instances', (object) [
            'blockname' => 'ai_control',
            'parentcontextid' => $coursecontext->id,
            'showinsubcontexts' => 1,
            'pagetypepattern' => '*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 1,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        (new aiconfig($coursecontext->id))->store();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        return [$coursecontext, \context_block::instance($blockid), $roleid];
    }

    #[Group('baseline')]
    /**
     * A block-level prohibition of block/ai_control:control must also be enforced by the web service.
     */
    public function test_execute_denied_by_block_override(): void {
        $this->resetAfterTest();
        [$coursecontext, $blockcontext, $roleid] = $this->setup_course_with_block();
        assign_capability('block/ai_control:control', CAP_PREVENT, $roleid, $blockcontext->id);

        try {
            update_aiconfig::execute(['id' => $coursecontext->id, 'enabled' => true, 'expiresat' => -1, 'purposes' => []]);
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertFalse((new aiconfig($coursecontext->id))->is_enabled());
        }
    }

    #[Group('baseline')]
    /**
     * An editing teacher without overrides can still update the AI config.
     */
    public function test_execute_allowed_without_override(): void {
        $this->resetAfterTest();
        [$coursecontext] = $this->setup_course_with_block();

        update_aiconfig::execute(['id' => $coursecontext->id, 'enabled' => true, 'expiresat' => -1, 'purposes' => []]);
        $this->assertTrue((new aiconfig($coursecontext->id))->is_enabled());
    }
}
