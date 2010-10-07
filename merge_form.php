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
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class local_amos_merge_form extends moodleform {
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('select', 'sourceversion', get_string('sourceversion', 'local_amos'), $this->_customdata['sourceversions']);
        $mform->setDefault('sourceversion', $this->_customdata['defaultsourceversion']);
        $mform->addRule('sourceversion', null, 'required', null, 'client');

        $mform->addElement('select', 'targetversion', get_string('targetversion', 'local_amos'), $this->_customdata['targetversions']);
        $mform->setDefault('targetversion', $this->_customdata['defaulttargetversion']);
        $mform->addRule('targetversion', null, 'required', null, 'client');

        $mform->addElement('select', 'language', get_string('language', 'local_amos'), $this->_customdata['languages']);
        $mform->setDefault('language', $this->_customdata['languagecurrent']);
        $mform->addRule('language', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('merge', 'local_amos'));
    }
}
