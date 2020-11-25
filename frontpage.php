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
 * Displays the lang.moodle.org front page content
 *
 * @package     local_amos
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$statsman = new local_amos_stats_manager();

[
    'contributedstrings' => $contributedstrings,
    'listcontributors' => $listcontributors,
] = $statsman->frontpage_contribution_stats();

echo $OUTPUT->render_from_template('local_amos/frontpage', [
    'langpackstats' => $statsman->get_language_pack_ratio_stats(),
    'contributedstrings' => $contributedstrings,
    'listcontributors' => $listcontributors,
]);
