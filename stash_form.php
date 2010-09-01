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
 * Moodle forms used by stash page
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class local_amos_pullrequest_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', 'Pull request details');

        $mform->addElement('text', 'name', 'Stash title', array('size'=>50, 'maxlength'=>255));
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_RAW);

        $mform->addElement('textarea', 'message', 'Pull request message', array('cols'=>80, 'rows'=>10));
        $mform->setType('message', PARAM_RAW);

        $mform->addElement('hidden', 'stashid');
        $mform->setType('stashid', PARAM_INT);

        $mform->addElement('submit', 'submit', 'Share stash with lang pack maintainers');
        $mform->addElement('cancel', 'cancel', get_string('cancel'));
    }
}
