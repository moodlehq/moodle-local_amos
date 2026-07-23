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

namespace local_amos\external;

use local_amos\local\amos_component;
use local_amos\local\amos_stage;
use local_amos\local\amos_string;
use local_amos\local\amos_version;

/**
 * Unit tests for external function {@see \local_amos\external\delete_translation}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2026 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class delete_translation_test extends \local_amos_testcase {
    /**
     * Test that permission check is performed.
     *
     * @runInSeparateProcess
     */
    public function test_execute_without_capability(): void {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $this->expectException(\required_capability_exception::class);
        delete_translation::execute(0);
    }

    /**
     * Test that a maintainer without the manage capability is still rejected.
     *
     * @runInSeparateProcess
     */
    public function test_execute_without_manage_capability(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $DB->insert_record('amos_translators', [
            'userid' => $USER->id,
            'lang' => 'cs',
            'status' => 0,
        ]);

        $this->expectException(\required_capability_exception::class);
        delete_translation::execute(0);
    }

    /**
     * Test basic behaviour of the method: deletes a bad backported translation record.
     *
     * This reproduces the scenario where a translation was imported with a manually
     * selected higher version (since=500), creating a duplicate erroneous record next
     * to the correct one (since=20). The since=500 record should be permanently
     * removable, while the since=20 record must remain untouched.
     *
     * @runInSeparateProcess
     */
    public function test_execute_basics(): void {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new amos_stage();

        $component = new amos_component('moodle', 'en', amos_version::by_code(20));
        $component->add_string(new amos_string('groupmode', 'Group mode'));
        $stage->add($component);
        $stage->commit('Introducing "Group mode" string', ['source' => 'unittest']);

        $component = new amos_component('moodle', 'cs', amos_version::by_code(20));
        $component->add_string(new amos_string('groupmode', 'Skupinový režim'));
        $stage->add($component);
        $stage->commit('Translating "Group mode" into Czech', ['source' => 'unittest']);

        // Simulate the buggy import: a duplicate translation manually committed at since=500.
        $component = new amos_component('moodle', 'cs', amos_version::by_code(500));
        $component->add_string(new amos_string('groupmode', 'Špatný duplicitní překlad'));
        $stage->add($component);
        $stage->commit('Backported bad import', ['source' => 'unittest']);

        $goodid = $DB->get_field('amos_translations', 'id', ['lang' => 'cs', 'component' => 'moodle',
            'strname' => 'groupmode', 'since' => 20], MUST_EXIST);
        $badid = $DB->get_field('amos_translations', 'id', ['lang' => 'cs', 'component' => 'moodle',
            'strname' => 'groupmode', 'since' => 500], MUST_EXIST);

        $response = delete_translation::execute($badid);
        $response = \core_external\external_api::clean_returnvalue(delete_translation::execute_returns(), $response);

        $this->assertEquals($badid, $response['translationid']);
        $this->assertFalse($DB->record_exists('amos_translations', ['id' => $badid]));
        $this->assertTrue($DB->record_exists('amos_translations', ['id' => $goodid]));
    }

    /**
     * Test that attempting to delete a non-existing record fails.
     *
     * @runInSeparateProcess
     */
    public function test_execute_invalid_id(): void {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        delete_translation::execute(0);
    }
}
