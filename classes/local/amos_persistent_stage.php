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
 * Storageable staging area
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_persistent_stage extends amos_stage {
    /** @var the owner of the stage */
    public $userid;

    /** @var string name of the stage */
    public $stageid;

    /**
     * Factory method returning an instance of the stage for the given user
     *
     * For now, we use sesskey() as the only supported stage name. In the future, users will
     * have control over their stages, giving them names etc.
     *
     * @param int $userid the owner of the stage
     * @param string $stageid stage name
     * @return amos_persistent_stage instance
     */
    public static function instance_for_user($userid, $stageid) {
        $stage = new amos_persistent_stage($userid, $stageid);
        $stage->restore();
        return $stage;
    }

    /**
     * Persistant stage constructor
     *
     * @param int $userid the owner of the stage
     * @param string $stageid stage name
     */
    protected function __construct($userid, $stageid) {
        if (empty($userid) || empty($stageid)) {
            throw new \coding_exception('Persistance stage identification failed');
        }
        $this->userid = $userid;
        $this->stageid = $stageid;
    }

    /**
     * Make sure the storage is ready for {@see store()} call
     *
     * @return string|false full path to file to use or false if fail
     */
    protected function get_storage() {
        if (!$dir = make_upload_directory('amos/stages/' . $this->userid, false)) {
            return false;
        }
        return $dir . '/' . $this->stageid;
    }

    /**
     * Save the staged strings into the storage
     *
     * @return boolean success status
     */
    public function store() {
        if (!$storage = $this->get_storage()) {
            return false;
        }
        $data = serialize($this->components);
        file_put_contents($storage, $data);
    }

    /**
     * Load the staged strings from the storage
     */
    public function restore() {
        global $CFG;

        $storage = $this->get_storage();
        if (is_readable($storage)) {
            $data = file_get_contents($storage);
            $this->components = unserialize($data);
        }
    }
}
