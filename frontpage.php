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

['contributedstrings' => $contributedstrings, 'listcontributors' => $listcontributors] = local_amos_frontpage_contribution_stats();

echo $OUTPUT->render_from_template('local_amos/frontpage', [
    'langpackstats' => local_amos_frontpage_translation_stats(),
    'contributedstrings' => $contributedstrings,
    'listcontributors' => $listcontributors,
]);

/**
 * Generate translations stats for the front page.
 *
 * @return array
 */
function local_amos_frontpage_translation_stats(): array {
    global $DB;

    $langnames = mlang_tools::list_languages(true, true, false, true);
    $latestversion = mlang_version::latest_version();
    $standardcomponents = \local_amos\local\util::standard_components_in_latest_version();

    [$standardsql, $standardparams] = $DB->get_in_or_equal(array_keys($standardcomponents), SQL_PARAMS_NAMED);

    $sql = "SELECT lang, SUM(numofstrings) AS totalnumofstrings
        FROM {amos_stats}
        WHERE component ${standardsql}
        AND branch = :latestversioncode
        GROUP BY lang
        ORDER BY totalnumofstrings DESC, MAX(timemodified), lang";

    $params = [
        'latestversioncode' => $latestversion->code,
    ];

    $params += $standardparams;

    $rs = $DB->get_recordset_sql($sql, $params);

    $langpacks = [];
    $primary = [];

    foreach ($rs as $record) {
        $langpacks[$record->lang] = (object) [
            'langcode' => $record->lang,
            'langname' => $langnames[$record->lang],
            'totalstrings' => $record->totalnumofstrings,
        ];
    }

    $rs->close();

    // Make sure that English is the first in the list.
    $english = $langpacks['en'];
    unset($langpacks['en']);
    $langpacks = array_merge(['en' => $english], $langpacks);

    foreach ($langpacks as $langpack) {
        if (substr($langpack->langcode, 0, 3) === 'en_') {
            $parent = 'en';
        } else {
            $langconfig = mlang_component::from_snapshot('langconfig', $langpack->langcode, $latestversion);
            if ($mlangstringparentlanguage = $langconfig->get_string('parentlanguage')) {
                $parent = $mlangstringparentlanguage->text;
            } else {
                $parent = '';
            }
            if ($parent === 'en') {
                $parent = '';
            }
        }

        if (empty($parent)) {
            $primary[] = $langpack;

        } else if (isset($langpacks[$parent])) {
            $langpack->parentlanguagecode = $parent;
            $langpack->parentlanguagename = $langpacks[$parent]->langname;
            $langpacks[$parent]->childpacks = $langpacks[$parent]->childpacks ?? [];
            $langpacks[$parent]->childpacks[] = $langpack;

        } else {
            // Orphaned language pack.
            continue;
        }
    }

    array_walk($langpacks, function (&$item) use ($english) {
        $item->totalenglish = $english->totalstrings;
        $item->ratio = round(100 * $item->totalstrings / $english->totalstrings);
    });

    return $primary;
}

/**
 * Populates the contribution front page block contents.
 *
 * @return string
 */
function local_amos_frontpage_contribution_stats(): array {
    global $CFG, $DB;

    $total = (int)$DB->get_field_sql("
        SELECT SUM(strings)
          FROM {amos_contributions} c
          JOIN mdl_amos_stashes s ON c.stashid = s.id
         WHERE c.status = 30");

    $namefields = get_all_user_name_fields(true, "u");
    $recent = $DB->get_records_sql("
        SELECT c.authorid AS id, $namefields, MAX(c.timecreated) AS mostrecent
          FROM {amos_contributions} c
          JOIN {user} u ON u.id = c.authorid
      GROUP BY c.authorid, $namefields
      ORDER BY mostrecent DESC", null, 0, 4);

    $links = array();
    foreach ($recent as $contributor) {
        $links[] = '<a href="'.$CFG->wwwroot.'/user/profile.php?id='.$contributor->id.'">'.s(fullname($contributor)).'</a>';
    }

    $links = get_string('contributethankslist', 'local_amos', [
        'contributor1' => $links[0],
        'contributor2' => $links[1],
        'contributor3' => $links[2],
        'contributor4' => $links[3],
    ]);

    return [
        'contributedstrings' => number_format($total, 0, '', get_string('thousandssep', 'core_langconfig')),
        'listcontributors' => $links,
    ];
}
