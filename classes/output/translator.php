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
 * Provides class {@see \local_amos\output\translator}.
 *
 * @package     local_amos
 * @category    output
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Represents the translation tool.
 *
 * @copyright   2010 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translator implements \renderable, \templatable {

    /** Number of rows per page. */
    const PERPAGE = 100;

    /** @var int total number of found strings matching the filter */
    public $numofrows = 0;

    /** @var int total number of untranslated strings */
    public $numofmissing = 0;

    /** @var int number of untranslated strings on the current page */
    public $numofmissingoncurrentpage = 0;

    /** @var int */
    public $currentpage = 1;

    /** @var array of object strings to display */
    public $strings = [];

    /** @var \local_amos\output\filter */
    public $filter = null;

    /**
     * Constructor.
     *
     * @param \local_amos\output\filter $filter
     * @param object $user working with the translator
     */
    public function __construct(\local_amos\output\filter $filter, object $user) {
        global $DB;

        $this->filter = $filter;

        // Get the list of strings to display according the current filter values.
        $last = $filter->get_data()->last;
        $version = $filter->get_data()->version;
        $languages = $filter->get_data()->language;
        $components = $filter->get_data()->component;

        if ((!$last && empty($version)) || empty($components) || empty($languages)) {
            return;
        }

        $missing = $filter->get_data()->missing;
        $outdated = $filter->get_data()->outdated;
        $has = $filter->get_data()->has;
        $helps = $filter->get_data()->helps;
        $substring = $filter->get_data()->substring;
        $substringregex = $filter->get_data()->substringregex;
        $substringcs = $filter->get_data()->substringcs;
        $stringid = $filter->get_data()->stringid;
        $stringidpartial = $filter->get_data()->stringidpartial;
        $stagedonly = $filter->get_data()->stagedonly;
        $app = $filter->get_data()->app;
        $workplace = $filter->get_data()->workplace;

        // Prepare the SQL queries to load all the filtered strings and their translations.
        $params = [];

        list($sqllanguages, $paramlanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED);
        list($sqlcomponents1, $paramcomponents1) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED);
        list($sqlcomponents2, $paramcomponents2) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED);

        $params += $paramlanguages;
        $params += $paramcomponents1;
        $params += $paramcomponents2;

        if ($last) {
            // Get know the most recent branch for every component.
            $latestcomponentbranch = $DB->get_records_select_menu('amos_strings',  "component $sqlcomponents1 GROUP BY component",
                $paramcomponents1,  '', "component, MAX(since) as branch");
        }

        // Get all the English strings and translations for the translator.

        $sql1 = "SELECT id, since, 'en' AS lang, component, strname, strtext, timemodified
                   FROM {amos_strings}
                  WHERE component $sqlcomponents1";

        $sql2 = "SELECT id, since, lang, component, strname, strtext, timemodified
                   FROM {amos_translations}
                  WHERE component $sqlcomponents2
                    AND lang $sqllanguages";

        if ($stringid) {
            if ($stringidpartial) {
                $sql1 .= " AND " . $DB->sql_like("strname", ":strname1", false);
                $sql2 .= " AND " . $DB->sql_like("strname", ":strname2", false);
                $params['strname1'] = '%' . $DB->sql_like_escape($stringid) . '%';
                $params['strname2'] = '%' . $DB->sql_like_escape($stringid) . '%';

            } else {
                $sql1 .= " AND strname = :strname1";
                $sql2 .= " AND strname = :strname2";
                $params['strname1'] = $stringid;
                $params['strname2'] = $stringid;
            }
        }

        if ($helps) {
            $sql1 .= " AND " . $DB->sql_like("strname", ":helpstrname1", false);
            $sql2 .= " AND " . $DB->sql_like("strname", ":helpstrname2", false);
            $params['helpstrname1'] = '%' . $DB->sql_like_escape('_help');
            $params['helpstrname2'] = '%' . $DB->sql_like_escape('_help');
        }

        $sql = "SELECT id, since, lang, component, strname, strtext, timemodified
                  FROM ($sql1 UNION $sql2) s";

        $recordset = $DB->get_recordset_sql($sql, $params);

        // Iterate over the results and pick the most recent string value for each selected branch.
        $tree = [];
        $newer = [];
        $compbranch = 0;

        foreach ($recordset as $record) {
            if ($last) {
                $compbranch = $latestcomponentbranch[$record->component];
            } else {
                $compbranch = $version;
            }

            if (!$last && $record->since > $compbranch) {
                // Make a note that more recent version exists and exit early as we are showing selected version only.
                $newer[$record->lang][$record->component][$record->strname] = true;
                continue;
            }

            if (!isset($tree[$record->lang][$record->component][$record->strname])) {
                $tree[$record->lang][$record->component][$record->strname] = $record;

            } else {
                $current = $tree[$record->lang][$record->component][$record->strname];

                if ($record->since < $current->since) {
                    continue;
                }

                if (($record->since == $current->since) && ($record->timemodified < $current->timemodified)) {
                    continue;
                }

                if (($record->since == $current->since) && ($record->timemodified == $current->timemodified)
                        && ($record->id < $current->id)) {
                    continue;
                }

                $tree[$record->lang][$record->component][$record->strname] = $record;
            }
        }

        $recordset->close();

        // Convert the tree into a new one containing only non-deletions.
        $s = [];

        foreach ($tree as $xlang => $xcomps) {
            foreach ($xcomps as $xcomp => $xstrnames) {
                foreach ($xstrnames as $xstrname => $record) {
                    if ($record->strtext !== null) {
                        $s[$xlang][$xcomp][$xstrname] = (object) [
                            'amosid' => $record->id,
                            'text' => $record->strtext,
                            'since' => $record->since,
                            'timemodified' => $record->timemodified,
                            'islatest' => empty($newer[$xlang][$xcomp][$xstrname]),
                        ];
                    }
                    unset($tree[$xlang][$xcomp][$xstrname]);
                }
            }
        }
        unset($tree);

        foreach ($s as $xlang => &$xcomps) {
            ksort($xcomps);
            foreach ($xcomps as $xcomp => &$xstrnames) {
                ksort($xstrnames);
            }
        }

        // Replace the loaded values with those already staged.
        $stage = \mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);
        foreach ($stage as $component) {
            foreach ($component as $staged) {
                if ($staged->component->version->code > $compbranch) {
                    continue;
                }
                $string = (object) [];
                $string->amosid = null;
                $string->text = $staged->text;
                $string->since = $staged->component->version->code;
                $string->timemodified = $staged->timemodified;
                $string->statusclass = 'staged';
                $string->nocleaning = $staged->nocleaning;
                $s[$component->lang][$component->name][$staged->id] = $string;
            }
        }

        $this->currentpage = $filter->get_data()->page;
        $from = ($this->currentpage - 1) * self::PERPAGE + 1;
        $to = $this->currentpage * self::PERPAGE;
        if (isset($s['en'])) {
            foreach ($s['en'] as $component => $t) {
                foreach ($t as $stringid => $english) {
                    reset($languages);
                    foreach ($languages as $lang) {
                        if ($lang == 'en') {
                            continue;
                        }
                        $string = (object) [];
                        $versince = \mlang_version::by_code($english->since);
                        $string->englishsincedir = $versince->dir;
                        $string->englishsincecode = $versince->code;
                        $string->englishsincelabel = $versince->label;
                        $string->islatestoriginal = $english->islatest;
                        $string->language = $lang;
                        $string->component = $component;
                        $string->stringid = $stringid;
                        // One day we may want to populate this somehow - such as where the string appears.
                        $string->metainfo = '';
                        $string->original = $english->text;
                        $string->originalid = $english->amosid;
                        $string->originalmodified = $english->timemodified;
                        $string->translatable = true;
                        $string->committable = false;
                        $string->islatesttranslation = empty($newer[$lang][$component][$stringid]);

                        if (isset($s[$lang][$component][$stringid])) {
                            $string->translation = $s[$lang][$component][$stringid]->text;
                            $string->translationid = $s[$lang][$component][$stringid]->amosid;
                            $string->timemodified = $s[$lang][$component][$stringid]->timemodified;
                            $versince = \mlang_version::by_code($s[$lang][$component][$stringid]->since);
                            $string->translationsincedir = $versince->dir;
                            $string->translationsincecode = $versince->code;
                            $string->translationsincelabel = $versince->label;

                            if (isset($s[$lang][$component][$stringid]->statusclass)) {
                                $string->statusclass = $s[$lang][$component][$stringid]->statusclass;

                            } else {
                                $string->statusclass = 'translated';
                            }

                            if ($string->englishsincecode >= $string->translationsincecode &&
                                    $string->originalmodified > $string->timemodified) {
                                $string->outdated = true;

                            } else {
                                $string->outdated = false;
                            }

                            if (isset($s[$lang][$component][$stringid]->nocleaning)) {
                                $string->nocleaning = $s[$lang][$component][$stringid]->nocleaning;

                            } else {
                                $string->nocleaning = false;
                            }

                        } else {
                            $string->translation = null;
                            $string->translationid = null;
                            $string->timemodified = null;
                            $string->translationsincedir = null;
                            $string->translationsincecode = null;
                            $string->translationsincelabel = null;
                            $string->statusclass = 'missing';
                            $string->outdated = false;
                            $string->nocleaning = false;
                        }

                        $applist = local_amos_applist_strings();
                        $workplacelist = local_amos_workplace_plugins();

                        if ($component == 'local_moodlemobileapp') {
                            $string->app = $stringid;
                        } else if (isset($applist[$component.'/'.$stringid])) {
                            $string->app = $applist[$component.'/'.$stringid];
                        } else {
                            $string->app = false;
                        }

                        if (in_array($component, $workplacelist)) {
                            $string->workplace = $component.'/'.$stringid;
                        } else {
                            $string->workplace = false;
                        }

                        unset($s[$lang][$component][$stringid]);

                        if ($has && $string->translation === null) {
                            continue;
                        }

                        if ($stagedonly && $string->statusclass != 'staged') {
                            continue;
                        }

                        if ($app && !$string->app) {
                            continue;
                        }

                        if ($workplace && !$string->workplace) {
                            continue;
                        }

                        if (!empty($substring)) {
                            // If defined, then either English or the translation must contain the substring.
                            if (empty($substringregex)) {
                                if (empty($substringcs)) {
                                    if (!stristr($string->original, trim($substring))
                                            && !stristr($string->translation, trim($substring))) {
                                        continue;
                                    }
                                } else {
                                    if (!strstr($string->original, trim($substring))
                                            && !strstr($string->translation, trim($substring))) {
                                        continue;
                                    }
                                }

                            } else {
                                // Considere substring as a regular expression.
                                if (empty($substringcs)) {
                                    if (!preg_match("/$substring/i", $string->original)
                                            && !preg_match("/$substring/i", $string->translation)) {
                                        continue;
                                    }
                                } else {
                                    if (!preg_match("/$substring/", $string->original)
                                            && !preg_match("/$substring/", $string->translation)) {
                                        continue;
                                    }
                                }
                            }
                        }

                        if ($missing) {
                            if ($string->statusclass === 'translated' && !$string->outdated) {
                                continue;
                            }
                        }

                        if ($outdated) {
                            if (!$string->outdated) {
                                continue;
                            }
                        }

                        $this->numofrows++;

                        if ($string->translation === null) {
                            $this->numofmissing++;
                        }

                        // Keep just strings from the current page.
                        if ($this->numofrows < $from || $this->numofrows > $to) {
                            unset($string);
                            continue;
                        }

                        if ($string->translation === null) {
                            $this->numofmissingoncurrentpage++;
                        }

                        $this->strings[] = $string;
                    }
                }
            }
        }

        $allowedlangs = \mlang_tools::list_allowed_languages($user->id);

        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) || !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }
        }

        $standard = \local_amos\local\util::standard_components_list();

        foreach ($this->strings as $string) {
            if ($string->language === 'en_fix') {
                if (!isset($standard[$string->component])) {
                    $string->committable = false;
                    $string->translatable = false;
                    $string->translation = get_string('unableenfixaddon', 'local_amos');
                }
            }
        }

        foreach ($this->strings as $string) {
            if ($string->language === 'en_fix' && $string->component === 'countries') {
                $string->committable = false;
                $string->translatable = false;
                $string->translation = get_string('unableenfixcountries', 'local_amos');
            }
        }

        // Check that the language has a maintainer to eventually commit the contribution.
        $maintainedlangscache = \cache::make_from_params(\cache_store::MODE_APPLICATION, 'local_amos', 'maintainedlangs');

        $maintainedlangs = $maintainedlangscache->get('maintainedlangs');

        if ($maintainedlangs === false) {
            $sql = "SELECT DISTINCT lang
                      FROM {amos_translators}
                     WHERE status = :maintainer";
            $maintainedlangs = array_flip($DB->get_fieldset_sql($sql, ['maintainer' => AMOS_USER_MAINTAINER]));
            $maintainedlangscache->set('maintainedlangs', $maintainedlangs);
        }

        $langnames = \mlang_tools::list_languages();

        foreach ($this->strings as $string) {
            if (!isset($maintainedlangs[$string->language]) && !$string->committable) {
                $string->translatable = false;
                $string->translation = get_string('unableunmaintained', 'local_amos', $langnames[$string->language]);
            }
        }
    }

    /**
     * Export data needed to render the translator via the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $result = [
            'permalink' => $this->filter->get_permalink()->out(false),
            'found' => $this->numofrows,
            'missing' => $this->numofmissing,
            'missingcurrentpage' => $this->numofmissingoncurrentpage,
            'strings' => $this->strings,
            'paginator' => $this->export_for_template_paginator($output),
        ];

        $listlanguages = \mlang_tools::list_languages();
        $standard = \local_amos\local\util::standard_components_list();

        foreach ($result['strings'] as &$string) {
            $string->displayenglishsince = $string->englishsincelabel . '+';
            $string->displaycomponent = $standard[$string->component] ?? $string->component;
            $string->displaylanguage = $listlanguages[$string->language] ?? $string->language;

            if ($string->translationsincelabel !== null) {
                $string->hastranslationsincelabel = true;
                $string->displaytranslationsince = $string->translationsincelabel . '+';
            }

            if ($string->app) {
                $string->isappstring = true;
                $string->infoapp = get_string('filtermisfapp_help', 'local_amos', $string->app);
            }

            if ($string->workplace) {
                $string->isworkplacestring = true;
                $string->infoworkplace = get_string('filtermisfworkplace_help', 'local_amos', $string->workplace);
            }

            $string->timelineurl = (new \moodle_url('/local/amos/timeline.php', [
                'component' => $string->component,
                'language' => $string->language,
                'stringid'  => $string->stringid,
            ]))->out(false);

            if ($string->committable && $string->translationid) {
                $string->hasuntranslateurl = true;
                $string->untranslateurl = (new \moodle_url('/local/amos/untranslate.php', [
                    'component' => $string->component,
                    'language'  => $string->language,
                    'stringid' => $string->stringid,
                    'since' => $string->translationsincecode,
                ]))->out(false);
            }

            $string->displayoriginal = \local_amos\local\util::add_breaks(s($string->original));
            // Workaround for <https://bugzilla.mozilla.org/show_bug.cgi?id=116083>.
            $string->displayoriginal = nl2br($string->displayoriginal);
            $string->displayoriginal = str_replace(array("\n", "\r"), '', $string->displayoriginal);

            $string->displaytranslation = \local_amos\local\util::add_breaks(s($string->translation));
        }

        return $result;
    }

    /**
     * Export data allowing to render the paginator.
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function export_for_template_paginator(\renderer_base $output): array {

        $totalpages = ceil($this->numofrows / self::PERPAGE);

        $result = [
            'hasmultiplepages' => ($totalpages > 1),
            'hasmorepages' => ($this->currentpage < $totalpages),
            'totalpages' => $totalpages,
            'currentpage' => $this->currentpage,
            'perpage' => self::PERPAGE,
            'navigation' => [],
        ];

        if ($this->currentpage > 1 && $this->currentpage <= $totalpages) {
            $result['navigation'][] = [
                'islink' => true,
                'label' => '<i class="fa fa-step-backward" aria-hidden="true" title="' . get_string('previouspage') . '"></i>',
                'value' => $this->currentpage - 1,
                'iscurrent' => false,
            ];
        }

        $lastprinted = null;

        for ($pnumber = 1; $pnumber < $totalpages && $pnumber <= $this->currentpage + 90; $pnumber++) {
            $result['navigation'][] = [
                'islink' => ($pnumber != $this->currentpage),
                'label' => $pnumber,
                'value' => $pnumber,
                'iscurrent' => ($pnumber == $this->currentpage),
            ];
            $lastprinted = $pnumber;
            if (abs($pnumber - $this->currentpage) >= 10) {
                $pnumber += 9;
            }
        }

        if ($lastprinted !== null && $lastprinted < $totalpages - 1) {
            $result['navigation'][] = [
                'islink' => false,
                'label' => '&hellip;',
            ];
        }

        if ($this->currentpage <= $totalpages) {
            $result['navigation'][] = [
                'islink' => ($totalpages != $this->currentpage),
                'label' => $totalpages,
                'value' => $totalpages,
                'iscurrent' => ($totalpages == $this->currentpage),
            ];
        }

        if ($this->currentpage < $totalpages) {
            $result['navigation'][] = [
                'islink' => true,
                'label' => '<i class="fa fa-step-forward" aria-hidden="true" title="' . get_string('nextpage') . '"></i>',
                'value' => $this->currentpage + 1,
                'iscurrent' => false,
            ];
        }

        return $result;
    }
}
