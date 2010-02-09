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
 * Provides classes and functions to handle various operations on Moodle strings.
 *
 * @package   core
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides iteration features for mlang classes
 *
 * This class just forward all iteration related calls to the aggregated iterator
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
 * Represents a single language pack
 */
class mlang_pack {

    /** @var string the branch this pack is for, eg. 'MOODLE_20_STABLE' */
    public $branch;

    /** @var string language code, eg. 'en' or 'cs' */
    public $name;

    /** @var holds references to components */
    protected $components = array();

    /**
     * @param string $name
     */
    public function __construct($branch, $name) {
        $this->branch   = $branch;
        $this->name     = $name;
    }

    public function get_iterator() {
        return new mlang_iterator($this->components);
    }

    public function add_component(mlang_component $component) {
        $this->components[$component->name] = $component;
    }

    /**
     * Loads all .php components from a given location
     *
     * @param string $path full path to the directory containing .php files with string definition
     * @param bool $deep shall the strings defined in components be loaded too
     * @return void
     */
    public function load_from_php($path, $deep=true) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($files as $file) {
            $filename = $file->getFilename();
            $realpath = $file->getRealpath();
            if (!$file->isFile()) {
                continue;
            }
            if (!$file->isReadable()) {
                throw new Exception('Component file ' . $filename . ' not readable');
            }
            if (substr($filename, -4) == '.php') {
                $componentname = mlang_component::name_from_filename($filename);
                $component = new mlang_component($componentname);
                if ($deep) {
                    $component->load_from_php($realpath);
                }
                $this->add_component($component);
            }
        }
    }

}

/**
 * Represents a collection of all strings for a given component
 */
class mlang_component {

    /** @var the name of the component - todo this should be split into type and name */
    public $name;

    /** @var mlang_pack we are part of */
    public $pack;

    /** @var holds instances of mlang_string in an associative array */
    protected $strings = array();

    /**
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     */
    public function __construct(mlang_pack $pack, $name) {
        $this->pack = $pack;
        $this->name = $name;
    }

    /**
     * Given a filename or a path to the file, returns the name of the component
     *
     * @param string $filename
     * @return string
     */
    public static function name_from_filename($filename) {
        $pathparts = pathinfo($filename);
        return $pathparts['filename'];
    }

    public function get_iterator() {
        return new mlang_iterator($this->strings);
    }

    /**
     * TODO: short description.
     *
     * @param string $stringid 
     * @return TODO
     */
    public function has_string($stringid) {
        return isset($this->strings[$stringid]);
    }

    /**
     * Loads string definitions from a PHP file in Moodle standard format
     *
     * @param string $realpath full path to the .php file where $string array is defined
     * @return void
     */
    public function load_from_php($realpath) {
        unset($string);
        if (is_readable($realpath)) {
            include($realpath);
        } else {
            throw new Exception('Strings definition file ' . $realpath . ' not readable');
        }
        if (!empty($string)) {
            foreach ($string as $id => $value) {
                $stringobject = new mlang_string($id, $value);
                // some more processing here?
                $this->strings[$id] = $stringobject;
            }
        } else {
            // debugging('No strings defined in ' . $realpath, DEBUG_DEVELOPER);
        }
    }

    /**
     * TODO: short description.
     *
     * @return TODO
     */
    public function load_from_db() {
        global $DB;

        $tmpstrings = array();
        $rs = $DB->get_recordset('amos_strings', array('branch' => $this->pack->branch , 'pack' => $this->pack->name , 'component' => $this->name));
        foreach ($rs as $record) {
            if (isset($tmpstrings[$record->stringid])) {
                $time = $tmpstrings[$record->stringid]->timemodified;
            } else {
                $time = -1;
            }
            if ($record->timemodified > $time) {
                $tmpstrings[$record->stringid] = clone($record);
            }
        }
        $rs->close();
        foreach ($tmpstrings as $tmpstring) {
            $stringobject = new mlang_string($tmpstring->stringid, $tmpstring->text);
            $stringobject->commithash = $tmpstring->commithash;
            $stringobject->timemodified = $tmpstring->timemodified;
            $this->strings[$stringobject->id] = $stringobject;
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

    /** @var mixed */
    public $commithash = null;

    /** @var mixed */
    public $timemodified = null;

    /**
     * @param string $id string identifier
     * @param string $text string text
     */
    public function __construct($id=null, $text='') {
        $this->id = $id;
        $this->text = $text;
    }
}

