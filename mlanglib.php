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
 * Moodle language manipulation library
 *
 * Provides classes and functions to handle various low-level operations on Moodle
 * strings in various formats.
 *
 * @package   moodlecore
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides iteration features for mlang classes
 *
 * This class just forwards all iteration related calls to the aggregated iterator
 */
class mlang_iterator implements iterator {

    /** @var iterator instance */
    protected $iterator;

    public function __construct(array $items) {
        $this->iterator = new ArrayIterator($items);
    }

    public function current(){
        return $this->iterator->current();
    }

    public function key(){
        return $this->iterator->key();
    }

    public function next(){
        return $this->iterator->next();
    }

    public function rewind(){
        return $this->iterator->rewind();
    }

    public function valid(){
        return $this->iterator->valid();
    }
}

/**
 * Represents a collection of strings for a given component
 */
class mlang_component {

    /** @var the name of the component, what {@link string_manager::get_string()} uses as the second param */
    public $name;

    /** @var string language code we are part of */
    public $lang;

    /** @var mlang_version we are part of */
    public $version;

    /** @var holds instances of mlang_string in an associative array */
    protected $strings = array();

    /**
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param mlang_version $version
     */
    public function __construct($name, $lang, mlang_version $version) {
        $this->name     = $name;
        $this->lang     = $lang;
        $this->version  = $version;
    }

    /**
     * Given a path to a file, returns the name of the component
     *
     * @param mixed $filepath
     * @return string
     */
    public static function name_from_filename($filepath) {
        $pathparts = pathinfo($filepath);
        return $pathparts['filename'];
    }

    /**
     * Factory method returning an instance with strings loaded from a file in Moodle PHP format
     *
     * @param string $filepath full path to the file to load
     * @param string $lang lang pack we are part of
     * @param mlang_version $version
     * @param string $name use this as a component name instead of guessing from the file name
     * @param int $timemodified use this as a timestamp of string modification instead of the filemtime()
     * @throw Exception
     * @return mlang_component
     */
    public static function from_phpfile($filepath, $lang, mlang_version $version, $timemodified=null, $name=null) {
        if (empty($name)) {
            $name = self::name_from_filename($filepath);
        }
        $component = new mlang_component($name, $lang, $version);
        unset($string);
        if (is_readable($filepath)) {
            if (empty($timemodified)) {
                $timemodified = filemtime($filepath);
            }
            include($filepath);
        } else {
            throw new Exception('Strings definition file ' . $filepath . ' not readable');
        }
        if (!empty($string) && is_array($string)) {
            foreach ($string as $id => $value) {
                $component->add_string(new mlang_string($id, $value, $timemodified), true);
            }
        } else {
            debugging('No strings defined in ' . $filepath, DEBUG_DEVELOPER);
        }
        return $component;
    }

    /**
     * Get a snapshot of all strings in the given component
     *
     * @param string $name
     * @param string $lang
     * @param mlang_version $version
     * @param int $timestamp time of the snapshot, empty for the most recent
     * @param bool $deleted shall deleted strings be included?
     * @return mdl_component component with the strings from the snapshot
     */
    public static function from_snapshot($name, $lang, mlang_version $version, $timestamp=null, $deleted=false) {
        global $DB;

        $params = array('branch' => $version->code, 'lang' => $lang, 'component' => $name);
        $sql = "SELECT r.stringid, r.text, r.timemodified, r.deleted
                  FROM {amos_repository} r
                  JOIN (SELECT branch, lang, component, stringid,MAX(timemodified) AS timemodified
                          FROM {amos_repository} ";
        if (!empty($timestamp)) {
            $sql .= "    WHERE timemodified <= :timemodified";
            $params = array_merge($params, array('timemodified' => $timestamp));
        }
        $sql .="         GROUP BY branch,lang,component,stringid) j
                    ON (r.branch = j.branch
                       AND r.lang = j.lang
                       AND r.component = j.component
                       AND r.stringid = j.stringid
                       AND r.timemodified = j.timemodified)
                 WHERE r.branch=:branch
                       AND r.lang=:lang
                       AND r.component=:component";
        if (empty($deleted)) {
            $sql .= "  AND deleted = 0";
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        $component = new mlang_component($name, $lang, $version);
        foreach ($rs as $r) {
            $component->add_string(new mlang_string($r->stringid, $r->text, $r->timemodified, $r->deleted));
        }
        $rs->close();

        return $component;
    }

    /**
     * Returns the iterator over strings
     */
    public function get_iterator() {
        return new mlang_iterator($this->strings);
    }

    /**
     * Returns the string object or null if not known
     *
     * @param string $id string identifier
     * @return mlang_string|null
     */
    public function get_string($id) {
        if (!isset($this->strings[$id])) {
            return null;
        }
        return $this->strings[$id];
    }

    /**
     * Checks if the component contains some strings or a given string
     *
     * @param string $id If null, checks if any string is defined. Otherwise checks for a given string
     * @return bool
     */
    public function has_string($id=null) {
        if (is_null($id)) {
            return (!empty($this->strings));
        } else {
            return (isset($this->strings[$id]));
        }
    }

    /**
     * Adds new string into the collection
     *
     * @param mlang_string $string to add
     * @param bool $force if true, existing string will be replaced
     * @throw coding_exception when trying to add existing component without $force
     * @return void
     */
    public function add_string(mlang_string $string, $force=false) {
        if (!$force && isset($this->strings[$string->id])) {
            throw new coding_exception('You are trying to add a string that already exists. If this is intentional, use the force parameter');
        }
        $this->strings[$string->id] = $string;
        $string->component = $this;
    }

    /**
     * Removes a string from the component container
     *
     * @param string $id string identifier
     */
    public function unlink_string($id) {
        if (!is_string($id)) {
            throw new coding_exception('Illegal param type');
        }
        if (isset($this->strings[$id])) {
            $this->strings[$id]->component = null;
            unset($this->strings[$id]);
        }
    }

    /**
     * Unlinks all strings
     */
    public function clear() {
        foreach ($this->strings as $string) {
            $this->unlink_string($string->id);
        }
    }
}

/**
 * Represents a single string
 */
class mlang_string {

    /** @var string identifier */
    public $id = null;

    /** @var string */
    public $text = '';

    /** @var int the time stamp when this string was saved */
    public $timemodified = null;

    /** @var bool is deleted */
    public $deleted = false;

    /** @var mlang_component we are part of */
    public $component;

    /**
     * @param string $id string identifier
     * @param string $text string text
     */
    public function __construct($id, $text='', $timemodified=null, $deleted=0) {
        if (is_null($timemodified)) {
            $timemodified = time();
        }
        $this->id           = $id;
        $this->text         = $text;
        $this->timemodified = $timemodified;
        $this->deleted      = $deleted;
    }

    /**
     * Returns true if the two given strings should be considered as different, false otherwise.
     *
     * @param mlang_string $a
     * @param mlang_string $b
     * @return bool
     */
    public static function differ(mlang_string $a, mlang_string $b) {
        if (trim($a->text) === trim($b->text)) {
            return false;
        }
        return true;
    }
}

/**
 * Staging area is a collection of components to be committed into the strings repository
 *
 * After obtaining a new instance and adding some components into it, you should either call commit()
 * or clear(). Otherwise, the copies of staged strings remain in PHP memory and they are not
 * garbage collected before of the bi-directional reference component-string.
 */
class mlang_stage {

    /** @var array of mlang_component */
    protected $components = array();

    /**
     * Adds a component into the staging area
     *
     * @param mlang_component $component
     * @param bool $force replace the previously staged string if there is such if there is already
     * @return void
     */
    public function add(mlang_component $component, $force=false) {
        if (!isset($this->components[$component->name])) {
            $this->components[$component->name] = new mlang_component($component->name, $component->lang, $component->version);
        }
        foreach ($component->get_iterator() as $string) {
            $this->components[$component->name]->add_string(clone($string), $force);
        }
    }

    /**
     * Removes staged components
     */
    public function clear() {
        foreach ($this->components as $component) {
            $component->clear();
        }
        $this->components = array();
    }

    /**
     * Check the staged strings against the repository cap and keep modified strings only
     *
     * @param bool $deletemissing if true, then all strings that are in repository but not in stage will be marked as to be deleted
     * @param int $deletetimestamp if $deletemissing is tru, what timestamp to use when removing strings (defaults to current)
     */
    public function rebase($deletemissing=false, $deletetimestamp=null) {
        foreach ($this->components as $cx => $component) {
            $cap = mlang_component::from_snapshot($component->name, $component->lang, $component->version);
            if ($deletemissing) {
                if (empty($deletetimestamp)) {
                    $deletetimestamp = time();
                }
                foreach ($cap->get_iterator() as $existing) {
                    $stagedstring = $component->get_string($existing->id);
                    if (is_null($stagedstring)) {
                        $tobedeleted = clone($existing);
                        $tobedeleted->deleted = true;
                        $tobedeleted->timemodified = $deletetimestamp;
                        $component->add_string($tobedeleted);
                    }
                }
            }
            foreach ($component->get_iterator() as $stagedstring) {
                $capstring = $cap->get_string($stagedstring->id);
                if (is_null($capstring)) {
                    // the staged string does not exist in the repository yet - will be committed
                    continue;
                }
                if (!empty($stagedstring->deleted)) {
                    // the staged string object is the removal record - will be committed
                    continue;
                }
                if (!mlang_string::differ($stagedstring, $capstring)) {
                    // the staged string is the same as the most recent one in the repository
                    $component->unlink_string($stagedstring->id);
                    continue;
                }
                if ($stagedstring->timemodified <= $capstring->timemodified) {
                    // the staged string is older than the cap, do not commit keep it
                    $component->unlink_string($stagedstring->id);
                    continue;
                }
            }
            // unstage the whole component if it is empty
            if (!$component->has_string()) {
                unset($this->components[$cx]);
            }
            $cap->clear();
        }
    }

    /**
     * Commit the strings in the staging area, by default rebasing first
     *
     * @param string $message commit message
     * @param array $meta optional meta information
     * @param bool $skiprebase if true, do not rebase before committing
     */
    public function commit($message='', array $meta=null, $skiprebase=false) {
        if (empty($skiprebase)) {
            $this->rebase();
        }
        foreach ($this->components as $cx => $component) {
            foreach ($component->get_iterator() as $string) {
                $record = new stdclass();
                if (!empty($meta)) {
                    foreach ($meta as $field => $value) {
                        $record->{$field} = $value;
                    }
                }
                $record->branch     = $component->version->code;
                $record->lang       = $component->lang;
                $record->component  = $component->name;
                $record->stringid   = $string->id;
                $record->text       = $string->text;
                $record->timemodified = $string->timemodified;
                $record->deleted    = $string->deleted;
                $record->commitmsg  = $message;

                $this->commit_low_add($record);
            }
        }
        $this->clear();
    }

    /**
     * Low level commit
     *
     * @param stdclass $record repository record to be inserted into strings database
     * @return new record identifier
     */
    protected function commit_low_add(stdclass $record) {
        global $DB;
        return $DB->insert_record('amos_repository', $record);
    }
}

/**
 * Provides information about Moodle versions and corresponding branches
 *
 * Do not modify the returned instances, they are not cloned during coponent copying.
 */
class mlang_version {
    /** @var int internal code of the version */
    public $code;

    /** @var string  human-readable label of the version */
    public $label;

    /** @var string the name of the corresponding CVS/git branch */
    public $branch;

    /** @var bool allow translations of strings on this branch? */
    public $translatable;

    /** @var bool is this a version that translators should focus on? */
    public $current;

    /**
     * Factory method
     *
     * @param int $code
     * @return mlang_version|null
     */
    public static function by_code($code) {
        foreach (self::versions_info() as $ver) {
            if ($ver['code'] == $code) {
                return new mlang_version($ver);
            }
        }
        return null;
    }

    /**
     * Factory method
     *
     * @param string $branch like 'MOODLE_20_STABLE'
     * @return mlang_version|null
     */
    public static function by_branch($branch) {
        foreach (self::versions_info() as $ver) {
            if ($ver['branch'] == $branch) {
                return new mlang_version($ver);
            }
        }
        return null;
    }

    /**
     * Used by factory methods to create instances of this class
     */
    protected function __construct(array $info) {
        foreach ($info as $property => $value) {
            $this->{$property} = $value;
        }
    }

    /**
     * @return array
     */
    protected static function versions_info() {
        return array(
            array(
                'code'          => 21,
                'label'         => '2.1',
                'branch'        => 'MOODLE_21_STABLE',
                'translatable'  => false,
                'current'       => false,
            ),
            array(
                'code'          => 20,
                'label'         => '2.0',
                'branch'        => 'MOODLE_20_STABLE',
                'translatable'  => true,
                'current'       => true,
            ),
            array(
                'code'          => 19,
                'label'         => '1.9',
                'branch'        => 'MOODLE_19_STABLE',
                'translatable'  => true,
                'current'       => true,
            ),
            array(
                'code'          => 18,
                'label'         => '1.8',
                'branch'        => 'MOODLE_18_STABLE',
                'translatable'  => true,
                'current'       => false,
            ),
            array(
                'code'          => 17,
                'label'         => '1.7',
                'branch'        => 'MOODLE_17_STABLE',
                'translatable'  => true,
                'current'       => false,
            ),
            array(
                'code'          => 16,
                'label'         => '1.6',
                'branch'        => 'MOODLE_16_STABLE',
                'translatable'  => true,
                'current'       => false,
            ),
        );
    }

}
