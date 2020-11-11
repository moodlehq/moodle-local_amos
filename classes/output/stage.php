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
 * Provides {local_amos\output\stage} class.
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
 * Represents the persistant stage to be displayed
 *
 * @package     local_amos
 * @category    output
 * @copyright   2010 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage implements \renderable, \templatable {

    /** @var array of objects to be rendered */
    public $strings;

    /** @var object holds the info needed to mimic a filter form */
    public $filterfields;

    /** $var local_amos_importfile_form form to import data */
    public $importform;

    /** @var local_amos_merge_form to merge strings form another branch */
    public $mergeform;

    /** @var local_amos_diff_form to stage differences between two branches */
    public $diffform;

    /** @var local_amos_execute_form to execute a given AMOScript */
    public $executeform;

    /** @var pre-set commit message */
    public $presetmessage;

    /** @var object if the stage comes from an applied contribution, this object holds the id and the contributor */
    public $stagedcontribution;

    /** @var bool */
    public $cancommit = false;

    /** @var bool */
    public $canstash = false;

    /**
     * @param object $user the owner of the stage
     */
    public function __construct(object $user) {
        global $DB;

        if (has_capability('local/amos:importfile', \context_system::instance(), $user)) {
            $this->importform = new \local_amos_importfile_form(
                new \moodle_url('/local/amos/importfile.php'),
                local_amos_importfile_options()
            );
        }

        if (has_capability('local/amos:commit', \context_system::instance(), $user)) {
            $this->cancommit = true;
            $this->mergeform = new \local_amos_merge_form(
                new \moodle_url('/local/amos/merge.php'),
                local_amos_merge_options()
            );
            $this->diffform = new \local_amos_diff_form(
                new \moodle_url('/local/amos/diff.php'),
                local_amos_diff_options()
            );
        }

        if (has_capability('local/amos:stash', \context_system::instance(), $user)) {
            $this->canstash = true;
        }

        if (has_all_capabilities(['local/amos:execute', 'local/amos:stage'], \context_system::instance(), $user)) {
            $this->executeform = new \local_amos_execute_form(
                new \moodle_url('/local/amos/execute.php'),
                local_amos_execute_options());
        }

        // Tree of strings to simulate ORDER BY component, stringid, lang, sincecode.
        $stringstree = [];

        // Final list populated from the stringstree.
        $this->strings = [];

        // Describes all strings that we will have to load to display the stage.
        $needed = [];

        // The stage we are visualising.
        $stage = \mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);

        foreach($stage as $component) {
            foreach ($component as $staged) {
                if (!isset($needed[$component->version->code][$component->lang][$component->name])) {
                    $needed[$component->version->code][$component->lang][$component->name] = [];
                }
                $needed[$component->version->code][$component->lang][$component->name][] = $staged->id;
                $needed[$component->version->code]['en'][$component->name][] = $staged->id;

                $string = (object) [];
                $string->component = $component->name;
                $string->sincecode = $component->version->code;
                $string->sincelabel = $component->version->label;
                $string->language = $component->lang;
                $string->stringid = $staged->id;
                $string->timemodified = $staged->timemodified;
                $string->deleted = $staged->deleted;
                $string->original = null;
                $string->current = null;
                $string->new = $staged->text;
                $string->committable = false;
                $string->nocleaning = $staged->nocleaning ?? false;

                $stringstree[$string->component][$string->stringid][$string->language][$string->sincecode] = $string;
            }
        }

        // Order by component.
        ksort($stringstree);
        foreach ($stringstree as $subtree) {
            // Order by stringid.
            ksort($subtree);
            foreach ($subtree as $subsubtree) {
                // Order by language.
                ksort($subsubtree);
                foreach ($subsubtree as $subsubsubtree) {
                    // Order by sincecode.
                    ksort($subsubsubtree);
                    foreach ($subsubsubtree as $string) {
                        $this->strings[] = $string;
                    }
                }
            }
        }

        unset($stringstree);

        $flng = [];
        $fcmp = [];

        foreach ($needed as $branch => $languages) {
            foreach ($languages as $language => $components) {
                $flng[$language] = true;
                foreach ($components as $component => $stringnames) {
                    $fcmp[$component] = true;
                    $needed[$branch][$language][$component] = \mlang_component::from_snapshot($component,
                        $language, \mlang_version::by_code($branch), null, false, false, $stringnames);
                }
            }
        }

        $this->filterfields = (object) [];
        $this->filterfields->flast = true;
        $this->filterfields->flng = array_keys($flng);
        $this->filterfields->fcmp = array_keys($fcmp);
        $allowedlangs = \mlang_tools::list_allowed_languages($user->id);

        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) or !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }

            if (!$needed[$string->sincecode]['en'][$string->component]->has_string($string->stringid)) {
                $string->original = '*DELETED*';
            } else {
                $string->original = $needed[$string->sincecode]['en'][$string->component]->get_string($string->stringid)->text;
            }

            if ($needed[$string->sincecode][$string->language][$string->component] instanceof \mlang_component) {
                $string->current = $needed[$string->sincecode][$string->language][$string->component]->get_string($string->stringid);
                if ($string->current instanceof \mlang_string) {
                    $string->current = $string->current->text;
                }
            }

            if (empty(\mlang_version::by_code($string->sincecode)->translatable)) {
                $string->committable = false;
            }
        }
    }

    /**
     * Export stage data to be rendered.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $result = [
            'strings' => [],
        ];

        $listlanguages = \mlang_tools::list_languages();

        $standard = [];
        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }

        foreach ($this->strings as $string) {
            $string->displaysince = $string->sincelabel . '+';
            $string->displaycomponent = $standard[$string->component] ?? $string->component;
            $string->displaylanguage = $listlanguages[$string->language] ?? $string->language;

            $string->displayoriginal = \local_amos\local\util::add_breaks(s($string->original));
            $string->hastranslationnew = false;
            $string->hastranslationcurrent = false;
            $string->hastranslationdiff = false;

            $string->unstageurl = (new \moodle_url('/local/amos/stage.php', [
                'unstage' => $string->stringid,
                'component' => $string->component,
                'branch' => $string->sincecode,
                'lang' => $string->language,
            ]))->out(false);

            $string->editurl = (new \moodle_url('/local/amos/view.php', [
                't' => time(),
                'v' => $string->sincecode,
                'l' => $string->language,
                'c' => $string->component,
                'd' => $string->stringid,
            ]))->out(false);

            $string->timelineurl = (new \moodle_url('/local/amos/timeline.php', [
                'component' => $string->component,
                'language' => $string->language,
                'stringid'  => $string->stringid,
            ]))->out(false);

            if ($string->deleted) {
                $string->statusclass = 'removal';
                $string->displaytranslationcurrent = \local_amos\local\util::add_breaks(s($string->current));
                $string->hastranslationcurrent = true;

            } else if ($string->current === null) {
                $string->statusclass = 'new';
                $string->displaytranslationnew = \local_amos\local\util::add_breaks(s($string->new));
                $string->hastranslationnew = true;

            } else if (($string->nocleaning && $string->current === $string->new) ||
                    (!$string->nocleaning && trim($string->current) === trim($string->new))) {
                $string->statusclass = 'nodiff';
                $string->displaytranslationcurrent = \local_amos\local\util::add_breaks(s($string->current));
                $string->hastranslationcurrent = true;

            } else {
                $string->statusclass = 'diff';
                $c = s($string->current);
                $n = s($string->new);
                $x1 = explode(' ', $c);
                $x2 = explode(' ', $n);

                $t = '';
                $diff = local_amos_simplediff($x1, $x2);
                $numd = 0;
                $numi = 0;
                foreach ($diff as $k) { // $diff is a sequence of chunks (words) $k
                    if (is_array($k)) {
                        if (!empty($k['d'])) {
                            $kd = implode(' ', $k['d']);
                            if (!empty($kd)) {
                                $t .= '<del>'.$kd.'</del> ';
                                $numd += count($k['d']);
                            }
                        }
                        if (!empty($k['i'])) {
                            $ki = implode(' ', $k['i']);
                            if (!empty($ki)) {
                                $t .= '<ins>'.$ki.'</ins> ';
                                $numi += count($k['i']);
                            }
                        }
                    } else {
                        $t .= $k . ' ';
                    }
                }

                if ($numi == 0 || $numd == 0 || ($numd == 1 && $numi == 1)) {
                    $string->diffmode = 'chunks';
                } else {
                    $string->diffmode = 'blocks';
                }
                $string->hastranslationdiff = true;
                $string->displaytranslationnew = \local_amos\local\util::add_breaks(s($string->new));
                $string->displaytranslationcurrent = \local_amos\local\util::add_breaks(s($string->current));
                $string->displaytranslationdiff = \local_amos\local\util::add_breaks($t);
            }

            $result['strings'][] = $string;
        }

        return $result;
    }
}
