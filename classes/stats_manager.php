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
 * Provides the {@link local_amos_stats_manager} class.
 *
 * @package     local_amos
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for accessing and updating the translation stats.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_stats_manager {

    /**
     * Update (or insert) the stats for the given language pack version.
     *
     * @param int $branch Version code such as 3700
     * @param string $lang Language code such as 'cs' or 'en'
     * @param string $component Component name such as 'forum', 'moodle' or 'workshopallocation_random'
     * @param int|null $numofstrings Number of strings in the given language pack
     */
    public function update_stats(int $branch, string $lang, string $component, int $numofstrings = null) {
        global $DB;

        $record = (object)[
            // Always set this even when not actually changing the numofstrings - keeps the timestamp of the recent check.
            'timemodified' => time(),
            'branch' => $branch,
            'lang' => $lang,
            'component' => $component,
            'numofstrings' => $numofstrings,
        ];

        $current = $DB->get_record('amos_stats', [
            'branch' => $record->branch,
            'lang' => $record->lang,
            'component' => $record->component,
        ], 'id', IGNORE_MISSING);

        if ($current) {
            $record->id = $current->id;
            $DB->update_record('amos_stats', $record, true);

        } else {
            $DB->insert_record('amos_stats', $record, false);
        }
    }
}
