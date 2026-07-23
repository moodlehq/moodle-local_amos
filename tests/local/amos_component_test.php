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
 * Provides {@see \local_amos\local\amos_component_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;

/**
 * Unit tests for the {@see \local_amos\local\amos_component} class.
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class amos_component_test extends \local_amos_testcase {
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

    public function test_component_get_recent_timemodified(): void {
        $now = time();
        $component = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertEquals(0, $component->get_recent_timemodified());
        $component->add_string(new amos_string('first', 'Hello', $now - 5));
        $component->add_string(new amos_string('second', 'Moodle', $now - 12));
        $component->add_string(new amos_string('third', 'World', $now - 4));
        $this->assertEquals($now - 4, $component->get_recent_timemodified());
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
}
