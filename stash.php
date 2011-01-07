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
 * View all available stashes and allow to delete, share or pop strings from them
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/stash_form.php');

$name   = optional_param('name', null, PARAM_RAW);  // stash name
$new    = optional_param('new', null, PARAM_BOOL);  // new stash requested
$apply  = optional_param('apply', null, PARAM_INT);
$pop    = optional_param('pop', null, PARAM_INT);
$drop   = optional_param('drop', null, PARAM_INT);
$submit = optional_param('submit', null, PARAM_INT);
$hide   = optional_param('hide', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stash', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/stash.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stash.php'));
$PAGE->set_title('AMOS stashes');
$PAGE->set_heading('AMOS stashes');
$PAGE->requires->yui_module('moodle-local_amos-stash', 'M.local_amos.init_stash');
$PAGE->requires->strings_for_js(array('confirmaction'), 'local_amos');

if ($new) {
    // pushing the current stage into a new stash
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash = mlang_stash::instance_from_stage($stage, $stage->userid, $name);
    $stash->push();
    redirect($PAGE->url);
}

if ($apply) {
    require_sesskey();
    require_capability('local/amos:stage', get_system_context());
    $stash = mlang_stash::instance_from_id($apply);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();
    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($pop) {
    require_sesskey();
    require_capability('local/amos:stage', get_system_context());
    $stash = mlang_stash::instance_from_id($pop);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();
    $stash->drop();
    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($drop) {
    require_sesskey();
    $stash = mlang_stash::instance_from_id($drop);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }
    $stash->drop();
    redirect($PAGE->url);
}

if ($hide) {
    require_sesskey();
    if (!$DB->count_records('amos_hidden_requests', array('userid' => $USER->id, 'stashid' => $hide))) {
        $record = new stdclass();
        $record->userid = $USER->id;
        $record->stashid = $hide;
        $DB->insert_record('amos_hidden_requests', $record);
    }
    redirect($PAGE->url);
}

$submitform = new local_amos_submit_form();

if ($submitform->is_cancelled()) {
    redirect($PAGE->url);

} elseif ($submitdata = $submitform->get_data()) {
    $stash = mlang_stash::instance_from_id($submitdata->stashid);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }

    // split the stashed components into separate packages by their language
    $stage = new mlang_stage();
    $langstages = array();  // (string)langcode => (mlang_stage)
    $stash->apply($stage);
    foreach ($stage->get_iterator() as $component) {
        $lang = $component->lang;
        if (!isset($langstages[$lang])) {
            $langstages[$lang] = new mlang_stage();
        }
        $langstages[$lang]->add($component);
    }
    $stage->clear();
    unset($stage);

    // create new contribution record for every language and attach a new stash to it
    foreach ($langstages as $lang => $stage) {
        $langstash = mlang_stash::instance_from_stage($stage, 0, $submitdata->name);
        $langstash->message = $submitdata->message;
        $langstash->push();

        $contribution               = new stdClass();
        $contribution->authorid     = $USER->id;
        $contribution->lang         = $lang;
        $contribution->assignee     = null;
        $contribution->subject      = $submitdata->name;
        $contribution->message      = $submitdata->message;
        $contribution->stashid      = $langstash->id;
        $contribution->status       = 0; // TODO use some class constant
        $contribution->timecreated  = $langstash->timecreated;
        $contribution->timemodified = null;

        $DB->insert_record('amos_contributions', $contribution);
    }

    // stash has been submited so it is dropped
    $stash->drop();
    redirect($PAGE->url);
}

$output = $PAGE->get_renderer('local_amos');

/// Output starts here
echo $output->header();

if ($submit) {
    require_sesskey();
    $stash = mlang_stash::instance_from_id($submit);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }
    echo $output->heading_with_help(get_string('submitting', 'local_amos'), 'submitting', 'local_amos');
    $stashinfo = local_amos_stash::instance_from_mlang_stash($stash, $USER);
    echo $output->render($stashinfo);
    $submitform->set_data(array(
        'name'    => s($stash->name),
        'stashid' => $stash->id,
    ));
    $submitform->display();

    echo $output->footer();
    die();
}

// Own stashes
if (!$stashes = $DB->get_records('amos_stashes', array('ownerid' => $USER->id), 'timecreated DESC')) {
    echo $output->heading(get_string('ownstashesnone', 'local_amos'));

} else {
    // catch output into these two variables
    $autosavestash = '';
    $ownstashes = '';

    foreach ($stashes as $stashdata) {
        $stash = local_amos_stash::instance_from_record($stashdata, $USER);
        if (has_capability('local/amos:stage', get_system_context())) {
            $stash->add_action('apply', new moodle_url($PAGE->url, array('apply' => $stash->id)), get_string('stashapply', 'local_amos'));
            if (!$stash->isautosave) {
                $stash->add_action('pop', new moodle_url($PAGE->url, array('pop' => $stash->id)), get_string('stashpop', 'local_amos'));
            }
        }
        if (!$stash->isautosave) {
            $stash->add_action('drop', new moodle_url($PAGE->url, array('drop' => $stash->id)), get_string('stashdrop', 'local_amos'));
            $stash->add_action('submit', new moodle_url($PAGE->url, array('submit' => $stash->id)), get_string('stashsubmit', 'local_amos'));
        }

        if ($stash->isautosave) {
            $autosavestash .= $output->render($stash);
        } else {
            $ownstashes .= $output->render($stash);
        }
    }

    if ($autosavestash) {
        echo $output->heading_with_help(get_string('stashautosave', 'local_amos'), 'stashautosave', 'local_amos');
        echo $autosavestash;
    }
    if ($ownstashes) {
        echo $output->heading(get_string('ownstashes', 'local_amos'));
        echo $ownstashes;
    }
}

echo $output->footer();
