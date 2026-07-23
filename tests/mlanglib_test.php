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
 * Unit tests for Moodle language manipulation library defined in mlanglib.php
 *
 * @package     local_amos
 * @category    test
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

use local_amos\local\amos_component;
use local_amos\local\amos_stage;
use local_amos\local\amos_string;
use local_amos\local\amos_tools;
use local_amos\local\amos_version;
use local_amos_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Makes protected method accessible for testing purposes
 */
class testable_amos_tools extends amos_tools {
    /**
     * Turn protected method to public to be able to test it directly.
     *
     * @param string $newstyle
     */
    public static function legacy_component_name($newstyle) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
        return parent::legacy_component_name($newstyle);
    }
}

/**
 * Test cases for the internal AMOS API
 */
final class mlanglib_test extends local_amos_testcase {
    /**
     * Excercise various helper methods
     */
    public function test_helpers(): void {
        $this->assertEquals('workshop', amos_component::name_from_filename('/web/moodle/mod/workshop/lang/en/workshop.php'));
        $this->assertFalse(amos_string::differ(
            new amos_string('first', 'This is a test string'),
            new amos_string('second', 'This is a test string')
        ));
        $this->assertFalse(amos_string::differ(
            new amos_string('first', '  This is a test string  '),
            new amos_string('second', 'This is a test string ')
        ));
        $this->assertTrue(amos_string::differ(
            new amos_string('first', 'This is a test string'),
            new amos_string('first', 'This is a test string!')
        ));
        $this->assertTrue(amos_string::differ(
            new amos_string('empty', ''),
            new amos_string('null', null)
        ));
        $this->assertFalse(amos_string::differ(
            new amos_string('null', null),
            new amos_string('anothernull', null)
        ));
    }

    /**
     * Test that components and stages are iterable and countable.
     */
    public function test_iterable_and_countable(): void {

        $this->resetAfterTest();

        $component = new amos_component('test', 'en', amos_version::by_code(310));

        $this->assertSame(0, count($component));
        foreach ($component as $string) {
            $this->assertTrue(false);
        }

        $component->add_string(new amos_string('foo', 'Foo'));
        $this->assertEquals(1, count($component));
        $i = 0;
        foreach ($component as $string) {
            $this->assertInstanceOf(amos_string::class, $string);
            $i++;
        }
        $this->assertSame(1, $i);

        $component->add_string(new amos_string('bar', 'Bar'));
        $this->assertEquals(2, count($component));
        $i = 0;
        foreach ($component as $string) {
            $this->assertInstanceOf(amos_string::class, $string);
            $i++;
        }
        $this->assertSame(2, $i);

        $stage = new amos_stage();
        $this->assertSame(0, count($stage));
        foreach ($stage as $component) {
            $this->assertTrue(false);
        }

        $stage->add($component);
        $this->assertSame(1, count($stage));
        $i = 0;
        foreach ($stage as $component) {
            $this->assertInstanceOf(amos_component::class, $component);
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
    public function test_simple_string_lifecycle(): void {
        global $DB, $CFG, $USER;

        $this->resetAfterTest();

        // Basic operations.
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
        $this->assertFalse($component->has_string('nonexisting'));
        $this->assertFalse($component->has_string('welcome'));
        $component->add_string(new amos_string('welcome', 'Welcome'));
        $this->assertTrue($component->has_string());
        $this->assertFalse($component->has_string('nonexisting'));
        $this->assertTrue($component->has_string('welcome'));
        $this->assertNull($component->get_string('nonexisting'));
        $s = $component->get_string('welcome');
        $this->assertTrue($s instanceof amos_string);
        $this->assertEquals('welcome', $s->id);
        $this->assertEquals('Welcome', $s->text);
        $component->unlink_string('nonexisting');
        $this->assertTrue($component->has_string());
        $component->unlink_string('welcome');
        $this->assertFalse($component->has_string());
        $component->add_string(new amos_string('welcome', 'Welcome'));
        $component->clear();
        unset($component);

        // Commit a single string.
        $now = time();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome', $now));
        $stage = new amos_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('welcome'));
        $component->clear();

        // Add two other strings.
        $now = time();
        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('hello', 'Hello', $now));
        $component->add_string(new amos_string('world', 'World', $now));
        $stage->add($component);
        $stage->commit('Two other string into AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $component->clear();

        // Delete a string.
        $now = time();
        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string('welcome'));
        $this->assertTrue($component->has_string('hello'));
        $this->assertTrue($component->has_string('world'));
        $component->clear();
    }

    public function test_add_existing_string(): void {
        $this->resetAfterTest();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_22_STABLE'));
        $this->assertFalse($component->has_string());
        $component->add_string(new amos_string('welcome', 'Welcome'));
        $component->add_string(new amos_string('welcome', 'Overwriting existing must be forced'), true);
        $this->assertEquals($component->get_string('welcome')->text, 'Overwriting existing must be forced');
        $this->expectException('coding_exception');
        $component->add_string(new amos_string('welcome', 'Overwriting existing throws exception'));
        $component->clear();
        unset($component);
    }

    public function test_deleted_has_same_timemodified(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome', $now));
        $stage = new amos_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', '', $now, true));
        $stage->add($component);
        $stage->commit('Marking string as deleted', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
        $component->clear();
        unset($component);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string());
    }

    public function test_more_recently_inserted_wins(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome', $now));
        $stage = new amos_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome!', $now));
        $stage->add($component);
        $stage->commit('Making a modification of the string with the same timestamp', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);

        $stage = new amos_stage();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('welcome', 'Welcome!', $now, 1));
        $stage->add($component);
        $stage->commit('Deleting the string with the same timestamp', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertFalse($component->has_string('welcome'));
        $component->clear();
        unset($component);

        $component = amos_component::from_snapshot('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'), null, true);
        $this->assertTrue($component->get_string('welcome')->deleted);
        $component->clear();
        unset($component);
    }

    public function test_component_from_phpfile_same_timestamp(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        // Get the initial strings from a file.
        $filecontents = <<<EOF
<?php
\$string['welcome'] = 'Welcome';

EOF;
        $tmp = make_temp_directory('amos');
        $filepath = $tmp . '/mlangunittest.php';
        file_put_contents($filepath, $filecontents);

        $component = amos_component::from_phpfile($filepath, 'en', amos_version::by_branch('MOODLE_20_STABLE'), $now, 'test', 2);
        $stage = new amos_stage();
        $stage->add($component);
        $stage->commit('First string in AMOS', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unlink($filepath);

        $filecontents = <<<EOF
<?php
\$string['welcome'] = 'Welcome!';

EOF;
        file_put_contents($filepath, $filecontents);

        // Commit modified file with the same timestamp.
        $component = amos_component::from_phpfile($filepath, 'en', amos_version::by_branch('MOODLE_20_STABLE'), $now, 'test', 2);
        $stage->add($component);
        $stage->commit('The string modified in the same timestamp', ['source' => 'unittest'], true);
        $component->clear();
        unset($component);
        unset($stage);
        unlink($filepath);

        // Now make sure that the more recently committed string wins.
        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);

        $component = amos_component::from_snapshot('test', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals($component->get_string('welcome')->text, 'Welcome!');
        $component->clear();
        unset($component);
    }

    public function test_component_from_phpfile_legacy_format(): void {
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

        $component = amos_component::from_phpfile($filepath, 'en', amos_version::by_branch('MOODLE_20_STABLE'), null, null, 1);
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertTrue($component->has_string('syntax'));
        $this->assertEquals("Multiline\nstring", $component->get_string('about')->text);
        $this->assertEquals('What {$a}\'Pe%"be', $component->get_string('syntax')->text);
        $this->assertEquals('%Y-%m-%d-%H-%M', $component->get_string('percents')->text);
        $component->clear();

        $component = amos_component::from_phpfile($filepath, 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('about'));
        $this->assertTrue($component->has_string('pluginname'));
        $this->assertTrue($component->has_string('author'));
        $this->assertTrue($component->has_string('syntax'));
        $this->assertEquals("Multiline\nstring", $component->get_string('about')->text);
        $this->assertEquals('What $a\'Pe%%\"be', $component->get_string('syntax')->text);
        $this->assertEquals('%%Y-%%m-%%d-%%H-%%M', $component->get_string('percents')->text);
        $component->clear();

        $component = amos_component::from_phpfile($filepath, 'en', amos_version::by_branch('MOODLE_19_STABLE'));
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

    public function test_explicit_rebasing(): void {
        $this->resetAfterTest();
        // Prepare the "cap" (base for rebasing).
        $stage = new amos_stage();
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Tree'));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing without string removal - the stage is not complete.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
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
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertFalse($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing with string removal - the stage is considered to be the full snapshot of the state.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        // String 'two' is missing and we want it to be removed from repository.
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
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
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_21_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        // String 'two' is missing and we want it to be removed from repository.
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_21_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
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

    public function test_rebasing_deletion_already_deleted(): void {
        $this->resetAfterTest();
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('trash', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('delme', 'Del me', time() - 1000, true));
        $stage->add($component);
        $stage->commit('The string was already born deleted... sad');
        $this->assertFalse($stage->has_component());
        unset($stage);

        $stage = new amos_stage();
        $component = new amos_component('trash', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('delme', 'Del me', null, true));
        $stage->add($component);
        $stage->rebase();
        $this->assertFalse($stage->has_component());
    }

    public function test_implicit_rebasing_during_commit(): void {
        global $DB;

        $this->resetAfterTest();

        // Prepare the "cap" (base for rebasing).
        $stage = new amos_stage();
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Tree'));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Commit the fix of the string.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->commit('Fixed typo Tree > Three', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Get the most recent version (so called "cap") of the component.
        $cap = amos_component::from_snapshot('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $concat = '';
        foreach ($cap as $s) {
            $concat .= $s->text;
        }
        // Strings in the cap shall be ordered by stringid.
        $this->assertEquals('OneThreeTwo', $concat);
    }

    /**
     * Rebasing should respect commits in whatever order so it is safe to re-run the import scripts
     */
    public function test_non_chronological_commits(): void {
        global $DB;

        $this->resetAfterTest();

        // Firstly commit the most recent version.
        $today = time();
        $stage = new amos_stage();
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Today Foo', $today));
        $component->add_string(new amos_string('bar', 'New Bar', $today));
        $component->add_string(new amos_string('job', 'Boring', $today));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // We are re-importing the history - let us commit the version that was actually created yesterday.
        $yesterday = time() - DAYSECS;
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Foo', $yesterday));
        $component->add_string(new amos_string('job', 'Boring', $yesterday));
        $stage->add($component);
        $stage->rebase($yesterday, true, $yesterday);
        $rebased = $stage->get_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf(amos_component::class, $rebased);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertTrue($rebased->has_string('foo'));
        $this->assertFalse($rebased->has_string('bar'));
        $this->assertTrue($rebased->has_string('job'));

        // And the same case using rebase() without deleting.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Foo', $yesterday));
        $component->add_string(new amos_string('job', 'Boring', $yesterday));
        $stage->add($component);
        $stage->rebase($yesterday);
        $rebased = $stage->get_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf(amos_component::class, $rebased);
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
    public function test_fix_syntax_sanity_v1_strings(): void {
        $this->assertEquals(amos_string::fix_syntax('No change', 1), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(amos_string::fix_syntax('Completed 100%% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks", 1), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks", 1), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks", 1), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline", 1), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline", 1), "FORM FEED\nnewline");
        $this->assertEquals(
            amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 1),
            "END OF TRANSMISSION BLOCK\nnewline"
        );
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline", 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline", 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline", 1), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline", 1), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 1), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 1), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines", 1), "Empty\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace \t \nat \nmultilines  ", 1),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Escape $variable names', 1), 'Escape \$variable names');
        $this->assertEquals(amos_string::fix_syntax('Escape $alike names', 1), 'Escape \$alike names');
        $this->assertEquals(amos_string::fix_syntax('String $a placeholder', 1), 'String $a placeholder');
        $this->assertEquals(amos_string::fix_syntax('Escaped \$a', 1), 'Escaped \$a');
        $this->assertEquals(amos_string::fix_syntax('Wrapped {$a}', 1), 'Wrapped {$a}');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a', 1), 'Trailing $a');
        $this->assertEquals(amos_string::fix_syntax('$a leading', 1), '$a leading');
        $this->assertEquals(amos_string::fix_syntax('Hit $a-times', 1), 'Hit $a-times');
        $this->assertEquals(amos_string::fix_syntax('This is $a_book', 1), 'This is \$a_book');
        $this->assertEquals(amos_string::fix_syntax('Bye $a, ttyl', 1), 'Bye $a, ttyl');
        $this->assertEquals(amos_string::fix_syntax('Object $a->foo placeholder', 1), 'Object $a->foo placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar', 1), 'Trailing $a->bar');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>', 1), '<strong>AMOS</strong>');
        $this->assertEquals(
            amos_string::fix_syntax('<a href="http://localhost">AMOS</a>', 1),
            '<a href=\"http://localhost\">AMOS</a>'
        );
        $this->assertEquals(
            amos_string::fix_syntax('<a href=\"http://localhost\">AMOS</a>', 1),
            '<a href=\"http://localhost\">AMOS</a>'
        );
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote", 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t", 1), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character", 1),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 1),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 1),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character", 1),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 1),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 1),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 1),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 1),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 1),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 1),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 1),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 1),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 1),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 1),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character", 1),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 1),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 1),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 1),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 1),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 1),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 1),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 1),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character",
                1
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 1),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 1),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 1),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 1),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 1),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 1),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 1),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 1),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 1),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 1),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 1),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 1),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 1),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character",
                1
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 1),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 1),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 1),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 1),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 1),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 1),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character", 1),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 1),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    /**
     * Sanity 2.x string
     * - the string is not eval'ed any more - no need to escape $variables
     * - placeholders can be only {$a} or {$a->something} or {$a->some_thing}, nothing else
     * - quoting marks are not escaped
     * - percent signs are not duplicated any more, reverting them into single (is it good idea?)
     */
    public function test_fix_syntax_sanity_v2_strings(): void {
        $this->assertEquals(amos_string::fix_syntax('No change'), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work'), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax('%%%% HEADER %%%%'), '%%%% HEADER %%%%');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks"), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks"), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks"), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline"), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline"), "FORM FEED\nnewline");
        $this->assertEquals(amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline"), "END OF TRANSMISSION BLOCK\nnewline");
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline"), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline"), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline"), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline"), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline"), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline"), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline"), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline"), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines"), "Empty\n\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  "),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Do not escape $variable names'), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape $alike names'), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Not $a placeholder'), 'Not $a placeholder');
        $this->assertEquals(amos_string::fix_syntax('String {$a} placeholder'), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a}'), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('{$a} leading'), '{$a} leading');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a'), 'Trailing $a');
        $this->assertEquals(amos_string::fix_syntax('$a leading'), '$a leading');
        $this->assertEquals(amos_string::fix_syntax('Not $a->foo placeholder'), 'Not $a->foo placeholder');
        $this->assertEquals(amos_string::fix_syntax('Object {$a->foo} placeholder'), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar'), 'Trailing $a->bar');
        $this->assertEquals(amos_string::fix_syntax('Invalid $a-> placeholder'), 'Invalid $a-> placeholder');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>'), '<strong>AMOS</strong>');
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote"), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t"), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?'), 'Delete role "$a->role"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?'), 'Delete role \"$a->role\"?');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character"),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character"),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character"),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character"),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character"),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character"),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character"),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character"),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character"),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character"),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character"),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character"),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character"),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character"),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character"),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character"),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character"),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character"),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character"),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character"),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character"),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character"),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character"
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character"),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character"),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character"),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character"),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character"),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character"),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character"),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character"),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character"),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character"),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character"),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character"),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character"),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character"
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character"),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character"),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character"),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character"),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character"),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character"),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character"),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character"),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    /**
     * Converting 1.x strings into 2.x strings
     * - unescape all variables
     * - wrap all placeholders in curly brackets
     * - unescape quoting marks
     * - collapse percent signs
     */
    public function test_fix_syntax_converting_from_v1_to_v2(): void {
        $this->assertEquals(amos_string::fix_syntax('No change', 2, 1), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax('Completed 100%% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks", 2, 1), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks", 2, 1), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks", 2, 1), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline", 2, 1), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline", 2, 1), "FORM FEED\nnewline");
        $this->assertEquals(
            amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 2, 1),
            "END OF TRANSMISSION BLOCK\nnewline"
        );
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline", 2, 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline", 2, 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 2, 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline", 2, 1), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline", 2, 1), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 2, 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 2, 1), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 2, 1), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines", 2, 1), "Empty\n\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  ", 2, 1),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Do not escape $variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape $alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$a names', 2, 1), 'Do not escape $a names');
        $this->assertEquals(amos_string::fix_syntax('String $a placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('String {$a} placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a', 2, 1), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('$a leading', 2, 1), '{$a} leading');
        $this->assertEquals(amos_string::fix_syntax('$a', 2, 1), '{$a}');
        $this->assertEquals(amos_string::fix_syntax('$a->single', 2, 1), '{$a->single}');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->foobar', 2, 1), 'Trailing {$a->foobar}');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a}', 2, 1), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('Hit $a-times', 2, 1), 'Hit {$a}-times');
        $this->assertEquals(amos_string::fix_syntax('This is $a_book', 2, 1), 'This is $a_book');
        $this->assertEquals(amos_string::fix_syntax('Object $a->foo placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Object {$a->foo} placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a->bar}', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(amos_string::fix_syntax('Invalid $a-> placeholder', 2, 1), 'Invalid {$a}-> placeholder');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>', 2, 1), '<strong>AMOS</strong>');
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote", 2, 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\'Murder!\', she wrote", 2, 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t", 2, 1), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(amos_string::fix_syntax('See &#36;CFG->foo', 2, 1), 'See $CFG->foo');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character", 2, 1),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 2, 1),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 2, 1),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character", 2, 1),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 2, 1),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 2, 1),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 2, 1),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 2, 1),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 2, 1),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 2, 1),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 2, 1),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 2, 1),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 2, 1),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 2, 1),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character", 2, 1),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 2, 1),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 2, 1),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 2, 1),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 2, 1),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 2, 1),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 2, 1),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 2, 1),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character",
                2,
                1
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 2, 1),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 2, 1),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 2, 1),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 2, 1),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 2, 1),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 2, 1),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 2, 1),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 2, 1),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 2, 1),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 2, 1),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 2, 1),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 2, 1),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 2, 1),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character",
                2,
                1
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 2, 1),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 2, 1),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 2, 1),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 2, 1),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 2, 1),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 2, 1),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character",
                2,
                1
            ),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 2, 1),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    public function test_clean_text(): void {
        $component = new amos_component('doodle', 'xx', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('first', "Line\n\n\n\n\n\n\nline"));
        $component->add_string(new amos_string('second', 'This \really \$a sucks'));
        $component->clean_texts();
        // One blank line allowed in format 1..
        $this->assertEquals("Line\n\nline", $component->get_string('first')->text);
        $this->assertEquals('This really \$a sucks', $component->get_string('second')->text);
        $component->clear();
        unset($component);

        $component = new amos_component('doodle', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('first', "Line\n\n\n\n\n\n\nline"));
        $component->add_string(new amos_string('second', 'This \really \$a sucks. Yes {$a}, it {$a->does}'));
        $component->add_string(new amos_string('third', "Multi   line  \n  trailing  "));
        $component->clean_texts();
        // Two blank lines allowed in format 2.
        $this->assertEquals("Line\n\n\nline", $component->get_string('first')->text);
        $this->assertEquals('This \really \$a sucks. Yes {$a}, it {$a->does}', $component->get_string('second')->text);
        $this->assertEquals("Multi   line\n  trailing", $component->get_string('third')->text);
        $component->clear();
        unset($component);
    }

    public function test_clean_texts_rebase(): void {
        $this->resetAfterTest();
        $stage = new amos_stage();
        $component = new amos_component('doodle', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('multiline', "Multi   line  \n  trailing  "));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();

        $component = amos_component::from_snapshot('doodle', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('multiline'));
        $this->assertEquals("Multi   line  \n  trailing  ", $component->get_string('multiline')->text);
        $component->clean_texts();
        $stage->add($component);
        $stage->commit('Cleaned', ['source' => 'unittest']);
        $component->clear();

        $component = amos_component::from_snapshot('doodle', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($component->has_string('multiline'));
        $this->assertEquals("Multi   line\n  trailing", $component->get_string('multiline')->text);
        $component->clear();
        unset($component);
    }

    public function test_get_string_keys(): void {
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $keys = $component->get_string_keys();
        $this->assertEquals(count($keys), 0);
        $this->assertTrue(empty($keys));

        $component->add_string(new amos_string('hello', 'Hello'));
        $component->add_string(new amos_string('world', 'World'));
        $keys = $component->get_string_keys();
        $keys = array_flip($keys);
        $this->assertEquals(count($keys), 2);
        $this->assertTrue(isset($keys['hello']));
        $this->assertTrue(isset($keys['world']));
    }

    public function test_intersect(): void {
        $comp1 = new amos_component('moodle', 'en', amos_version::by_branch('MOODLE_18_STABLE'));
        $comp1->add_string(new amos_string('one', 'One'));
        $comp1->add_string(new amos_string('two', 'Two'));
        $comp1->add_string(new amos_string('three', 'Three'));

        $comp2 = new amos_component('moodle', 'cs', amos_version::by_branch('MOODLE_18_STABLE'));
        $comp2->add_string(new amos_string('one', 'Jedna'));
        $comp2->add_string(new amos_string('two', 'Dva'));
        $comp2->add_string(new amos_string('seven', 'Sedm'));
        $comp2->add_string(new amos_string('eight', 'Osm'));

        $comp2->intersect($comp1);
        $this->assertEquals(2, count($comp2->get_string_keys()));
        $this->assertTrue($comp2->has_string('one'));
        $this->assertTrue($comp2->has_string('two'));
    }

    public function test_extract_script_from_text(): void {
        $noscript = 'This is text with no AMOS script';
        $emptyarray = amos_tools::extract_script_from_text($noscript);
        $this->assertTrue(empty($emptyarray));

        $oneliner = 'MDL-12345 Some message AMOS   BEGIN  MOV   [a,  b],[c,d] CPY [e,f], [g ,h]  AMOS' .
            "\t" . 'END BEGIN ignore AMOS   ';
        $script = amos_tools::extract_script_from_text($oneliner);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV [a, b],[c,d]', $script[0]);
        $this->assertEquals('CPY [e,f], [g ,h]', $script[1]);

        // phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings
        $multiline = 'This is a typical usage of AMOS script in a commit message
                    AMOS BEGIN
                     MOV a,b  
                     CPY  c,d
                    AMOS END
                   Here it can continue';
        // phpcs:enable
        $script = amos_tools::extract_script_from_text($multiline);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV a,b', $script[0]);
        $this->assertEquals('CPY c,d', $script[1]);

        // If there is no empty line between commit subject and AMOS script:.
        $oneliner2 = 'Blah blah blah AMOS   BEGIN  CMD AMOS END blah blah';
        $script = amos_tools::extract_script_from_text($oneliner2);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(1, count($script));
        $this->assertEquals('CMD', $script[0]);
    }

    public function test_list_languages(): void {
        $this->resetAfterTest();
        $stage = new amos_stage();
        $component = new amos_component('langconfig', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'English'));
        $component->add_string(new amos_string('thislanguage', 'English'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'Czech'));
        $component->add_string(new amos_string('thislanguage', 'Česky'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'cs', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'CS'));
        $component->add_string(new amos_string('thislanguage', 'ČS'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'xx', amos_version::by_branch('MOODLE_21_STABLE'));
        $component->add_string(new amos_string('thislanguage', 'Xx'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'yy', amos_version::by_branch('MOODLE_23_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'Yy'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering languages', ['source' => 'unittest']);

        $langs = amos_tools::list_languages(true, true, false);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 4);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertTrue(array_key_exists('en', $langs));
        $this->assertEquals($langs['en'], 'English');
        $this->assertEquals($langs['cs'], 'Czech');
        $this->assertEquals($langs['xx'], '???');
        $this->assertEquals($langs['yy'], 'Yy');

        $langs = amos_tools::list_languages(false, true, true);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 3);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertEquals($langs['cs'], 'Czech [cs]');
        $this->assertEquals($langs['xx'], '??? [xx]');
        $this->assertEquals($langs['yy'], 'Yy [yy]');

        $langs = amos_tools::list_languages(true, true, true, true);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 4);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertEquals($langs['en'], 'English [en]');
        $this->assertEquals($langs['cs'], 'Czech / Česky [cs]');
        $this->assertEquals($langs['xx'], '??? / Xx [xx]');
        $this->assertEquals($langs['yy'], 'Yy / ??? [yy]');
    }

    public function test_list_components(): void {

        $this->resetAfterTest();

        $stage = new amos_stage();
        $component = new amos_component('workshop', 'en', amos_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new amos_string('modulename', 'Workshop'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('workshop', 'en', amos_version::by_branch('MOODLE_39_STABLE'));
        $component->add_string(new amos_string('modulename', 'Workshop 2.x'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth', 'en', amos_version::by_branch('MOODLE_310_STABLE'));
        $component->add_string(new amos_string('foo', 'Bar'));
        $stage->add($component);
        $component->clear();

        // This will play no role as there is no English original.
        $component = new amos_component('assign', 'cs', amos_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new amos_string('pluginname', 'Úkol'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering component strings', ['source' => 'unittest']);

        $comps = amos_tools::list_components();

        $this->assertEquals(gettype($comps), 'array');
        $this->assertEquals(count($comps), 2);
        $this->assertEquals($comps['auth'], 310);
        $this->assertEquals($comps['workshop'], 38);
    }

    public function test_execution_strings(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_20_STABLE');

        // This is to prevent situation where a string is added and immediately removed in the same second. Such
        // situations are not supported yet very well in AMOS. It would require to rewrite well tuned getting component
        // from snapshot.
        $past = time() - 1;
        $component = new amos_component('auth', 'en', $version);
        $component->add_string(new amos_string('authenticate', 'Authenticate', $past));
        $component->add_string(new amos_string('ldap', 'Use LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth_ldap', 'en', $version);
        $component->add_string(new amos_string('pluginname', 'LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth', 'cs', $version);
        $component->add_string(new amos_string('authenticate', 'Autentizovat', $past));
        $component->add_string(new amos_string('ldap', 'Pouzit LDAP', $past));
        $stage->add($component);
        $component->clear();
        unset($component);

        $stage->commit('Adding some testing strings', ['source' => 'unittest']);
        unset($stage);

        $stage = amos_tools::execute('MOV [ldap,core_auth],[pluginname,auth_ldap]', $version);
        $stage->commit('Moving string ldap into auth_ldap', ['source' => 'unittest']);
        unset($stage);

        $component = amos_component::from_snapshot('auth_ldap', 'cs', $version);
        $this->assertTrue($component->has_string('pluginname'));
        $component->clear();

        $component = amos_component::from_snapshot('auth', 'cs', $version);
        // The MOV command is an alias for CPY now, it does not actually remove anything.
        $this->assertTrue($component->has_string('ldap'));
        $component->clear();

        $component = amos_component::from_snapshot('auth', 'en', $version);
        $this->assertTrue($component->has_string('ldap'));  // English string are not affected by AMOS script!
        $component->clear();

        $component = amos_component::from_snapshot('auth_ldap', 'en', $version);
        $string = $component->get_string('pluginname');
        $this->assertEquals($string->text, 'LDAP');
        unset($string);
        $component->clear();
    }

    public function test_execution_strings_move(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_20_STABLE');
        $now = time();

        // This block emulates parse-core.php.
        $component = new amos_component('admin', 'en', $version);
        $component->add_string(new amos_string('configsitepolicy', 'OLD', $now - 2));
        $stage->add($component);
        $stage->rebase($now - 2, true, $now - 2);
        $stage->commit('Committed initial English string', ['source' => 'unittest'], true, $now - 2);
        $component->clear();
        unset($component);

        // This block emulates parse-lang.php.
        $component = new amos_component('admin', 'cs', $version);
        $component->add_string(new amos_string('configsitepolicy', 'OLD in cs', $now - 1));
        $stage->add($component);
        $stage->rebase();
        $stage->commit('Committed initial Czech translation', ['source' => 'unittest'], true, $now - 1);
        $component->clear();
        unset($component);

        // This block emulates parse-core.php again later.
        // Now the string is moved in the English pack by the developer who provides AMOS script in commit message.
        // This happened in b593d49d593ee778f525b4074f5ee7978c5e2960.
        $component = new amos_component('admin', 'en', $version);
        $component->add_string(new amos_string('sitepolicy_help', 'NEW', $now));
        $component->add_string(new amos_string('configsitepolicy', 'OLD', $now, true));
        $commitmsg = 'MDL-24570 multiple sitepolicy fixes + adding new separate guest user policy
AMOS BEGIN
 MOV [configsitepolicy,core_admin],[sitepolicy_help,core_admin]
AMOS END';
        $stage->add($component);
        $stage->rebase($now, true, $now);
        $stage->commit($commitmsg, ['source' => 'unittest'], true, $now);
        $component->clear();
        unset($component);

        // Execute AMOS script if the commit message contains some.
        if ($version->code >= 20) {
            $instructions = amos_tools::extract_script_from_text($commitmsg);
            if (!empty($instructions)) {
                foreach ($instructions as $instruction) {
                    $changes = amos_tools::execute($instruction, $version, $now);
                    $changes->rebase($now);
                    $changes->commit($commitmsg, ['source' => 'commitscript'], true, $now);
                    unset($changes);
                }
            }
        }

        // The moved string is gone from English.
        $componenten = amos_component::from_snapshot('admin', 'en', $version, $now);
        $this->assertFalse($componenten->has_string('configsitepolicy'));
        $this->assertTrue($componenten->has_string('sitepolicy_help'));
        $this->assertEquals('NEW', $componenten->get_string('sitepolicy_help')->text);
        $this->assertEquals(1, $componenten->get_number_of_strings());

        // It is still present in the raw snapshot of the Czech.
        $componentcs = amos_component::from_snapshot('admin', 'cs', $version, $now);
        $this->assertTrue($componentcs->has_string('configsitepolicy'));
        $this->assertTrue($componentcs->has_string('sitepolicy_help'));
        $this->assertEquals('OLD in cs', $componentcs->get_string('configsitepolicy')->text);
        $this->assertEquals('OLD in cs', $componentcs->get_string('sitepolicy_help')->text);
        $this->assertEquals(2, $componentcs->get_number_of_strings());

        // Prune all strings not present in the English (this is what exporting ZIPs does).
        $componentcs->intersect($componenten);
        $this->assertFalse($componentcs->has_string('configsitepolicy'));
        $this->assertTrue($componentcs->has_string('sitepolicy_help'));
        $this->assertEquals('OLD in cs', $componentcs->get_string('sitepolicy_help')->text);
        $this->assertEquals(1, $componentcs->get_number_of_strings());
    }

    public function test_legacy_component_name(): void {
        $this->assertEquals(testable_amos_tools::legacy_component_name('core'), 'moodle');
        $this->assertEquals(testable_amos_tools::legacy_component_name('core_grades'), 'grades');
        $this->assertEquals(testable_amos_tools::legacy_component_name('block_foobar'), 'block_foobar');
        $this->assertEquals(testable_amos_tools::legacy_component_name('auth_oauth2'), 'auth_oauth2');
        $this->assertEquals(testable_amos_tools::legacy_component_name('mod_forum2'), 'forum2');
        $this->assertEquals(testable_amos_tools::legacy_component_name('mod_foobar'), 'foobar');
        $this->assertEquals(testable_amos_tools::legacy_component_name('moodle'), 'moodle');
        $this->assertEquals(testable_amos_tools::legacy_component_name('admin'), 'admin');
        $this->assertEquals(testable_amos_tools::legacy_component_name(' mod_whitespace  '), 'whitespace');
        $this->assertEquals(testable_amos_tools::legacy_component_name('[syntaxerr'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('syntaxerr,'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('syntax err'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('enrol__invalid'), false);
    }

    public function test_component_get_recent_timemodified(): void {
        $now = time();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals(0, $component->get_recent_timemodified());
        $component->add_string(new amos_string('first', 'Hello', $now - 5));
        $component->add_string(new amos_string('second', 'Moodle', $now - 12));
        $component->add_string(new amos_string('third', 'World', $now - 4));
        $this->assertEquals($now - 4, $component->get_recent_timemodified());
    }

    public function test_merge_strings_from_another_component(): void {
        // Prepare two components with some strings.
        $component19 = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_19_STABLE'));
        $component19->add_string(new amos_string('first', 'First $a'));
        $component19->add_string(new amos_string('second', 'Second \"string\"'));
        $component19->add_string(new amos_string('third', 'Third'));
        $component19->add_string(new amos_string('fifth', 'Fifth \"string\"'));

        $component20 = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component20->add_string(new amos_string('second', '*deleted*', null, true));
        $component20->add_string(new amos_string('third', 'Third already merged'));
        $component20->add_string(new amos_string('fourth', 'Fourth only in component20'));
        // Merge component19 into component20.
        amos_tools::merge($component19, $component20);
        // Check the results.
        $this->assertEquals(4, $component19->get_number_of_strings());
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
        $this->assertEquals('*deleted*', $component20->get_string('second')->text);
        $this->assertTrue($component20->get_string('second')->deleted);
        $this->assertEquals('Third already merged', $component20->get_string('third')->text);
        $this->assertEquals('Fourth only in component20', $component20->get_string('fourth')->text);
        $this->assertFalse($component19->has_string('fourth'));
        $this->assertEquals('Fifth "string"', $component20->get_string('fifth')->text);
        // Clear source component and make sure that strings are still in the new one.
        $component19->clear();
        unset($component19);
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
    }

    public function test_get_affected_strings(): void {
        $this->resetAfterTest();
        $diff = file(dirname(__FILE__) . '/fixtures/parserdata002.txt');
        $affected = amos_tools::get_affected_strings($diff);
        $this->assertEquals(count($affected), 5);
        $this->assertTrue(in_array('configdefaultuserroleid', $affected));
        $this->assertTrue(in_array('confignodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nolangupdateneeded', $affected));
        $this->assertTrue(in_array('mod/something:really_nasty-like0098187.this', $affected));
    }

    public function test_execution_forced_copy(): void {
        $this->resetAfterTest();

        $this->register_language('en', 22);
        $this->register_language('cs', 22);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_22_STABLE');
        $time = time();
        $component = new amos_component('assignment', 'cs', $version);
        $component->add_string(new amos_string('pluginname', 'Úkol (2.2)', $time - 60));
        $component->add_string(new amos_string('modulename', 'Úkol', $time - 120));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Adding some testing strings', ['source' => 'unittest']);
        unset($stage);

        $stage = amos_tools::execute('FCP [pluginname,assignment],[modulename,assignment]', $version);
        $stage->commit('Forced copy of a string', ['source' => 'unittest']);
        unset($stage);

        $component = amos_component::from_snapshot('assignment', 'cs', $version);
        $this->assertEquals('Úkol (2.2)', $component->get_string('pluginname')->text);
        $this->assertEquals('Úkol (2.2)', $component->get_string('modulename')->text);
        $component->clear();
    }

    public function test_enfix_postmerge_cleanup(): void {
        $this->resetAfterTest();
        $now = time();
        $stage = new amos_stage();
        $componentname = 'test';
        $version = amos_version::by_branch('MOODLE_24_STABLE');

        // Prepare the initial state of the reference component.
        $component = new amos_component($componentname, 'en', $version);
        $component->add_string(new amos_string('first', 'First $a', $now - 4 * WEEKSECS));
        $component->add_string(new amos_string('second', 'Second \"string\"', $now - 4 * WEEKSECS));
        $component->add_string(new amos_string('third', 'Third', $now - 4 * WEEKSECS));
        $component->add_string(new amos_string('fifth', 'Fifth \"string\"', $now - 4 * WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('First version of en', ['source' => 'unittest']);

        // Prepare the component that is supposed to contain fixes for the reference component.
        $component = new amos_component($componentname, 'en_fix', $version);
        $component->add_string(new amos_string('second', 'Second', $now - 3 * WEEKSECS));
        $component->add_string(new amos_string('third', 'Third', $now - 3 * WEEKSECS));
        $component->add_string(new amos_string('fourth', 'Fourth', $now - 3 * WEEKSECS));
        $component->add_string(new amos_string('fifth', 'Modified', $now - 3 * WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('First version of en_fix', ['source' => 'unittest']);

        // Simulate the result of merging en_fix into en.
        $component = new amos_component($componentname, 'en', $version);
        $component->add_string(new amos_string('second', 'Second', $now - 2 * WEEKSECS));
        $component->add_string(new amos_string('fifth', 'Modified', $now - 2 * WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Merge en_fix into en', ['source' => 'unittest']);

        // Perform more changes at en_fix.
        $component = new amos_component($componentname, 'en_fix', $version);
        $component->add_string(new amos_string('first', 'First', $now - WEEKSECS));
        $component->add_string(new amos_string('fifth', 'Fifth \"string\"', $now - WEEKSECS));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Another version of en_fix', ['source' => 'unittest']);

        // Simulate the enfix-cleanup.php execution.
        $en = amos_component::from_snapshot($componentname, 'en', $version);
        $enfix = amos_component::from_snapshot($componentname, 'en_fix', $version);
        $enfix->intersect($en);
        $removed = $enfix->complement($en);
        $stage->add($enfix);
        $stage->rebase(null, true);
        $stage->commit('Removing strings merged into the English', null, true);

        // Check the result.
        $this->assertEqualsCanonicalizing(['second', 'third'], $removed);
        $enfix = amos_component::from_snapshot($componentname, 'en_fix', $version);
        $this->assertEquals(2, $enfix->get_number_of_strings());
        $this->assertEquals('First', $enfix->get_string('first')->text);
        $this->assertEquals('Fifth \"string\"', $enfix->get_string('fifth')->text);
    }

    public function test_backport_translations(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);
        $this->register_language('en_fix', 20);

        $stage = new amos_stage();
        $version21 = amos_version::by_branch('MOODLE_21_STABLE');
        $version22 = amos_version::by_branch('MOODLE_22_STABLE');
        $version23 = amos_version::by_branch('MOODLE_23_STABLE');
        $version24 = amos_version::by_branch('MOODLE_24_STABLE');
        $version25 = amos_version::by_branch('MOODLE_25_STABLE');
        $time = time();

        // Register Foo plugin English strings for Moodle 2.3 and higher (pretend that happened 180 days ago).
        $component = new amos_component('foo', 'en', $version23);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('done', 'Done', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 180 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.3 strings', ['source' => 'unittest']);

        // Translate only some of the strings into Czech on 2.3.
        $component = new amos_component('foo', 'cs', $version23);
        $component->add_string(new amos_string('modulename', 'Fu', $time - 179 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 179 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.3 strings into Czech', ['source' => 'unittest']);

        // Change one and add one string in 2.4.
        $component = new amos_component('foo', 'en', $version24);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('done', 'Finished', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('end', 'End', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 90 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.4 strings', ['source' => 'unittest']);

        // Update 2.3 Czech translation.
        $component = new amos_component('foo', 'cs', $version24);
        $component->add_string(new amos_string('modulename', 'Fu', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('done', 'Ukončeno', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('end', 'Konec', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('orphan', 'Orphan', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.4 strings into Czech', ['source' => 'unittest']);

        $component = new amos_component('foo', 'en_fix', $version24);
        $component->add_string(new amos_string('modulename', 'Fooh', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Since 2.4, the module name is different', ['source' => 'unittest']);

        testable_amos_tools::backport_translations('foo');

        $component = amos_component::from_snapshot('foo', 'cs', $version23);
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

        $component = amos_component::from_snapshot('foo', 'en_fix', $version23);
        // The en_fix strings are exception and not backported.
        $this->assertFalse($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'en_fix', $version24);
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'en_fix', $version25);
        // The string exists on 2.5 implicitly (as it exists since 2.4), not as a result of backporting.
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'cs', $version24);
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
        $component = new amos_component('foo', 'cs', $version23);
        $component->add_string(new amos_string('done', 'Hotovo', $time - 40 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add missing 2.3 string', ['source' => 'unittest']);

        // The added translation is valid for 2.3 only as the 2.4 version still applies even if was committed earlier.
        $component = amos_component::from_snapshot('foo', 'cs', $version23);
        $this->assertEquals('Hotovo', $component->get_string('done')->text);
        $this->assertEquals($time - 40 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'cs', $version24);
        $this->assertEquals('Ukončeno', $component->get_string('done')->text);
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        // New version of the Foo for Moodle 2.5 is released, strings have not changed.
        $component = new amos_component('foo', 'en', $version25);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('done', 'Finished', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('end', 'End', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 20 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.5 strings', ['source' => 'unittest']);

        testable_amos_tools::backport_translations('foo', ['en', 'cs']);

        $component = amos_component::from_snapshot('foo', 'cs', $version25);
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertTrue($component->has_string('aaa'));
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->has_string('done'));
        $component->clear();

        // Foo plugin is released for 2.1 too (marked as supporting 2.1 in the plugins directory).
        $component = new amos_component('foo', 'en', $version21);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('done', 'Done', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 15 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.1 strings', ['source' => 'unittest']);

        // Without backporting, there would be no 2.1 Czech translation.
        $component = amos_component::from_snapshot('foo', 'cs', $version21);
        $this->assertFalse($component->has_string());

        // Backport the Czech translations.
        testable_amos_tools::backport_translations('foo');

        $component = amos_component::from_snapshot('foo', 'cs', $version21);
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
     * Test the {@see amos_string::should_be_included_in_stats()} results.
     */
    public function test_should_be_included_in_stats(): void {
        $this->resetAfterTest();

        $this->assertTrue((new amos_string('one', 'One'))->should_be_included_in_stats());
        $this->assertTrue((new amos_string('one_link', 'foo'))->should_be_included_in_stats());
        $this->assertFalse((new amos_string('del', '', null, true))->should_be_included_in_stats());
    }

    /**
     * Test the {@see amos_component::get_number_of_strings()} results.
     */
    public function test_get_number_of_strings(): void {
        $this->resetAfterTest();

        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_37_STABLE'));

        $this->assertEquals(0, $component->get_number_of_strings());

        $component->add_string(new amos_string('welcome', 'Welcome'));

        $this->assertEquals(1, $component->get_number_of_strings());
        $this->assertEquals(1, $component->get_number_of_strings(true));

        $component->add_string(new amos_string('welcome_help', 'This is used for the help tooltip.'));

        $this->assertEquals(2, $component->get_number_of_strings());
        $this->assertEquals(2, $component->get_number_of_strings(true));

        $component->add_string(new amos_string('welcome_link', 'test/welcome'));

        $this->assertEquals(3, $component->get_number_of_strings());
        $this->assertEquals(3, $component->get_number_of_strings(true));

        $component->add_string(new amos_string('deleted', '', null, true));

        $this->assertEquals(4, $component->get_number_of_strings());
        $this->assertEquals(3, $component->get_number_of_strings(true));

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

    /**
     * Test that amos_version instances are re-used.
     */
    public function test_mlang_version_reuse(): void {

        $v20b = amos_version::by_branch('MOODLE_20_STABLE');
        $v20c = amos_version::by_code(20);
        $v20d = amos_version::by_dir('2.0');
        $v21c = amos_version::by_code(21);

        $this->assertSame($v20b, $v20c);
        $this->assertSame($v20c, $v20d);
        $this->assertNotSame($v20c, $v21c);
    }
}
