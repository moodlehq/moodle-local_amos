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
 * New language form
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

class local_amos_newlanguage_form extends moodleform {

    public function definition() {
        $mform          = $this->_form;

        $mform->addElement('header', 'compulsory', 'New language');

        $mform->addElement('text', 'langcode', 'Language code', array('size' => 5));
        $mform->setType('langcode', PARAM_SAFEDIR);
        $mform->addRule('langcode', get_string('err_required', 'form'), 'required', null, 'client');

        $mform->addElement('text', 'langname', 'Language name in the language itself');
        $mform->addRule('langname', get_string('err_required', 'form'), 'required', null, 'client');

        $mform->addElement('text', 'langnameint', 'International language name in English');
        $mform->addRule('langnameint', get_string('err_required', 'form'), 'required', null, 'client');

        $this->add_action_buttons(false, 'Register new language');
    }

    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = parent::validation($data, $files);

        $tempcode = clean_param($data['langcode'], PARAM_SAFEDIR);
        $tempcode = strtolower($tempcode);
        if ($tempcode !== $data['langcode']) {
            $errors['langcode'] = 'Invalid language code format';
        }

        if ($DB->record_exists('amos_repository', array('lang' => $data['langcode']))) {
            $errors['langcode'] = 'Already exists';
        }

        return $errors;
    }
}
