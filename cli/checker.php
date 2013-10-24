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
 * Performs a set of checks of the AMOS repository consistency
 *
 * This script should be run regularly via cron. Standard output is supposed
 * to be redirected to a file. Only check failures are sent to the standard error
 * output - that can be used to send emails by the cron job automatically.
 *
 * @package   local_amos
 * @copyright 2012 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Provides all the checks to execute and some helper methods
 */
class amos_checker {

    const RESULT_SUCCESS = 0;
    const RESULT_FAILURE = 1;

    /**
     * Checks the decsep, thousandssep and listsep config strings are set correctly
     *
     * @link http://tracker.moodle.org/browse/MDL-31332
     * @link http://tracker.moodle.org/browse/MDL-30788
     * @link http://docs.moodle.org/dev/Translation_langconfig#listsep.2Ccore_langconfig
     * @return int
     */
    protected function check_separators() {

        $details = array();
        $langnames = array();
        $tree = mlang_tools::components_tree(array('component' => 'langconfig'));
        foreach ($tree as $branch => $languages) {
            $version = mlang_version::by_code($branch);
            foreach (array_keys($languages) as $language) {
                if ($language === 'en_fix') {
                    // Having empty values in en_fix is expected, do not check it.
                    continue;
                }
                $langconfig = mlang_component::from_snapshot('langconfig', $language, $version);
                if ($langname = $langconfig->get_string('thislanguageint')) {
                    $langnames[$language] = $langname->text;
                } else {
                    $langnames[$language] = $language;
                }
                if ($decsep = $langconfig->get_string('decsep')) {
                    $decsep = $decsep->text;
                }
                if ($thousandssep = $langconfig->get_string('thousandssep')) {
                    $thousandssep = $thousandssep->text;
                }
                if ($listsep = $langconfig->get_string('listsep')) {
                    $listsep = $listsep->text;
                }
                $details[$language][$version->label] = array();
                if (empty($decsep) and empty($thousandssep)) {
                    $details[$language][$version->label][] = '! empty decsep and thousandssep';
                } else if (empty($decsep) or empty($thousandssep)) {
                    $details[$language][$version->label][] = '!! empty descsep or thousandssep';
                } else if ($decsep === $thousandssep) {
                    $details[$language][$version->label][] = '!!! decsep === thousandssep';
                }
                if (empty($listsep)) {
                    $details[$language][$version->label][] = '! empty listsep';
                } else if ($listsep === $decsep) {
                    $details[$language][$version->label][] = '!!! listsep === decsep';
                }
            }
        }

        ksort($details);
        $status = self::RESULT_SUCCESS;
        foreach ($details as $language => $branches) {
            foreach ($branches as $branch => $msgs) {
                foreach ($msgs as $msg) {
                    $this->output(sprintf('  Misconfigured separators: %s {%s} %s %s', $langnames[$language], $language, $branch, $msg), true);
                    $status = self::RESULT_FAILURE;
                }
            }
        }

        return $status;
    }

    /**
     * Search for pending contributions left if the "In review" state
     *
     * If all strings are already translated in the contribution (it means,
     * there are no actual translated strings after the contribution is
     * applied and rebased) then automatically mark such contribution
     * as accepted.
     *
     * @return int
     */
    protected function check_pending_reviews() {
        global $DB;

        // Suppose this check will pass successfully.
        $status = self::RESULT_SUCCESS;

        // Search for contributions in "In review" state not modified for
        // couple of days.
        $rs = $DB->get_recordset_select("amos_contributions",
            "status = :status AND timemodified <= :timemodified",
            array(
                'status' => local_amos_contribution::STATE_REVIEW,
                'timemodified' => time() - 3 * DAYSECS,
            )
        );

        foreach ($rs as $contribution) {
            // Get the contributed components and rebase them to see what would happen.
            $stash = mlang_stash::instance_from_id($contribution->stashid);
            $stage = new mlang_stage();
            $stash->apply($stage);
            $stage->rebase();
            list($rebasedstrings, $rebasedlanguages, $rebasedcomponents) = mlang_stage::analyze($stage);

            if ($rebasedstrings == 0) {
                $msg = sprintf('  Contribution #%d still in review with no new strings to be committed - marking as accepted',
                    $contribution->id);
                $this->output($msg, true);
                $status = self::RESULT_FAILURE;
                $DB->set_field('amos_contributions', 'status', local_amos_contribution::STATE_ACCEPTED,
                    array('id' => $contribution->id));

            } else {
                $msg = sprintf('  Contribution #%d still in review', $contribution->id);
                $this->output($msg, true);
                $status = self::RESULT_FAILURE;
            }
        }
        $rs->close();

        return $status;
    }
    /**
     * Returns the list of check methods to execute
     *
     * @return array of strings - method names
     */
    protected function get_checkers() {
        $methods = array();
        foreach (get_class_methods($this) as $method) {
            if (substr($method, 0, 6) === 'check_') {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     * Outputs the given message to the stdout or to the stderr
     *
     * @param string $message text to display
     * @param bool $error is this the error message for stderr
     * @return void
     */
    protected function output($message, $error = false) {
        $full = sprintf("[%-19s] %s\n", date('Y-m-d H:i:s'), $message);
        if ($error) {
            fputs(STDERR, $full);
        } else {
            fputs(STDOUT, $full);
        }
    }

    /**
     * Executes all check_* methods of this class
     *
     * @return int 0 for success, >0 if some checks fail
     */
    public function execute() {
        $this->output('START AMOS checks report');
        $result = 0;
        foreach ($this->get_checkers() as $method) {
            $this->output(' RUNNING ' . $method);
            $status = $this->$method();
            if ($status === self::RESULT_FAILURE) {
                $this->output(' FAILED ' . $method, true);
                $result = 1;
            } else if ($status === self::RESULT_SUCCESS) {
                $this->output(' OK ' . $method);
            } else {
                $this->output(' ERROR ' . $method . ' returned unexpected value', true);
            }
        }
        $this->output('END AMOS checks report');
        return $result;
    }

}

$checker = new amos_checker();
exit($checker->execute());
