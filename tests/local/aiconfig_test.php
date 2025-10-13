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

use local_ai_manager\base_purpose;

/**
 * Test class for the aiconfig class.
 *
 * @package    block_ai_control
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class aiconfig_test extends \advanced_testcase {
    /**
     * Tests the creation and storing of the aiconfig object.
     *
     * @covers \block_ai_control\local\aiconfig::__construct
     * @covers \block_ai_control\local\aiconfig::load_from_db
     * @covers \block_ai_control\local\aiconfig::store
     */
    public function test_constructor(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $category = $this->getDataGenerator()->create_category();
        try {
            new aiconfig(\context_coursecat::instance($category->id)->id);
        } catch (\Exception $e) {
            $this->assertTrue($e instanceof \coding_exception);
        }

        // This will not throw an exception.
        $aiconfig = new aiconfig(\context_course::instance($course->id)->id);
        $aiconfig->set_enabled(true);
        $aiconfig->set_expiresat(-1);
        $purposes = array_values(base_purpose::get_all_purposes());
        $aiconfig->set_enabledpurposes($purposes);
        $aiconfig->store();
        $record = $DB->record_exists('block_ai_control_config', ['contextid' => \context_course::instance($course->id)->id]);
        $this->assertNotFalse($record);

        // Overwrite and thus reload the aiconfig object.
        $aiconfig = new aiconfig(\context_course::instance($course->id)->id);
        $this->assertTrue($aiconfig->is_enabled());
        $this->assertEquals(-1, $aiconfig->get_expiresat());
        $this->assertEquals($purposes, $aiconfig->get_enabledpurposes());
    }

    /**
     * Tests the config if the access to the AI tools is enabled including the expiry time.
     *
     * @covers \block_ai_control\local\aiconfig::is_enabled
     * @covers \block_ai_control\local\aiconfig::set_enabled
     * @covers \block_ai_control\local\aiconfig::get_expiresat
     * @covers \block_ai_control\local\aiconfig::set_expiresat
     */
    public function test_enabled_and_expiry_time(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $aiconfig = new aiconfig(\context_course::instance($course->id)->id);

        // We check if the enabled state including the setting of the expiry time works.
        $aiconfig->set_enabled(true);
        $currenttime = time();
        $clock = $this->mock_clock_with_frozen($currenttime);
        $aiconfig->set_expiresat(time() + HOURSECS);
        $this->assertTrue($aiconfig->is_enabled());

        // Now fake that an hour plus 5 seconds has passed.
        $clock->bump(HOURSECS + 5);
        $this->assertFalse($aiconfig->is_enabled());

        // Now check that disabling works independent of the expiry time.
        $this->mock_clock_with_frozen($currenttime);
        $aiconfig->set_expiresat($currenttime + HOURSECS);
        $this->assertTrue($aiconfig->is_enabled());
        $aiconfig->set_enabled(false);
        $this->assertFalse($aiconfig->is_enabled());

        // Now check the infinity expiry setting.
        $aiconfig->set_enabled(true);
        $aiconfig->set_expiresat(-1);
        $this->assertTrue($aiconfig->is_enabled());
    }

    /**
     * Tests the enabled purpose configurations.
     *
     * @covers \block_ai_control\local\aiconfig::get_enabledpurposes
     * @covers \block_ai_control\local\aiconfig::set_enabledpurposes
     * @covers \block_ai_control\local\aiconfig::enable_purpose
     * @covers \block_ai_control\local\aiconfig::disable_purpose
     */
    public function test_enabled_purposes(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $aiconfig = new aiconfig(\context_course::instance($course->id)->id);

        // This is an array of purpose plugin name strings.
        $purposes = base_purpose::get_all_purposes();
        $this->assertGreaterThan(0, count($purposes) > 0);
        $aiconfig->set_enabledpurposes($purposes);
        $this->assertEquals($purposes, $aiconfig->get_enabledpurposes());

        $disabledpurpose = array_pop($purposes);
        $aiconfig->set_enabledpurposes($purposes);
        $this->assertEquals($purposes, $aiconfig->get_enabledpurposes());

        $aiconfig->enable_purpose($disabledpurpose);
        $this->assertTrue(in_array($disabledpurpose, $aiconfig->get_enabledpurposes()));
        $aiconfig->disable_purpose($disabledpurpose);
        $this->assertFalse(in_array($disabledpurpose, $aiconfig->get_enabledpurposes()));

        $fakepurpose = 'fakepurpose';
        try {
            $aiconfig->set_enabledpurposes([$fakepurpose]);
        } catch (\Exception $e) {
            $this->assertTrue($e instanceof \coding_exception);
        }
    }
}
