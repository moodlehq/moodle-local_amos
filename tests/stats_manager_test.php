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

require_once($CFG->dirroot.'/local/amos/mlanglib.php');

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

    /**
     * Test obtaining stats for the given plugin.
     */
    public function test_get_component_stats() {
        $this->resetAfterTest();

        $this->helper_add_language('en', 'English');
        $this->helper_add_language('cs', 'Czech');
        $this->helper_add_language('de', 'German');

        $statsman = new local_amos_stats_manager();

        $this->assertFalse($statsman->get_component_stats('tool_foo'));

        $statsman->update_stats('3700', 'en', 'tool_foo', 12);
        $statsman->update_stats('3600', 'en', 'tool_foo', 9);

        $statsman->update_stats('3700', 'cs', 'tool_foo', 10);
        $statsman->update_stats('3600', 'cs', 'tool_foo', 6);
        $statsman->update_stats('3500', 'cs', 'tool_foo', 8);

        $statsman->update_stats('3700', 'de', 'tool_foo', 12);
        $statsman->update_stats('3600', 'de', 'tool_foo', 10000000);

        $statsman->update_stats('3700', 'fr', 'tool_foo', 1);

        $raw = $statsman->get_component_stats('tool_foo');

        $this->assertDebuggingCalled('Unknown language code: fr');
        $this->assertTrue(isset($raw['lastmodified']));
        $this->assertTrue(isset($raw['langnames']));
        $this->assertEquals(3, count($raw['langnames']));
        $this->assertContains(['lang' => 'en', 'name' => 'English (en)'], $raw['langnames']);
        $this->assertContains(['lang' => 'cs', 'name' => 'Czech (cs)'], $raw['langnames']);
        $this->assertContains(['lang' => 'de', 'name' => 'German (de)'], $raw['langnames']);
        $this->assertNotEmpty($raw['branches']);

        foreach ($raw['branches'] as $branchinfo) {
            $this->assertTrue(isset($branchinfo['branch']));
            $this->assertNotEmpty($branchinfo['languages']);
            foreach ($branchinfo['languages'] as $langinfo) {
                 $this->assertTrue(isset($langinfo['lang']));
                 $this->assertTrue(isset($langinfo['numofstrings']));
                 $this->assertTrue(isset($langinfo['ratio']));
                 $data[$branchinfo['branch']][$langinfo['lang']] = $langinfo['ratio'];
            }
        }

        $this->assertEquals(100, $data['3.7']['en']);
        $this->assertEquals(83, $data['3.7']['cs']);
        $this->assertEquals(100, $data['3.7']['de']);
        $this->assertEquals(100, $data['3.6']['de']);
        $this->assertFalse(isset($data['3.7']['fr']));
        $this->assertFalse(isset($data['3.5']['cs']));
    }

    /**
     * Helper function for registering a known language in AMOS.
     *
     * @param string $code
     * @param string $name
     */
    protected function helper_add_language($code, $name) {

        $stage = new mlang_stage();

        foreach ([
            mlang_version::by_branch('MOODLE_37_STABLE'),
            mlang_version::by_branch('MOODLE_36_STABLE'),
            mlang_version::by_branch('MOODLE_35_STABLE'),
        ] as $mlangversion) {
            $component = new mlang_component('langconfig', $code, $mlangversion);
            $component->add_string(new mlang_string('thislanguageint', $name));
            $stage->add($component);
            $component->clear();
        }

        $stage->commit('Registering a new language '.$name, array('source' => 'unittest'));

        // Rebuild the cache.
        mlang_tools::list_languages(true, false);
    }
}
