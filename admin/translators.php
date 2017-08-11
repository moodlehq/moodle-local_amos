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
 * Manage list of translators
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/mlanglib.php');
require_once(dirname(dirname(__FILE__)).'/locallib.php');

require_login(SITEID, false);
require_capability('local/amos:manage', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/admin/translators.php');
$PAGE->set_title('AMOS ' . get_string('maintainers', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('maintainers', 'local_amos'));
navigation_node::override_active_url(new moodle_url('/local/amos/credits.php'));

$action = required_param('action', PARAM_ALPHA);
$status = required_param('status', PARAM_INT);
$langcode = required_param('langcode', PARAM_SAFEDIR);
$confirm = optional_param('confirm', false, PARAM_BOOL);

$languages = mlang_tools::list_languages(false);

if (!isset($languages[$langcode])) {
    print_error('error_unknown_language', 'local_amos', '', $langcode);
}

if ($action === 'add') {
    if ($status == AMOS_USER_MAINTAINER) {
        $actionname = get_string('creditsaddmaintainer', 'local_amos');
        $selector = new local_amos_maintainer_selector('user', array());
    } else if ($status == AMOS_USER_CONTRIBUTOR) {
        $actionname = get_string('creditsaddcontributor', 'local_amos');
        $selector = new local_amos_contributor_selector('user', array());
    } else {
        print_error('error_unknown_status', 'local_amos', '', $status);
    }

} else if ($action === 'del') {
    $deluser = $DB->get_record('user', array('id' => required_param('user', PARAM_INT)), '*', MUST_EXIST);
    if ($status == AMOS_USER_MAINTAINER) {
        $actionname = get_string('creditsdelmaintainer', 'local_amos');
    } else if ($status == AMOS_USER_CONTRIBUTOR) {
        $actionname = get_string('creditsdelcontributor', 'local_amos');
    } else {
        print_error('error_unknown_status', 'local_amos', '', $status);
    }
}

if (data_submitted()) {
    require_sesskey();

    if ($action === 'add') {
        $adduser = $selector->get_selected_user();
        if (empty($adduser)) {
            redirect(new moodle_url($PAGE->url, array('action' => $action, 'status' => $status, 'langcode' => $langcode)));
        }

        if ($status === AMOS_USER_MAINTAINER) {
            if ($DB->record_exists('amos_translators', array('userid' => $adduser->id, 'lang' => $langcode))) {
                // Promote an existing contributor to a maintainer.
                $DB->set_field('amos_translators', 'status', AMOS_USER_MAINTAINER, array('userid' => $adduser->id, 'lang' => $langcode));

            } else {
                // New maintainer.
                $DB->insert_record('amos_translators', array('userid' => $adduser->id, 'lang' => $langcode, 'status' => AMOS_USER_MAINTAINER));
            }

        } else if ($status === AMOS_USER_CONTRIBUTOR) {
            if (!$DB->record_exists('amos_translators', array('userid' => $adduser->id, 'lang' => $langcode))) {
                $DB->insert_record('amos_translators', array('userid' => $adduser->id, 'lang' => $langcode, 'status' => AMOS_USER_CONTRIBUTOR));
            }
        }

    } else if ($action === 'del' and $confirm) {
        if ($status === AMOS_USER_MAINTAINER) {
            // Degrade the maintainer to the contributor only.
            $DB->set_field('amos_translators', 'status', AMOS_USER_CONTRIBUTOR, array('userid' => $deluser->id, 'lang' => $langcode));

        } else if ($status === AMOS_USER_CONTRIBUTOR) {
            $DB->delete_records('amos_translators', array('userid' => $deluser->id, 'lang' => $langcode));
        }

    }

    $maintainedlangscache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_amos', 'maintainedlangs');
    $maintainedlangscache->purge();

    redirect(new moodle_url('/local/amos/credits.php', array('editmode' => 1), 'credits-language-'.$langcode));
}

if ($action === 'add') {
    // Display a form to add a maintainer or a contributor.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('language', 'local_amos').': '.$languages[$langcode]);
    echo $OUTPUT->heading(get_string('action').': '.$actionname);
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => $PAGE->url));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'langcode', 'value' => $langcode));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => $action));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'status', 'value' => $status));
    echo $selector->display(true);
    echo html_writer::start_div('buttons');
    echo html_writer::tag('button', $actionname, array('type' => 'submit', 'class' => 'btn btn-primary'));
    echo ' ';
    echo html_writer::link(new moodle_url('/local/amos/credits.php', array('editmode' => 1), 'credits-language-'.$langcode), get_string('cancel'),
            array('class' => 'btn'));
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();

} else if ($action === 'del') {
    // Display a confirmation box.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('language', 'local_amos').': '.$languages[$langcode]);
    echo $OUTPUT->heading(get_string('user').': '.$OUTPUT->user_picture($deluser).' '.fullname($deluser));
    echo $OUTPUT->heading(get_string('action').': '.$actionname);
    echo html_writer::start_div('', array('style' => 'text-align: center'));
    echo $OUTPUT->confirm(
        get_string('areyousure'),
        new moodle_url($PAGE->url, array('action' => 'del', 'status' => $status, 'langcode' => $langcode, 'user' => $deluser->id, 'confirm' => 1)),
        new moodle_url('/local/amos/credits.php', array('editmode' => 1), 'credits-language-'.$langcode)
    );
    echo html_writer::end_div();
    echo $OUTPUT->footer();

} else {
    print_error('error_unknown_action', 'local_amos');
}
