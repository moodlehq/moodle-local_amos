<?php
// This file is part of Moodle - https://moodle.org/
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
 * Unit tests for external function {@see \local_amos\external\get_string_timeline}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_external_get_string_timeline_testcase extends local_amos_testcase {

    /**
     * Test that permission check is performed.
     */
    public function test_execute_without_capability() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $spammer = create_role('Spammer', 'spammer', 'Prohibits contributions to the site');
        assign_capability('local/amos:stage', CAP_PROHIBIT, $spammer, SYSCONTEXTID);

        role_assign($spammer, $user->id, SYSCONTEXTID);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(required_capability_exception::class);
        \local_amos\external\get_string_timeline::execute('', '', '');
    }

    /**
     * Test basic behaviour of the method.
     */
    public function test_execute_basics() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new mlang_stage();
        $component = new mlang_component('foo_bar', 'en', mlang_version::by_code(39));
        $component->add_string(new mlang_string('foobar', 'Foo bar'));
        $stage->add($component);
        $stage->commit('First string', ['source' => 'unittest']);

        $stage = new mlang_stage();
        $component = new mlang_component('foo_bar', 'cs', mlang_version::by_code(39));
        $component->add_string(new mlang_string('foobar', 'Fů bár'));
        $stage->add($component);
        $stage->commit('První překlad', ['source' => 'unittest']);

        $stage = new mlang_stage();
        $component = new mlang_component('foo_bar', 'en', mlang_version::by_code(310));
        $component->add_string(new mlang_string('foobar', 'Foo bar!'));
        $stage->add($component);
        $stage->commit('Add exclamation mark in Moodle 3.10', ['source' => 'unittest']);

        $response = \local_amos\external\get_string_timeline::execute('foo_bar', 'foobar', 'cs');
        $response = external_api::clean_returnvalue(\local_amos\external\get_string_timeline::execute_returns(), $response);

        $this->assertEquals('Foo bar!', $response['changes'][0]['english']['displaytext']);
        $this->assertEquals('en', $response['changes'][0]['english']['langcode']);
        $this->assertEquals('3.10+', $response['changes'][0]['english']['displaysince']);
        $this->assertFalse($response['changes'][0]['translation']['hascontent']);

        $this->assertFalse($response['changes'][1]['english']['hascontent']);
        $this->assertEquals('3.9+', $response['changes'][1]['translation']['displaysince']);
        $this->assertEquals('Fů bár', $response['changes'][1]['translation']['displaytext']);
    }
}
