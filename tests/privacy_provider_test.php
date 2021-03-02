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
 * Provides the {@see local_amos_privacy_provider_testcase} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Unit tests for the {@see \local_amos\privacy\provider} class.
 *
 * @copyright 2018 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test {@see \local_amos\privacy\provider::get_contexts_for_userid()} implementation.
     */
    public function test_get_contexts_for_userid() {
        global $DB;
        $this->resetAfterTest();

        $u = $this->getDataGenerator()->create_user();

        $contextlist = \local_amos\privacy\provider::get_contexts_for_userid($u->id);
        $this->assertInstanceOf(\core_privacy\local\request\contextlist::class, $contextlist);
        $this->assertEquals([SYSCONTEXTID], $contextlist->get_contextids());
    }

    /**
     * Test {@see \local_amos\privacy\provider::get_users_in_context()} implementation.
     */
    public function test_get_users_in_context() {
        global $DB;
        $this->resetAfterTest();

        $context = context_system::instance();

        $userlist = new \core_privacy\local\request\userlist($context, 'local_amos');

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $u4 = $this->getDataGenerator()->create_user();

        $DB->insert_record('amos_commits', [
            'source' => 'test',
            'timecommitted' => time(),
            'commitmsg' => 'Dummy commit',
            'commithash' => sha1('foo bar'),
            'userid' => $u1->id,
            'userinfo' => 'Test User1 <u1@example.com>',
        ]);

        $DB->insert_record('amos_translators', [
            'userid' => $u2->id,
            'lang' => 'cs',
            'status' => 0,
        ]);

        $DB->insert_record('amos_preferences', [
            'userid' => $u3->id,
            'name' => 'test_pref_name',
            'value' => 'Test preference value',
        ]);

        \local_amos\privacy\provider::get_users_in_context($userlist);

        $expected = [$u1->id, $u2->id, $u3->id];
        $actual = $userlist->get_userids();
        $this->assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * Test {@see \local_amos\privacy\provider::export_data()} implementation.
     */
    public function test_export_user_data() {
        global $DB;
        $this->resetAfterTest();

        $syscontext = \context_system::instance();

        $u1 = $this->getDataGenerator()->create_user();

        $DB->insert_record('amos_preferences', [
            'userid' => $u1->id,
            'name' => 'filter_default',
            'value' => '{}',
        ]);

        $contextlist = new \core_privacy\local\request\approved_contextlist($u1, 'local_amos', [$syscontext->id]);

        \local_amos\privacy\provider::export_user_data($contextlist);

        $writer = \core_privacy\local\request\writer::with_context($syscontext);

        $data = $writer->get_data([
            'AMOS',
            get_string('preferences', 'core'),
        ]);

        $this->assertEquals('{}', $data->filter_default);
    }
}
