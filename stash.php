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
$download = optional_param('download', null, PARAM_INT);
$submit = optional_param('submit', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stash', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/stash.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stash.php'));
$PAGE->set_title('AMOS ' . get_string('stashes', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('stashes', 'local_amos'));
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
    require_capability('local/amos:stage', context_system::instance());
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
    require_capability('local/amos:stage', context_system::instance());
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

if ($download) {
    require_sesskey();
    $stash = mlang_stash::instance_from_id($download);
    if ($stash->ownerid != $USER->id) {
        print_error('stashaccessdenied', 'local_amos');
        die();
    }
    $stash->send_zip("stash.zip");
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
    foreach ($stage as $component) {
        $lang = $component->lang;
        if (!isset($langstages[$lang])) {
            $langstages[$lang] = new mlang_stage();
        }
        $langstages[$lang]->add($component);
    }
    $stage->clear();
    unset($stage);

    $amosbot = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    // create new contribution record for every language and attach a new stash to it
    foreach ($langstages as $lang => $stage) {

        list($origstrings, $origlanguages, $origcomponents) = mlang_stage::analyze($stage);

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

        $contribution->id = $DB->insert_record('amos_contributions', $contribution);

        // Notify the language pack maintainers.
        $sql = "SELECT u.*
                  FROM {amos_translators} t
                  JOIN {user} u ON t.userid = u.id
                 WHERE t.status = :status AND t.lang = :lang";
        $maintainers = $DB->get_records_sql($sql, array('status' => AMOS_USER_MAINTAINER, 'lang' => $lang));

        foreach ($maintainers as $maintainer) {
            $data = new \core\message\message();
            $data->component = 'local_amos';
            $data->name = 'contribution';
            $data->userfrom = $amosbot;
            $data->userto = $maintainer;
            $data->subject = get_string_manager()->get_string('contribnotif', 'local_amos', array('id' => $contribution->id), $maintainer->lang);
            $data->fullmessage = get_string_manager()->get_string('contribnotifsubmitted', 'local_amos', array(
                'id' => $contribution->id,
                'subject' => $contribution->subject,
                'message' => $contribution->message,
                'language' => implode(', ', array_filter(array_map('trim', explode('/', $origlanguages)))),
                'components' => implode(', ', array_filter(array_map('trim', explode('/', $origcomponents)))),
                'strings' => $origstrings,
                'contriburl' => (new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id)))->out(false),
                'fullname' => fullname($USER),
            ), $maintainer->lang);
            $data->fullmessageformat = FORMAT_PLAIN;
            $data->fullmessagehtml = '';
            $data->smallmessage = '';
            $data->notification = 1;
            message_send($data);
        }
    }

    // stash has been submited so it is dropped
    $stash->drop();

    // Drop the staged strings as they all have been sent to contributors now.
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->clear();
    $stage->store();
    unset($SESSION->local_amos->presetcommitmessage);
    unset($SESSION->local_amos->stagedcontribution);

    redirect(new moodle_url('/local/amos/contrib.php'));
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
    if (empty($stashinfo->strings)) {
        echo $output->heading(get_string('stagestringsnone', 'local_amos'));
        echo $output->footer();
        die();
    }
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
        if (has_capability('local/amos:stage', context_system::instance())) {
            $stash->add_action('apply', new moodle_url($PAGE->url, array('apply' => $stash->id)), get_string('stashapply', 'local_amos'));
            if (!$stash->isautosave) {
                $stash->add_action('pop', new moodle_url($PAGE->url, array('pop' => $stash->id)), get_string('stashpop', 'local_amos'));
            }
        }
        if (!$stash->isautosave) {
            $stash->add_action('drop', new moodle_url($PAGE->url, array('drop' => $stash->id)), get_string('stashdrop', 'local_amos'));
        }
        $stash->add_action('download', new moodle_url($PAGE->url, array('download' => $stash->id)), get_string('stashdownload', 'local_amos'));
        if (!$stash->isautosave) {
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
