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
 * A stash is a snapshot of a stage
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_stash {
    /** @var int identifier of the record in the database table amos_stashes */
    public $id;

    /** @var int id of the user who owns the stash or 0 if the stash is attached to a contribution */
    public $ownerid;

    /** @var int the timestamp of when the stash was created */
    public $timecreated;

    /** @var int|null the timestamp of when the stash was modified */
    public $timemodified;

    /** @var string unique identification of the stage */
    public $hash;

    /** @var string short name or title of the stash */
    public $name;

    /** @var string message describing the stash */
    public $message;

    /** @var amos_stage stored in the stash */
    protected $stage;

    /** @var string serialized $stage */
    protected $stageserialized;

    /**
     * Factory method returning new instance of the stash from the passed stage
     *
     * @param amos_stage $stage the stage to be stashed
     * @param int $ownerid the user id of the stash owner or 0 for a stash without owner
     * @param string $name optional stash name
     * @return amos_stash instance
     */
    public static function instance_from_stage(amos_stage $stage, $ownerid = 0, $name = '') {
        $instance = new amos_stash($ownerid);
        $instance->name = $name;
        $instance->set_stage($stage);
        return $instance;
    }

    /**
     * Factory method returning stash instance previously pushed into the stash pool
     *
     * @param int $id stash id
     * @return amos_stash instance
     */
    public static function instance_from_id($id) {
        global $DB;

        if (empty($id)) {
            throw new \coding_exception('Invalid stash identifier');
        }

        $record = $DB->get_record('amos_stashes', ['id' => $id], '*', MUST_EXIST);

        $instance = new amos_stash($record->ownerid);
        $instance->id           = $record->id;
        $instance->hash         = $record->hash;
        $instance->timecreated  = $record->timecreated;
        $instance->timemodified = $record->timemodified;
        $instance->name         = $record->name;
        $instance->message      = $record->message;

        $instance->load_from_file();

        return $instance;
    }

    /**
     * Updates the AUTOSAVE stash of the given persistent stage
     *
     * For every user, AMOS keeps a single instance of amos_stash to store the most recent
     * stage state. This behaves as a backup of the staged strings before they are committed.
     *
     * @param amos_persistent_stage $stage
     */
    public static function autosave(amos_persistent_stage $stage) {
        global $DB;

        $instance = new amos_stash($stage->userid);
        $instance->name = 'AUTOSAVE';
        $instance->set_stage($stage);
        $instance->hash = 'xxxxautosaveuser' . $stage->userid;

        if ($id = $DB->get_field('amos_stashes', 'id', ['hash' => $instance->hash, 'ownerid' => $stage->userid])) {
            $instance->id = $id;
        }
        $instance->push();
    }

    /**
     * Stores the stash into the stash pool
     *
     * Dumps the serialized stash contents into a file in moodledata and creates new record
     * in database table amos_stashes. Sets $this->hash and $this->id. In a special case of
     * autosave stashes where their id is known in advance, this methods updates the stashed
     * information.
     */
    public function push() {
        global $DB;

        if (is_null($this->hash)) {
            $this->set_hash();
        }
        $this->save_into_file();

        [$strings, $languages, $components] = amos_stage::analyze($this->stage);

        if (is_null($this->id)) {
            $record                 = new \stdclass();
            $record->ownerid        = $this->ownerid;
            $record->hash           = $this->hash;
            $record->languages      = $languages;
            $record->components     = $components;
            $record->strings        = $strings;
            $record->timecreated    = $this->timecreated;
            $record->timemodified   = null;
            $record->name           = $this->name;
            $record->message        = $this->message;

            $this->id = $DB->insert_record('amos_stashes', $record);
        } else {
            $record                 = new \stdclass();
            $record->id             = $this->id;
            $record->languages      = $languages;
            $record->components     = $components;
            $record->strings        = $strings;
            $record->timemodified   = time();

            $DB->update_record('amos_stashes', $record);
        }
    }

    /**
     * Apply the stashed translation into the given stage
     *
     * If the stage already contains a stashed string, it is overwritten.
     *
     * @param amos_stage $stage stage to put the stashed translation into
     */
    public function apply(amos_stage $stage) {

        foreach ($this->stage as $component) {
            $stage->add($component, true);
        }
    }

    /**
     * Removes the stash from the stash pool
     */
    public function drop() {
        global $DB;

        if (!$DB->count_records('amos_stashes', ['id' => $this->id, 'hash' => $this->hash, 'ownerid' => $this->ownerid])) {
            throw new \coding_exception('Attempt to delete non-existing stash (record)');
        }
        if (!is_writable($this->get_storage_filename())) {
            throw new \coding_exception('Attempt to delete non-existing stash (file)');
        }

        $DB->delete_records('amos_stashes', ['id' => $this->id, 'hash' => $this->hash, 'ownerid' => $this->ownerid]);
        // If the stash file is not referenced any more, delete it.
        if (!$DB->record_exists('amos_stashes', ['hash' => $this->hash])) {
            $filename = $this->get_storage_filename();
            unlink($filename);
            @rmdir(dirname($filename));
            @rmdir(dirname(dirname($filename)));
        }
    }

    /**
     * Reads the stashed strings from the file, sets $this->stage
     *
     * This should be called exclusively by {@see self::instance_from_id()}
     *
     * @see self::save_into_file()
     */
    public function load_from_file() {

        if (!is_null($this->stageserialized) || !is_null($this->stage)) {
            throw new \coding_exception('Already loaded stash can not be overwritten');
        }

        $filename = $this->get_storage_filename();

        if (!is_readable($filename)) {
            throw new \coding_exception('Unable to read stash storage ' . $filename);
        }

        $this->stage = unserialize(file_get_contents($filename));
    }

    /**
     * Exports the stash into a zip file and sends it to browser
     *
     * @param string $filename filename to send
     */
    public function send_zip($filename) {
        $this->stage->send_zip($filename);
    }

    /**
     * Public construction not allowed, use a factory method.
     *
     * @param int $ownerid
     */
    protected function __construct($ownerid) {

        $this->ownerid = $ownerid;
        $this->timecreated = time();
    }

    /**
     * Sets the stashed stage
     *
     * @param amos_stage $stage
     */
    protected function set_stage(amos_stage $stage) {
        $this->stage = $stage;
        $this->stageserialized = serialize($stage);
    }


    /**
     * Calculates unique identifier for this stash
     *
     * Stash identifier is a unique string that can be used as the safe filename
     * of the stash storage.
     */
    protected function set_hash() {
        if (is_null($this->ownerid) || is_null($this->stageserialized) || is_null($this->timecreated)) {
            throw new \coding_exception('Unable to calculate stash identifier');
        }
        $this->hash = md5($this->ownerid . '@' . $this->timecreated . '#' . $this->stageserialized . '%' . rand());
    }

    /**
     * Dumps the stash contents into a file in moodledata/amos/stashes/
     *
     * @see self::load_from_file()
     */
    protected function save_into_file() {

        if (empty($this->stageserialized)) {
            throw new \coding_exception('Stage not prepared before saving the file');
        }

        $filename = $this->get_storage_filename();

        if (!is_dir(dirname($filename))) {
            mkdir(dirname($filename), 0755, true);
        }

        file_put_contents($filename, $this->stageserialized);
    }

    /**
     * Returns the fullpath of the file where the stash should be saved into
     *
     * @return string full path to the filename
     */
    protected function get_storage_filename() {
        global $CFG;

        if (empty($this->hash) || strlen($this->hash) < 4) {
            throw new \coding_exception('Invalid stash hash');
        }

        $subdir1 = substr($this->hash, 0, 2);
        $subdir2 = substr($this->hash, 2, 2);

        return $CFG->dataroot . "/amos/stashes/$subdir1/$subdir2/" . $this->hash;
    }
}
