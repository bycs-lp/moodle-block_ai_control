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
use local_ai_manager\local\userinfo;

/**
 * Test class for the hook_callbacks class.
 *
 * @package    block_ai_control
 * @copyright  2024 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Tests the creation and storing of the aiconfig object.
     *
     * @dataProvider handle_additional_user_restriction_provider
     * @covers       \block_ai_control\local\hook_callbacks::handle_additional_user_restriction
     * @param bool $aiconfigexists if there exists an aiconfig. It will exist if a block exists in a course so it is equivalent to
     *  the fact that the teacher has adden an AI control center block in the course.
     * @param bool $enabled if the AI config for the test course should be enabled (and a time in the future is being set so that
     *  AI tools are indeed accessible)
     * @param bool $purposeenabled if the purpose which is being used for testing is enabled in the AI config in the course
     * @param bool $coursecontext if the test should be run in the course context or a context outside
     * @param bool $controlcapability if the user used for testing should have the 'block/ai_control:control' capability, which
     *  means the user usually has the teacher role in the course
     * @param bool $expected the expected output: true, if the hook is allowing access, false otherwise
     * @param string|null $expectedmessage the expected message if access is blocked
     */
    public function test_handle_additional_user_restriction(
        bool $aiconfigexists,
        bool $enabled,
        bool $purposeenabled,
        bool $coursecontext,
        bool $controlcapability,
        bool $expected,
        ?string $expectedmessage
    ): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        enrol_try_internal_enrol($course->id, $user->id);
        $context = $coursecontext ? \context_course::instance($course->id) : \context_system::instance();
        if ($controlcapability && $context === $coursecontext) {
            $roleid = $this->getDataGenerator()->create_role(['shortname' => 'aicontroller']);
            $this->getDataGenerator()->role_assign($roleid, $user->id, $context);
            assign_capability('block/ai_control:control', CAP_ALLOW, $roleid, $context);
        }

        $purposestring = 'chat';
        $purpose = \core\di::get(\local_ai_manager\local\connector_factory::class)->get_purpose_by_purpose_string($purposestring);
        if ($aiconfigexists) {
            $aiconfig = new aiconfig(\context_course::instance($course->id)->id);
            $aiconfig->set_enabled($enabled);
            $aiconfig->set_expiresat(-1);
            $enabledpurposes = [];
            if ($purposeenabled) {
                $enabledpurposes[] = $purposestring;
            }
            $aiconfig->set_enabledpurposes($enabledpurposes);
            $aiconfig->store();
        }

        // We do not use this. The hook provides it however, so we create a default userinfo object to pass it to the hook.
        $userinfo = new userinfo($user->id);

        $hook = new \local_ai_manager\hook\additional_user_restriction($userinfo, $context, $purpose);
        hook_callbacks::handle_additional_user_restriction($hook);
        if ($expected) {
            $this->assertTrue($hook->is_allowed());
            $this->assertSame('', $hook->get_message());
        } else {
            $this->assertFalse($hook->is_allowed());
            $this->assertSame($expectedmessage, $hook->get_message());
        }
    }

    /**
     * Provider for test_handle_additional_user_restriction.
     *
     * @return array the provider data
     */
    public static function handle_additional_user_restriction_provider(): array {
        $allgoodconfig = [
            'aiconfigexists' => true,
            'enabled' => true,
            'purposeenabled' => true,
            'coursecontext' => true,
            'controlcapability' => false,
        ];
        return [
            'noaiconfig' => [
                ...$allgoodconfig,
                'aiconfigexists' => false,
                'expected' => false,
                'expectedmessage' => get_string('noaiincourse', 'block_ai_control'),
            ],
            'studentallowed' => [
                ...$allgoodconfig,
                'expected' => true,
                'expectedmessage' => null,
            ],
            'notenabled' => [
                ...$allgoodconfig,
                'enabled' => false,
                'expected' => false,
                'expectedmessage' => get_string('noaiincourse', 'block_ai_control'),
            ],
            'purposedisabled' => [
                ...$allgoodconfig,
                'purposeenabled' => false,
                'expected' => false,
                'expectedmessage' => get_string('notallowedincourse', 'block_ai_control', 'chat'),
            ],
            'disabledbutteacher' => [
                ...$allgoodconfig,
                'controlcapability' => true,
                'expected' => true,
                'expectedmessage' => null,
            ],
            'disabledbutothercontext' => [
                ...$allgoodconfig,
                'enabled' => false,
                'coursecontext' => false,
                'expected' => true,
                'expectedmessage' => null,
            ],
        ];
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
