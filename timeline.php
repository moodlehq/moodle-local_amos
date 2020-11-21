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
 * Displays a timeline history of a given string
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(SITEID, false);
require_capability('local/amos:stage', context_system::instance());

$component = required_param('component', PARAM_ALPHANUMEXT);
$stringid = required_param('stringid', PARAM_STRINGID);
$language = required_param('language', PARAM_ALPHANUMEXT);

$PAGE->set_url('/local/amos/timeline.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('timeline', 'local_amos'));
$PAGE->set_context(context_system::instance());

$timeline = \local_amos\external\get_string_timeline::execute($component, $stringid, $language);
$timeline = external_api::clean_returnvalue(\local_amos\external\get_string_timeline::execute_returns(), $timeline);

$output = $PAGE->get_renderer('local_amos');

echo $output->header();
echo $output->render_from_template('local_amos/timeline', $timeline);
echo $output->footer();
