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
 * Stages the strings with different texts on two branches
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true); // progress bar is used here

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/diff_form.php');

require_login(SITEID, false);
require_capability('local/amos:stage', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/diff.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stage.php'));
$PAGE->set_title('AMOS ' . get_string('diff', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('diff', 'local_amos'));

$diffform = new local_amos_diff_form(null, local_amos_diff_options());

if ($data = $diffform->get_data()) {

    if ($data->versiona == $data->versionb) {
        notice(get_string('nodiffs', 'local_amos'), new moodle_url('/local/amos/stage.php'));
    }

    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());

    $versiona = mlang_version::by_code($data->versiona);
    $versionb = mlang_version::by_code($data->versionb);

    $tree = mlang_tools::components_tree(array('branch' => $versiona->code, 'lang' => 'en'));
    $componentnames = array_keys($tree[$versiona->code]['en']);
    $total = count($componentnames);
    unset($tree);

    echo $OUTPUT->header();
    $progressbar = new progress_bar();
    $progressbar->create();         // prints the HTML code of the progress bar

    // we may need a bit of extra execution time and memory here
    @set_time_limit(HOURSECS);
    raise_memory_limit(MEMORY_EXTRA);

    // number of differences
    $num = 0;

    foreach ($componentnames as $i => $componentname) {

        $progressbar->update($i, $total, get_string('diffprogress', 'local_amos'));

        // the most recent snapshots
        $englisha    = mlang_component::from_snapshot($componentname, 'en', $versiona);
        $englishb    = mlang_component::from_snapshot($componentname, 'en', $versionb);
        $translateda = mlang_component::from_snapshot($componentname, $data->language, $versiona);
        $translatedb = mlang_component::from_snapshot($componentname, $data->language, $versionb);
        // working components that holds the strings to be staged
        $worka       = new mlang_component($componentname, $data->language, $versiona);
        $workb       = new mlang_component($componentname, $data->language, $versionb);

        foreach ($englisha->get_iterator() as $strenglisha) {

            $strenglishb    = $englishb->get_string($strenglisha->id);
            $strtranslateda = $translateda->get_string($strenglisha->id);
            $strtranslatedb = $translatedb->get_string($strenglisha->id);

            // nothing compares to you, my dear null string
            if (is_null($strenglishb) or is_null($strtranslateda) or is_null($strtranslatedb)) {
                continue;
            }

            // in case we will need it, decide which of the translations is the more recent
            if ($strtranslateda->timemodified >= $strtranslatedb->timemodified) {
                $strtranslatedrecent = $strtranslateda;
            } else {
                $strtranslatedrecent = $strtranslatedb;
            }

            $englishchanged = mlang_string::differ($strenglisha, $strenglishb);
            $translatedchanged = mlang_string::differ($strtranslateda, $strtranslatedb);

            // English strings have changed but translated ones have not
            if ($data->mode == 1) {
                if ($englishchanged and !$translatedchanged) {
                    $worka->add_string($strtranslateda);
                    $workb->add_string($strtranslatedb);
                    $num++;
                }
            }

            // English strings have not changed but translated ones have
            if ($data->mode == 2) {
                if (!$englishchanged and $translatedchanged) {
                    if ($data->action == 1) {
                        $worka->add_string($strtranslateda);
                        $workb->add_string($strtranslatedb);
                    } else {
                        $worka->add_string($strtranslatedrecent);
                        $workb->add_string($strtranslatedrecent);
                    }
                    $num++;
                }
            }

            // Either English or translated strings have changed (but not both)
            if ($data->mode == 3) {
                if (($englishchanged or $translatedchanged) and (!($englishchanged and $translatedchanged))) {
                    if ($data->action == 1) {
                        $worka->add_string($strtranslateda);
                        $workb->add_string($strtranslatedb);
                    } else {
                        $worka->add_string($strtranslatedrecent);
                        $workb->add_string($strtranslatedrecent);
                    }
                    $num++;
                }
            }

            // Both English and translated strings have changed
            if ($data->mode == 4) {
                if ($englishchanged and $translatedchanged) {
                    if ($data->action == 1) {
                        $worka->add_string($strtranslateda);
                        $workb->add_string($strtranslatedb);
                    } else {
                        $worka->add_string($strtranslatedrecent);
                        $workb->add_string($strtranslatedrecent);
                    }
                    $num++;
                }
            }
        }

        // if some strings were detected, stage them
        if ($worka->has_string()) {
            $stage->add($worka);
        }
        if ($workb->has_string()) {
            $stage->add($workb);
        }

        // clear all the components used
        $englisha->clear();
        $englishb->clear();
        $translateda->clear();
        $translatedb->clear();
        $worka->clear();
        $workb->clear();
    }

    // store the persistant stage
    $stage->store();

    // if no new strings are merged, inform the user
    if (!$stage->has_component()) {
        $progressbar->update($total, $total, get_string('nodiffs', 'local_amos'));
        echo html_writer::empty_tag('br');
        echo $OUTPUT->continue_button(new moodle_url('/local/amos/stage.php'), 'get');
        echo $OUTPUT->footer();
        exit(0);
    }

    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $a           = new stdClass();
    $a->versiona = $versiona->label;
    $a->versionb = $versionb->label;
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage3', 'local_amos', $a);
}

$progressbar->update($total, $total, get_string('diffprogressdone', 'local_amos', $num));
echo html_writer::empty_tag('br');
echo $OUTPUT->continue_button(new moodle_url('/local/amos/stage.php'), 'get');
echo $OUTPUT->footer();
