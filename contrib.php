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
 * Displays and manages submitted contributions
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once($CFG->dirroot . '/comment/lib.php');

$id     = optional_param('id', null, PARAM_INT);
$assign = optional_param('assign', null, PARAM_INT);
$resign = optional_param('resign', null, PARAM_INT);
$apply  = optional_param('apply', null, PARAM_INT);
$review = optional_param('review', null, PARAM_INT);
$accept = optional_param('accept', null, PARAM_INT);
$reject = optional_param('reject', null, PARAM_INT);
$closed = optional_param('closed', false, PARAM_BOOL);  // show resolved contributions, too
$changelang = optional_param('changelang', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stash', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/contrib.php');
if ($closed) {
    $PAGE->url->param('closed', $closed);
}
if (!is_null($id)) {
    $PAGE->url->param('id', $id);
}
navigation_node::override_active_url(new moodle_url('/local/amos/contrib.php'));
$PAGE->set_title('AMOS ' . get_string('contributions', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('contributions', 'local_amos'));
//$PAGE->requires->yui_module('moodle-local_amos-contrib', 'M.local_amos.init_contrib');
//$PAGE->requires->strings_for_js(array('confirmaction'), 'local_amos');

if ($assign) {
    require_capability('local/amos:commit', context_system::instance());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $assign), '*', MUST_EXIST);

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->assignee = $USER->id;
    $contribution->timemodified = time();
    $DB->update_record('amos_contributions', $contribution);
    redirect(new moodle_url($PAGE->url, array('id' => $assign)));
}

if ($resign) {
    require_capability('local/amos:commit', context_system::instance());
    require_sesskey();

    $contribution = $DB->get_record('amos_contributions', array('id' => $resign, 'assignee' => $USER->id), '*', MUST_EXIST);

    $contribution->assignee = null;
    $contribution->timemodified = time();
    $DB->update_record('amos_contributions', $contribution);
    redirect(new moodle_url($PAGE->url, array('id' => $resign)));
}

if ($apply) {
    require_capability('local/amos:stage', context_system::instance());
    require_sesskey();

    $contribution = $DB->get_record('amos_contributions', array('id' => $apply), '*', MUST_EXIST);

    if ($contribution->authorid != $USER->id) {
        $author = $DB->get_record('user', array('id' => $contribution->authorid));
    } else {
        $author = $USER;
    }

    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();

    $a = new stdClass();
    $a->id = $contribution->id;
    $a->author = fullname($author);
    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage', 'local_amos', $a);
    $SESSION->local_amos->stagedcontribution = $a;

    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($review) {
    require_capability('local/amos:commit', context_system::instance());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $review), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->assignee = $USER->id;
    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_REVIEW;
    $DB->update_record('amos_contributions', $contribution);

    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash->apply($stage);
    $stage->store();

    $a = new stdClass();
    $a->id = $contribution->id;
    $a->author = fullname($author);
    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage', 'local_amos', $a);
    $SESSION->local_amos->stagedcontribution = $a;

    redirect(new moodle_url('/local/amos/stage.php'));
}

if ($accept) {
    require_capability('local/amos:commit', context_system::instance());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $accept, 'assignee' => $USER->id), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));
    $amosbot      = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_ACCEPTED;
    $DB->update_record('amos_contributions', $contribution);

    // Notify the contributor.
    $data = new stdClass();
    $data->component = 'local_amos';
    $data->name = 'contribution';
    $data->userfrom = $amosbot;
    $data->userto = $author;
    $data->subject = get_string_manager()->get_string('contribnotif', 'local_amos', array('id' => $contribution->id), $author->lang);
    $data->fullmessage = get_string_manager()->get_string('contribnotifaccepted', 'local_amos', array(
        'id' => $contribution->id,
        'subject' => $contribution->subject,
        'contriburl' => (new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id)))->out(false),
        'fullname' => fullname($USER),
    ), $author->lang);
    $data->fullmessageformat = FORMAT_PLAIN;
    $data->fullmessagehtml = '';
    $data->smallmessage = '';
    $data->notification = 1;
    message_send($data);

    redirect(new moodle_url($PAGE->url, array('id' => $accept)));
}

if ($reject) {
    require_capability('local/amos:commit', context_system::instance());
    require_sesskey();

    $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $reject, 'assignee' => $USER->id), '*', MUST_EXIST);
    $author       = $DB->get_record('user', array('id' => $contribution->authorid));
    $amosbot      = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    if ($maintainerof !== 'all') {
        if (!in_array($contribution->lang, $maintainerof)) {
            print_error('contributionaccessdenied', 'local_amos');
        }
    }

    $contribution->timemodified = time();
    $contribution->status = local_amos_contribution::STATE_REJECTED;
    $DB->update_record('amos_contributions', $contribution);

    // Notify the contributor.
    $data = new stdClass();
    $data->component = 'local_amos';
    $data->name = 'contribution';
    $data->userfrom = $amosbot;
    $data->userto = $author;
    $data->subject = get_string_manager()->get_string('contribnotif', 'local_amos', array('id' => $contribution->id), $author->lang);
    $data->fullmessage = get_string_manager()->get_string('contribnotifrejected', 'local_amos', array(
        'id' => $contribution->id,
        'subject' => $contribution->subject,
        'contriburl' => (new moodle_url('/local/amos/contrib.php', array('id' => $contribution->id)))->out(false),
        'fullname' => fullname($USER),
    ), $author->lang);
    $data->fullmessageformat = FORMAT_PLAIN;
    $data->fullmessagehtml = '';
    $data->smallmessage = '';
    $data->notification = 1;
    message_send($data);

    redirect(new moodle_url($PAGE->url, array('id' => $reject)));
}

if ($changelang) {
    $newlang = required_param('newlang', PARAM_SAFEDIR);
    require_capability('local/amos:changecontriblang', context_system::instance());
    require_sesskey();

    $contriborig = $DB->get_record('amos_contributions', array('id' => $changelang), '*', MUST_EXIST);

    if (empty($newlang)) {
        redirect(new moodle_url($PAGE->url, array('id' => $contriborig->id)));
    }

    $listlanguages = mlang_tools::list_languages();

    if (empty($listlanguages[$newlang])) {
        print_error('err_invalid_target_language', 'local_amos', new moodle_url($PAGE->url, array('id' => $contriborig->id)), null, $newlang);
    }

    // Load the stash associated with the originial contribution.
    $stashorig = mlang_stash::instance_from_id($contriborig->stashid);
    $stage = new mlang_stage();
    $stashorig->apply($stage);

    // Change the language of the stashed strings.
    $stage->change_lang($contriborig->lang, $newlang);

    // Store them as the new stash.
    $stashnew = mlang_stash::instance_from_stage($stage, 0, $stashorig->name);
    $stashnew->message = $stashorig->message;
    $stashnew->push();

    // Record the new contribution record associated with the new stash.
    if (!empty($listlanguages[$contriborig->lang])) {
        $languagenameorig = $listlanguages[$contriborig->lang];
    } else if ($contriborig->lang === '') {
        $languagenameorig = "undefined";
    } else {
        $languagenameorig = "wrong (".$contriborig->lang.")";
    }

    $contribnew               = new stdClass();
    $contribnew->authorid     = $contriborig->authorid;
    $contribnew->lang         = $newlang;
    $contribnew->assignee     = null;
    $contribnew->subject      = $contriborig->subject;
    $contribnew->message      = $contriborig->message."\n\nNote: This contribution was previously submitted as #".$contriborig->id." on ".date('Y-m-d', $contriborig->timecreated)." to the ".$languagenameorig." language pack by mistake.";
    $contribnew->stashid      = $stashnew->id;
    $contribnew->status       = local_amos_contribution::STATE_NEW;
    $contribnew->timecreated  = $stashnew->timecreated;
    $contribnew->timemodified = null;

    $contribnew->id = $DB->insert_record('amos_contributions', $contribnew);

    // Automatically reject the original contribution and add a note to it.
    $contriborig->timemodified = time();
    $contriborig->assignee = $USER->id;
    $contriborig->status = local_amos_contribution::STATE_REJECTED;
    $contriborig->message .= "\n\nNote: This contribution was submitted to the ".$languagenameorig." language pack by mistake and has been moved to the ".$listlanguages[$newlang]." language pack as #".$contribnew->id.".";

    $DB->update_record('amos_contributions', $contriborig);

    // Notify the contributor.
    $author = $DB->get_record('user', array('id' => $contriborig->authorid));
    $amosbot = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!

    $data = new stdClass();
    $data->component = 'local_amos';
    $data->name = 'contribution';
    $data->userfrom = $amosbot;
    $data->userto = $author;
    $data->subject = get_string_manager()->get_string('contribnotif', 'local_amos', array('id' => $contriborig->id), $author->lang);
    $data->fullmessage = get_string_manager()->get_string('contribnotifconverted', 'local_amos', array(
        'id' => $contriborig->id,
        'subject' => $contriborig->subject,
        'contriborigurl' => (new moodle_url('/local/amos/contrib.php', array('id' => $contriborig->id)))->out(false),
        'contribnewurl' => (new moodle_url('/local/amos/contrib.php', array('id' => $contribnew->id)))->out(false),
        'fullname' => fullname($USER),
    ), $author->lang);
    $data->fullmessageformat = FORMAT_PLAIN;
    $data->fullmessagehtml = '';
    $data->smallmessage = '';
    $data->notification = 1;
    message_send($data);

    // And redirect to the new contribution page.
    redirect(new moodle_url($PAGE->url, array('id' => $contribnew->id)));
}

$output = $PAGE->get_renderer('local_amos');
if (!empty($CFG->usecomments)) {
    comment::init();
}

// Particular contribution record
if ($id) {

    if (has_capability('local/amos:commit', context_system::instance())) {
        $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
        $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
        foreach ($maintenances as $maintained) {
            if ($maintained->lang === 'X') {
                $maintainerof = 'all';
                break;
            }
            $maintainerof[] = $maintained->lang;
        }
    } else {
        $maintainerof = false;
    }

    $contribution = $DB->get_record('amos_contributions', array('id' => $id), '*', MUST_EXIST);

    if ($contribution->authorid !== $USER->id) {
        $author = $DB->get_record('user', array('id' => $contribution->authorid));
    } else {
        $author = $USER;
    }

    // get the contributed components and rebase them to see what would happen
    $stash = mlang_stash::instance_from_id($contribution->stashid);
    $stage = new mlang_stage();
    $stash->apply($stage);
    list($origstrings, $origlanguages, $origcomponents) = mlang_stage::analyze($stage);
    $stage->rebase();
    list($rebasedstrings, $rebasedlanguages, $rebasedcomponents) = mlang_stage::analyze($stage);

    $contribinfo                = new local_amos_contribution($contribution, $author);
    $contribinfo->language      = implode(', ', array_filter(array_map('trim', explode('/', $origlanguages))));
    $contribinfo->components    = implode(', ', array_filter(array_map('trim', explode('/', $origcomponents))));
    $contribinfo->strings       = $origstrings;
    $contribinfo->stringsreb    = $rebasedstrings;

    if ($maintainerof and ($maintainerof === 'all' or in_array($contribution->lang, $maintainerof))) {
        if ($contribution->status == local_amos_contribution::STATE_REVIEW and $contribinfo->stringsreb == 0) {
            // Maintainers tend to leave the contribution in the "In review" state.
            // So let us automatically accept it if all strings are already translated.
            // This may lead to unexpected acceptance in certain situations but of the
            // evils, many "in review" contributions appear to be the worse one.
            redirect(new moodle_url('/local/amos/contrib.php', array('accept' => $contribution->id, 'sesskey' => sesskey())));
        }
    }

    echo $output->header();
    echo $output->render($contribinfo);

    echo html_writer::start_tag('div', array('class' => 'contribactions'));
    if ($maintainerof and ($maintainerof === 'all' or in_array($contribution->lang, $maintainerof))) {
        if ($contribution->status == local_amos_contribution::STATE_NEW) {
            echo $output->single_button(new moodle_url($PAGE->url, array('review' => $id)), get_string('contribstartreview', 'local_amos'),
                    'post', array('class' => 'singlebutton review'));
        }
        if ($contribution->assignee == $USER->id) {
            echo $output->single_button(new moodle_url($PAGE->url, array('resign' => $id)), get_string('contribresign', 'local_amos'),
                    'post', array('class' => 'singlebutton resign'));
        } else {
            echo $output->single_button(new moodle_url($PAGE->url, array('assign' => $id)), get_string('contribassigntome', 'local_amos'),
                    'post', array('class' => 'singlebutton assign'));
        }
    }
    if (has_capability('local/amos:stage', context_system::instance())) {
        echo $output->single_button(new moodle_url($PAGE->url, array('apply' => $id)), get_string('contribapply', 'local_amos'),
                'post', array('class' => 'singlebutton apply'));
    }
    if ($contribution->assignee == $USER->id and $contribution->status > local_amos_contribution::STATE_NEW) {
        if ($contribution->status != local_amos_contribution::STATE_ACCEPTED) {
            echo $output->single_button(new moodle_url($PAGE->url, array('accept' => $id)), get_string('contribaccept', 'local_amos'),
                    'post', array('class' => 'singlebutton accept'));
        }
        if ($contribution->status != local_amos_contribution::STATE_REJECTED) {
            echo $output->single_button(new moodle_url($PAGE->url, array('reject' => $id)), get_string('contribreject', 'local_amos'),
                    'post', array('class' => 'singlebutton reject'));
        }
    }
    echo $output->help_icon('contribactions', 'local_amos');
    echo html_writer::end_tag('div');

    if (has_capability('local/amos:changecontriblang', context_system::instance())) {
        $listlanguages = mlang_tools::list_languages(false);
        if (empty($contribution->lang) or isset($listlanguages[$contribution->lang])) {
            echo html_writer::start_tag('div', array('class' => 'contribactions'));
            unset($listlanguages[$contribution->lang]);
            echo html_writer::start_tag('form', array('action' => $PAGE->url, 'method' => 'post'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'changelang', 'value' => $contribution->id));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            echo html_writer::select($listlanguages, 'newlang');
            echo html_writer::tag('button', get_string('contriblanguagebutton', 'local_amos'), array('type' => 'submit'));
            echo html_writer::end_tag('form');
            echo html_writer::end_tag('div');
        }
    }

    if (!empty($CFG->usecomments)) {
        $options = new stdClass();
        $options->context = context_system::instance();
        $options->area    = 'amos_contribution';
        $options->itemid  = $contribution->id;
        $options->showcount = true;
        $options->component = 'local_amos';
        $options->autostart = true;
        $commentmanager = new comment($options);
        echo $output->container($commentmanager->output(), 'commentswrapper');
    }

    echo $output->footer();
    exit;
}

echo $output->header();

// Incoming contributions
if (has_capability('local/amos:commit', context_system::instance())) {
    $maintenances = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER, 'userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    if (empty($maintainerof)) {
        $contributions = array();

    } else {
        $params = array();

        if (is_array($maintainerof)) {
            list($langsql, $langparams) = $DB->get_in_or_equal($maintainerof);
            $params = array_merge($params, $langparams);
        } else {
            $langsql = "";
        }

        $sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified,
                       s.components, s.strings,
                       ".user_picture::fields('a', null, 'authorid', 'author').",
                       ".user_picture::fields('m', null, 'assigneeid', 'assignee')."
                  FROM {amos_contributions} c
                  JOIN {amos_stashes} s ON (c.stashid = s.id)
                  JOIN {user} a ON c.authorid = a.id
             LEFT JOIN {user} m ON c.assignee = m.id";

        if ($closed) {
            $sql .= " WHERE c.status >= 0"; // true
        }  else {
            $sql .= " WHERE c.status < 20"; // do not show resolved contributions
        }

        if ($langsql) {
            $sql .= " AND c.lang $langsql";
        }

        // In review first, then New and then Accepted and Rejected together, then order by date
        $sql .= " ORDER BY CASE WHEN c.status = 10 THEN 1
                                WHEN c.status = 0  THEN 2
                                ELSE 3
                           END,
                           COALESCE (c.timemodified, c.timecreated) DESC";

        $contributions = $DB->get_records_sql($sql, $params);
    }

    if (empty($contributions)) {
        echo $output->heading(get_string('contribincomingnone', 'local_amos'));

    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable contributionlist incoming';
        $table->head = array(
            get_string('contribid', 'local_amos'),
            get_string('contribstatus', 'local_amos'),
            get_string('contribauthor', 'local_amos'),
            get_string('contribsubject', 'local_amos'),
            get_string('contribtimemodified', 'local_amos'),
            get_string('contribassignee', 'local_amos'),
            get_string('language', 'local_amos'),
            get_string('strings', 'local_amos')
        );
        $table->colclasses = array('id', 'status', 'author', 'subject', 'timemodified', 'assignee', 'language', 'strings');

        foreach ($contributions as $contribution) {
            $url = new moodle_url($PAGE->url, array('id' => $contribution->id));
            $cells   = array();
            $cells[] = new html_table_cell(html_writer::link($url, '#'.$contribution->id));
            $status  = get_string('contribstatus'.$contribution->status, 'local_amos');
            $cells[] = new html_table_cell(html_writer::link($url, $status));
            $author  = new user_picture(user_picture::unalias($contribution, null, 'authorid', 'author'));
            $author->size = 16;
            $cells[] = new html_table_cell($output->render($author) . s(fullname($author->user)));
            $cells[] = new html_table_cell(html_writer::link($url, s($contribution->subject)));
            $time    = is_null($contribution->timemodified) ? $contribution->timecreated : $contribution->timemodified;
            $cells[] = new html_table_cell(userdate($time, get_string('strftimedaydatetime', 'langconfig')));
            if (is_null($contribution->assigneeid)) {
                $assignee = get_string('contribassigneenone', 'local_amos');
            } else {
                $assignee = new user_picture(user_picture::unalias($contribution, null, 'assigneeid', 'assignee'));
                $assignee->size = 16;
                $assignee = $output->render($assignee) . s(fullname($assignee->user));
            }
            $cells[] = new html_table_cell($assignee);
            $cells[] = new html_table_cell(s($contribution->lang));
            $cells[] = new html_table_cell(s($contribution->strings));
            $row = new html_table_row($cells);
            $row->attributes['class'] = 'status'.$contribution->status;
            $table->data[] = $row;
        }

        echo $output->heading(get_string('contribincomingsome', 'local_amos', count($contributions)));
        echo html_writer::table($table);
    }
}

// Submitted contributions
$sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified,
               s.components, s.strings,
               ".user_picture::fields('m', null, 'assigneeid', 'assignee')."
          FROM {amos_contributions} c
          JOIN {amos_stashes} s ON (c.stashid = s.id)
     LEFT JOIN {user} m ON c.assignee = m.id
         WHERE c.authorid = ?";

if (!$closed) {
    $sql .= " AND c.status < 20"; // do not show resolved contributions
}

// In review first, then New and then Accepted and Rejected together, then order by date
$sql .= " ORDER BY CASE WHEN c.status = 10 THEN 1
                        WHEN c.status = 0  THEN 2
                        ELSE 3
                   END,
                   COALESCE (c.timemodified, c.timecreated) DESC";

$contributions = $DB->get_records_sql($sql, array($USER->id));

if (empty($contributions)) {
    echo $output->heading(get_string('contribsubmittednone', 'local_amos'));

} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable contributionlist submitted';
    $table->head = array(
        get_string('contribid', 'local_amos'),
        get_string('contribstatus', 'local_amos'),
        get_string('contribsubject', 'local_amos'),
        get_string('contribtimemodified', 'local_amos'),
        get_string('contribassignee', 'local_amos'),
        get_string('language', 'local_amos'),
        get_string('strings', 'local_amos')
    );
    $table->colclasses = array('id', 'status', 'subject', 'timemodified', 'assignee', 'language', 'strings');

    foreach ($contributions as $contribution) {
        $url = new moodle_url($PAGE->url, array('id' => $contribution->id));
        $cells   = array();
        $cells[] = new html_table_cell(html_writer::link($url, '#'.$contribution->id));
        $status  = get_string('contribstatus'.$contribution->status, 'local_amos');
        $cells[] = new html_table_cell(html_writer::link($url, $status));
        $cells[] = new html_table_cell(html_writer::link($url, s($contribution->subject)));
        $time    = is_null($contribution->timemodified) ? $contribution->timecreated : $contribution->timemodified;
        $cells[] = new html_table_cell(userdate($time, get_string('strftimedaydatetime', 'langconfig')));
        if (is_null($contribution->assigneeid)) {
            $assignee = get_string('contribassigneenone', 'local_amos');
        } else {
            $assignee = new user_picture(user_picture::unalias($contribution, null, 'assigneeid', 'assignee'));
            $assignee->size = 16;
            $assignee = $output->render($assignee) . s(fullname($assignee->user));
        }
        $cells[] = new html_table_cell($assignee);
        $cells[] = new html_table_cell(s($contribution->lang));
        $cells[] = new html_table_cell(s($contribution->strings));
        $row = new html_table_row($cells);
        $row->attributes['class'] = 'status'.$contribution->status;
        $table->data[] = $row;
    }

    echo $output->heading(get_string('contribsubmittedsome', 'local_amos', count($contributions)));
    echo html_writer::table($table);

}

if ($closed) {
    echo $output->single_button(new moodle_url($PAGE->url, array('closed' => false)),
        get_string('contribclosedno', 'local_amos'), 'get', array('class' => 'singlebutton showclosed'));
} else {
    echo $output->single_button(new moodle_url($PAGE->url, array('closed' => true)),
        get_string('contribclosedyes', 'local_amos'), 'get', array('class' => 'singlebutton showclosed'));
}

echo $output->footer();
