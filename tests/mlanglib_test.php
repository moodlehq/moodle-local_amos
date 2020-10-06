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
 * @package   local_amos
 * @category  phpunit
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Makes protected method accessible for testing purposes
 */
class testable_mlang_tools extends mlang_tools {
    public static function legacy_component_name($newstyle) {
        return parent::legacy_component_name($newstyle);
    }
}

/**
 * Test cases for the internal AMOS API
 */
class mlang_test extends advanced_testcase {

    /**
     * Helper method to quickly register a language on the given branch(-es)
     *
     * @param string $langcode the code of the language, such as 'en'
     * @param int $since the code of the branch to register the language at.
     */
    protected function register_language($langcode, $since) {

        $stage = new mlang_stage();

        $component = new mlang_component('langconfig', $langcode, mlang_version::by_code($since));
        $component->add_string(new mlang_string('thislanguage', $langcode));
        $component->add_string(new mlang_string('thislanguageint', $langcode));
        $stage->add($component);
        $component->clear();

        $stage->commit('Register language '.$langcode, array('source' => 'unittest'));
    }

    /**
     * Excercise various helper methods
     */
    public function test_helpers() {
        $this->assertEquals('workshop', mlang_component::name_from_filename('/web/moodle/mod/workshop/lang/en/workshop.php'));
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
     * Test that components and stages are iterable and countable.
     */
    public function test_iterable_and_countable() {

        $this->resetAfterTest();

        $component = new mlang_component('test', 'en', mlang_version::by_code(310));

        $this->assertSame(0, count($component));
        foreach ($component as $string) {
            $this->assertTrue(false);
        }

        $component->add_string(new mlang_string('foo', 'Foo'));
        $this->assertEquals(1, count($component));
        $i = 0;
        foreach ($component as $string) {
            $this->assertInstanceOf(mlang_string::class, $string);
            $i++;
        }
        $this->assertSame(1, $i);

        $component->add_string(new mlang_string('bar', 'Bar'));
        $this->assertEquals(2, count($component));
        $i = 0;
        foreach ($component as $string) {
            $this->assertInstanceOf(mlang_string::class, $string);
            $i++;
        }
        $this->assertSame(2, $i);

        $stage = new mlang_stage();
        $this->assertSame(0, count($stage));
        foreach ($stage as $component) {
            $this->assertTrue(false);
        }

        $stage->add($component);
        $this->assertSame(1, count($stage));
        $i = 0;
        foreach ($stage as $component) {
            $this->assertInstanceOf(mlang_component::class, $component);
            $i++;
        }
        $this->assertSame(1, $i);

        $stage->commit('Test', ['source' => 'unittest']);

        $this->assertSame(0, count($stage));
        foreach ($stage as $component) {
            $this->assertTrue(false);
        }
    }

    /**
     * Standard procedure to add strings into AMOS repository
     */
    public function test_simple_string_lifecycle() {
        global $DB, $CFG, $USER;

        $this->resetAfterTest();

        // Basic operations.
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
        $this->assertEquals('welcome', $s->id);
        $this->assertEquals('Welcome', $s->text);
        $component->unlink_string('nonexisting');
        $this->assertTrue($component->has_string());
        $component->unlink_string('welcome');
        $this->assertFalse($component->has_string());
        $component->add_string(new mlang_string('welcome', 'Welcome'));
        $component->clear();
        unset($component);

        // Commit a single string.
        $now = time();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome', $now));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('welcome'));
        $component->clear();

        // Add two other strings.
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

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $component->clear();

        // Delete a string.
        $now = time();
        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $component->clear();
    }

    public function test_add_existing_string() {
        $this->resetAfterTest();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_22_STABLE'));
        $this->assertFalse($component->has_string());
        $component->add_string(new mlang_string('welcome', 'Welcome'));
        $component->add_string(new mlang_string('welcome', 'Overwriting existing must be forced'), true);
        $this->assertEquals($component->get_string('welcome')->text, 'Overwriting existing must be forced');
        $this->expectException('coding_exception');
        $component->add_string(new mlang_string('welcome', 'Overwriting existing throws exception'));
        $component->clear();
        unset($component);
    }

    public function test_deleted_has_same_timemodified() {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome', $now));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
        $component->clear();
        unset($component);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
    }

    public function test_more_recently_inserted_wins() {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome', $now));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome!', $now));
        $stage->add($component);
        $stage->commit('Making a modification of the string with the same timestamp', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);

        $stage = new mlang_stage();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('welcome', 'Welcome!', $now, 1));
        $stage->add($component);
        $stage->commit('Deleting the string with the same timestamp', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string('welcome'));
        $component->clear();
        unset($component);

        $component = mlang_component::from_snapshot('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'), null, true);
        $this->assertTrue($component->get_string('welcome')->deleted);
        $component->clear();
        unset($component);
    }

    public function test_component_from_phpfile_same_timestamp() {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        // get the initial strings from a file
        $filecontents = <<<EOF
<?php
\$string['welcome'] = 'Welcome';

EOF;
        $tmp = make_temp_directory('amos');
        $filepath = $tmp . '/mlangunittest.php';
        file_put_contents($filepath, $filecontents);

        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_20_STABLE'), $now, 'test', 2);
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unlink($filepath);

        $filecontents = <<<EOF
<?php
\$string['welcome'] = 'Welcome!';

EOF;
        file_put_contents($filepath, $filecontents);

        // commit modified file with the same timestamp
        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_20_STABLE'), $now, 'test', 2);
        $stage->add($component);
        $stage->commit('The string modified in the same timestamp', array('source' => 'unittest'), true);
        $component->clear();
        unset($component);
        unset($stage);
        unlink($filepath);

        // now make sure that the more recently committed string wins
        $component = mlang_component::from_snapshot('test', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);

        $component = mlang_component::from_snapshot('test', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);
    }

    public function test_component_from_phpfile_legacy_format() {
        $filecontents = <<<EOF
<?php
\$string['about'] = 'Multiline
string';
\$string['pluginname'] = 'AMOS';
\$string['author'] = 'David Mudrak';
\$string['syntax'] = 'What \$a\\'Pe%%\\"be';
\$string['percents'] = '%%Y-%%m-%%d-%%H-%%M';

EOF;
        $tmp = make_temp_directory('amos');
        $filepath = $tmp . '/mlangunittest.php';
        file_put_contents($filepath, $filecontents);

        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_20_STABLE'), null, null, 1);
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertTrue($component->has_string('syntax'));
        $this->assertEquals("Multiline\nstring", $component->get_string('about')->text);
        $this->assertEquals('What {$a}\'Pe%"be', $component->get_string('syntax')->text);
        $this->assertEquals('%Y-%m-%d-%H-%M', $component->get_string('percents')->text);
        $component->clear();

        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertTrue($component->has_string('syntax'));
        $this->assertEquals("Multiline\nstring", $component->get_string('about')->text);
        $this->assertEquals('What $a\'Pe%%\"be', $component->get_string('syntax')->text);
        $this->assertEquals('%%Y-%%m-%%d-%%H-%%M', $component->get_string('percents')->text);
        $component->clear();

        $component = mlang_component::from_phpfile($filepath, 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertTrue($component->has_string('syntax'));
        $this->assertEquals("Multiline\nstring", $component->get_string('about')->text);
        $this->assertEquals('What $a\'Pe%%\\"be', $component->get_string('syntax')->text);
        $this->assertEquals('%%Y-%%m-%%d-%%H-%%M', $component->get_string('percents')->text);
        $component->clear();

        unlink($filepath);
    }

    public function test_explicit_rebasing() {
        $this->resetAfterTest();
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
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing on a higher branch.
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('one', 'One')); // same as the current
        $component->add_string(new mlang_string('two', 'Two')); // same as the current
        $component->add_string(new mlang_string('three', 'Three')); // changed
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($rebased instanceof mlang_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertFalse($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
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
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof mlang_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertTrue($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('two');
        $this->assertTrue($s->deleted);
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing with string removal on a higher branch.
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('numbers', 'en', mlang_version::by_branch('MOODLE_21_STABLE'));
        $component->add_string(new mlang_string('one', 'One')); // same as the current
        // Rtring 'two' is missing and we want it to be removed from repository.
        $component->add_string(new mlang_string('three', 'Three')); // changed
        $stage->add($component);
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', mlang_version::by_branch('MOODLE_21_STABLE'));
        $this->assertTrue($rebased instanceof mlang_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertTrue($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('two');
        $this->assertTrue($s->deleted);
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);
    }

    public function test_rebasing_deletion_already_deleted() {
        $this->resetAfterTest();
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('trash', 'cs', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('delme', 'Del me', time() - 1000, true));    // deleted string
        $stage->add($component);
        $stage->commit('The string was already born deleted... sad');
        $this->assertFalse($stage->has_component());
        unset($stage);

        $stage = new mlang_stage();
        $component = new mlang_component('trash', 'cs', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('delme', 'Del me', null, true));    // already deleted string
        $stage->add($component);
        $stage->rebase();
        $this->assertFalse($stage->has_component());
    }

    public function test_implicit_rebasing_during_commit() {
        global $DB;

        $this->resetAfterTest();

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

        // get the most recent version (so called "cap") of the component
        $cap = mlang_component::from_snapshot('numbers', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $concat = '';
        foreach ($cap as $s) {
            $concat .= $s->text;
        }
        // strings in the cap shall be ordered by stringid
        $this->assertEquals('OneThreeTwo', $concat);
    }

    /**
     * Rebasing should respect commits in whatever order so it is safe to re-run the import scripts
     */
    public function test_non_chronological_commits() {
        global $DB;

        $this->resetAfterTest();

        // firstly commit the most recent version
        $today = time();
        $stage = new mlang_stage();
        $component = new mlang_component('things', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('foo', 'Today Foo', $today));   // changed today
        $component->add_string(new mlang_string('bar', 'New Bar', $today));     // new today
        $component->add_string(new mlang_string('job', 'Boring', $today));      // not changed
        $stage->add($component);
        $stage->commit('Initial commit', array('source' => 'unittest'));
        $component->clear();
        unset($component);
        unset($stage);

        // we are re-importing the history - let us commit the version that was actually created yesterday
        $yesterday = time() - DAYSECS;
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('things', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('foo', 'Foo', $yesterday));         // as it was yesterday
        $component->add_string(new mlang_string('job', 'Boring', $yesterday));      // still the same
        $stage->add($component);
        $stage->rebase($yesterday, true, $yesterday);
        $rebased = $stage->get_component('things', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf('mlang_component', $rebased);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertTrue($rebased->has_string('foo'));
        $this->assertFalse($rebased->has_string('bar'));
        $this->assertTrue($rebased->has_string('job'));

        // and the same case using rebase() without deleting
        $stage = new mlang_stage();
        $this->assertFalse($stage->has_component());
        $component = new mlang_component('things', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('foo', 'Foo', $yesterday));         // as it was yesterday
        $component->add_string(new mlang_string('job', 'Boring', $yesterday));      // still the same
        $stage->add($component);
        $stage->rebase($yesterday);
        $rebased = $stage->get_component('things', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf('mlang_component', $rebased);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertTrue($rebased->has_string('foo'));
        $this->assertFalse($rebased->has_string('bar'));
        $this->assertTrue($rebased->has_string('job'));
    }

    /**
     * Sanity 1.x string
     * - all variables but $a placeholders must be escaped because the string is eval'ed
     * - all ' and " must be escaped
     * - all single % must be converted into %% for backwards compatibility
     */
    public function test_fix_syntax_sanity_v1_strings() {
        $this->assertEquals(mlang_string::fix_syntax('No change', 1), 'No change');
        $this->assertEquals(mlang_string::fix_syntax('Completed 100% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(mlang_string::fix_syntax('Completed 100%% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(mlang_string::fix_syntax("Windows\r\nsucks", 1), "Windows\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Linux\nsucks", 1), "Linux\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Mac\rsucks", 1), "Mac\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("LINE TABULATION\x0Bnewline", 1), "LINE TABULATION\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("FORM FEED\x0Cnewline", 1), "FORM FEED\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 1), "END OF TRANSMISSION BLOCK\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF MEDIUM\x19newline", 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("SUBSTITUTE\x1Anewline", 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("NEXT LINE\xC2\x85newline", 1), "NEXT LINE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("START OF STRING\xC2\x98newline", 1), "START OF STRING\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 1), "Unicode Zl\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 1), "Unicode Zp\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Empty\n\n\n\n\n\nlines", 1), "Empty\n\nlines");
        $this->assertEquals(mlang_string::fix_syntax("Trailing   \n  whitespace \t \nat \nmultilines  ", 1), "Trailing\n  whitespace\nat\nmultilines");
        $this->assertEquals(mlang_string::fix_syntax('Escape $variable names', 1), 'Escape \$variable names');
        $this->assertEquals(mlang_string::fix_syntax('Escape $alike names', 1), 'Escape \$alike names');
        $this->assertEquals(mlang_string::fix_syntax('String $a placeholder', 1), 'String $a placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Escaped \$a', 1), 'Escaped \$a');
        $this->assertEquals(mlang_string::fix_syntax('Wrapped {$a}', 1), 'Wrapped {$a}');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a', 1), 'Trailing $a');
        $this->assertEquals(mlang_string::fix_syntax('$a leading', 1), '$a leading');
        $this->assertEquals(mlang_string::fix_syntax('Hit $a-times', 1), 'Hit $a-times'); // this is placeholder',
        $this->assertEquals(mlang_string::fix_syntax('This is $a_book', 1), 'This is \$a_book'); // this is not
        $this->assertEquals(mlang_string::fix_syntax('Bye $a, ttyl', 1), 'Bye $a, ttyl');
        $this->assertEquals(mlang_string::fix_syntax('Object $a->foo placeholder', 1), 'Object $a->foo placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a->bar', 1), 'Trailing $a->bar');
        $this->assertEquals(mlang_string::fix_syntax('<strong>AMOS</strong>', 1), '<strong>AMOS</strong>');
        $this->assertEquals(mlang_string::fix_syntax('<a href="http://localhost">AMOS</a>', 1), '<a href=\"http://localhost\">AMOS</a>');
        $this->assertEquals(mlang_string::fix_syntax('<a href=\"http://localhost\">AMOS</a>', 1), '<a href=\"http://localhost\">AMOS</a>');
        $this->assertEquals(mlang_string::fix_syntax("'Murder!', she wrote", 1), "'Murder!', she wrote"); // will be escaped by var_export()
        $this->assertEquals(mlang_string::fix_syntax("\t  Trim Hunter  \t\t", 1), 'Trim Hunter');
        $this->assertEquals(mlang_string::fix_syntax('Delete role "$a->role"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(mlang_string::fix_syntax('Delete role \"$a->role\"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\0 NULL control character", 1), 'Delete ASCII NULL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 1), 'Delete ASCII ENQUIRY control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 1), 'Delete ASCII ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x07 BELL control character", 1), 'Delete ASCII BELL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 1), 'Delete ASCII SHIFT OUT control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 1), 'Delete ASCII SHIFT IN control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 1), 'Delete ASCII DATA LINK ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 1), 'Delete ASCII DEVICE CONTROL ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 1), 'Delete ASCII DEVICE CONTROL TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 1), 'Delete ASCII DEVICE CONTROL THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 1), 'Delete ASCII DEVICE CONTROL FOUR control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 1), 'Delete ASCII NEGATIVE ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 1), 'Delete ASCII SYNCHRONOUS IDLE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 1), 'Delete ASCII ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x7F DELETE control character", 1), 'Delete ASCII DELETE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 1), 'Delete ISO 8859 PADDING CHARACTER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 1), 'Delete ISO 8859 HIGH OCTET PRESET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 1), 'Delete ISO 8859 NO BREAK HERE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 1), 'Delete ISO 8859 INDEX control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 1), 'Delete ISO 8859 START OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 1), 'Delete ISO 8859 END OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 1), 'Delete ISO 8859 CHARACTER TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character", 1), 'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 1), 'Delete ISO 8859 LINE TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 1), 'Delete ISO 8859 PARTIAL LINE FORWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 1), 'Delete ISO 8859 PARTIAL LINE BACKWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 1), 'Delete ISO 8859 REVERSE LINE FEED control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 1), 'Delete ISO 8859 SINGLE SHIFT TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 1), 'Delete ISO 8859 SINGLE SHIFT THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 1), 'Delete ISO 8859 DEVICE CONTROL STRING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 1), 'Delete ISO 8859 PRIVATE USE ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 1), 'Delete ISO 8859 PRIVATE USE TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 1), 'Delete ISO 8859 SET TRANSMIT STATE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 1), 'Delete ISO 8859 MESSAGE WAITING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 1), 'Delete ISO 8859 START OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 1), 'Delete ISO 8859 END OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character", 1), 'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 1), 'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 1), 'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 1), 'Delete ISO 8859 OPERATING SYSTEM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 1), 'Delete ISO 8859 PRIVACY MESSAGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 1), 'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 1), 'Delete Unicode ZERO WIDTH SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character", 1), 'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 1), 'Delete Unicode REPLACEMENT CHARACTER control character');
    }

    /**
     * Sanity 2.x string
     * - the string is not eval'ed any more - no need to escape $variables
     * - placeholders can be only {$a} or {$a->something} or {$a->some_thing}, nothing else
     * - quoting marks are not escaped
     * - percent signs are not duplicated any more, reverting them into single (is it good idea?)
     */
    public function test_fix_syntax_sanity_v2_strings() {
        $this->assertEquals(mlang_string::fix_syntax('No change'), 'No change');
        $this->assertEquals(mlang_string::fix_syntax('Completed 100% of work'), 'Completed 100% of work');
        $this->assertEquals(mlang_string::fix_syntax('%%%% HEADER %%%%'), '%%%% HEADER %%%%'); // was not possible before
        $this->assertEquals(mlang_string::fix_syntax("Windows\r\nsucks"), "Windows\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Linux\nsucks"), "Linux\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Mac\rsucks"), "Mac\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("LINE TABULATION\x0Bnewline"), "LINE TABULATION\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("FORM FEED\x0Cnewline"), "FORM FEED\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline"), "END OF TRANSMISSION BLOCK\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF MEDIUM\x19newline"), "END OF MEDIUM\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("SUBSTITUTE\x1Anewline"), "SUBSTITUTE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline"), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("NEXT LINE\xC2\x85newline"), "NEXT LINE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("START OF STRING\xC2\x98newline"), "START OF STRING\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline"), "STRING TERMINATOR\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline"), "Unicode Zl\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline"), "Unicode Zp\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Empty\n\n\n\n\n\nlines"), "Empty\n\n\nlines"); // now allows up to two empty lines
        $this->assertEquals(mlang_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  "), "Trailing\n  whitespace\nat\nmultilines");
        $this->assertEquals(mlang_string::fix_syntax('Do not escape $variable names'), 'Do not escape $variable names');
        $this->assertEquals(mlang_string::fix_syntax('Do not escape $alike names'), 'Do not escape $alike names');
        $this->assertEquals(mlang_string::fix_syntax('Not $a placeholder'), 'Not $a placeholder');
        $this->assertEquals(mlang_string::fix_syntax('String {$a} placeholder'), 'String {$a} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Trailing {$a}'), 'Trailing {$a}');
        $this->assertEquals(mlang_string::fix_syntax('{$a} leading'), '{$a} leading');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a'), 'Trailing $a');
        $this->assertEquals(mlang_string::fix_syntax('$a leading'), '$a leading');
        $this->assertEquals(mlang_string::fix_syntax('Not $a->foo placeholder'), 'Not $a->foo placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Object {$a->foo} placeholder'), 'Object {$a->foo} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a->bar'), 'Trailing $a->bar');
        $this->assertEquals(mlang_string::fix_syntax('Invalid $a-> placeholder'), 'Invalid $a-> placeholder');
        $this->assertEquals(mlang_string::fix_syntax('<strong>AMOS</strong>'), '<strong>AMOS</strong>');
        $this->assertEquals(mlang_string::fix_syntax("'Murder!', she wrote"), "'Murder!', she wrote"); // will be escaped by var_export()
        $this->assertEquals(mlang_string::fix_syntax("\t  Trim Hunter  \t\t"), 'Trim Hunter');
        $this->assertEquals(mlang_string::fix_syntax('Delete role "$a->role"?'), 'Delete role "$a->role"?');
        $this->assertEquals(mlang_string::fix_syntax('Delete role \"$a->role\"?'), 'Delete role \"$a->role\"?');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\0 NULL control character"), 'Delete ASCII NULL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character"), 'Delete ASCII ENQUIRY control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character"), 'Delete ASCII ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x07 BELL control character"), 'Delete ASCII BELL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character"), 'Delete ASCII SHIFT OUT control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character"), 'Delete ASCII SHIFT IN control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character"), 'Delete ASCII DATA LINK ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character"), 'Delete ASCII DEVICE CONTROL ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character"), 'Delete ASCII DEVICE CONTROL TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character"), 'Delete ASCII DEVICE CONTROL THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character"), 'Delete ASCII DEVICE CONTROL FOUR control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character"), 'Delete ASCII NEGATIVE ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character"), 'Delete ASCII SYNCHRONOUS IDLE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x1B ESCAPE control character"), 'Delete ASCII ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x7F DELETE control character"), 'Delete ASCII DELETE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character"), 'Delete ISO 8859 PADDING CHARACTER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character"), 'Delete ISO 8859 HIGH OCTET PRESET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character"), 'Delete ISO 8859 NO BREAK HERE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character"), 'Delete ISO 8859 INDEX control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character"), 'Delete ISO 8859 START OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character"), 'Delete ISO 8859 END OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character"), 'Delete ISO 8859 CHARACTER TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character"), 'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character"), 'Delete ISO 8859 LINE TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character"), 'Delete ISO 8859 PARTIAL LINE FORWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character"), 'Delete ISO 8859 PARTIAL LINE BACKWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character"), 'Delete ISO 8859 REVERSE LINE FEED control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character"), 'Delete ISO 8859 SINGLE SHIFT TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character"), 'Delete ISO 8859 SINGLE SHIFT THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character"), 'Delete ISO 8859 DEVICE CONTROL STRING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character"), 'Delete ISO 8859 PRIVATE USE ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character"), 'Delete ISO 8859 PRIVATE USE TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character"), 'Delete ISO 8859 SET TRANSMIT STATE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character"), 'Delete ISO 8859 MESSAGE WAITING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character"), 'Delete ISO 8859 START OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character"), 'Delete ISO 8859 END OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character"), 'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character"), 'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character"), 'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character"), 'Delete ISO 8859 OPERATING SYSTEM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character"), 'Delete ISO 8859 PRIVACY MESSAGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character"), 'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character"), 'Delete Unicode ZERO WIDTH SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character"), 'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character"), 'Delete Unicode REPLACEMENT CHARACTER control character');
    }

    /**
     * Converting 1.x strings into 2.x strings
     * - unescape all variables
     * - wrap all placeholders in curly brackets
     * - unescape quoting marks
     * - collapse percent signs
     */
    public function test_fix_syntax_converting_from_v1_to_v2() {
        $this->assertEquals(mlang_string::fix_syntax('No change', 2, 1), 'No change');
        $this->assertEquals(mlang_string::fix_syntax('Completed 100% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(mlang_string::fix_syntax('Completed 100%% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(mlang_string::fix_syntax("Windows\r\nsucks", 2, 1), "Windows\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Linux\nsucks", 2, 1), "Linux\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("Mac\rsucks", 2, 1), "Mac\nsucks");
        $this->assertEquals(mlang_string::fix_syntax("LINE TABULATION\x0Bnewline", 2, 1), "LINE TABULATION\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("FORM FEED\x0Cnewline", 2, 1), "FORM FEED\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 2, 1), "END OF TRANSMISSION BLOCK\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("END OF MEDIUM\x19newline", 2, 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("SUBSTITUTE\x1Anewline", 2, 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 2, 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("NEXT LINE\xC2\x85newline", 2, 1), "NEXT LINE\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("START OF STRING\xC2\x98newline", 2, 1), "START OF STRING\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 2, 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 2, 1), "Unicode Zl\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 2, 1), "Unicode Zp\nnewline");
        $this->assertEquals(mlang_string::fix_syntax("Empty\n\n\n\n\n\nlines", 2, 1), "Empty\n\n\nlines");
        $this->assertEquals(mlang_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  ", 2, 1), "Trailing\n  whitespace\nat\nmultilines");
        $this->assertEquals(mlang_string::fix_syntax('Do not escape $variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(mlang_string::fix_syntax('Do not escape \$variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(mlang_string::fix_syntax('Do not escape $alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(mlang_string::fix_syntax('Do not escape \$alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(mlang_string::fix_syntax('Do not escape \$a names', 2, 1), 'Do not escape $a names');
        $this->assertEquals(mlang_string::fix_syntax('String $a placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('String {$a} placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a', 2, 1), 'Trailing {$a}');
        $this->assertEquals(mlang_string::fix_syntax('$a leading', 2, 1), '{$a} leading');
        $this->assertEquals(mlang_string::fix_syntax('$a', 2, 1), '{$a}');
        $this->assertEquals(mlang_string::fix_syntax('$a->single', 2, 1), '{$a->single}');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a->foobar', 2, 1), 'Trailing {$a->foobar}');
        $this->assertEquals(mlang_string::fix_syntax('Trailing {$a}', 2, 1), 'Trailing {$a}');
        $this->assertEquals(mlang_string::fix_syntax('Hit $a-times', 2, 1), 'Hit {$a}-times');
        $this->assertEquals(mlang_string::fix_syntax('This is $a_book', 2, 1), 'This is $a_book');
        $this->assertEquals(mlang_string::fix_syntax('Object $a->foo placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Object {$a->foo} placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(mlang_string::fix_syntax('Trailing $a->bar', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(mlang_string::fix_syntax('Trailing {$a->bar}', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(mlang_string::fix_syntax('Invalid $a-> placeholder', 2, 1), 'Invalid {$a}-> placeholder'); // weird but BC
        $this->assertEquals(mlang_string::fix_syntax('<strong>AMOS</strong>', 2, 1), '<strong>AMOS</strong>');
        $this->assertEquals(mlang_string::fix_syntax("'Murder!', she wrote", 2, 1), "'Murder!', she wrote"); // will be escaped by var_export()
        $this->assertEquals(mlang_string::fix_syntax("\'Murder!\', she wrote", 2, 1), "'Murder!', she wrote"); // will be escaped by var_export()
        $this->assertEquals(mlang_string::fix_syntax("\t  Trim Hunter  \t\t", 2, 1), 'Trim Hunter');
        $this->assertEquals(mlang_string::fix_syntax('Delete role "$a->role"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(mlang_string::fix_syntax('Delete role \"$a->role\"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(mlang_string::fix_syntax('See &#36;CFG->foo', 2, 1), 'See $CFG->foo');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\0 NULL control character", 2, 1), 'Delete ASCII NULL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 2, 1), 'Delete ASCII ENQUIRY control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 2, 1), 'Delete ASCII ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x07 BELL control character", 2, 1), 'Delete ASCII BELL control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 2, 1), 'Delete ASCII SHIFT OUT control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 2, 1), 'Delete ASCII SHIFT IN control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 2, 1), 'Delete ASCII DATA LINK ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 2, 1), 'Delete ASCII DEVICE CONTROL ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 2, 1), 'Delete ASCII DEVICE CONTROL TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 2, 1), 'Delete ASCII DEVICE CONTROL THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 2, 1), 'Delete ASCII DEVICE CONTROL FOUR control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 2, 1), 'Delete ASCII NEGATIVE ACKNOWLEDGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 2, 1), 'Delete ASCII SYNCHRONOUS IDLE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 2, 1), 'Delete ASCII ESCAPE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ASCII\x7F DELETE control character", 2, 1), 'Delete ASCII DELETE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 2, 1), 'Delete ISO 8859 PADDING CHARACTER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 2, 1), 'Delete ISO 8859 HIGH OCTET PRESET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 2, 1), 'Delete ISO 8859 NO BREAK HERE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 2, 1), 'Delete ISO 8859 INDEX control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 2, 1), 'Delete ISO 8859 START OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 2, 1), 'Delete ISO 8859 END OF SELECTED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 2, 1), 'Delete ISO 8859 CHARACTER TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character", 2, 1), 'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 2, 1), 'Delete ISO 8859 LINE TABULATION SET control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 2, 1), 'Delete ISO 8859 PARTIAL LINE FORWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 2, 1), 'Delete ISO 8859 PARTIAL LINE BACKWARD control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 2, 1), 'Delete ISO 8859 REVERSE LINE FEED control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 2, 1), 'Delete ISO 8859 SINGLE SHIFT TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 2, 1), 'Delete ISO 8859 SINGLE SHIFT THREE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 2, 1), 'Delete ISO 8859 DEVICE CONTROL STRING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 2, 1), 'Delete ISO 8859 PRIVATE USE ONE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 2, 1), 'Delete ISO 8859 PRIVATE USE TWO control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 2, 1), 'Delete ISO 8859 SET TRANSMIT STATE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 2, 1), 'Delete ISO 8859 MESSAGE WAITING control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 2, 1), 'Delete ISO 8859 START OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 2, 1), 'Delete ISO 8859 END OF GUARDED AREA control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character", 2, 1), 'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 2, 1), 'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 2, 1), 'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 2, 1), 'Delete ISO 8859 OPERATING SYSTEM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 2, 1), 'Delete ISO 8859 PRIVACY MESSAGE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 2, 1), 'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 2, 1), 'Delete Unicode ZERO WIDTH SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character", 2, 1), 'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character');
        $this->assertEquals(mlang_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 2, 1), 'Delete Unicode REPLACEMENT CHARACTER control character');
    }

    public function test_clean_text() {
        $component = new mlang_component('doodle', 'xx', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('first', "Line\n\n\n\n\n\n\nline"));
        $component->add_string(new mlang_string('second', 'This \really \$a sucks'));
        $component->clean_texts();
        $this->assertEquals("Line\n\nline", $component->get_string('first')->text); // one blank line allowed in format 1
        $this->assertEquals('This really \$a sucks', $component->get_string('second')->text);
        $component->clear();
        unset($component);

        $component = new mlang_component('doodle', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('first', "Line\n\n\n\n\n\n\nline"));
        $component->add_string(new mlang_string('second', 'This \really \$a sucks. Yes {$a}, it {$a->does}'));
        $component->add_string(new mlang_string('third', "Multi   line  \n  trailing  "));
        $component->clean_texts();
        $this->assertEquals("Line\n\n\nline", $component->get_string('first')->text); // two blank lines allowed in format 2
        $this->assertEquals('This \really \$a sucks. Yes {$a}, it {$a->does}', $component->get_string('second')->text);
        $this->assertEquals("Multi   line\n  trailing", $component->get_string('third')->text);
        $component->clear();
        unset($component);
    }

    public function test_clean_texts_rebase() {
        $this->resetAfterTest();
        $stage = new mlang_stage();
        $component = new mlang_component('doodle', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('multiline', "Multi   line  \n  trailing  "));
        $stage->add($component);
        $stage->commit('Initial commit', array('source' => 'unittest'));
        $component->clear();

        $component = mlang_component::from_snapshot('doodle', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('multiline'));
        $this->assertEquals("Multi   line  \n  trailing  ", $component->get_string('multiline')->text);
        $component->clean_texts();
        $stage->add($component);
        $stage->commit('Cleaned', array('source' => 'unittest'));
        $component->clear();

        $component = mlang_component::from_snapshot('doodle', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('multiline'));
        $this->assertEquals("Multi   line\n  trailing", $component->get_string('multiline')->text);
        $component->clear();
        unset($component);
    }

    /*
    public function test_get_phpfile_location() {
        $component = new mlang_component('moodle', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertEquals('lang/en_utf8/moodle.php', $component->get_phpfile_location());

        $component = new mlang_component('moodle', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals('lang/en/moodle.php', $component->get_phpfile_location());

        $component = new mlang_component('workshop', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertEquals('lang/en_utf8/workshop.php', $component->get_phpfile_location());

        $component = new mlang_component('workshop', 'en', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals('mod/workshop/lang/en/workshop.php', $component->get_phpfile_location());
        $this->assertEquals('lang/en/workshop.php', $component->get_phpfile_location(false));

        $component = new mlang_component('workshopform_accumulative', 'cs', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals('mod/workshop/form/accumulative/lang/cs/workshopform_accumulative.php', $component->get_phpfile_location());
        $this->assertEquals('lang/cs/workshopform_accumulative.php', $component->get_phpfile_location(false));

        $component = new mlang_component('gradeexport_xml', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $this->assertEquals('lang/en_utf8/gradeexport_xml.php', $component->get_phpfile_location());

        $component = new mlang_component('gradeexport_xml', 'es', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals('grade/export/xml/lang/es/gradeexport_xml.php', $component->get_phpfile_location());
        $this->assertEquals('lang/es/gradeexport_xml.php', $component->get_phpfile_location(false));
    }
     */

    public function test_get_string_keys() {
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $keys = $component->get_string_keys();
        $this->assertEquals(count($keys), 0);
        $this->assertTrue(empty($keys));

        $component->add_string(new mlang_string('hello', 'Hello'));
        $component->add_string(new mlang_string('world', 'World'));
        $keys = $component->get_string_keys();
        $keys = array_flip($keys);
        $this->assertEquals(count($keys), 2);
        $this->assertTrue(isset($keys['hello']));
        $this->assertTrue(isset($keys['world']));
    }

    public function test_intersect() {
        $master = new mlang_component('moodle', 'en', mlang_version::by_branch('MOODLE_18_STABLE'));
        $master->add_string(new mlang_string('one', 'One'));
        $master->add_string(new mlang_string('two', 'Two'));
        $master->add_string(new mlang_string('three', 'Three'));

        $slave = new mlang_component('moodle', 'cs', mlang_version::by_branch('MOODLE_18_STABLE'));
        $slave->add_string(new mlang_string('one', 'Jedna'));
        $slave->add_string(new mlang_string('two', 'Dva'));
        $slave->add_string(new mlang_string('seven', 'Sedm'));
        $slave->add_string(new mlang_string('eight', 'Osm'));

        $slave->intersect($master);
        $this->assertEquals(2, count($slave->get_string_keys()));
        $this->assertTrue($slave->has_string('one'));
        $this->assertTrue($slave->has_string('two'));
    }

    public function test_extract_script_from_text() {
        $noscript = 'This is text with no AMOS script';
        $emptyarray = mlang_tools::extract_script_from_text($noscript);
        $this->assertTrue(empty($emptyarray));

        $oneliner = 'MDL-12345 Some message AMOS   BEGIN  MOV   [a,  b],[c,d] CPY [e,f], [g ,h]  AMOS'."\t".'END BEGIN ignore AMOS   ';
        $script = mlang_tools::extract_script_from_text($oneliner);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV [a, b],[c,d]', $script[0]);
        $this->assertEquals('CPY [e,f], [g ,h]', $script[1]);

        $multiline = 'This is a typical usage of AMOS script in a commit message
                    AMOS BEGIN
                     MOV a,b  
                     CPY  c,d
                    AMOS END
                   Here it can continue';
        $script = mlang_tools::extract_script_from_text($multiline);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV a,b', $script[0]);
        $this->assertEquals('CPY c,d', $script[1]);

        // if there is no empty line between commit subject and AMOS script:
        $oneliner2 = 'Blah blah blah AMOS   BEGIN  CMD AMOS END blah blah';
        $script = mlang_tools::extract_script_from_text($oneliner2);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(1, count($script));
        $this->assertEquals('CMD', $script[0]);
    }

    public function test_list_languages() {
        $this->resetAfterTest();
        $stage = new mlang_stage();
        $component = new mlang_component('langconfig', 'en', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('thislanguageint', 'English'));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('langconfig', 'cs', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new mlang_string('thislanguageint', 'Czech'));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('langconfig', 'cs', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new mlang_string('thislanguageint', 'CS'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering two languages', array('source' => 'unittest'));

        $langs = mlang_tools::list_languages(true, true, false);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 2);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertTrue(array_key_exists('en', $langs));
        $this->assertEquals($langs['en'], 'English');
        $this->assertEquals($langs['cs'], 'Czech');

        $langs = mlang_tools::list_languages(false, true, true);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 1);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertEquals($langs['cs'], 'Czech (cs)');
    }

    public function test_list_components() {

        $this->resetAfterTest();

        $stage = new mlang_stage();
        $component = new mlang_component('workshop', 'en', mlang_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new mlang_string('modulename', 'Workshop'));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('workshop', 'en', mlang_version::by_branch('MOODLE_39_STABLE'));
        $component->add_string(new mlang_string('modulename', 'Workshop 2.x'));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('auth', 'en', mlang_version::by_branch('MOODLE_310_STABLE'));
        $component->add_string(new mlang_string('foo', 'Bar'));
        $stage->add($component);
        $component->clear();

        // This will play no role as there is no English original.
        $component = new mlang_component('assign', 'cs', mlang_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'Úkol'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering component strings', array('source' => 'unittest'));

        $comps = mlang_tools::list_components();

        $this->assertEquals(gettype($comps), 'array');
        $this->assertEquals(count($comps), 2);
        $this->assertEquals($comps['auth'], 310);
        $this->assertEquals($comps['workshop'], 38);
    }

    public function test_execution_strings() {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new mlang_stage();
        $version = mlang_version::by_branch('MOODLE_20_STABLE');
        // this is to prevent situation where a string is added and immediately removed in the same second. Such
        // situations are not supported yet very well in AMOS as it would require to rewrite well tuned getting
        // component from snapshot
        $past = time() - 1;
        $component = new mlang_component('auth', 'en', $version);
        $component->add_string(new mlang_string('authenticate', 'Authenticate', $past));
        $component->add_string(new mlang_string('ldap', 'Use LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('auth_ldap', 'en', $version);
        $component->add_string(new mlang_string('pluginname', 'LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new mlang_component('auth', 'cs', $version);
        $component->add_string(new mlang_string('authenticate', 'Autentizovat', $past));
        $component->add_string(new mlang_string('ldap', 'Pouzit LDAP', $past));
        $stage->add($component);
        $component->clear();
        unset($component);

        $stage->commit('Adding some testing strings', array('source' => 'unittest'));
        unset($stage);

        $stage = mlang_tools::execute('MOV [ldap,core_auth],[pluginname,auth_ldap]', $version);
        $stage->commit('Moving string ldap into auth_ldap', array('source' => 'unittest'));
        unset($stage);

        $component = mlang_component::from_snapshot('auth_ldap', 'cs', $version);
        $this->assertTrue($component->has_string('pluginname'));
        $component->clear();

        $component = mlang_component::from_snapshot('auth', 'cs', $version);
        $this->assertFalse($component->has_string('ldap'));
        $component->clear();

        $component = mlang_component::from_snapshot('auth', 'en', $version);
        $this->assertTrue($component->has_string('ldap'));  // English string are not affected by AMOS script!
        $component->clear();

        $component = mlang_component::from_snapshot('auth_ldap', 'en', $version);
        $string = $component->get_string('pluginname');
        $this->assertEquals($string->text, 'LDAP');
        unset($string);
        $component->clear();
    }

    public function test_execution_strings_move() {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new mlang_stage();
        $version = mlang_version::by_branch('MOODLE_20_STABLE');
        $now = time();

        // this block emulates parse-core.php
        $component = new mlang_component('admin', 'en', $version);
        $component->add_string(new mlang_string('configsitepolicy', 'OLD', $now - 2));
        $stage->add($component);
        $stage->rebase($now - 2, true, $now - 2);
        $stage->commit('Committed initial English string', array('source' => 'unittest'), true, $now - 2);
        $component->clear();
        unset($component);

        // this block emulates parse-lang.php
        $component = new mlang_component('admin', 'cs', $version);
        $component->add_string(new mlang_string('configsitepolicy', 'OLD in cs', $now - 1));
        $stage->add($component);
        $stage->rebase();
        $stage->commit('Committed initial Czech translation', array('source' => 'unittest'), true, $now - 1);
        $component->clear();
        unset($component);

        // this block emulates parse-core.php again later
        // now the string is moved in the English pack by the developer who provides AMOS script in commit message
        // this happened in b593d49d593ee778f525b4074f5ee7978c5e2960
        $component = new mlang_component('admin', 'en', $version);
        $component->add_string(new mlang_string('sitepolicy_help', 'NEW', $now));
        $component->add_string(new mlang_string('configsitepolicy', 'OLD', $now, true));
        $commitmsg = 'MDL-24570 multiple sitepolicy fixes + adding new separate guest user policy
AMOS BEGIN
 MOV [configsitepolicy,core_admin],[sitepolicy_help,core_admin]
AMOS END';
        $stage->add($component);
        $stage->rebase($now, true, $now);
        $stage->commit($commitmsg, array('source' => 'unittest'), true, $now);
        $component->clear();
        unset($component);

        // execute AMOS script if the commit message contains some
        if ($version->code >= 20) {
            $instructions = mlang_tools::extract_script_from_text($commitmsg);
            if (!empty($instructions)) {
                foreach ($instructions as $instruction) {
                    $changes = mlang_tools::execute($instruction, $version, $now);
                    $changes->rebase($now);
                    $changes->commit($commitmsg, array('source' => 'commitscript'), true, $now);
                    unset($changes);
                }
            }
        }

        // check the results
        $component = mlang_component::from_snapshot('admin', 'cs', $version, $now);
        $this->assertTrue($component->has_string('sitepolicy_help'));
        $this->assertEquals('OLD in cs', $component->get_string('sitepolicy_help')->text);
        $this->assertEquals(1, $component->get_number_of_strings());
    }

    public function test_legacy_component_name() {
        $this->assertEquals(testable_mlang_tools::legacy_component_name('core'), 'moodle');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('core_grades'), 'grades');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('block_foobar'), 'block_foobar');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('auth_oauth2'), 'auth_oauth2');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('mod_forum2'), 'forum2');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('mod_foobar'), 'foobar');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('moodle'), 'moodle');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('admin'), 'admin');
        $this->assertEquals(testable_mlang_tools::legacy_component_name(' mod_whitespace  '), 'whitespace');
        $this->assertEquals(testable_mlang_tools::legacy_component_name('[syntaxerr'), false);
        $this->assertEquals(testable_mlang_tools::legacy_component_name('syntaxerr,'), false);
        $this->assertEquals(testable_mlang_tools::legacy_component_name('syntax err'), false);
        $this->assertEquals(testable_mlang_tools::legacy_component_name('enrol__invalid'), false);
    }

    public function test_component_get_recent_timemodified() {
        $now = time();
        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals(0, $component->get_recent_timemodified());
        $component->add_string(new mlang_string('first', 'Hello', $now - 5));
        $component->add_string(new mlang_string('second', 'Moodle', $now - 12));
        $component->add_string(new mlang_string('third', 'World', $now - 4));
        $this->assertEquals($now - 4, $component->get_recent_timemodified());
    }

    public function test_merge_strings_from_another_component() {
        // prepare two components with some strings
        $component19 = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_19_STABLE'));
        $component19->add_string(new mlang_string('first', 'First $a'));
        $component19->add_string(new mlang_string('second', 'Second \"string\"'));
        $component19->add_string(new mlang_string('third', 'Third'));
        $component19->add_string(new mlang_string('fifth', 'Fifth \"string\"'));

        $component20 = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_20_STABLE'));
        $component20->add_string(new mlang_string('second', '*deleted*', null, true));
        $component20->add_string(new mlang_string('third', 'Third already merged'));
        $component20->add_string(new mlang_string('fourth', 'Fourth only in component20'));
        // merge component19 into component20
        mlang_tools::merge($component19, $component20);
        // check the results
        $this->assertEquals(4, $component19->get_number_of_strings());
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
        $this->assertEquals('*deleted*', $component20->get_string('second')->text);
        $this->assertTrue($component20->get_string('second')->deleted);
        $this->assertEquals('Third already merged', $component20->get_string('third')->text);
        $this->assertEquals('Fourth only in component20', $component20->get_string('fourth')->text);
        $this->assertFalse($component19->has_string('fourth'));
        $this->assertEquals('Fifth "string"', $component20->get_string('fifth')->text);
        // clear source component and make sure that strings are still in the new one
        $component19->clear();
        unset($component19);
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
    }

    public function test_get_affected_strings() {
        $this->resetAfterTest();
        $diff = file(dirname(__FILE__) . '/fixtures/parserdata002.txt');
        $affected = mlang_tools::get_affected_strings($diff);
        $this->assertEquals(count($affected), 5);
        $this->assertTrue(in_array('configdefaultuserroleid', $affected));
        $this->assertTrue(in_array('confignodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nolangupdateneeded', $affected));
        $this->assertTrue(in_array('mod/something:really_nasty-like0098187.this', $affected));
    }

    public function test_execution_forced_copy() {
        $this->resetAfterTest();

        $this->register_language('en', 22);
        $this->register_language('cs', 22);

        $stage = new mlang_stage();
        $version = mlang_version::by_branch('MOODLE_22_STABLE');
        $time = time();
        $component = new mlang_component('assignment', 'cs', $version);
        $component->add_string(new mlang_string('pluginname', 'Úkol (2.2)', $time - 60));
        $component->add_string(new mlang_string('modulename', 'Úkol', $time - 120));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Adding some testing strings', array('source' => 'unittest'));
        unset($stage);

        $stage = mlang_tools::execute('FCP [pluginname,assignment],[modulename,assignment]', $version);
        $stage->commit('Forced copy of a string', array('source' => 'unittest'));
        unset($stage);

        $component = mlang_component::from_snapshot('assignment', 'cs', $version);
        $this->assertEquals('Úkol (2.2)', $component->get_string('pluginname')->text);
        $this->assertEquals('Úkol (2.2)', $component->get_string('modulename')->text);
        $component->clear();
    }

    public function test_enfix_postmerge_cleanup() {
        $this->resetAfterTest();
        $now = time();
        $stage = new mlang_stage();
        $componentname = 'test';
        $version = mlang_version::by_branch('MOODLE_24_STABLE');

        // Prepare the initial state of the reference component
        $component = new mlang_component($componentname, 'en', $version);
        $component->add_string(new mlang_string('first', 'First $a', $now - 4 * WEEKSECS));
        $component->add_string(new mlang_string('second', 'Second \"string\"', $now - 4 * WEEKSECS));
        $component->add_string(new mlang_string('third', 'Third', $now - 4 * WEEKSECS));
        $component->add_string(new mlang_string('fifth', 'Fifth \"string\"', $now - 4 * WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('First version of en', array('source' => 'unittest'));

        // Prepare the component that is supposed to contain fixes for the reference component
        $component = new mlang_component($componentname, 'en_fix', $version);
        $component->add_string(new mlang_string('second', 'Second', $now - 3 * WEEKSECS)); // Real change
        $component->add_string(new mlang_string('third', 'Third', $now - 3 * WEEKSECS)); // No real change
        $component->add_string(new mlang_string('fourth', 'Fourth', $now - 3 * WEEKSECS)); // Only in en_fix (unexpected)
        $component->add_string(new mlang_string('fifth', 'Modified', $now - 3 * WEEKSECS)); // Real change
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('First version of en_fix', array('source' => 'unittest'));

        // Simulate the result of merging en_fix into en
        $component = new mlang_component($componentname, 'en', $version);
        $component->add_string(new mlang_string('second', 'Second', $now - 2 * WEEKSECS));
        $component->add_string(new mlang_string('fifth', 'Modified', $now - 2 * WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Merge en_fix into en', array('source' => 'unittest'));

        // Perform more changes at en_fix
        $component = new mlang_component($componentname, 'en_fix', $version);
        $component->add_string(new mlang_string('first', 'First', $now - WEEKSECS)); // New real change
        $component->add_string(new mlang_string('fifth', 'Fifth \"string\"', $now - WEEKSECS)); // Undo the change
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Another version of en_fix', array('source' => 'unittest'));

        // Simulate the enfix-cleanup.php execution
        $en = mlang_component::from_snapshot($componentname, 'en', $version);
        $enfix = mlang_component::from_snapshot($componentname, 'en_fix', $version);
        $enfix->intersect($en);
        $enfix->complement($en);
        $stage->add($enfix);
        $stage->rebase(null, true);
        $stage->commit('Removing strings merged into the English', null, true);

        // Check the result
        $enfix = mlang_component::from_snapshot($componentname, 'en_fix', $version);
        $this->assertEquals(2, $enfix->get_number_of_strings());
        $this->assertEquals('First', $enfix->get_string('first')->text);
        $this->assertEquals('Fifth \"string\"', $enfix->get_string('fifth')->text);
    }

    /**
     * Test that delegated transactions are supported and work as expected
     */
    public function test_simulate_commit_transaction_rollback() {
        global $DB;

        $this->preventResetByRollback(); // We test rollback here so we don't want the outer one.
        $this->resetAfterTest();

        $this->assertEquals(0, $DB->count_records('amos_commits'));
        $this->assertEquals(0, $DB->count_records('amos_repository'));
        $this->assertEquals(0, $DB->count_records('amos_texts'));
        $this->assertEquals(0, $DB->count_records('amos_snapshot'));

        $snapshot = (object)array(
            'branch' => 2500,
            'lang' => 'en',
            'component' => 'test',
            'stringid' => 'whatever',
            'repoid' => 12345,
        );
        $DB->insert_record('amos_snapshot', $snapshot);

        $this->assertEquals(1, $DB->count_records('amos_snapshot'));

        $thrown1 = false;
        $thrown2 = false;
        $thrown3 = false;
        $committed = false;

        try {
            $transaction = $DB->start_delegated_transaction();

            $commit = (object)array(
                'commitmsg' => 'This commit will fail',
                'timecommitted' => time(),
            );
            $commit->id = $DB->insert_record('amos_commits', $commit);

            $textid = $DB->insert_record('amos_texts', array('text' => 'Whatever', 'texthash' => sha1('Whatever')));

            $repo = (object)array(
                'commitid' => $commit->id,
                'branch' => 2500,
                'lang' => 'en',
                'component' => 'test',
                'stringid' => 'whatever',
                'textid' => $textid,
                'timemodified' => time() - 60,
                'deleted' => 0,
            );
            $repo->id = $DB->insert_record('amos_repository', $repo);

            $snapshot = (object)array(
                'branch' => 2500,
                'lang' => 'en',
                'component' => 'test',
                'stringid' => 'whatever',
                'repoid' => $repo->id,
            );
            $snapshot->id = $DB->insert_record('amos_snapshot', $snapshot); // This violates unique index

            $transaction->allow_commit();
            $committed = true;

        } catch (Exception $e) {
            try {
                $thrown1 = true;
                $transaction->rollback($e); // rolls back and rethrows the exception
                $thrown2 = true;
            } catch (Exception $e) {
                $thrown3 = true;
            }
        }

        $this->assertFalse($committed);
        $this->assertTrue($thrown1);
        $this->assertFalse($thrown2);
        $this->assertTrue($thrown3);
        $this->assertEquals(0, $DB->count_records('amos_commits'));
        $this->assertEquals(0, $DB->count_records('amos_repository'));
        $this->assertEquals(0, $DB->count_records('amos_texts'));
        $this->assertEquals(1, $DB->count_records('amos_snapshot'));
    }

    public function test_backport_translations() {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);
        $this->register_language('en_fix', 20);

        $stage = new mlang_stage();
        $version21 = mlang_version::by_branch('MOODLE_21_STABLE');
        $version22 = mlang_version::by_branch('MOODLE_22_STABLE');
        $version23 = mlang_version::by_branch('MOODLE_23_STABLE');
        $version24 = mlang_version::by_branch('MOODLE_24_STABLE');
        $version25 = mlang_version::by_branch('MOODLE_25_STABLE');
        $time = time();

        // Register Foo plugin English strings for Moodle 2.3 and higher (pretend that happened 180 days ago).
        $component = new mlang_component('foo', 'en', $version23);
        $component->add_string(new mlang_string('modulename', 'Foo', $time - 180 * DAYSECS));
        $component->add_string(new mlang_string('done', 'Done', $time - 180 * DAYSECS));
        $component->add_string(new mlang_string('aaa', 'AAA', $time - 180 * DAYSECS));
        $component->add_string(new mlang_string('bbb', 'BBB', $time - 180 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.3 strings', array('source' => 'unittest'));

        // Translate only some of the strings into Czech on 2.3.
        $component = new mlang_component('foo', 'cs', $version23);
        $component->add_string(new mlang_string('modulename', 'Fu', $time - 179 * DAYSECS));
        $component->add_string(new mlang_string('aaa', 'AAA', $time - 179 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.3 strings into Czech', array('source' => 'unittest'));

        // Change one and add one string in 2.4.
        $component = new mlang_component('foo', 'en', $version24);
        $component->add_string(new mlang_string('modulename', 'Foo', $time - 90 * DAYSECS));
        $component->add_string(new mlang_string('done', 'Finished', $time - 90 * DAYSECS)); // Changed in 2.4
        $component->add_string(new mlang_string('end', 'End', $time - 90 * DAYSECS)); // New in 2.4
        $component->add_string(new mlang_string('aaa', 'AAA', $time - 90 * DAYSECS));
        $component->add_string(new mlang_string('bbb', 'BBB', $time - 90 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.4 strings', array('source' => 'unittest'));

        // Update 2.3 Czech translation.
        $component = new mlang_component('foo', 'cs', $version24);
        $component->add_string(new mlang_string('modulename', 'Fu', $time - 45 * DAYSECS));
        $component->add_string(new mlang_string('done', 'Ukončeno', $time - 45 * DAYSECS));
        $component->add_string(new mlang_string('end', 'Konec', $time - 45 * DAYSECS));
        $component->add_string(new mlang_string('bbb', 'BBB', $time - 45 * DAYSECS));
        $component->add_string(new mlang_string('orphan', 'Orphan', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.4 strings into Czech', array('source' => 'unittest'));

        $component = new mlang_component('foo', 'en_fix', $version24);
        $component->add_string(new mlang_string('modulename', 'Fooh', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Since 2.4, the module name is different', array('source' => 'unittest'));

        testable_mlang_tools::backport_translations('foo');

        $component = mlang_component::from_snapshot('foo', 'cs', $version23);
        // The modulename translation on 2.3 was not affected by the 2.4 version because the value is identical.
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('modulename')->timemodified);
        // This was already present.
        $this->assertTrue($component->has_string('aaa'));
        // The bbb translation was backported from 2.4 with the current timestamp.
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->get_string('bbb')->timemodified >= $time);
        // The end string is introduced in 2.4 only so it was not backported.
        $this->assertFalse($component->has_string('end'));
        // Same reason, the orphan string is not in English is it is not backported.
        $this->assertFalse($component->has_string('orphan'));
        // The 2.4 English original of "done" is different from 2.3, so 2.4 translation is not backported.
        $this->assertFalse($component->has_string('done'));
        $component->clear();

        $component = mlang_component::from_snapshot('foo', 'en_fix', $version23);
        // The en_fix strings are exception and not backported.
        $this->assertFalse($component->has_string('modulename'));
        $component->clear();

        $component = mlang_component::from_snapshot('foo', 'en_fix', $version24);
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = mlang_component::from_snapshot('foo', 'en_fix', $version25);
        // The string exists on 2.5 implicitly (as it exists since 2.4), not as a result of backporting.
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = mlang_component::from_snapshot('foo', 'cs', $version24);
        // There was no change committed for 2.4 and the original 2.3 version is used.
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('modulename')->timemodified);
        $this->assertTrue($component->has_string('aaa'));
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('aaa')->timemodified);
        // This was committed in 2.4.
        $this->assertTrue($component->has_string('bbb'));
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('bbb')->timemodified);
        $this->assertTrue($component->has_string('done'));
        $this->assertEquals('Ukončeno', $component->get_string('done')->text);
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        // Translate the missing "done" string in 2.3 cs.
        $component = new mlang_component('foo', 'cs', $version23);
        $component->add_string(new mlang_string('done', 'Hotovo', $time - 40 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add missing 2.3 string', array('source' => 'unittest'));

        // The added translation is valid for 2.3 only as the 2.4 version still applies even if was committed earlier.
        $component = mlang_component::from_snapshot('foo', 'cs', $version23);
        $this->assertEquals('Hotovo', $component->get_string('done')->text);
        $this->assertEquals($time - 40 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        $component = mlang_component::from_snapshot('foo', 'cs', $version24);
        $this->assertEquals('Ukončeno', $component->get_string('done')->text);
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        // New version of the Foo for Moodle 2.5 is released, strings have not changed.
        $component = new mlang_component('foo', 'en', $version25);
        $component->add_string(new mlang_string('modulename', 'Foo', $time - 20 * DAYSECS));
        $component->add_string(new mlang_string('done', 'Finished', $time - 20 * DAYSECS));
        $component->add_string(new mlang_string('end', 'End', $time - 20 * DAYSECS));
        $component->add_string(new mlang_string('aaa', 'AAA', $time - 20 * DAYSECS));
        $component->add_string(new mlang_string('bbb', 'BBB', $time - 20 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.5 strings', array('source' => 'unittest'));

        testable_mlang_tools::backport_translations('foo', ['en', 'cs']);

        $component = mlang_component::from_snapshot('foo', 'cs', $version25);
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertTrue($component->has_string('aaa'));
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->has_string('done'));
        $component->clear();

        // Foo plugin is released for 2.1 too (marked as supporting 2.1 in the plugins directory).
        $component = new mlang_component('foo', 'en', $version21);
        $component->add_string(new mlang_string('modulename', 'Foo', $time - 15 * DAYSECS));
        $component->add_string(new mlang_string('done', 'Done', $time - 15 * DAYSECS));
        $component->add_string(new mlang_string('aaa', 'AAA', $time - 15 * DAYSECS));
        $component->add_string(new mlang_string('bbb', 'BBB', $time - 15 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.1 strings', array('source' => 'unittest'));

        // Without backporting, there would be no 2.1 Czech translation.
        $component = mlang_component::from_snapshot('foo', 'cs', $version21);
        $this->assertFalse($component->has_string());

        // Backport the Czech translations.
        testable_mlang_tools::backport_translations('foo');

        $component = mlang_component::from_snapshot('foo', 'cs', $version21);
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertTrue($component->get_string('modulename')->timemodified >= $time);
        $this->assertEquals('AAA', $component->get_string('aaa')->text);
        $this->assertTrue($component->get_string('aaa')->timemodified >= $time);
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->get_string('bbb')->timemodified >= $time);
        // The end string is introduced in 2.4 only so it was not backported.
        $this->assertFalse($component->has_string('end'));
        // Same reason, the orphan string is not in English is it is not backported.
        $this->assertFalse($component->has_string('orphan'));
        // The 2.3 English original of "done" is backported even if it is changed again in 2.4.
        $this->assertEquals('Hotovo', $component->get_string('done')->text);
        $this->assertTrue($component->get_string('done')->timemodified >= $time);
        $component->clear();
    }

    /**
     * Test the {@link mlang_string::should_be_included_in_stats()} results.
     */
    public function test_should_be_included_in_stats() {
        $this->resetAfterTest();

        $this->assertTrue((new mlang_string('one', 'One'))->should_be_included_in_stats());
        $this->assertFalse((new mlang_string('one_link', 'foo'))->should_be_included_in_stats());
        $this->assertFalse((new mlang_string('del', '', null, true))->should_be_included_in_stats());
    }

    /**
     * Test the {@link mlang_component::get_number_of_strings()} results.
     */
    public function test_get_number_of_strings() {
        $this->resetAfterTest();

        $component = new mlang_component('test', 'xx', mlang_version::by_branch('MOODLE_37_STABLE'));

        $this->assertEquals(0, $component->get_number_of_strings());

        $component->add_string(new mlang_string('welcome', 'Welcome'));

        $this->assertEquals(1, $component->get_number_of_strings());
        $this->assertEquals(1, $component->get_number_of_strings(true));

        $component->add_string(new mlang_string('welcome_help', 'This is used for the help tooltip.'));

        $this->assertEquals(2, $component->get_number_of_strings());
        $this->assertEquals(2, $component->get_number_of_strings(true));

        $component->add_string(new mlang_string('welcome_link', 'test/welcome'));

        $this->assertEquals(3, $component->get_number_of_strings());
        $this->assertEquals(2, $component->get_number_of_strings(true));

        $component->add_string(new mlang_string('deleted', '', null, true));

        $this->assertEquals(4, $component->get_number_of_strings());
        $this->assertEquals(2, $component->get_number_of_strings(true));

        $component->unlink_string('deleted');
        $component->unlink_string('welcome_link');

        $this->assertEquals(2, $component->get_number_of_strings());
        $this->assertEquals(2, $component->get_number_of_strings(true));

        $component->unlink_string('welcome_help');

        $this->assertEquals(1, $component->get_number_of_strings());
        $this->assertEquals(1, $component->get_number_of_strings(true));

        $component->clear();
        unset($component);
    }

    public function test_stash_push() {

    }
}
