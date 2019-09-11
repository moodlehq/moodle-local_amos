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
     * Test notifications only print most recent changes.
     */
    public function test_notifications_only_most_recent() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        $user1 = $generator->create_user(array('mailformat' => 2));

        $today = strtotime('12:00:00');
        $alsotoday = strtotime('11:00:00');
        $past = strtotime('-12 day', $today);

        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'IntermediateString',$alsotoday));
        $stage->add($component);
        $stage->commit('Alter pluginname', array('source' => 'amos'));
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        $this->assertCount(1, $sink->get_messages());
        $this->assertContains("OldString", $sink->get_messages()[0]->body);
        $this->assertContains("NewString", $sink->get_messages()[0]->body);
        $this->assertNotContains("IntermediateString", $sink->get_messages()[0]->body);
        $sink->close();
    }

    /**
     * Test that multiple changes can be displayed in the notification.
     */
    public function test_multiple_subscriptions_one_user() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        // Print plain format, since htmlformat creates linebreaks within strings.
        $user1 = $generator->create_user(array('mailformat' => 2));

        $today = strtotime('12:00:00');
        $past = strtotime('-12 day', $today);

        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        $component = new mlang_component('theme_boost', 'cs', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldThemeString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewThemeString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'cs';
        $record->component = 'theme_boost';
        $DB->insert_record('amos_subscription', $record);

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        $this->assertCount(1, $sink->get_messages());
        $this->assertContains("OldThemeString", $sink->get_messages()[0]->body);
        $this->assertContains("NewThemeString", $sink->get_messages()[0]->body);
        $sink->close();
    }

    /**
     * Test that multiple subscriptions to multiple users work.
     */
    public function test_multiple_subscriptions_multiple_users() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        // Print plain format, since htmlformat creates linebreaks within strings.
        $user1 = $generator->create_user(array('mailformat' => 2));
        $user2 = $generator->create_user(array('mailformat' => 2));

        $today = strtotime('12:00:00');
        $past = strtotime('-12 day', $today);

        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        $component = new mlang_component('theme_boost', 'cs', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldThemeString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewThemeString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user2->id;
        $record->lang = 'cs';
        $record->component = 'theme_boost';
        $DB->insert_record('amos_subscription', $record);

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        $this->assertCount(2, $sink->get_messages());
        $sink->close();
    }

    /**
     * Test that old and unsubscribed changes do not cause notification.
     */
    public function test_no_matching_subscription() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        // Print plain format, since htmlformat creates linebreaks within strings.
        $user1 = $generator->create_user(array('mailformat' => 2));
        $user2 = $generator->create_user(array('mailformat' => 2));

        $today = strtotime('12:00:00');
        $past = strtotime('-12 day', $today);
        $past2 = strtotime('-12 day', $today);

        // This change is old.
        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewString', $past2));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // This change is new but not subscribed.
        $component = new mlang_component('theme_boost', 'cs', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldThemeString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewThemeString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'cs';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user2->id;
        $record->lang = 'cs';
        $record->component = 'theme_boost_campus';
        $DB->insert_record('amos_subscription', $record);

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        // There should be no
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Test that old and unsubscribed changes do not cause notification.
     */
    public function test_only_subscriptions_since_lastrun() {
        global $DB;
        $this->resetAfterTest(true);

        $generator = self::getDataGenerator();

        // Print plain format, since htmlformat creates linebreaks within strings.
        $user1 = $generator->create_user(array('mailformat' => 2));

        $today = strtotime('12:00:00');
        $yesterday = strtotime('-1 day', $today);
        $past = strtotime('-12 day', $today);

        // This change is old.
        $component = new mlang_component('admin', 'de', mlang_version::by_branch('MOODLE_36_STABLE'));
        $component->add_string(new mlang_string('pluginname', 'OldPluginString', $past));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'bot'), true);
        $component->unlink_string('pluginname');
        $component->add_string(new mlang_string('pluginname', 'NewPluginString', $yesterday));
        $component->add_string(new mlang_string('mystring', 'NewString', $today));
        $stage->add($component);
        $stage->commit('Add pluginname', array('source' => 'amos'));
        $component->clear();

        // Add a subscription.
        $record = new stdClass();
        $record->userid = $user1->id;
        $record->lang = 'de';
        $record->component = 'admin';
        $DB->insert_record('amos_subscription', $record);

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        // There should be no
        $this->assertCount(1, $sink->get_messages());
        $this->assertContains("NewString", $sink->get_messages()[0]->body);
        $this->assertNotContains("NewPluginString", $sink->get_messages()[0]->body);
        $sink->close();

        $daybeforeyesterday = strtotime('-2 day', $today);
        set_config('timesubnotified', $daybeforeyesterday, 'local_amos');

        $sink = $this->redirectEmails();
        $task = new \local_amos\task\notify_subscribers();
        $task->execute();
        // There should be no
        $this->assertCount(1, $sink->get_messages());
        $this->assertContains("NewString", $sink->get_messages()[0]->body);
        $this->assertContains("NewPluginString", $sink->get_messages()[0]->body);
        $sink->close();
    }
}
