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
 * Unit tests for external function {@see \local_amos\external\make_translation_uptodate}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_external_make_translation_uptodate_testcase extends local_amos_testcase {

    /**
     * Test that permission check is performed.
     *
     * @runInSeparateProcess
     */
    public function test_execute_without_capability() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $spammer = create_role('Nonmaintainer', 'nonmaintainer', 'Prohibits committing to any lang pack.');
        assign_capability('local/amos:commit', CAP_PROHIBIT, $spammer, SYSCONTEXTID);

        role_assign($spammer, $user->id, SYSCONTEXTID);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(required_capability_exception::class);
        \local_amos\external\make_translation_uptodate::execute(0, 0);
    }

    /**
     * Test that only defined language pack maintainers can use the method.
     *
     * @runInSeparateProcess
     */
    public function test_execute_without_being_maintainer() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        $now = time();
        self::setAdminUser();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new mlang_stage();

        $component = new mlang_component('moodle', 'en', mlang_version::by_code(20));
        $component->add_string(new mlang_string('groupmode', 'Group Mode', $now - 100));
        $stage->add($component);
        $stage->commit('Introducing "Group Mode" string', ['source' => 'unittest']);

        $component = new mlang_component('moodle', 'cs', mlang_version::by_code(20));
        $component->add_string(new mlang_string('groupmode', 'Skupinový režim', $now - 90));
        $stage->add($component);
        $stage->commit('Translating "Group Mode" string into Czech', ['source' => 'unittest']);

        $component = new mlang_component('moodle', 'en', mlang_version::by_code(21));
        $component->add_string(new mlang_string('groupmode', 'Group mode', $now - 80));
        $stage->add($component);
        $stage->commit('Correcting the letter case in the string', ['source' => 'unittest']);

        $originalid = $DB->get_field('amos_strings', 'id', ['component' => 'moodle', 'strname' => 'groupmode', 'since' => 21],
            MUST_EXIST);
        $translationid = $DB->get_field('amos_translations', 'id', ['lang' => 'cs', 'component' => 'moodle',
            'strname' => 'groupmode', 'since' => 20], MUST_EXIST);

        $this->expectException(invalid_parameter_exception::class);
        $this->expectExceptionMessage('Invalid parameters: not a maintainer of cs');

        \local_amos\external\make_translation_uptodate::execute($originalid, $translationid);
    }

    /**
     * Test basic behaviour of the method.
     *
     * @runInSeparateProcess
     */
    public function test_execute_basics() {
        global $DB, $USER;
        $this->resetAfterTest(true);

        $now = time();
        self::setAdminUser();

        $DB->insert_record('amos_translators', [
            'userid' => $USER->id,
            'lang' => 'cs',
            'status' => 0,
        ]);

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new mlang_stage();

        $component = new mlang_component('moodle', 'en', mlang_version::by_code(20));
        $component->add_string(new mlang_string('groupmode', 'Group Mode', $now - 100));
        $stage->add($component);
        $stage->commit('Introducing "Group Mode" string', ['source' => 'unittest']);

        $component = new mlang_component('moodle', 'cs', mlang_version::by_code(20));
        $component->add_string(new mlang_string('groupmode', 'Skupinový režim', $now - 90));
        $stage->add($component);
        $stage->commit('Translating "Group Mode" string into Czech', ['source' => 'unittest']);

        $component = new mlang_component('moodle', 'en', mlang_version::by_code(21));
        $component->add_string(new mlang_string('groupmode', 'Group mode', $now - 80));
        $stage->add($component);
        $stage->commit('Correcting the letter case in the string', ['source' => 'unittest']);

        $originalid = $DB->get_field('amos_strings', 'id', ['component' => 'moodle', 'strname' => 'groupmode', 'since' => 21],
            MUST_EXIST);
        $translationid = $DB->get_field('amos_translations', 'id', ['lang' => 'cs', 'component' => 'moodle',
            'strname' => 'groupmode', 'since' => 20], MUST_EXIST);

        $response = \local_amos\external\make_translation_uptodate::execute($originalid, $translationid);
        $response = \core_external\external_api::clean_returnvalue(\local_amos\external\make_translation_uptodate::execute_returns(), $response);

        $this->assertTrue(is_array($response));
        $this->assertEquals('Skupinový režim', $DB->get_field('amos_translations', 'strtext', [
            'id' => $response['translationid'],
        ]));
        $this->assertEquals('2.1+', $response['displaytranslationsince']);
    }
}
