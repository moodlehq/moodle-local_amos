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
 * Provides the {@link local_amos_stats_manager_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Tests for the {@link local_amos_stats_manager} class.
 */
class local_amos_stats_manager_test extends advanced_testcase {

    /**
     * Test the workflow for updating the stats.
     */
    public function test_update_stats() {
        global $DB;
        $this->resetAfterTest();

        $statsman = new local_amos_stats_manager();

        // Inserting fresh data.
        $statsman->update_stats('3700', 'cs', 'tool_foo', 78);
        $statsman->update_stats('3700', 'en', 'tool_foo', 81);

        $this->assertEquals(78, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3700, 'lang' => 'cs', 'component' => 'tool_foo']));
        $this->assertEquals(81, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3700, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data from another branch do not affect data on other branches.
        $statsman->update_stats('3600', 'en', 'tool_foo', 72);

        $this->assertEquals(72, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3600, 'lang' => 'en', 'component' => 'tool_foo']));
        $this->assertEquals(81, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3700, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data can be updated.
        $statsman->update_stats('3700', 'en', 'tool_foo', 85);

        $this->assertEquals(85, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3700, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data can be NULL and NULL'ed.
        $statsman->update_stats('3800', 'cs', 'tool_foo', null);
        $statsman->update_stats('3700', 'en', 'tool_foo', null);

        $this->assertSame(null, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3800, 'lang' => 'cs', 'component' => 'tool_foo']));
        $this->assertSame(null, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 3700, 'lang' => 'en', 'component' => 'tool_foo']));
    }
}

