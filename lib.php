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
 * AMOS interface library
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Navigation API /////////////////////////////////////////////////////////////.

/**
 * Puts AMOS into the global navigation tree.
 *
 * @param global_navigation $navigation
 */
function local_amos_extend_navigation(global_navigation $navigation) {

    $amos = $navigation->add('AMOS', new moodle_url('/local/amos/'), navigation_node::TYPE_CUSTOM,
        null, 'amos_root', new pix_icon('t/editstring', ''));
    $amos->showinflatnavigation = true;

    if (has_capability('local/amos:stage', context_system::instance())) {
        $amos->add(get_string('translatortool', 'local_amos'), new moodle_url('/local/amos/view.php'),
            navigation_node::TYPE_CUSTOM, null, 'translator');
        $amos->add(get_string('stage', 'local_amos'), new moodle_url('/local/amos/stage.php'),
            navigation_node::TYPE_CUSTOM, null, 'stage');
    }

    if (has_capability('local/amos:stash', context_system::instance())) {
        $amos->add(get_string('stashes', 'local_amos'), new moodle_url('/local/amos/stash.php'),
            navigation_node::TYPE_CUSTOM, null, 'stashes');
        $amos->add(get_string('contributions', 'local_amos'), new moodle_url('/local/amos/contrib.php'),
            navigation_node::TYPE_CUSTOM, null, 'contributions');
    }
    $amos->add(get_string('creditstitleshort', 'local_amos'), new moodle_url('/local/amos/credits.php'),
        navigation_node::TYPE_CUSTOM, null, 'credits');

    if (has_capability('local/amos:manage', context_system::instance())) {
        $admin = $amos->add(get_string('administration'));
        $admin->add(get_string('newlanguage', 'local_amos'), new moodle_url('/local/amos/admin/newlanguage.php'));
    }
}

// Comments API ///////////////////////////////////////////////////////////////.

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @param stdClass $commentparams the comment parameters
 * @return array
 */
function local_amos_comment_permissions($commentparams) {
    return ['post' => true, 'view' => true];
}

/**
 * Validates comment parameters before performing other comments actions
 *
 * The passed params object contains properties:
 * - context: context the context object
 * - courseid: int course id
 * - cm: stdClass course module object
 * - commentarea: string comment area
 * - itemid: int itemid
 *
 * @param stdClass $commentparams the comment parameters
 * @return boolean
 */
function local_amos_comment_validate($commentparams) {
    global $DB;

    if ($commentparams->commentarea != 'amos_contribution') {
        throw new comment_exception('invalidcommentarea');
    }

    $syscontext = context_system::instance();
    if ($syscontext->id != $commentparams->context->id) {
        throw new comment_exception('invalidcontext');
    }

    if (!$DB->record_exists('amos_contributions', ['id' => $commentparams->itemid])) {
        throw new comment_exception('invalidcommentitemid');
    }

    if (SITEID != $commentparams->courseid) {
        throw new comment_exception('invalidcourseid');
    }

    return true;
}

/**
 * Callback on added comment.
 *
 * @param stdClass $comment
 * @param stdClass $meta
 */
function local_amos_comment_add($comment, $meta) {
    global $DB;

    if ($comment->commentarea === 'amos_contribution') {
        // Notify users about new comments.
        $contribution = $DB->get_record('amos_contributions', ['id' => $comment->itemid], '*', MUST_EXIST);

        // Populate the list of users who should be notified.
        $users = [];
        // The author of the contribution.
        $users[$contribution->authorid] = true;
        if (!empty($contribution->assignee)) {
            // The assignee if the contribution has been assigned.
            $users[$contribution->assignee] = true;
        } else {
            // All the maintainers if it has not been assigned yet.
            $records = $DB->get_records('amos_translators', ['status' => 0, 'lang' => $contribution->lang]);
            foreach ($records as $record) {
                $users[$record->userid] = true;
            }
        }

        // Load all user records.
        $amosbot = $DB->get_record('user', ['id' => 2]);
        $fromuser = $DB->get_record('user', ['id' => $comment->userid]);
        $users = $DB->get_records_list('user', 'id', array_keys($users));

        // Do not send the copy to the user themselves.
        unset($users[$comment->userid]);

        // Notify remaining users.
        foreach ($users as $user) {
            $data = new \core\message\message();
            $data->component = 'local_amos';
            $data->name = 'contribution';
            $data->userfrom = $amosbot;
            $data->userto = $user;
            $data->subject = get_string_manager()->get_string('contribnotif', 'local_amos',
                ['id' => $contribution->id], $user->lang);
            $data->fullmessage = get_string_manager()->get_string('contribnotifcommented', 'local_amos', [
                'id' => $contribution->id,
                'subject' => $contribution->subject,
                'contriburl' => (new moodle_url('/local/amos/contrib.php', ['id' => $contribution->id]))->out(false),
                'fullname' => fullname($fromuser),
                'message' => $comment->content,
            ], $user->lang);
            $data->fullmessageformat = FORMAT_PLAIN;
            $data->fullmessagehtml = '';
            $data->smallmessage = '';
            $data->notification = 1;

            message_send($data);
        }
    }
}

/**
 * Overrides the default template used when printing comments to allow for better styling.
 *
 * @param stdClass $params
 * @return string
 */
function local_amos_comment_template($params) {

    $template  = html_writer::start_tag('div', ['class' => 'amos-comment']);
    $template .= html_writer::start_div('comment-header');
    $template .= html_writer::tag('div', '___picture___', ['class' => 'comment-userpicture']);
    $template .= html_writer::tag('div', '___name___', ['class' => 'comment-userfullname']);
    $template .= html_writer::tag('div', '___time___', ['class' => 'comment-time']);
    $template .= html_writer::end_div();
    $template .= html_writer::tag('div', '___content___', ['class' => 'comment-comment']);
    $template .= html_writer::end_tag('div');

    return $template;
}
