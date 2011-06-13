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
 * Moodle form used as the filter at the log page
 *
 * @category  local
 * @package   local_amos
 * @copyright 2010 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class local_amos_log_form extends moodleform {

    function definition() {
        global $USER;

        $mform = $this->_form;

        // Commits filter
        $mform->addElement('header', 'logfiltercommits', get_string('logfiltercommits', 'local_amos'));

        // Committed after
        $mform->addElement('date_time_selector', 'committedafter', get_string('logfiltercommittedafter', 'local_amos'),
                array('optional' => true, 'timezone' => 'UTC', 'applydst' => false));
        $mform->setDefault('committedafter', $USER->lastlogin);

        // Committed before
        $mform->addElement('date_time_selector', 'committedbefore', get_string('logfiltercommittedbefore', 'local_amos'),
                array('optional' => true, 'timezone' => 'UTC', 'applydst' => false));
        $mform->setAdvanced('committedbefore');

        // Committer
        $committers = get_users_by_capability(get_system_context(), 'local/amos:commit', user_picture::fields('u'), 'lastname, firstname');
        $users = array('' => '');
        foreach ($committers as $committer) {
            $users[$committer->id] = s(fullname($committer) . ' <' . $committer->email . '>');
        }
        $usergrp[] = $mform->createElement('select', 'userid', '', $users);
        $usergrp[] = $mform->createElement('text', 'userinfo', '');
        $mform->setType('userinfo', PARAM_NOTAGS);
        $mform->addGroup($usergrp, 'usergrp', get_string('logfilterusergrp', 'local_amos'), get_string('logfilterusergrpor', 'local_amos'), false);
        $mform->setAdvanced('usergrp');


        // Source
        $sources = array(
            ''              => '',
            'git'           => get_string('logfiltersourcegit', 'local_amos'),
            'amos'          => get_string('logfiltersourceamos', 'local_amos'),
            'revclean'      => get_string('logfiltersourcerevclean', 'local_amos'),
            'commitscript'  => get_string('logfiltersourcecommitscript', 'local_amos'),
        );
        $mform->addElement('select', 'source', get_string('logfiltersource', 'local_amos'), $sources);
        $mform->setAdvanced('source');

        // Commit message
        $mform->addElement('text', 'commitmsg', get_string('logfiltercommitmsg', 'local_amos'));
        $mform->setAdvanced('commitmsg');

        // Commit hash
        $mform->addElement('text', 'commithash', get_string('logfiltercommithash', 'local_amos'));
        $mform->setAdvanced('commithash');

        // Strings filter
        $mform->addElement('header', 'logfilterstrings', get_string('logfilterstrings', 'local_amos'));

        // Branch
        $branchgrp = array();
        foreach (mlang_version::list_all() as $version) {
            $branchgrp[] = $mform->createElement('checkbox', $version->code, '', $version->label);
        }
        $mform->addGroup($branchgrp, 'branch', get_string('logfilterbranch', 'local_amos'), ' ');
        foreach (mlang_version::list_all() as $version) {
            if ($version->current) {
                $mform->setDefault('branch['.$version->code.']', 1);
            }
        }

        // Lang
        $langgrp = array();
        $langgrp[] = $mform->createElement('checkbox', 'langenabled', '', get_string('enable'));
        $langgrp[] = $mform->createElement('select', 'lang', '', mlang_tools::list_languages(),
                array('multiple' => 'multiple', 'size' => 5));
        $mform->addGroup($langgrp, 'langgrp', get_string('logfilterlang', 'local_amos'), '<br />', false);
        $mform->setDefault('lang', array('en', current_language()));
        $mform->disabledIf('lang', 'langenabled'); // does not seem to work for multiple selects :-/
        $mform->setAdvanced('langgrp');

        // Components
        $optionscore = array();
        $optionsstandard = array();
        $optionscontrib = array();
        $standard = array();
        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }
        foreach (mlang_tools::list_components() as $componentname => $undefined) {
            if (isset($standard[$componentname])) {
                if ($standard[$componentname] === 'core' or substr($standard[$componentname], 0, 5) === 'core_') {
                    $optionscore[$componentname] = $standard[$componentname];
                } else {
                    $optionsstandard[$componentname] = $standard[$componentname];
                }
            } else {
                $optionscontrib[$componentname] = $componentname;
            }
        }
        asort($optionscore);
        asort($optionsstandard);
        asort($optionscontrib);
        $options = array(
                get_string('pluginclasscore', 'local_amos') => $optionscore,
                get_string('pluginclassstandard', 'local_amos') => $optionsstandard,
                get_string('pluginclassnonstandard', 'local_amos') => $optionscontrib,
        );
        $componentgrp = array();
        $componentgrp[] = $mform->createElement('checkbox', 'componentenabled', '', get_string('enable'));
        $componentgrp[] = $mform->createElement('selectgroups', 'component', '', $options,
                array('multiple' => 'multiple', 'size' => 5));
        $mform->addGroup($componentgrp, 'componentgrp', get_string('logfiltercomponent', 'local_amos'), '<br />', false);
        $mform->disabledIf('component', 'componentenabled'); // does not seem to work for multiple selects :-/
        $mform->setAdvanced('componentgrp');

        // Stringid
        $mform->addElement('text', 'stringid', get_string('logfilterstringid', 'local_amos'));
        $mform->setType('stringid', PARAM_STRINGID);
        $mform->setAdvanced('stringid');

        // Submit
        $mform->addElement('submit', 'submit', get_string('logfiltershow', 'local_amos'));
        $mform->closeHeaderBefore('submit');

    }
}
