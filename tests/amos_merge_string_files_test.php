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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Provides {@see \local_amos\amos_merge_string_files_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2013 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

use basic_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php');

require_once(__DIR__ . '/classes/cli/testable_amos_cli_logger.php');
require_once(__DIR__ . '/classes/cli/testable_amos_merge_string_files.php');

/**
 * Unit tests for the {@see \amos_merge_string_files} class
 *
 * @copyright 2013 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class amos_merge_string_files_test extends basic_testcase {
    public function test_load_strings_from_file(): void {
        $logger = new testable_amos_cli_logger();
        $helper = new testable_amos_merge_string_files($logger);

        $found = $helper->load_strings_from_file(dirname(__FILE__) . '/fixtures/merge001.php');
        $this->assertTrue(is_array($found));
        $this->assertEquals(3, count($found));
        $this->assertEquals(['foo', 'amos', 'abc'], array_keys($found));
        $this->assertEquals('Rock\'s!', $found['amos']);
    }

    public function test_replace_strings_in_file(): void {
        global $CFG;

        $logger = new testable_amos_cli_logger();
        $helper = new testable_amos_merge_string_files($logger);

        $filecontents = "\$string['foo'] = 'Foo';";
        $fromstrings = ['foo' => 'Foo'];
        $tostrings = ['foo' => 'Bar'];
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
        $expected = "\$string['foo'] = 'Bar';";
        $this->assertEquals($expected, $filecontents);

        // phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings
        $filecontents = '<?php
/**
 * GNU rocks!
 * This is a file full of things like $string[\'foo\'] = \'Blahblah\';
 */
$string [ \'foo\'  ]=  \'Foo \\\'man\\\' oh
 {$a->f} matic \' ;  // @todo 
// This is inline comment
  $string [\'bar\']="Rock\'n\'roll! dude ";  // unsupported at the moment

$string[\'baz\'] = \'Baz\';
';
        //phpcs:enable

        $fromstrings = [
            'foo' => "Foo 'man' oh\n {\$a->f} matic ",
            'bar' => "Rock'n'roll! dude ",
        ];
        $tostrings = [
            'foo' => "Foo\n{\$a}\nBar",
            'bar' => 'Hell"yeah!' . "\n",
            'baz' => 'Should not be changed',
        ];
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
        // phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings
        $expected = '<?php
/**
 * GNU rocks!
 * This is a file full of things like $string[\'foo\'] = \'Blahblah\';
 */
$string [ \'foo\'  ]=  \'Foo
{$a}
Bar\';  // @todo 
// This is inline comment
  $string [\'bar\']="Rock\'n\'roll! dude ";  // unsupported at the moment

$string[\'baz\'] = \'Baz\';
';
        //phpcs:enable
        $this->assertEquals($expected, $filecontents);

        $filecontents = file_get_contents(dirname(__FILE__) . '/fixtures/merge003.php');
        $fromstrings = $helper->load_strings_from_file(dirname(__FILE__) . '/fixtures/merge003.php');
        $tostrings = $helper->load_strings_from_file(dirname(__FILE__) . '/fixtures/merge004.php');
        $this->assertEquals(1, count($tostrings));
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
        $tmpfile = $CFG->phpunit_dataroot . '/amos_test_replace_strings_in_file.php';
        file_put_contents($tmpfile, $filecontents);
        $string = [];
        require($tmpfile);
        $this->assertEquals(7, count($string));
        $this->assertEquals([
            'error_authmnetneeded', 'error_localusersonly', 'error_roamcapabilityneeded',
            'mnet_hosts:addinstance', 'mnet_hosts:myaddinstance', 'pluginname', 'server',
        ], array_keys($string));
        $this->assertEquals('Add a new network servers block to My home', $string['mnet_hosts:myaddinstance']);
    }
}
