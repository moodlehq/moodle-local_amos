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
class local_amos_privacy_provider_testcase extends advanced_testcase {

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

        \local_amos\privacy\provider::get_users_in_context($userlist);

        $expected = [$u1->id, $u2->id];
        $actual = $userlist->get_userids();
        $this->assertEqualsCanonicalizing($expected, $actual);
    }
}
