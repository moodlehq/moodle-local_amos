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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for {@see \local_amos_importfile_stage_auto()}.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2026 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

use local_amos\local\amos_component;
use local_amos\local\amos_stage;
use local_amos\local\amos_string;
use local_amos\local\amos_version;
use local_amos_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Tests for the auto-version import staging function.
 */
final class importfile_auto_test extends local_amos_testcase {
    /**
     * Set up English strings used across all test methods.
     *
     * Configures three known branches (39, 310, 400) and commits two English
     * strings for component 'foo_test':
     *   - 'alpha' introduced at branch 39
     *   - 'beta'  introduced at branch 400
     */
    protected function set_up_english_strings(): void {
        set_config('branchesall', '39,310,400', 'local_amos');

        $stage = new amos_stage();

        $component = new amos_component('foo_test', 'en', amos_version::by_code(39));
        $component->add_string(new amos_string('alpha', 'Alpha'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add alpha at 3.9', ['source' => 'unittest']);

        $component = new amos_component('foo_test', 'en', amos_version::by_code(400));
        $component->add_string(new amos_string('beta', 'Beta'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add beta at 4.0', ['source' => 'unittest']);
    }

    /**
     * Each string in the import file is staged at the version where its English
     * source was most recently introduced.
     */
    public function test_strings_staged_at_correct_individual_versions(): void {
        $this->resetAfterTest();
        $this->set_up_english_strings();

        // Simulate a parsed import file containing translations for both strings.
        $parsed = new amos_component('foo_test', 'cs', amos_version::latest_version());
        $parsed->add_string(new amos_string('alpha', 'Alfa'));
        $parsed->add_string(new amos_string('beta', 'Béta'));

        $stage = new amos_stage();
        local_amos_importfile_stage_auto($stage, $parsed);
        $parsed->clear();

        // 'alpha' was introduced at branch 39; its translation must be there.
        $component39 = $stage->get_component('foo_test', 'cs', amos_version::by_code(39));
        $this->assertNotNull($component39, 'Expected component at version 39');
        $this->assertSame('Alfa', $component39->get_string('alpha')->text);
        $this->assertNull($component39->get_string('beta'), 'beta must not be at version 39');

        // 'beta' was introduced at branch 400; its translation must be there.
        $component400 = $stage->get_component('foo_test', 'cs', amos_version::by_code(400));
        $this->assertNotNull($component400, 'Expected component at version 400');
        $this->assertSame('Béta', $component400->get_string('beta')->text);
        $this->assertNull($component400->get_string('alpha'), 'alpha must not be at version 400');
    }

    /**
     * A translated string that has no corresponding English string is silently
     * skipped and must not appear in the stage at any version.
     */
    public function test_string_absent_from_english_is_skipped(): void {
        $this->resetAfterTest();
        $this->set_up_english_strings();

        $parsed = new amos_component('foo_test', 'cs', amos_version::latest_version());
        $parsed->add_string(new amos_string('alpha', 'Alfa'));
        $parsed->add_string(new amos_string('nonexistent', 'Neexistující'));

        $stage = new amos_stage();
        local_amos_importfile_stage_auto($stage, $parsed);
        $parsed->clear();

        // 'alpha' is staged normally.
        $component39 = $stage->get_component('foo_test', 'cs', amos_version::by_code(39));
        $this->assertNotNull($component39);
        $this->assertNotNull($component39->get_string('alpha'));

        // 'nonexistent' must not be in the stage at any version.
        foreach (['39', '310', '400'] as $code) {
            $comp = $stage->get_component('foo_test', 'cs', amos_version::by_code((int) $code));
            if ($comp !== null) {
                $this->assertNull(
                    $comp->get_string('nonexistent'),
                    "nonexistent must not be staged at version {$code}"
                );
            }
        }
    }

    /**
     * A string that has been deleted in the current English must not be staged.
     */
    public function test_string_deleted_in_english_is_skipped(): void {
        $this->resetAfterTest();
        set_config('branchesall', '39,310,400', 'local_amos');

        $stage = new amos_stage();

        // Add 'gamma' at branch 39.
        $component = new amos_component('foo_test', 'en', amos_version::by_code(39));
        $component->add_string(new amos_string('gamma', 'Gamma'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add gamma', ['source' => 'unittest']);

        // Delete 'gamma' at branch 400 (strtext = null means deleted).
        $component = new amos_component('foo_test', 'en', amos_version::by_code(400));
        $component->add_string(new amos_string('gamma', null, null, true));
        $stage->add($component);
        $component->clear();
        $stage->commit('Delete gamma', ['source' => 'unittest']);

        // Now try to import a translation for 'gamma'.
        $parsed = new amos_component('foo_test', 'cs', amos_version::latest_version());
        $parsed->add_string(new amos_string('gamma', 'Gama'));

        $stage = new amos_stage();
        local_amos_importfile_stage_auto($stage, $parsed);
        $parsed->clear();

        // Nothing should be staged because 'gamma' is currently deleted in English.
        $this->assertFalse(
            $stage->has_component(),
            'Stage must be empty when the English string is deleted'
        );
    }

    /**
     * When an English string is updated in a newer version, the imported
     * translation must be staged at that newer version.
     */
    public function test_updated_english_string_staged_at_new_version(): void {
        $this->resetAfterTest();
        set_config('branchesall', '39,310,400', 'local_amos');

        $stage = new amos_stage();

        // 'delta' introduced at branch 39 with original text.
        $component = new amos_component('foo_test', 'en', amos_version::by_code(39));
        $component->add_string(new amos_string('delta', 'Delta'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add delta', ['source' => 'unittest']);

        // 'delta' updated at branch 400 with new text.
        $component = new amos_component('foo_test', 'en', amos_version::by_code(400));
        $component->add_string(new amos_string('delta', 'Delta (revised)'));
        $stage->add($component);
        $component->clear();
        $stage->commit('Revise delta', ['source' => 'unittest']);

        $parsed = new amos_component('foo_test', 'cs', amos_version::latest_version());
        $parsed->add_string(new amos_string('delta', 'Délta'));

        $stage = new amos_stage();
        local_amos_importfile_stage_auto($stage, $parsed);
        $parsed->clear();

        // The translation must be at version 400 (the most recent English version).
        $component400 = $stage->get_component('foo_test', 'cs', amos_version::by_code(400));
        $this->assertNotNull($component400, 'Expected component at version 400');
        $this->assertSame('Délta', $component400->get_string('delta')->text);

        // Must NOT be staged at the older version 39.
        $component39 = $stage->get_component('foo_test', 'cs', amos_version::by_code(39));
        $this->assertNull($component39, 'Translation must not be at version 39');
    }
}
