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

namespace local_amos\local;

/**
 * Unit tests for the {@see \local_amos\local\amos_stage} class.
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(amos_stage::class)]
final class amos_stage_test extends \local_amos_testcase {
    public function test_explicit_rebasing(): void {
        $this->resetAfterTest();
        // Prepare the "cap" (base for rebasing).
        $stage = new amos_stage();
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Tree'));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing without string removal - the stage is not complete.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertFalse($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing on a higher branch.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase();
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertFalse($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing with string removal - the stage is considered to be the full snapshot of the state.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        // String 'two' is missing and we want it to be removed from repository.
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertTrue($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('two');
        $this->assertTrue($s->deleted);
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);

        // Rebasing with string removal on a higher branch.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_21_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        // String 'two' is missing and we want it to be removed from repository.
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->rebase(null, true);
        $rebased = $stage->get_component('numbers', 'en', amos_version::by_branch('MOODLE_21_STABLE'));
        $this->assertTrue($rebased instanceof amos_component);
        $this->assertFalse($rebased->has_string('one'));
        $this->assertTrue($rebased->has_string('two'));
        $this->assertTrue($rebased->has_string('three'));
        $s = $rebased->get_string('two');
        $this->assertTrue($s->deleted);
        $s = $rebased->get_string('three');
        $this->assertEquals('Three', $s->text);
        $stage->clear();
        $component->clear();
        unset($component);
        unset($stage);
    }

    public function test_rebasing_deletion_already_deleted(): void {
        $this->resetAfterTest();
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('trash', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('delme', 'Del me', time() - 1000, true));
        $stage->add($component);
        $stage->commit('The string was already born deleted... sad');
        $this->assertFalse($stage->has_component());
        unset($stage);

        $stage = new amos_stage();
        $component = new amos_component('trash', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('delme', 'Del me', null, true));
        $stage->add($component);
        $stage->rebase();
        $this->assertFalse($stage->has_component());
    }

    public function test_implicit_rebasing_during_commit(): void {
        global $DB;

        $this->resetAfterTest();

        // Prepare the "cap" (base for rebasing).
        $stage = new amos_stage();
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Tree'));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Commit the fix of the string.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('one', 'One'));
        $component->add_string(new amos_string('two', 'Two'));
        $component->add_string(new amos_string('three', 'Three'));
        $stage->add($component);
        $stage->commit('Fixed typo Tree > Three', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // Get the most recent version (so called "cap") of the component.
        $cap = amos_component::from_snapshot('numbers', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $concat = '';
        foreach ($cap as $s) {
            $concat .= $s->text;
        }
        // Strings in the cap shall be ordered by stringid.
        $this->assertEquals('OneThreeTwo', $concat);
    }

    /**
     * Rebasing should respect commits in whatever order so it is safe to re-run the import scripts
     */
    public function test_non_chronological_commits(): void {
        global $DB;

        $this->resetAfterTest();

        // Firstly commit the most recent version.
        $today = time();
        $stage = new amos_stage();
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Today Foo', $today));
        $component->add_string(new amos_string('bar', 'New Bar', $today));
        $component->add_string(new amos_string('job', 'Boring', $today));
        $stage->add($component);
        $stage->commit('Initial commit', ['source' => 'unittest']);
        $component->clear();
        unset($component);
        unset($stage);

        // We are re-importing the history - let us commit the version that was actually created yesterday.
        $yesterday = time() - DAYSECS;
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Foo', $yesterday));
        $component->add_string(new amos_string('job', 'Boring', $yesterday));
        $stage->add($component);
        $stage->rebase($yesterday, true, $yesterday);
        $rebased = $stage->get_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf(amos_component::class, $rebased);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertTrue($rebased->has_string('foo'));
        $this->assertFalse($rebased->has_string('bar'));
        $this->assertTrue($rebased->has_string('job'));

        // And the same case using rebase() without deleting.
        $stage = new amos_stage();
        $this->assertFalse($stage->has_component());
        $component = new amos_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('foo', 'Foo', $yesterday));
        $component->add_string(new amos_string('job', 'Boring', $yesterday));
        $stage->add($component);
        $stage->rebase($yesterday);
        $rebased = $stage->get_component('things', 'en', amos_version::by_branch('MOODLE_20_STABLE'));
        $this->assertInstanceOf(amos_component::class, $rebased);
        $component->clear();
        unset($component);
        unset($stage);
        $this->assertTrue($rebased->has_string('foo'));
        $this->assertFalse($rebased->has_string('bar'));
        $this->assertTrue($rebased->has_string('job'));
    }
}
