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
 * Unit tests for external function {@see \local_amos\external\get_translator_data}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_external_get_translator_data_testcase extends local_amos_testcase {

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
        \local_amos\external\get_translator_data::execute('');
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
        $component->clear();

        $stage->commit('First string', ['source' => 'unittest']);

        // Emaulate what URLSearchParams.toString() does on the client side.
        $filterquery = http_build_query([
            '__lazyform_amosfilter' => 1,
            'sesskey' => sesskey(),
            'fcmp' => ['foo_bar', 'baz_quix'],
            'flng' => ['cs'],
            'flast' => 1,
            'ftxt' => 'Foo bar',
        ], '', '&');

        $response = \local_amos\external\get_translator_data::execute($filterquery);
        $response = external_api::clean_returnvalue(\local_amos\external\get_translator_data::execute_returns(), $response);

        $this->assertTrue(isset($response['json']));

        $data = json_decode($response['json'], true);

        $this->assertEquals(1, $data['missing']);
        $this->assertEquals(1, count($data['strings']));
        $this->assertEquals('Foo bar', $data['strings'][0]['original']);
    }
}
