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
 * AMOS interface library
 *
 * @package   amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/// Navigation API /////////////////////////////////////////////////////////////

/**
 * Puts AMOS into the global navigation tree.
 *
 * @param global_navigation $navigation
 */
function local_amos_extends_navigation(global_navigation $navigation) {
    $amos = $navigation->add('AMOS', new moodle_url('/local/amos/'), navigation_node::TYPE_CUSTOM, null, 'amos_root');
    if (has_capability('local/amos:stage', context_system::instance())) {
        $amos->add(get_string('translatortool', 'local_amos'), new moodle_url('/local/amos/view.php'), navigation_node::TYPE_CUSTOM, null, 'translator');
        $amos->add(get_string('stage', 'local_amos'), new moodle_url('/local/amos/stage.php'), navigation_node::TYPE_CUSTOM, null, 'stage');
    }
    if (has_capability('local/amos:stash', context_system::instance())) {
        $amos->add(get_string('stashes', 'local_amos'), new moodle_url('/local/amos/stash.php'), navigation_node::TYPE_CUSTOM, null, 'stashes');
        $amos->add(get_string('contributions', 'local_amos'), new moodle_url('/local/amos/contrib.php'), navigation_node::TYPE_CUSTOM, null, 'contributions');
    }
    $amos->add(get_string('log', 'local_amos'), new moodle_url('/local/amos/log.php'), navigation_node::TYPE_CUSTOM, null, 'log');
    $amos->add(get_string('creditstitleshort', 'local_amos'), new moodle_url('/local/amos/credits.php'), navigation_node::TYPE_CUSTOM, null, 'credits');
    if (has_capability('local/amos:manage', context_system::instance())) {
        $admin = $amos->add(get_string('administration'));
        $admin->add(get_string('maintainers', 'local_amos'), new moodle_url('/local/amos/admin/translators.php'));
        $admin->add(get_string('newlanguage', 'local_amos'), new moodle_url('/local/amos/admin/newlanguage.php'));
    }
}

/// Comments API ///////////////////////////////////////////////////////////////

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
    return array('post' => true, 'view' => true);
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

    if (!$DB->record_exists('amos_contributions', array('id' => $commentparams->itemid))) {
        throw new comment_exception('invalidcommentitemid');
    }

    if (SITEID != $commentparams->courseid) {
        throw new comment_exception('invalidcourseid');
    }

    return true;
}
