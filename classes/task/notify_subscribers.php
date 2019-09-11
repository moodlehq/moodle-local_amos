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
 * Provides the {@link \local_amos\task\notify_subscribers} class.
 *
 * @package     local_amos
 * @category    task
 * @copyright   2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Sends notifications about changes of the language packs to the respective subsrcibers.
 *
 * @copyright 2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_subscribers extends \core\task\scheduled_task {

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasknotifysubscribers', 'local_amos');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB, $PAGE;
        $subject = get_string('subscription_mail_subject', 'local_amos');
        $lasttimerun = get_config('local_amos', 'timesubnotified');
        // For the very first run, we do not want to send out everything that ever happend.
        // So we initialize the config with the date from yesterday.
        if (!$lasttimerun) {
            $today              = strtotime('12:00:00');
            $lasttimerun          = strtotime('-1 day', $today);
        }
        $getsql     = "SELECT distinct s.userid
                         FROM {amos_commits} c
                         JOIN {amos_repository} r ON (c.id = r.commitid)
                         JOIN {amos_texts} t ON (r.textid = t.id)
                         JOIN {amos_subscription} s ON (s.lang = r.lang AND s.component = r.component)
                        WHERE timecommitted > $lasttimerun";

        $users = $DB->get_records_sql($getsql);
        $output = $PAGE->get_renderer('local_amos');
        foreach ($users as $user) {
            $user = \core_user::get_user($user->userid);
            if ($user) {
                $notification_html = new \local_amos_sub_notification($user, true);
                $notification = new \local_amos_sub_notification($user);

                $content_html = $output->render($notification_html);
                $content = $output->render($notification);
                email_to_user($user, \core_user::get_noreply_user(), $subject, $content, $content_html);
            }
        }
        set_config('timesubnotified', time(), 'local_amos');
    }

}
