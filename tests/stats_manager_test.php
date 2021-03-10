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
 * Provides the {@see local_amos_stats_manager_test} class.
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
 * Tests for the {@see local_amos_stats_manager} class.
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
        $statsman->update_stats('37', 'cs', 'tool_foo', 78);
        $statsman->update_stats('37', 'en', 'tool_foo', 81);

        $this->assertEquals(78, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'cs', 'component' => 'tool_foo']));
        $this->assertEquals(81, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data from another branch do not affect data on other branches.
        $statsman->update_stats('36', 'en', 'tool_foo', 72);

        $this->assertEquals(72, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 36, 'lang' => 'en', 'component' => 'tool_foo']));
        $this->assertEquals(81, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data can be updated.
        $statsman->update_stats('37', 'en', 'tool_foo', 85);

        $this->assertEquals(85, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));

        // Data can be NULL and NULL'ed.
        $statsman->update_stats('38', 'cs', 'tool_foo', null);
        $statsman->update_stats('37', 'en', 'tool_foo', null);

        $this->assertSame(null, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 38, 'lang' => 'cs', 'component' => 'tool_foo']));
        $this->assertSame(null, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));
    }

    /**
     * Test updating stats when using the write buffer.
     */
    public function test_buffered_workflow() {
        global $DB;
        $this->resetAfterTest();

        $statsman = new local_amos_stats_manager();

        $statsman->update_stats(36, 'en', 'tool_foo', 80);
        $statsman->update_stats(37, 'en', 'tool_foo', 80);

        $statsman->add_to_buffer('37', 'en', 'tool_foo', 81);
        $statsman->add_to_buffer('37', 'en', 'tool_bar', 42);
        $statsman->add_to_buffer('37', 'cs', 'tool_foo', 78);

        $this->assertEquals(80, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));
        $this->assertFalse($DB->record_exists('amos_stats', ['branch' => 37, 'lang' => 'en', 'component' => 'tool_bar']));
        $this->assertFalse($DB->record_exists('amos_stats', ['branch' => 37, 'lang' => 'cs', 'component' => 'tool_foo']));

        $statsman->write_buffer();

        $this->assertEquals(81, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_foo']));
        $this->assertEquals(42, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'en', 'component' => 'tool_bar']));
        $this->assertEquals(78, $DB->get_field('amos_stats', 'numofstrings',
            ['branch' => 37, 'lang' => 'cs', 'component' => 'tool_foo']));
    }

    /**
     * Test obtaining stats for the given plugin.
     */
    public function test_get_component_stats() {
        $this->resetAfterTest();

        set_config('branchesall', '35,36,37', 'local_amos');

        $statsman = new local_amos_stats_manager();

        $this->helper_add_language('en', 'English');
        $this->helper_add_language('cs', 'Czech', 'Čeština');
        $this->helper_add_language('de', 'German', 'Deutsch');
        $this->helper_add_language('en_us', 'English (US)');
        $this->helper_add_language('sk', 'Slovak', 'Slovenčina');

        $this->assertFalse($statsman->get_component_stats('tool_foo'));

        $statsman->update_stats('37', 'en', 'tool_foo', 12);
        $statsman->update_stats('36', 'en', 'tool_foo', 9);

        $statsman->update_stats('37', 'cs', 'tool_foo', 10);
        $statsman->update_stats('36', 'cs', 'tool_foo', 6);
        $statsman->update_stats('35', 'cs', 'tool_foo', 8);

        $statsman->update_stats('37', 'de', 'tool_foo', 12);
        $statsman->update_stats('36', 'de', 'tool_foo', 10000000);

        $statsman->update_stats('37', 'fr', 'tool_foo', 1);

        $statsman->update_stats('36', 'sk', 'tool_foo', 0);
        $statsman->update_stats('35', 'sk', 'tool_foo', 0);

        $raw = $statsman->get_component_stats('tool_foo');

        $this->assertDebuggingCalled('Unknown language code: fr');
        $this->assertTrue(isset($raw['lastmodified']));
        $this->assertTrue(isset($raw['langnames']));
        $this->assertEquals(3, count($raw['langnames']));
        $this->assertContains(['lang' => 'en', 'name' => 'English [en]'], $raw['langnames']);
        $this->assertContains(['lang' => 'cs', 'name' => 'Czech [cs]'], $raw['langnames']);
        $this->assertContains(['lang' => 'de', 'name' => 'German [de]'], $raw['langnames']);
        $this->assertNotEmpty($raw['branches']);

        foreach ($raw['branches'] as $branchinfo) {
            $this->assertTrue(isset($branchinfo['branch']));
            $this->assertNotEmpty($branchinfo['languages']);
            foreach ($branchinfo['languages'] as $langinfo) {
                 $this->assertTrue(isset($langinfo['lang']));
                 $this->assertTrue(isset($langinfo['numofstrings']));
                 $this->assertTrue(isset($langinfo['ratio']));
                 $data[$branchinfo['branch']][$langinfo['lang']] = $langinfo['ratio'];
                 $this->assertTrue($langinfo['lang'] !== 'sk');
                 $this->assertTrue($langinfo['lang'] !== 'en_us');
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
     * Test obtaining stats showing the translation completeness.
     */
    public function test_get_language_pack_ratio_stats() {
        $this->resetAfterTest();

        set_config('branchesall', '35,36,37', 'local_amos');

        $statsman = new local_amos_stats_manager();

        $this->helper_add_language('en', 'English');
        $this->helper_add_language('cs', 'Czech', 'Čeština');
        $this->helper_add_language('de', 'German', 'Deutsch');
        $this->helper_add_language('de_du', 'German (personal)', 'Deutsch (Du)', 'de');

        $statsman->update_stats('35', 'en', 'moodle', 10);
        $statsman->update_stats('35', 'cs', 'moodle', 9);
        $statsman->update_stats('35', 'de', 'moodle', 10);
        $statsman->update_stats('35', 'de_du', 'moodle', 1);

        $statsman->update_stats('36', 'en', 'moodle', 12);
        $statsman->update_stats('36', 'cs', 'moodle', 10);
        $statsman->update_stats('36', 'de', 'moodle', 9);
        $statsman->update_stats('36', 'de_du', 'moodle', 1);

        $statsman->update_stats('37', 'en', 'moodle', 20);
        $statsman->update_stats('37', 'cs', 'moodle', 15);
        $statsman->update_stats('37', 'de', 'moodle', 17);
        $statsman->update_stats('37', 'de_du', 'moodle', 3);

        $lateststats = $statsman->get_language_pack_ratio_stats();

        $this->assertEquals(3, count($lateststats));

        $this->assertEquals('en', $lateststats[0]->langcode);
        $this->assertEquals('English', $lateststats[0]->langname);
        $this->assertEquals(20, $lateststats[0]->totalstrings);
        $this->assertEquals(20, $lateststats[0]->totalenglish);
        $this->assertEquals(100, $lateststats[0]->ratio);

        $this->assertEquals('de', $lateststats[1]->langcode);
        $this->assertEquals('German / Deutsch', $lateststats[1]->langname);
        $this->assertEquals(17, $lateststats[1]->totalstrings);
        $this->assertEquals(20, $lateststats[1]->totalenglish);
        $this->assertEquals(round(17 / 20 * 100), $lateststats[1]->ratio);
        $this->assertEquals(1, count($lateststats[1]->childpacks));
        $this->assertEquals('de_du', $lateststats[1]->childpacks[0]->langcode);

        $this->assertEquals('cs', $lateststats[2]->langcode);

        $lateststats = $statsman->get_language_pack_ratio_stats(36);

        $this->assertEquals(3, count($lateststats));
        $this->assertEquals('en', $lateststats[0]->langcode);
        $this->assertEquals('cs', $lateststats[1]->langcode);
        $this->assertEquals('de', $lateststats[2]->langcode);
    }

    /**
     * Test obtaining data for the download page.
     */
    public function test_get_language_pack_download_page_data() {
        $this->resetAfterTest();

        set_config('branchesall', '400', 'local_amos');

        $statsman = new local_amos_stats_manager();

        $this->helper_add_language('en', 'English');
        $this->helper_add_language('cs', 'Czech', 'Čeština', 'en');
        $this->helper_add_language('de_du', 'German (personal)', 'Deutsch (Du)', 'de');
        $this->helper_add_language('de', 'German', 'Deutsch');
        $this->helper_add_language('en_us', 'English (US)', 'English (US)');
        $this->helper_add_language('en_us_k12', 'English (US) K12', 'English (US) K12', 'en_us');

        $statsman->update_stats('400', 'en', 'moodle', 10);
        $statsman->update_stats('400', 'de_du', 'moodle', 1);
        $statsman->update_stats('400', 'cs', 'moodle', 9);
        $statsman->update_stats('400', 'de', 'moodle', 10);
        $statsman->update_stats('400', 'en_us', 'moodle', 2);
        $statsman->update_stats('400', 'en_us_k12', 'moodle', 3);

        $data = $statsman->get_language_pack_download_page_data(400);

        $this->assertEquals(6, count($data));
        $this->assertEquals('cs', $data[0]['langcode']);
        $this->assertEquals('Czech / Čeština', $data[0]['langname']);
        $this->assertEquals('9', $data[0]['totalstrings']);
        $this->assertEquals('en', $data[1]['langcode']);
        $this->assertEquals('en_us', $data[2]['langcode']);
        $this->assertEquals('en', $data[2]['parentlanguagecode']);
        $this->assertEquals('en_us_k12', $data[3]['langcode']);
        $this->assertEquals('en_us', $data[3]['parentlanguagecode']);
        $this->assertEquals('de', $data[4]['langcode']);
        $this->assertEquals('de_du', $data[5]['langcode']);
        $this->assertEquals('de', $data[5]['parentlanguagecode']);
    }

    /**
     * Helper function for registering a known language in AMOS.
     *
     * @param string $code
     * @param string $thislanguageint
     * @param string $thislanguage
     * @param string $parent
     */
    protected function helper_add_language(string $code, string $thislanguageint, string $thislanguage = '', string $parent = '') {

        $stage = new mlang_stage();
        $component = new mlang_component('langconfig', $code, mlang_version::oldest_version());
        $component->add_string(new mlang_string('thislanguageint', $thislanguageint));
        $component->add_string(new mlang_string('thislanguage', $thislanguage ?? $thislanguageint));
        $component->add_string(new mlang_string('parentlanguage', $parent));
        $stage->add($component);
        $stage->commit('Registering language ' . $code, ['source' => 'unittest']);;

        // Rebuild the cache.
        mlang_tools::list_languages(true, false);
    }
}
