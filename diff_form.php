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
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class local_amos_diff_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addGroup(array(
            $mform->createElement('select', 'versiona', '', $this->_customdata['versions']),
            $mform->createElement('select', 'versionb', '', $this->_customdata['versions']),
            ), 'versionsgroup', get_string('diffversions', 'local_amos'), ' - ', false);
        $mform->setDefault('versiona', $this->_customdata['defaultversion']);
        reset($this->_customdata['versions']); // this is a trick so that we can use key() below
        $mform->setDefault('versionb', key($this->_customdata['versions']));
        $mform->addRule('versionsgroup', null, 'required', null, 'client');

        $mform->addElement('select', 'language', get_string('language', 'local_amos'), $this->_customdata['languages']);
        $mform->setDefault('language', $this->_customdata['languagecurrent']);
        $mform->addRule('language', null, 'required', null, 'client');

        $options = array();
        for ($i = 1; $i <=4; $i++) {
            $options[$i] = get_string('diffmode'.$i, 'local_amos');
        }
        $mform->addElement('select', 'mode', get_string('diffmode', 'local_amos'), $options);

        $options = array(
            1 => get_string('diffaction1', 'local_amos'),
            2 => get_string('diffaction2', 'local_amos')
        );
        $mform->addElement('select', 'action', get_string('diffaction', 'local_amos'), $options);

        $this->add_action_buttons(false, get_string('diff', 'local_amos'));
    }
}
