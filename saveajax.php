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
 * Stage a single string using AJAX
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');

require_login(SITEID, false);
if (!has_capability('local/amos:stage', context_system::instance())) {
    header('HTTP/1.1 403 Forbidden');
    die();
}
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$lang = optional_param('lang', null, PARAM_SAFEDIR);
$originalid = optional_param('originalid', null, PARAM_INT);
$text = optional_param('text', null, PARAM_RAW);
$nocleaning = optional_param('nocleaning', false, PARAM_BOOL);

if (is_null($lang) or is_null($originalid) or is_null($text)) {
    header('HTTP/1.1 400 Bad Request');
    die();
}

$record = $DB->get_record('amos_strings', array('id' => $originalid), 'id,strname,component,since', MUST_EXIST);
$version = mlang_version::by_code($record->since);
$component = new mlang_component($record->component, $lang, $version);
if (!$version->translatable) {
    header('HTTP/1.1 400 Bad Request');
    die();
}
$string = new mlang_string($record->strname, $text);
$string->nocleaning = $nocleaning;
$string->clean_text();
$component->add_string($string);

$stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
$stage->add($component, true);
$stage->store();
mlang_stash::autosave($stage);

header('Content-Type: application/json; charset: utf-8');
$response = new stdclass();
$response->text = local_amos_renderer::add_breaks(s($string->text));
$response->nocleaning = $string->nocleaning;

add_to_log(SITEID, 'amos', 'stage', '', $lang.' '.$version->label.' ['.$string->id.','.$component->name.']', 0, $USER->id);

echo json_encode($response);
