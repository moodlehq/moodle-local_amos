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
 * Provides {@see \local_amos\storage_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

use advanced_testcase;
use local_amos\local\amos_component;
use local_amos\local\amos_stage;
use local_amos\local\amos_string;
use local_amos\local\amos_version;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Unit tests for the mlanglib.php functionality after the storage DB layout update.
 *
 * @copyright 2020 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class storage_test extends advanced_testcase {
    /**
     * Test essential operations with the strings storage.
     */
    public function test_storage_essentials(): void {
        global $DB, $CFG, $USER;

        $this->resetAfterTest();

        // Committing a single string in 3.9.
        $now = time() - 5;
        $component = new amos_component('test', 'en', amos_version::by_branch('MOODLE_39_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome', $now));
        $stage = new amos_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $this->assertEquals(1, $DB->count_records('amos_strings'));
        $this->assertTrue($DB->record_exists('amos_strings', [
            'component' => 'test',
            'since' => 39,
            'strname' => 'welcome',
            'timemodified' => $now,
        ]));

        // Adding more strings in 3.10.
        $now = time() - 4;
        $stage = new amos_stage();
        $component = new amos_component('test', 'en', amos_version::by_branch('MOODLE_310_STABLE'));
        $component->add_string(new amos_string('hello', 'Hello', $now));
        $component->add_string(new amos_string('world', 'World', $now));
        $stage->add($component);
        $stage->commit('Two other string into AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $this->assertEquals(3, $DB->count_records('amos_strings'));

        // Deleting the first string in 3.11.
        $now = time() - 3;
        $stage = new amos_stage();
        $component = new amos_component('test', 'en', amos_version::by_branch('MOODLE_311_STABLE'));
        $component->add_string(new amos_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        // Nothing on pre-3.9 versions here.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_38_STABLE'));
        $this->assertFalse($component->has_string());

        // Load 3.9 snapshot.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_39_STABLE'));
        $this->assertTrue($component->has_string());
        $this->assertTrue($component->has_string('welcome'));
        $this->assertEquals(1, $component->get_number_of_strings());
        $component->clear();
        unset($component);

        // Load 3.10 snapshot - one 3.9 string inherited, two more added.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_310_STABLE'));
        $this->assertTrue($component->has_string());
        $this->assertTrue($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $this->assertEquals(3, $component->get_number_of_strings());
        $component->clear();
        unset($component);

        // Load 3.11 snapshot - only the two strings inherited from 3.10.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_311_STABLE'));
        $this->assertTrue($component->has_string());
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $this->assertEquals(2, $component->get_number_of_strings());
        $component->clear();
        unset($component);

        // Load 3.11 snapshot included the deleted strings.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_311_STABLE'), null, true);
        $this->assertTrue($component->has_string());
        $this->assertTrue($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $this->assertEquals(3, $component->get_number_of_strings());
        $component->clear();
        unset($component);
    }
}
