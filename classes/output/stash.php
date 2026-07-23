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
 * Provides class {@see \local_amos\output\stash}.
 *
 * @package     local_amos
 * @category    output
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\output;

use local_amos\local\amos_stage;
use local_amos\local\amos_stash;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Renderable stash
 */
class stash implements \renderable {
    /** @var int identifier in the table of stashes */
    public $id;
    /** @var string title of the stash */
    public $name;
    /** @var int timestamp of when the stash was created */
    public $timecreated;
    /** @var int timestamp of when the stash was modified */
    public $timemodified;
    /** @var \stdClass the owner of the stash */
    public $owner;
    /** @var array of language names */
    public $languages = [];
    /** @var array of component names */
    public $components = [];
    /** @var int number of stashed strings */
    public $strings = 0;
    /** @var bool is autosave stash */
    public $isautosave;

    /** @var array of stdClasses representing stash actions */
    protected $actions = [];

    /**
     * Factory method using an instance if {@see amos_stash} as a data source
     *
     * @param amos_stash $stash
     * @param \stdClass $owner owner user data
     * @return stash new instance
     */
    public static function instance_from_amos_stash(amos_stash $stash, \stdClass $owner) {

        if ($stash->ownerid != $owner->id) {
            throw new \coding_exception('Stash owner mismatch');
        }

        $new                = new self();
        $new->id            = $stash->id;
        $new->name          = $stash->name;
        $new->timecreated   = $stash->timecreated;
        $new->timemodified  = $stash->timemodified;

        $stage = new amos_stage();
        $stash->apply($stage);
        [$new->strings, $new->languages, $new->components] = amos_stage::analyze($stage);
        $stage->clear();
        unset($stage);

        $new->components    = explode('/', trim($new->components, '/'));
        $new->languages     = explode('/', trim($new->languages, '/'));

        $new->owner         = $owner;

        if ($stash->hash === 'xxxxautosaveuser' . $new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Factory method using plain database record from amos_stashes table as a source
     *
     * @param \stdClass $record stash record from amos_stashes table
     * @param \stdClass $owner owner user data
     * @return stash new instance
     */
    public static function instance_from_record(\stdClass $record, \stdClass $owner) {

        if ($record->ownerid != $owner->id) {
            throw new \coding_exception('Stash owner mismatch');
        }

        $new                = new self();
        $new->id            = $record->id;
        $new->name          = $record->name;
        $new->timecreated   = $record->timecreated;
        $new->timemodified  = $record->timemodified;
        $new->strings       = $record->strings;
        $new->components    = explode('/', trim($record->components, '/'));
        $new->languages     = explode('/', trim($record->languages, '/'));
        $new->owner         = $owner;

        if ($record->hash === 'xxxxautosaveuser' . $new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Constructor is not public, use one of factory methods above
     */
    protected function __construct() {
    }

    /**
     * Register a new action that can be done with the stash
     *
     * @param string $id action identifier
     * @param \moodle_url $url action handler
     * @param string $label action name
     */
    public function add_action($id, \moodle_url $url, $label) {

        $action             = new \stdClass();
        $action->id         = $id;
        $action->url        = $url;
        $action->label      = $label;
        $this->actions[]    = $action;
    }

    /**
     * Get the list of actions attached to this stash
     *
     * @return array of stdClasses with $url and $label properties
     */
    public function get_actions() {
        return $this->actions;
    }
}
