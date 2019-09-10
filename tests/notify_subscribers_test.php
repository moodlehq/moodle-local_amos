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
 * Provides the {@link local_amos_notify_subscribers_testcase} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Unit tests for the AMOS notify_subscribers_tasks.
 *
 * @copyright 2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_notify_subscribers_testcase extends advanced_testcase {

    /**
     * Test the permission check for the update_strings_file external function.
     */
    public function test_notifications() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();

        $component = new mlang_component('langconfig', 'cs', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('thislanguage', 'Čeština'));
        $component->add_string(new mlang_string('thislanguageint', 'Czech'));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Registering the Czech language', array('source' => 'bot'), true);
        $component->clear();

        $component = new mlang_component('langconfig', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('thislanguage', 'German'));
        $component->add_string(new mlang_string('thislanguageint', 'DE'));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Registering the German language', array('source' => 'amos'), true);
        $component->clear();

        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('accounts', 'Kontos'));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Registering the German string form accounts', array('source' => 'amos'), true);
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'cs';
        $record->component = 'langconfig';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'langconfig';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user2->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription, which has not changes.
        $record = new stdClass();
        $record->userid = $user3->id;
        $record->lang = 'cs';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        set_config('timesubnotified', time() - 86400, 'local_amos');

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        $this->assertCount(2, $sink->get_messages());
        $this->assertContains("thislanguage", $sink->get_messages()[0]->body);
        $sink->close();
    }
}
