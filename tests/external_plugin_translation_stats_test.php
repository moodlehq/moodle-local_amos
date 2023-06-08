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
 * Provides class {@see local_amos_external_plugin_translation_stats_testcase}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests for AMOS external function 'plugin_translation_stats'.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_external_plugin_translation_stats_testcase extends externallib_advanced_testcase {

    /**
     * Test the behaviour of the plugin_translation_stats external function when unknown component is requested.
     */
    public function test_plugin_translation_stats_unknown_component() {
        $this->resetAfterTest(true);

        // No special capability needed.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $this->expectException(invalid_parameter_exception::class);

        \local_amos\external\plugin_translation_stats::execute('muhehe');
    }

    /**
     * Test the behaviour of the plugin_translation_stats external function.
     */
    public function test_plugin_translation_stats() {
        global $CFG;
        require_once($CFG->dirroot.'/local/amos/mlanglib.php');

        $this->resetAfterTest(true);

        // No special capability needed.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $stage = new mlang_stage();
        $component = new mlang_component('langconfig', 'en', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('thislanguageint', 'English'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Registering English language', ['source' => 'unittest']);

        $statsman = new local_amos_stats_manager();
        $statsman->update_stats('36', 'en', 'tool_foo', 9);

        $raw = \local_amos\external\plugin_translation_stats::execute('tool_foo');
        $clean = \core_external\external_api::clean_returnvalue(\local_amos\external\plugin_translation_stats::execute_returns(), $raw);

        $this->assertEquals(1, count($clean['langnames']));
        $this->assertEquals(1, count($clean['branches']));
    }
}
