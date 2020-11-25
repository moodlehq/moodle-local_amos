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
     * Reset MUC caches.
     */
    public static function reset_caches() {

        $cache = cache::make('local_amos', 'stats');
        $cache->purge();
    }

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

    /**
     * Return translation stats for the given component.
     *
     * @param string $component
     * @return object|bool False if no component found, stats data otherwise.
     */
    public function get_component_stats(string $component) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/amos/mlanglib.php');

        $cache = cache::make('local_amos', 'stats');
        $cachekey = 'component_' . $component;

        if ($cached = $cache->get($cachekey)) {
            return $cached;
        }

        $data = [];
        $lastmodified = 0;
        $rs = $DB->get_recordset('amos_stats', ['component' => $component], 'branch DESC, numofstrings DESC, lang ASC');

        foreach ($rs as $record) {
            if ($record->timemodified > $lastmodified) {
                $lastmodified = $record->timemodified;
            }
            $data[$record->branch][$record->lang] = $record->numofstrings;
        }

        $rs->close();

        if (empty($data)) {
            // No such component.
            return false;
        }

        $result = [
            'lastmodified' => $lastmodified,
            'langnames' => [],
            'branches' => [],
        ];

        $langnames = mlang_tools::list_languages();
        $langused = [];

        foreach ($data as $branch => $langs) {
            $mlangversion = mlang_version::by_code($branch);

            if (empty($mlangversion)) {
                debugging('Unknown branch code: '.$branch);
                continue;
            }

            $branchinfo = [
                'branch' => $mlangversion->dir,
                'languages' => [],
            ];

            foreach ($langs as $lang => $numofstrings) {
                if (!isset($langnames[$lang])) {
                    debugging('Unknown language code: '.$lang);
                    continue;
                }

                if (empty($numofstrings)) {
                    continue;
                }

                $langused[$lang] = $langnames[$lang];

                $langinfo = [
                    'lang' => $lang,
                    'numofstrings' => $numofstrings,
                    'ratio' => null,
                ];

                if ($lang === 'en') {
                    array_unshift($branchinfo['languages'], $langinfo);
                } else {
                    array_push($branchinfo['languages'], $langinfo);
                }
            }

            array_push($result['branches'], $branchinfo);
        }

        foreach ($result['branches'] as $bix => $branchinfo) {
            $numofenglish = 0;
            foreach ($branchinfo['languages'] as $lix => $langinfo) {
                if ($langinfo['lang'] === 'en') {
                    $numofenglish = $langinfo['numofstrings'];
                    break;
                }
            }

            if (empty($numofenglish)) {
                unset($result['branches'][$bix]);
                continue;
            }

            foreach ($branchinfo['languages'] as $lix => $langinfo) {
                $ratio = round($langinfo['numofstrings'] / $numofenglish * 100);
                $result['branches'][$bix]['languages'][$lix]['ratio'] = max(0, min(100, $ratio));
            }
        }

        foreach ($langused as $langcode => $langname) {
            array_push($result['langnames'], [
                'lang' => $langcode,
                'name' => $langname,
            ]);
        }

        $cache->set($cachekey, $result);

        return $result;
    }

    /**
     * Return the number of translated strings in standard language packs.
     *
     * This is displyed as the indicator of the language pack completeness.
     *
     * @param int|null $vercode - Show stats for the version, defaults to latest version.
     * return array
     */
    public function get_language_pack_ratio_stats(?int $vercode = null): array {
        global $DB;

        if ($vercode) {
            $version = mlang_version::by_code($vercode);
        } else {
            $version = mlang_version::latest_version();
        }

        $cache = cache::make('local_amos', 'stats');
        $cachekey = 'langpackratio' . $version->code;

        if ($cached = $cache->get($cachekey)) {
            return $cached;
        }

        $langnames = mlang_tools::list_languages(true, true, false, true);
        $standardcomponents = \local_amos\local\util::standard_components_in_version($version->code);

        [$standardsql, $standardparams] = $DB->get_in_or_equal(array_keys($standardcomponents), SQL_PARAMS_NAMED);

        $sql = "SELECT lang, SUM(numofstrings) AS totalnumofstrings
                  FROM {amos_stats}
                 WHERE component ${standardsql}
                   AND branch = :vercode
              GROUP BY lang
              ORDER BY totalnumofstrings DESC, MAX(timemodified), lang";

        $params = [
            'vercode' => $version->code,
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
        $english = $langpacks['en'] ?? [];
        unset($langpacks['en']);
        $langpacks = array_merge(['en' => $english], $langpacks);

        foreach ($langpacks as $langpack) {
            if (empty($langpack)) {
                continue;
            }

            if (substr($langpack->langcode, 0, 3) === 'en_') {
                $parent = 'en';

            } else {
                $langconfig = mlang_component::from_snapshot('langconfig', $langpack->langcode, $version);
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
            if (!empty($item)) {
                $item->totalenglish = $english->totalstrings ?? 0;
                if ($item->totalenglish > 0) {
                    $item->ratio = max(0, min(100, round(100 * $item->totalstrings / $english->totalstrings)));
                } else {
                    $item->ratio = 0;
                }
            }
        });

        $cache->set($cachekey, $primary);

        return $primary;
    }

    /**
     * Populates the contribution stats for the front page.
     *
     * @return string
     */
    public function frontpage_contribution_stats(): array {
        global $CFG, $DB;

        $cache = cache::make('local_amos', 'stats');
        $cachekey = 'contributionstats';

        if ($cached = $cache->get($cachekey)) {
            return $cached;
        }

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

        $result = [
            'contributedstrings' => number_format($total, 0, '', get_string('thousandssep', 'core_langconfig')),
            'listcontributors' => $links,
        ];

        $cache->set($cachekey, $result);

        return $result;
    }
}
