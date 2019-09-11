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
 * View your subscription and change settings
 *
 * @package   local-amos
 * @copyright 2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @copyright 2019 Martin Gauk <gauk@math.tu-berlin.de>
 * @copyright 2019 Jan Eberhardt <eberhardt@tu-berlin.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');

require_login(SITEID, false);

#$name   = optional_param('name', null, PARAM_RAW);  // stash name
$mode = optional_param('m', null, PARAM_INT);
$component = optional_param('c', null, PARAM_ALPHAEXT);
$lang = optional_param('l', null, PARAM_ALPHAEXT);

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/subscription.php');
$PAGE->set_title('AMOS ' . get_string('subscription', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('subscription', 'local_amos'));
$PAGE->requires->strings_for_js(array('processing', 'googletranslate'), 'local_amos');
$PAGE->requires->yui_module('moodle-local_amos-filter', 'M.local_amos.init_filter', null, null, true);
#$PAGE->requires->yui_module('moodle-local_amos-translator', 'M.local_amos.init_translator', null, null, true);
$PAGE->requires->yui_module('moodle-local_amos-timeline', 'M.local_amos.init_timeline', null, null, true);

$filter = new local_amos_subscription_filter($PAGE->url);
$output = $PAGE->get_renderer('local_amos');
$table = new local_amos\local\subscription_table('subscription_table');
$table->define_baseurl($PAGE->url);

echo $output->header();
echo $output->render($filter);
echo $table->out(40, true);
echo $output->footer();