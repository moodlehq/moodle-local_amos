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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines administration settings for AMOS
 *
 * @package     local_amos
 * @category    admin
 * @copyright   2010 David Mudrák <david@moodle.com>, 2019 Pau Ferrer Ocaña <crazyserver@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_amos', get_string('manageamos', 'local_amos'));

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtextarea(
            'local_amos/branchesall',
            get_string('branchesall', 'local_amos'),
            get_string('branchesall_desc', 'local_amos'),
            '20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,310,311,400',
            PARAM_RAW_TRIMMED,
            60, 3
        ));

        $settings->add(new admin_setting_configtext(
            'local_amos/branchsupported',
            get_string('branchsupported', 'local_amos'),
            get_string('branchsupported_desc', 'local_amos'),
            '35',
            PARAM_INT,
            10
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_amos/standardcomponents',
            get_string('standardcomponents', 'local_amos'),
            get_string('standardcomponents_desc', 'local_amos'),
            '',
            PARAM_RAW_TRIMMED,
            60, 10
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_amos/plugintypelocations',
            get_string('plugintypelocations', 'local_amos'),
            get_string('plugintypelocations_desc', 'local_amos'),
            implode(PHP_EOL, [
                'assignment mod/assignment/type',
                'datafield mod/data/field',
                'datapreset mod/data/preset',
                'quiz mod/quiz/report',
                'quizaccess mod/quiz/accessrule',
                'scormreport mod/scorm/report',
                'workshopform mod/workshop/form',
                'workshopallocation mod/workshop/allocation',
                'workshopeval mod/workshop/eval',
                'assignsubmission mod/assign/submission',
                'assignfeedback mod/assign/feedback',
                'booktool mod/book/tool',
                'tinymce lib/editor/tinymce/plugins',
                'atto lib/editor/atto/plugins',
                'logstore admin/tool/log/store',
                'ltisource mod/lti/source',
                'ltiservice mod/lti/service',
                'forumreport mod/forum/report',
            ]),
            PARAM_RAW_TRIMMED,
            60, 10
        ));

        $settings->add(new admin_setting_configtext(
            'local_amos/applangindexfile',
            get_string('applangindexfile', 'local_amos'),
            get_string('applangindexfile_desc', 'local_amos'),
            'https://raw.githubusercontent.com/moodlehq/moodlemobile2/integration/scripts/langindex.json',
            PARAM_URL
        ));
    }

    $ADMIN->add('root', $settings, 'registrationmoodleorg');
}
