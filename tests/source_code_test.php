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
 * Provides {@see local_amos_source_code_testcase} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Test the implementation of {@see \local_amos\local\source_code} class.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_source_code_testcase extends advanced_testcase {

    /**
     * Test {@see \local_amos\local\source_code::parse_version_php()}.
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
     * Test {@see \local_amos\local\source_code::get_included_string_files()}.
     */
    public function test_get_included_string_files() {

        // Admin tool with the old subplugins.php file.
        $toolold = new \local_amos\local\source_code(__DIR__.'/fixtures/tool_old');
        $files = $toolold->get_included_string_files();

        $this->assertStringStartsWith('// Just a test file', $files['tool_old']['lang/en/tool_old.php']);
        $this->assertStringStartsWith('// Just a test file',
            $files['oldsubtype_subplug']['subtype/subplug/lang/en/oldsubtype_subplug.php']);

        // Activity module with the new subplugins.json file.
        $foonew = new \local_amos\local\source_code(__DIR__.'/fixtures/mod_new');
        $files = $foonew->get_included_string_files();

        $this->assertStringContainsString('// Just a test file', $files['mod_new']['lang/en/new.php']);
        $this->assertStringStartsWith('// Just a test file',
            $files['newsubtype_subplug']['subtype/subplug/lang/en/newsubtype_subplug.php']);
    }
}
