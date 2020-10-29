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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Base class for AMOS advanced test cases providing some useful utility methods.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_testcase extends advanced_testcase {

    /**
     * Register a language on the given branch.
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

        $stage->commit('Register language ' . $langcode, array('source' => 'unittest'));
    }
}
