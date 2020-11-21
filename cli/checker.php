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

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Provides all the checks to execute and some helper methods
 */
class amos_checker {

    const RESULT_SUCCESS = 0;
    const RESULT_FAILURE = 1;
    const RESULT_PARAMS = 2;

    /** @var string checker task to execute via $this->check_{task} call */
    protected $task = null;

    /** @var null|array used for caching the list of maintainers of a lang pack */
    protected $maintainers = null;

    /** @var null|stdClass the user record for the AMOS bot */
    protected $amosbot = null;

    /**
     * Prepare an instance of the given task checker.
     *
     * @param string $taskname Name of the check task to execute.
     */
    public function __construct(string $taskname) {

        if (!method_exists($this, 'check_'.$taskname)) {
            fputs(STDERR, "Error: Unknown checker task: ".$taskname."\n");
            exit(self::RESULT_PARAMS);
        }

        $this->task = $taskname;
    }

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
        $langnames = mlang_tools::list_languages();
        $versions = mlang_version::list_all();
        foreach ($versions as $version) {
            foreach (array_keys($langnames) as $language) {
                if ($language === 'en_fix') {
                    // Having empty values in en_fix is expected, do not check it.
                    continue;
                }
                $langconfig = mlang_component::from_snapshot('langconfig', $language, $version);
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
                    $this->output(sprintf('  Misconfigured separators: %s {%s} %s %s',
                        $langnames[$language], $language, $branch, $msg), true);
                    $status = self::RESULT_FAILURE;
                }
            }
        }

        return $status;
    }

    /**
     * Search for pending contributions
     *
     * Pending are contributions left in the "New" state for longer than a week
     * or in the "In review" state for longer than three days.
     *
     * If all strings are already translated in the contribution (it means,
     * there are no actual translated strings after the contribution is
     * applied and rebased) then automatically mark such contribution
     * as accepted.
     *
     * @return int
     */
    protected function check_pending_contributions() {
        global $DB;

        // Suppose this check will pass successfully.
        $status = self::RESULT_SUCCESS;

        // Search for contributions in "In review" state not modified for
        // a week.
        $rs = $DB->get_recordset_select("amos_contributions",
            "(status = :statusnew AND timecreated <= :timecreated) OR (status = :statusreview AND timemodified <= :timemodified)",
            array(
                'statusnew' => local_amos_contribution::STATE_NEW,
                'statusreview' => local_amos_contribution::STATE_REVIEW,
                'timecreated' => time() - 7 * DAYSECS,
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
                $msg = sprintf('  Pending contribution #%d with no new strings to be committed - marking as accepted',
                    $contribution->id);
                $this->output($msg, true);
                $status = self::RESULT_FAILURE;
                $DB->set_field('amos_contributions', 'status', local_amos_contribution::STATE_ACCEPTED,
                    array('id' => $contribution->id));

            } else {
                $maintainers = $this->get_maintainers($contribution->lang);
                if (empty($maintainers)) {
                    $msg = sprintf('  Pending contribution #%d - no active maintainers to notify!!', $contribution->id);
                } else {
                    $msg = sprintf('  Pending contribution #%d - notifying maintainers %s', $contribution->id,
                        implode(',', array_keys($maintainers)));

                    foreach ($maintainers as $maintainer) {
                        $this->notify($maintainer,
                            get_string_manager()->get_string('contribnotif', 'local_amos', [
                                'id' => $contribution->id,
                            ], $maintainer->lang),
                            get_string_manager()->get_string('contribnotifpending', 'local_amos', [
                                'id' => $contribution->id,
                                'subject' => $contribution->subject,
                                'contriburl' => (new moodle_url('/local/amos/contrib.php', ['id' => $contribution->id]))
                                    ->out(false),
                                'docsurl' => 'https://docs.moodle.org/dev/Maintaining_a_language_pack'
                            ], $maintainer->lang)
                        );
                    }
                }
                $this->output($msg, true);
                $status = self::RESULT_FAILURE;
            }
        }
        $rs->close();

        return $status;
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
     * Sends notification to the user(s)
     *
     * @param stdClass $user to be notified
     * @param string $subject
     * @param string $message
     */
    protected function notify($user, $subject, $message) {
        global $DB;

        if ($this->amosbot === null) {
            $this->amosbot = $DB->get_record('user', array('id' => 2)); // XXX mind the hardcoded value here!
        }

        $data = new \core\message\message();
        $data->component = 'local_amos';
        $data->name = 'checker';
        $data->userfrom = $this->amosbot;
        $data->userto = $user;
        $data->subject = $subject;
        $data->fullmessage = $message;
        $data->fullmessageformat = FORMAT_PLAIN;
        $data->fullmessagehtml = '';
        $data->smallmessage = '';
        $data->notification = 1;

        message_send($data);
    }

    /**
     * Returns maintainers of the given language pack
     *
     * @param string $langcode
     * @return array
     */
    protected function get_maintainers($langcode) {
        global $DB;

        if ($this->maintainers === null) {
            $this->maintainers = array();
            $records = $DB->get_records('amos_translators', array('status' => AMOS_USER_MAINTAINER));
            foreach ($records as $record) {
                if ($record->lang === 'X') {
                    // We do not want these "universal" maintainers here.
                    continue;
                }
                $this->maintainers[$record->lang][$record->userid] = true;
            }
        }

        if (empty($this->maintainers[$langcode])) {
            return array();
        }

        foreach ($this->maintainers[$langcode] as $userid => $userinfo) {
            if ($userinfo === true) {
                $this->maintainers[$langcode][$userid] = $DB->get_record('user', array('id' => $userid));
            }
        }

        return $this->maintainers[$langcode];
    }

    /**
     * Executes all check_* methods of this class
     *
     * @return int 0 for success, >0 if some checks fail
     */
    public function execute() {

        $this->output('START AMOS checker task '.$this->task);
        $method = 'check_'.$this->task;
        $this->output(' RUNNING ' . $method);
        $status = $this->$method();

        if ($status === self::RESULT_FAILURE) {
            $this->output(' FAILED ' . $method, true);

        } else if ($status === self::RESULT_SUCCESS) {
            $this->output(' OK ' . $method);

        } else {
            $this->output(' ERROR ' . $method . ' returned unexpected value', true);
        }

        $this->output('END AMOS checker task '.$this->task);

        return $status;
    }

}

list($options, $unrecognized) = cli_get_params(array('task' => ''));

if (empty($options['task']) || is_numeric((string)$options['task'])) {
    fputs(STDERR, "Error: Please run with the --task=name parameter to specify the checker task to perform.\n");
    exit(amos_checker::RESULT_PARAMS);
}

if (!empty($unrecognized)) {
    fputs(STDERR, "Error: Unrecognized parameter detected: ".implode($unrecognized)."\n");
    exit(amos_checker::RESULT_PARAMS);
}

$checker = new amos_checker($options['task']);

exit($checker->execute());
