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
 * Unit tests for Moodle string file parsers defined in mlangparser.php
 *
 * @package   local_amos
 * @category  phpunit
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlangparser.php'); // Include the code to test

/**
 * Test cases for the parsers api
 */
class mlangparser_test extends basic_testcase {

    public function test_singleton_instances() {
        $parser = mlang_parser_factory::get_parser('php');
        $this->assertTrue(in_array('mlang_parser', class_implements($parser)));
        $this->assertTrue($parser instanceof mlang_php_parser);
        $another = mlang_parser_factory::get_parser('php');
        $this->assertSame($another, $parser);
        $this->setExpectedException('coding_exception');
        $clone = clone($parser);
    }

    public function test_php_parser() {
        $parser = mlang_parser_factory::get_parser('php');
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));

        // empty string does nothing
        $parser->parse('', $component);
        $this->assertFalse($component->has_string());
        $component->clear();

        // data must be valid PHP code started with PHP start tag
        $data = '$string[\'none\'] = \'None\';';
        $component->clear();
        $parser->parse($data, $component);
        $this->assertFalse($component->has_string());

        // trivial string to parse
        $data = '<?php $string[\'one\'] = \'One\';';
        $component->clear();
        $parser->parse($data, $component);
        $this->assertEquals('One', $component->get_string('one')->text);

        // more complex example
        $data = file_get_contents(dirname(__FILE__).'/fixtures/parserdata001.txt');
        $component->clear();
        $parser->parse($data, $component);
        $this->assertFalse($component->has_string('notincodeblock'));
        $this->assertFalse($component->has_string('commented1'));
        $this->assertFalse($component->has_string('commented2'));
        $this->assertFalse($component->has_string('commented3'));
        $this->assertEquals($component->get_number_of_strings(), 6);
        $this->assertEquals($component->get_string('valid1')->text, 'This is {$a} valid string {$a->and} should be parsed');
        $this->assertEquals($component->get_string('valid2')->text, "Multiline\nstring");
        $this->assertEquals($component->get_string('valid3')->text, 'What \$a\'Pe%%\"be');
        $this->assertEquals($component->get_string('valid4')->text, "\$string['self'] = 'Eh?';");
        $this->assertEquals($component->get_string('valid5')->text, 'First');
        $this->assertEquals($component->get_string('valid6')->text, 'Second');

        // more complex example (format 1.x)
        $data = file_get_contents(dirname(__FILE__).'/fixtures/parserdata003.txt');
        $component->clear();
        $parser->parse($data, $component, 1);
        $this->assertEquals($component->get_number_of_strings(), 3);
        $this->assertEquals($component->get_string('valid1')->text, 'This "is" {$a} valid string {$a->and} should be parsed');
        $this->assertEquals($component->get_string('valid2')->text, "Multiline\nstring");
        $this->assertEquals($component->get_string('valid3')->text, 'What $a\'Pe%"be');

        // double quotes are allowed only if they do not contain dollar sign
        $data = '<?php $string["id"] = "No dollar here";';
        $component->clear();
        $parser->parse($data, $component);
        $this->assertEquals($component->get_string('id')->text, 'No dollar here');

        // only strings conforming to PARAM_STRINGID are accepted
        $data = file_get_contents(dirname(__FILE__).'/fixtures/parserdata004.txt');
        $component->clear();
        $parser->parse($data, $component);
        $this->assertEquals($component->get_number_of_strings(), 2);
    }

    public function test_php_parser_failure_double_quotes() {
        $parser = mlang_parser_factory::get_parser('php');
        $data = '<?php $string["id"] = "This {$a} fails";';
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->setExpectedException('mlang_parser_exception');
        $parser->parse($data, $component);
    }

    public function test_php_parser_usupported_string_concatenate() {
        $parser = mlang_parser_factory::get_parser('php');
        $data = '<?php $string[\'invalid\'] = \'Hello \' . \' world\';';
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->setExpectedException('mlang_parser_exception');
        $parser->parse($data, $component);
    }

    public function test_php_parser_security_variable_expansion() {
        // security issues
        $parser = mlang_parser_factory::get_parser('php');
        $data = '<?php $string[\'dbpass\'] = $CFG->dbpass;'; // this would give the user sensitive data about AMOS portal
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->setExpectedException('mlang_parser_exception');
        $parser->parse($data, $component);
    }
}
