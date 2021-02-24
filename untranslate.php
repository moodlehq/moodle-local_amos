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
 * Untranslate an existing translation
 *
 * @package     local_amos
 * @copyright   2015 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$since = required_param('since', PARAM_INT);
$component = required_param('component', PARAM_ALPHANUMEXT);
$language = required_param('language', PARAM_ALPHANUMEXT);
$stringid = required_param('stringid', PARAM_STRINGID);
$confirm = optional_param('confirm', false, PARAM_BOOL);

require_login(SITEID, false);
require_capability('local/amos:commit', context_system::instance());

$allowedlangs = mlang_tools::list_allowed_languages();

if (empty($allowedlangs['X']) and empty($allowedlangs[$language])) {
    throw new moodle_exception('err_unexpected_language', 'local_amos');
}

$PAGE->set_pagelayout('standard');
$PAGE->set_url(new moodle_url('/local/amos/untranslate.php', [
    'component' => $component,
    'language' => $language,
    'stringid' => $stringid,
    'since' => $since,
]));
$PAGE->set_title('AMOS '.get_string('untranslating', 'local_amos'));
$PAGE->navbar->add(get_string('untranslating', 'local_amos'));

navigation_node::override_active_url(new moodle_url('/local/amos/view.php'));

$mversion = mlang_version::by_code($since);

if (!$mversion->translatable) {
    throw new moodle_exception('err_nontranslatable_version', 'local_amos');
}

if ($confirm) {
    require_sesskey();

    $mstage = new mlang_stage();
    $mcomponent = mlang_component::from_snapshot($component, $language, $mversion, null, false, false, [$stringid]);
    $cstring = $mcomponent->get_string($stringid);

    if ($cstring !== null) {
        $mstring = new mlang_string($cstring->id, $cstring->text, null, true);
        $mcomponent->add_string($mstring, true);
        $mstage->add($mcomponent);

        $message = 'Untranslate the string '.$stringid;
        $meta = [
            'source' => 'amos',
            'userid' => $USER->id,
            'userinfo' => fullname($USER) . ' <' . $USER->email . '>',
        ];
        $mstage->commit($message, $meta);
    }

    redirect(new moodle_url('/local/amos/view.php'));
}

$output = $PAGE->get_renderer('local_amos');

echo $output->header();
echo $output->heading(get_string('untranslatetitle', 'local_amos'));

$a = [
    'component' => $component,
    'language' => $language,
    'stringid' => $stringid,
    'since' => $mversion->label,
];

echo $output->confirm(
    get_string('untranslateconfirm', 'local_amos', $a),
    new moodle_url($PAGE->url, ['sesskey' => sesskey(), 'confirm' => true]),
    new moodle_url('/local/amos/view.php')
);

echo $output->footer();
