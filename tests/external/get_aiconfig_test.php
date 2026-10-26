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

/**
 * Tests for the get_aiconfig external function.
 *
 * @package    block_ai_control
 * @copyright  2026 ISB Bayern
 * @author     Thomas Schönlein
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_aiconfig::class)]
final class get_aiconfig_test extends \advanced_testcase {
    /**
     * Creates a course with an enrolled student and optionally an ai_control block instance.
     *
     * @param bool $withblock if an ai_control block instance should be created in the course
     * @return array the course context, the block context (null without block) and the student role id
     */
    private function setup_course_with_student(bool $withblock): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $blockcontext = null;
        if ($withblock) {
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
            $blockcontext = \context_block::instance($blockid);
            (new aiconfig($coursecontext->id))->store();
        }
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        return [$coursecontext, $blockcontext, $roleid];
    }

    /**
     * A student without overrides can read the AI config.
     */
    public function test_execute_allowed_without_override(): void {
        $this->resetAfterTest();
        [$coursecontext] = $this->setup_course_with_student(true);

        $result = get_aiconfig::execute($coursecontext->id);
        $this->assertFalse($result->enabled);
    }

    /**
     * A block-level prohibition of block/ai_control:view must also be enforced by the web service.
     */
    public function test_execute_denied_by_block_override(): void {
        $this->resetAfterTest();
        [$coursecontext, $blockcontext, $roleid] = $this->setup_course_with_student(true);
        assign_capability('block/ai_control:view', CAP_PREVENT, $roleid, $blockcontext->id);

        $this->expectException(\required_capability_exception::class);
        get_aiconfig::execute($coursecontext->id);
    }

    /**
     * A course without an ai_control block instance has no block context to check, so the request fails.
     */
    public function test_execute_without_block_instance(): void {
        $this->resetAfterTest();
        [$coursecontext] = $this->setup_course_with_student(false);

        $this->expectException(\dml_missing_record_exception::class);
        get_aiconfig::execute($coursecontext->id);
    }
}
