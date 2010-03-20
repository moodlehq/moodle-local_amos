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
 * Unit tests for Moodle language manipulation library defined in mlanglib.php
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB, $CFG;

if (empty($CFG->unittestprefix)) {
    die('You must define $CFG->unittestprefix to run these unit tests.');
}

require_once($CFG->dirroot . '/local/amos/mlanglib.php'); // Include the code to test

/**
 * Test cases for the internal workshop api
 */
class mlang_test extends UnitTestCase {

    /** setup testing environment */
    public function setUp() {
        global $DB, $CFG;

        $this->realDB = $DB;
        $dbclass = get_class($this->realDB);
        $DB = new $dbclass();
        $DB->connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $CFG->unittestprefix);

        if ($DB->get_manager()->table_exists('amos_repository')) {
            $DB->get_manager()->delete_tables_from_xmldb_file($CFG->dirroot . '/local/amos/db/install.xml');
        }
        $DB->get_manager()->install_from_xmldb_file($CFG->dirroot . '/local/amos/db/install.xml');
    }

    public function tearDown() {
        global $DB, $CFG;

        // $DB->get_manager()->delete_tables_from_xmldb_file($CFG->dirroot . '/local/amos/db/install.xml');
        $DB = $this->realDB;
    }

    /**
     * Excercise various helper methods
     */
    public function test_helpers() {
        $this->assertEqual('workshop', mlang_component::name_from_filename('/web/moodle/mod/workshop/lang/en/workshop.php'));
        $this->assertFalse(mlang_string::differ(
                new mlang_string('first', 'This is a test string'),
                new mlang_string('second', 'This is a test string')));
        $this->assertFalse(mlang_string::differ(
                new mlang_string('first', '  This is a test string  '),
                new mlang_string('second', 'This is a test string ')));
        $this->assertTrue(mlang_string::differ(
                new mlang_string('first', 'This is a test string'),
                new mlang_string('first', 'This is a test string!')));
        $this->assertTrue(mlang_string::differ(
                new mlang_string('empty', ''),
                new mlang_string('null', null)));
        $this->assertFalse(mlang_string::differ(
                new mlang_string('null', null),
                new mlang_string('anothernull', null)));
    }

    /**
     * Standard procedure to add strings into AMOS repository
     */
    public function test_simple_string_lifecycle() {
        global $DB, $CFG, $USER;

        // basic operations
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
        $this->assertFalse($component->has_string('nonexisting'));
        $this->assertFalse($component->has_string('welcome'));
        $component->add_string(new mlang_string('welcome', 'Welcome'));
        $this->assertTrue($component->has_string());
        $this->assertFalse($component->has_string('nonexisting'));
        $this->assertTrue($component->has_string('welcome'));
        $this->assertNull($component->get_string('nonexisting'));
        $s = $component->get_string('welcome');
        $this->assertTrue($s instanceof mlang_string);
        $this->assertEqual('welcome', $s->id);
        $this->assertEqual('Welcome', $s->text);
        $component->unlink_string('nonexisting');
        $this->assertTrue($component->has_string());
        $component->unlink_string('welcome');
        $this->assertFalse($component->has_string());
        $component->add_string(new mlang_string('welcome', 'Welcome'));
        $component->clear();
        $this->assertFalse($component->has_string());
        $component->add_string(new mlang_string('welcome', 'Welcome'));
        $this->expectException();
        $component->add_string(new mlang_string('welcome', 'Overwriting existing throws exception'));
        $this->assertNoErrors();
        $component->add_string(new mlang_string('welcome', 'Overwriting existing must be forced'), true);
        $component->clear();
        unset($component);

        // commit a single string
        $now = time();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome', $now));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertEqual(1, $DB->count_records('amos_repository'));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'test', 'lang' => 'xx', 'stringid' => 'welcome', 'timemodified' => $now)));

        // add two other strings
        $now = time();
        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('hello', 'Hello', $now));
        $component->add_string(new mlang_string('world', 'World', $now));
        $stage->add($component);
        $stage->commit('Two other string into AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertEqual(3, $DB->count_records('amos_repository'));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'test', 'lang' => 'xx', 'stringid' => 'hello', 'timemodified' => $now)));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'test', 'lang' => 'xx', 'stringid' => 'world', 'timemodified' => $now)));

        // delete a string
        $now = time();
        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertEqual(4, $DB->count_records('amos_repository'));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'test', 'lang' => 'xx', 'stringid' => 'welcome', 'timemodified' => $now, 'deleted' => 1)));
    }

    public function test_component_from_phpfile() {
        $filecontents = <<<EOF
<?php
\$string['about'] = 'Multiline
string';
\$string['pluginname'] = 'AMOS';
\$string['author'] = 'David Mudrak';
EOF;
        $tmp = make_upload_directory('temp/amos', false);
        $filepath = $tmp . '/mlangunittest.php';
        file_put_contents($filepath, $filecontents);
        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertEqual("Multiline\nstring", $component->get_string('about')->text);
        unlink($filepath);
    }

    public function test_explicit_rebasing() {
        // prepare the "cap" (base for rebasing)
        $stage = new mlang_stage();
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('one', 'One'));
        $component->add_string(new mlang_string('two', 'Two'));
        $component->add_string(new mlang_string('three', 'Tree')); // typo here
        $stage->add($component);
        $stage->commit('Initial commit', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        // rebasing without string removal - the stage is not complete
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('one', 'One')); // same as the current
        $component->add_string(new mlang_string('two', 'Two')); // same as the current
        $component->add_string(new mlang_string('three', 'Three')); // changed
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof mlang_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertFalse($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('three');
        $this->assertEqual('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // rebasing with string removal - the stage is considered to be the full snapshot of the state
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('one', 'One')); // same as the current
        // string 'two' is missing and we want it to be removed from repository
        $component->add_string(new mlang_string('three', 'Three')); // changed
        $stage->add($component);
        $stage->rebase(true);
        $rebased = $stage->get_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof mlang_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertTrue($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('two');
        $this->assertTrue($s->deleted);
        $s = $rebased->get_string('three');
        $this->assertEqual('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);
    }

    public function test_implicit_rebasing_during_commit() {
        global $DB;

        // prepare the "cap" (base for rebasing)
        $stage = new mlang_stage();
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('one', 'One'));
        $component->add_string(new mlang_string('two', 'Two'));
        $component->add_string(new mlang_string('three', 'Tree')); // typo here
        $stage->add($component);
        $stage->commit('Initial commit', array('source' => 'unittest'));
        $component->clear();
        unset($component);
        unset($stage);

        // commit the fix of the string
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('one', 'One')); // same as the current
        $component->add_string(new mlang_string('two', 'Two')); // same as the current
        $component->add_string(new mlang_string('three', 'Three')); // changed
        $stage->add($component);
        $stage->commit('Fixed typo Tree > Three', array('source' => 'unittest'));
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertEqual(4, $DB->count_records('amos_repository'));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'numbers', 'lang' => 'en', 'stringid' => 'three', 'text' => 'Tree')));
        $this->assertTrue($DB->record_exists('amos_repository', array(
                'component' => 'numbers', 'lang' => 'en', 'stringid' => 'three', 'text' => 'Three')));

        // get the most recent version (so called "cap") of the component
        $cap = mlang_component::from_snapshot('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $concat = '';
        foreach ($cap->get_iterator() as $s) {
            $concat .= $s->text;
        }
        // strings in the cap shall be ordered by stringid
        $this->assertEqual('OneThreeTwo', $concat);
    }

}
