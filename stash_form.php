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
 * Provides {@see local_amos_submit_form} class.
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Form used at the stash page.
 *
 * @package     local_amos
 * @copyright   2010 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_submit_form extends moodleform {

    /**
     * Defines form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('stashsubmitdetails', 'local_amos'));

        $mform->addElement('text', 'name', get_string('stashsubmitsubject', 'local_amos'), ['size' => 50, 'maxlength' => 255]);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_RAW);

        $mform->addElement('textarea', 'message', get_string('stashsubmitmessage', 'local_amos'), ['cols' => 80, 'rows' => 10]);
        $mform->setType('message', PARAM_RAW);

        $mform->addElement('hidden', 'stashid');
        $mform->setType('stashid', PARAM_INT);

        $mform->addElement('submit', 'submit', get_string('stashsubmit', 'local_amos'));
        $mform->addElement('cancel', 'cancel', get_string('cancel'));
    }
}
