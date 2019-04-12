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
 * Provides {@link local_amos_source_code_testcase} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Test the implementation of {@link \local_amos\local\source_code} class.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_source_code_testcase extends advanced_testcase {

    /**
     * Test {@link \local_amos\local\source_code::parse_version_php()}.
     */
    public function test_get_version_php() {
        global $CFG;

        $amos = new \local_amos\local\source_code($CFG->dirroot.'/local/amos');
        $info = $amos->get_version_php();

        $this->assertEquals('local_amos', $info['component']);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('release', $info);
        $this->assertArrayHasKey('requires', $info);
        $this->assertArrayHasKey('maturity', $info);
    }

    /**
     * Test {@link \local_amos\local\source_code::get_included_string_files()}.
     */
    public function test_get_included_string_files() {
        global $CFG;

        $workshop = new \local_amos\local\source_code($CFG->dirroot.'/mod/workshop');
        $stringfiles = $workshop->get_included_string_files();

        // Because we use a standard module here, debugging is expected once for each subplugin type.
        $msg = 'get_list_of_plugins() should not be used to list real plugins, use core_component::get_plugin_list() instead!';
        $this->assertDebuggingCalledCount(3, array_fill(0, 3, $msg), array_fill(0, 3, DEBUG_DEVELOPER));

        // Check that we get both workshop's and its subplugins' files.
        $this->assertStringStartsWith('<?php', $stringfiles['mod_workshop']['lang/en/workshop.php']);
        $this->assertStringStartsWith('<?php',
            $stringfiles['workshopform_accumulative']['form/accumulative/lang/en/workshopform_accumulative.php']);
    }
}
