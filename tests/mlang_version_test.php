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
 * Provides {@see local_amos_mlang_version_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Unit tests for the mlang_version class functionality.
 *
 * @copyright 2020 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_mlang_version_test extends advanced_testcase {

    /**
     * Test mlang_version factory methods.
     *
     * @dataProvider mlang_version_properties_provider
     *
     * @param int $code
     * @param string $label
     * @param string $dir
     * @param bool $translatable
     */
    public function test_factory_methods(int $code, string $label, string $dir, bool $translatable) {

        $v = mlang_version::by_code($code);
        $this->assert_mlang_version_properties_match($v, $code, $label, $dir, $translatable);

        $v = mlang_version::by_branch('MOODLE_' . $code . '_STABLE');
        $this->assert_mlang_version_properties_match($v, $code, $label, $dir, $translatable);

        $v = mlang_version::by_dir($dir);
        $this->assert_mlang_version_properties_match($v, $code, $label, $dir, $translatable);
    }

    /**
     * Helper function to assert that the mlang_version properties match the given values.
     *
     * @param mlang_version $v
     * @param int $code
     * @param string $label
     * @param string $dir
     * @param bool $translatable
     */
    protected function assert_mlang_version_properties_match(mlang_version $v, int $code, string $label,
            string $dir, bool $translatable) {

        $this->assertSame($v->label, $label);
        $this->assertSame($v->dir, $dir);
        $this->assertSame($v->branch, 'MOODLE_' . $code . '_STABLE');
        $this->assertSame($v->translatable, $translatable);
    }

    /**
     * Provide data for {@see self::test_factory_methods}.
     *
     * @return array
     */
    public function mlang_version_properties_provider(): array {
        return [
            'Moodle 1.x - not translatable' => [
                'code' => 16,
                'label' => '1.6',
                'dir' => '1.6',
                'translatable' => false,
            ],
            'Moodle 2.0' => [
                'code' => 20,
                'label' => '2.0',
                'dir' => '2.0',
                'translatable' => true,
            ],
            'Moodle 3.1' => [
                'code' => 31,
                'label' => '3.1',
                'dir' => '3.1',
                'translatable' => true,
            ],
            'Moodle 3.9' => [
                'code' => 39,
                'label' => '3.9',
                'dir' => '3.9',
                'translatable' => true,
            ],
            'Moodle 3.10' => [
                'code' => 310,
                'label' => '3.10',
                'dir' => '3.10',
                'translatable' => true,
            ],
            'Moodle 3.11' => [
                'code' => 311,
                'label' => '3.11',
                'dir' => '3.11',
                'translatable' => true,
            ],
            'Moodle 4.0' => [
                'code' => 400,
                'label' => '4.0',
                'dir' => '4.0',
                'translatable' => true,
            ],
            'Moodle 4.1' => [
                'code' => 401,
                'label' => '4.1',
                'dir' => '4.1',
                'translatable' => true,
            ],
            'Moodle 4.10' => [
                'code' => 410,
                'label' => '4.10',
                'dir' => '4.10',
                'translatable' => true,
            ],
            'Moodle 9.99' => [
                'code' => 999,
                'label' => '9.99',
                'dir' => '9.99',
                'translatable' => true,
            ],
            'Moodle 10.0' => [
                'code' => 1000,
                'label' => '10.0',
                'dir' => '10.0',
                'translatable' => true,
            ],
            'Moodle 31.1' => [
                'code' => 3101,
                'label' => '31.1',
                'dir' => '31.1',
                'translatable' => true,
            ],
            'Moodle 31.10' => [
                'code' => 3110,
                'label' => '31.10',
                'dir' => '31.10',
                'translatable' => true,
            ],
        ];
    }

    /**
     * Test obtaining the list of versions.
     */
    public function test_list_all() {

        $this->resetAfterTest();

        set_config('brancheslist', '38,39,310,400', 'local_amos');

        $list = mlang_version::list_all();

        $this->assertEquals(4, count($list));
        $this->assertSame('3.9', $list[39]->dir);
        $this->assertSame('MOODLE_310_STABLE', $list[310]->branch);
    }

    /**
     * Test obtaining the most recent version
     */
    public function test_latest_version() {

        $this->resetAfterTest();

        set_config('brancheslist', '38,39,310', 'local_amos');

        $latest = mlang_version::latest_version();

        $this->assertSame('3.10', $latest->dir);
    }

    /**
     * Test obtaining a range of versions.
     */
    public function test_list_range() {

        $this->resetAfterTest();

        set_config('brancheslist', '38,39,310,311,400,401', 'local_amos');

        $range = mlang_version::list_range(400);

        $this->assertEquals(2, count($range));
        $this->assertSame('4.0', $range[400]->dir);
        $this->assertSame('4.1', $range[401]->dir);

        $range = mlang_version::list_range(39 + 1, 311 + 1);

        $this->assertEquals(2, count($range));
        $this->assertSame('3.10', $range[310]->dir);
        $this->assertSame('3.11', $range[311]->dir);

        $range = mlang_version::list_range(401, 401);

        $this->assertEquals(1, count($range));
        $this->assertSame('4.1', $range[401]->dir);

        $range = mlang_version::list_range(401, 400);

        $this->assertEquals(0, count($range));
    }
}
