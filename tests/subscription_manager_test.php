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
 * Provides the {@link local_amos_subscription_manager_test} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @copyright   2019 Martin Gauk <gauk@math.tu-berlin.de>
 * @copyright   2019 Jan Eberhardt <eberhardt@tu-berlin.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/local/amos/mlanglib.php');

/**
 * Tests for the {@link local_amos\subscription_manager} class.
 */
class local_amos_subscription_manager_test extends advanced_testcase {

    /**
     * Helper method to quickly register a language on the given branch(-es)
     *
     * @param string $langcode the code of the language, such as 'en'
     * @param int|array $branchcodes the code of the branch or a list of them
     */
    protected function register_language($langcode, $branchcodes) {

        if (!is_array($branchcodes)) {
            $branchcodes = array($branchcodes);
        }

        $stage = new mlang_stage();

        foreach ($branchcodes as $branchcode) {
            $component = new mlang_component('langconfig', $langcode, mlang_version::by_code($branchcode));
            $component->add_string(new mlang_string('thislanguage', $langcode));
            $component->add_string(new mlang_string('thislanguageint', $langcode));
            $stage->add($component);
            $component->clear();
        }

        $stage->commit('Register language '.$langcode, array('source' => 'unittest'));
    }

    /**
     * Test the workflow of fetching, adding and removing subscriptions.
     */
    public function test_subscriptions() {
        $this->resetAfterTest(true);

        $this->register_language('en', mlang_version::MOODLE_36);
        $this->register_language('de', mlang_version::MOODLE_36);
        $this->register_language('fr', mlang_version::MOODLE_36);
        $this->register_language('cz', mlang_version::MOODLE_36);

        // Test subscriptions of one user.
        $manager = new \local_amos\subscription_manager(2);
        $manager->add_subscription('langconfig', 'de');
        $manager->add_subscription('langconfig', 'fr');
        $manager->add_subscription('langconfig', 'cz');
        $manager->add_subscription('langconfig', 'aa'); // Should not be added as it is invalid.
        $manager->apply_changes();

        $subs = $manager->fetch_subscriptions();
        $this->assertArrayHasKey('langconfig', $subs);
        $this->assertTrue(in_array('de', $subs['langconfig']));
        $this->assertTrue(in_array('fr', $subs['langconfig']));
        $this->assertTrue(in_array('cz', $subs['langconfig']));
        $this->assertFalse(in_array('aa', $subs['langconfig']));
        $this->assertEquals(3, count($subs['langconfig']));

        // Try to add one language again.
        $manager->add_subscription('langconfig', 'de');
        $manager->apply_changes();
        $subs = $manager->fetch_subscriptions();
        $this->assertEquals(3, count($subs['langconfig']));

        // Remove one language.
        $manager->remove_subscription('langconfig', 'de');
        $manager->apply_changes();

        $subs = $manager->fetch_subscriptions();
        $this->assertArrayHasKey('langconfig', $subs);
        $this->assertFalse(in_array('de', $subs['langconfig']));
        $this->assertTrue(in_array('fr', $subs['langconfig']));
        $this->assertTrue(in_array('cz', $subs['langconfig']));

        // Remove all other languages for one component.
        $manager->remove_component_subscription('langconfig');
        $manager->apply_changes();

        $subs = $manager->fetch_subscriptions();
        $this->assertEmpty($subs);

        // Add languages again and remove all.
        $manager->add_subscription('langconfig', 'de');
        $manager->add_subscription('langconfig', 'fr');
        $manager->add_subscription('langconfig', 'cz');
        $manager->apply_changes();

        $subs = $manager->fetch_subscriptions();
        $this->assertEquals(3, count($subs['langconfig']));

        $manager->remove_all_subscriptions();
        $subs = $manager->fetch_subscriptions();
        $this->assertEmpty($subs);
    }
}
