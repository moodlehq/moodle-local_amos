<?php

// This file is part of Moodle - http://moodle.org/
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
 * Unit tests for {@link amos_merge_string_files} class
 *
 * @package   local_amos
 * @category  phpunit
 * @copyright 2013 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php'); // Include the code to test

/**
 * Test cases for the merge strings file helper class
 */
class mergefiles_test extends basic_testcase {

    public function test_load_strings_from_file() {
        $logger = new testable_amos_cli_logger();
        $helper = new testable_amos_merge_string_files($logger);

        $found = $helper->load_strings_from_file(dirname(__FILE__).'/fixtures/merge001.php');
        $this->assertTrue(is_array($found));
        $this->assertEquals(3, count($found));
        $this->assertEquals(array('foo', 'amos', 'abc'), array_keys($found));
        $this->assertEquals('Rock\'s!', $found['amos']);
    }

    public function test_replace_strings_in_file() {
        global $CFG;

        $logger = new testable_amos_cli_logger();
        $helper = new testable_amos_merge_string_files($logger);

        $filecontents = "\$string['foo'] = 'Foo';";
        $fromstrings = array('foo' => 'Foo');
        $tostrings = array('foo' => 'Bar');
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
        $expected = "\$string['foo'] = 'Bar';";
        $this->assertEquals($expected, $filecontents);

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
        $fromstrings = array(
            'foo' => "Foo 'man' oh\n {\$a->f} matic ",
            'bar' => "Rock'n'roll! dude ",
        );
        $tostrings = array(
            'foo' => "Foo\n{\$a}\nBar",
            'bar' => 'Hell"yeah!'."\n",
            'baz' => 'Should not be changed',
        );
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
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
        $this->assertEquals($expected, $filecontents);

        $filecontents = file_get_contents(dirname(__FILE__).'/fixtures/merge003.php');
        $fromstrings = $helper->load_strings_from_file(dirname(__FILE__).'/fixtures/merge003.php');
        $tostrings = $helper->load_strings_from_file(dirname(__FILE__).'/fixtures/merge004.php');
        $this->assertEquals(1, count($tostrings));
        $count = $helper->replace_strings_in_file($filecontents, $fromstrings, $tostrings);
        $this->assertEquals(1, $count);
        $tmpfile = $CFG->phpunit_dataroot.'/amos_test_replace_strings_in_file.php';
        file_put_contents($tmpfile, $filecontents);
        $string = array();
        require($tmpfile);
        $this->assertEquals(7, count($string));
        $this->assertEquals(array(
            'error_authmnetneeded', 'error_localusersonly', 'error_roamcapabilityneeded',
            'mnet_hosts:addinstance', 'mnet_hosts:myaddinstance', 'pluginname', 'server'
        ), array_keys($string));
        $this->assertEquals('Add a new network servers block to My home', $string['mnet_hosts:myaddinstance']);
    }
}


/**
 * Provides access to protected methods we want to explicitly test
 *
 * @copyright 2013 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_amos_merge_string_files extends amos_merge_string_files {

}

/**
 * Dummy implementation of the AMOS logger suitable for unit testing
 *
 * @copyright 2013 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_amos_cli_logger extends amos_cli_logger {

    /**
     * Logs a message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     */
    public function log($job, $message, $level = self::LEVEL_INFO) {
    }
}
