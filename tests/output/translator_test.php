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

namespace local_amos\output;

/**
 * Unit tests for {@see \local_amos\output\translator}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2024 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translator_test extends \local_amos_testcase {

    /**
     * Test that permission check is performed.
     */
    public function test_filter_by_substring() {
        global $PAGE, $USER;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new \mlang_stage();

        $component = new \mlang_component('foo_bar', 'en', \mlang_version::by_code(39));
        $component->add_string(new \mlang_string('s1', 'AAA'));
        $component->add_string(new \mlang_string('s2', 'BBB'));
        $component->add_string(new \mlang_string('s3', 'CCC'));
        $stage->add($component);
        $component->clear();

        $component = new \mlang_component('foo_bar', 'cs', \mlang_version::by_code(39));
        $component->add_string(new \mlang_string('s1', 'Ddd'));
        $component->add_string(new \mlang_string('s2', 'Aaa'));
        $component->add_string(new \mlang_string('s3', 'aaa'));
        $stage->add($component);
        $component->clear();

        $stage->commit('First strings and translations', ['source' => 'unittest']);

        $_POST['__lazyform_amosfilter'] = 1;
        $_POST['flng'] = ['cs'];
        $_POST['fcmp'] = ['foo_bar'];
        $_POST['sesskey'] = sesskey();
        $_POST['ftxt'] = 'aaa';

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        // By default, search in both English and Czech.
        $this->assertEquals(3, count($data['strings']));
        $this->assertEquals('s1', $data['strings'][0]->stringid);
        $this->assertEquals('s2', $data['strings'][1]->stringid);
        $this->assertEquals('s3', $data['strings'][2]->stringid);

        // Do not apply to translations, search in English only.
        $_POST['ftxe'] = 1;
        $_POST['ftxn'] = 0;

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        $this->assertEquals(1, count($data['strings']));
        $this->assertEquals('s1', $data['strings'][0]->stringid);

        // Search in Czech only.
        $_POST['ftxe'] = 0;
        $_POST['ftxn'] = 1;
        $_POST['ftxt'] = 'ddd';

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        $this->assertEquals(1, count($data['strings']));
        $this->assertEquals('s1', $data['strings'][0]->stringid);

        // Same string but in English only.
        $_POST['ftxe'] = 1;
        $_POST['ftxn'] = 0;
        $_POST['ftxt'] = 'ddd';

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        $this->assertEquals(0, count($data['strings']));
    }
}
