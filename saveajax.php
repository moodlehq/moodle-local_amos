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
if (!has_capability('local/amos:stage', get_system_context())) {
    header('HTTP/1.1 403 Forbidden');
    die();
}
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$stringid = optional_param('stringid', null, PARAM_ALPHANUMEXT);
$text     = optional_param('text', null, PARAM_RAW);

// remove all zero-width space characters from the text
// {@link http://www.fileformat.info/info/unicode/char/200b/index.htm}
$text = preg_replace('|\xe2\x80\x8b|', '', $text);

if (is_null($stringid) or is_null($text)) {
    header('HTTP/1.1 400 Bad Request');
    die();
}

list($lang, $originalid, $translationid) = local_amos_translator::decode_identifier($stringid);
$record = $DB->get_record('amos_repository', array('id' => $originalid), 'id,stringid,component,branch', MUST_EXIST);

$component = new mlang_component($record->component, $lang, mlang_version::by_code($record->branch));
$string = new mlang_string($record->stringid, $text);
$component->add_string($string);

$stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
$stage->add($component, true);
$stage->store();
mlang_stash::autosave($stage);

header('Content-Type: application/json; charset: utf-8');
$response = new stdclass();
$response->text = local_amos_renderer::add_breaks(s($string->text));

echo json_encode($response);
