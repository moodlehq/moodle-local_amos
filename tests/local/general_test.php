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
 * Provides {@see \local_amos\local\general_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;

/**
 * Unit tests covering behaviour that spans several classes of the internal AMOS API and thus
 * does not have a single class under test.
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class general_test extends \local_amos_testcase {
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
}
