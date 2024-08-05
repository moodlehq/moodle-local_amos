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
        $component->add_string(new \mlang_string('foobar', 'Foo bar'));
        $component->add_string(new \mlang_string('foobaz', 'Foo baz'));
        $stage->add($component);
        $component->clear();

        $component = new \mlang_component('foo_bar', 'cs', \mlang_version::by_code(39));
        $component->add_string(new \mlang_string('foobar', 'Fů bar'));
        $component->add_string(new \mlang_string('foobaz', 'Foo baz'));
        $stage->add($component);
        $component->clear();

        $stage->commit('First strings and translations', ['source' => 'unittest']);

        $_POST['__lazyform_amosfilter'] = 1;
        $_POST['flng'] = ['cs'];
        $_POST['fcmp'] = ['foo_bar'];
        $_POST['sesskey'] = sesskey();
        $_POST['ftxt'] = 'Foo';

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        // By default, both English and Czech are to be filtered so only 'foobaz' string is returned.
        $this->assertEquals(1, count($data['strings']));
        $this->assertEquals('foobaz', $data['strings'][0]->stringid);

        // Do not apply to translations.
        $_POST['ftxn'] = 0;

        $filter = new \local_amos\output\filter(new \moodle_url('/'));
        $translator = new \local_amos\output\translator($filter, $USER);
        $data = $translator->export_for_template($PAGE->get_renderer('core'));

        // Now the filter applies to the English only so both strings should be returned.
        $this->assertEquals(2, count($data['strings']));
    }
}
