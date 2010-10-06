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

class local_amos_importfile_form extends moodleform {
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('select', 'version', get_string('version', 'local_amos'), $this->_customdata['versions']);
        $mform->setDefault('version', $this->_customdata['versioncurrent']);
        $mform->addRule('version', null, 'required', null, 'client');

        $mform->addElement('select', 'language', get_string('language', 'local_amos'), $this->_customdata['languages']);
        $mform->setDefault('language', $this->_customdata['languagecurrent']);
        $mform->addRule('language', null, 'required', null, 'client');

        $fpoptions = array('accepted_types' => 'php', 'maxbytes' => 500*1024);
        $mform->addElement('filepicker', 'importfile', get_string('files'), null,  $fpoptions);
        $mform->addRule('importfile', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('import'));
    }
}
