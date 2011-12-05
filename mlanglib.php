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
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Base exception thrown by low level language manipulation operations
 */
class mlang_exception extends moodle_exception {
    /**
     * @param string $hint short description of problem
     * @param string $debuginfo detailed information how to fix problem
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct('err_exception', 'local_amos', '', $hint, $debuginfo);
    }
}

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
     * @param mlang_version $version the branch to put this string on
     * @param int $timemodified use this as a timestamp of string modification instead of the filemtime()
     * @param string $name use this as a component name instead of guessing from the file name
     * @param int $format in what string format the file is written - 1 for 1.x string, 2 for 2.x strings
     * @throw Exception
     * @return mlang_component
     */
    public static function from_phpfile($filepath, $lang, mlang_version $version, $timemodified=null, $name=null, $format=null) {
        if (empty($name)) {
            $name = self::name_from_filename($filepath);
        }
        $component = new mlang_component($name, $lang, $version);
        unset($string);
        if (is_readable($filepath)) {
            if (empty($timemodified)) {
                $timemodified = filemtime($filepath);
            }
            $a = ''; // empty placeholder to prevent PHP notices
            include($filepath);
        } else {
            throw new Exception('Strings definition file ' . $filepath . ' not readable');
        }
        if ($version->code <= mlang_version::MOODLE_19) {
            // we are going to import strings for 1.x branch
            $target = 1;
            if (is_null($format)) {
                $format = 1;
            }
            if ($format !== 1) {
                throw new coding_exception('For Moodle 1.x, only strings in legacy format are supported.');
            }
        } else {
            // we are going to import strings for 2.x branch
            $target = 2;
            if (is_null($format)) {
                $format = 2;
            }
        }

        if (!empty($string) && is_array($string)) {
            foreach ($string as $id => $value) {
                $id = clean_param($id, PARAM_STRINGID);
                if (empty($id)) {
                    continue;
                }
                $value = mlang_string::fix_syntax($value, $target, $format);
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
     * @param bool $fullinfo shall full information about the string (commit messages, source etc) be returned?
     * @return mlang_component component with the strings from the snapshot
     */
    public static function from_snapshot($name, $lang, mlang_version $version, $timestamp=null, $deleted=false,
                                         $fullinfo=false, array $stringids=null) {
        global $DB;

        $params = array(
            'inner_branch' => $version->code, 'inner_lang' => $lang, 'inner_component' => $name,
            'outer_branch' => $version->code, 'outer_lang' => $lang, 'outer_component' => $name,
            );
        if (!empty($stringids)) {
            list($inner_strsql, $inner_strparams) = $DB->get_in_or_equal($stringids, SQL_PARAMS_NAMED, 'innerstringid000000');
            list($outer_strsql, $outer_strparams) = $DB->get_in_or_equal($stringids, SQL_PARAMS_NAMED, 'outerstringid000000');
            $params = array_merge($params, $inner_strparams, $outer_strparams);
        }
        if ($fullinfo) {
            $sql = "SELECT r.id, r.commitid, r.branch, r.lang, r.component, r.stringid, r.text, r.timemodified, r.deleted,
                           c.source, c.timecommitted, c.commitmsg, c.commithash, c.userid, c.userinfo";
        } else {
            $sql = "SELECT r.stringid, r.text, r.timemodified, r.deleted";
        }
        $sql .= " FROM {amos_repository} r
                  JOIN (SELECT branch, lang, component, stringid, MAX(timemodified) AS timemodified
                          FROM {amos_repository}
                         WHERE branch=:inner_branch
                           AND lang=:inner_lang
                           AND component=:inner_component";
        if (!empty($stringids)) {
            $sql .= "      AND stringid $inner_strsql";
        }
        if (!empty($timestamp)) {
            $sql .= "      AND timemodified <= :timemodified";
            $params = array_merge($params, array('timemodified' => $timestamp));
        }
        $sql .="         GROUP BY branch,lang,component,stringid) j
                    ON (r.branch = j.branch
                       AND r.lang = j.lang
                       AND r.component = j.component
                       AND r.stringid = j.stringid
                       AND r.timemodified = j.timemodified)";
        if ($fullinfo) {
            $sql .= "
             LEFT JOIN {amos_commits} c
                    ON (r.commitid = c.id)";
        }
        $sql.= " WHERE r.branch=:outer_branch
                       AND r.lang=:outer_lang
                       AND r.component=:outer_component";
        if (!empty($stringids)) {
            $sql .= "  AND r.stringid $outer_strsql";
        }
        $sql.= " ORDER BY r.stringid, r.id";
        $rs = $DB->get_recordset_sql($sql, $params);
        $component = new mlang_component($name, $lang, $version);
        foreach ($rs as $r) {
            if (empty($deleted) and $r->deleted) {
                // we do not want to include deleted strings - note that this must be checked here and not
                // in SQL above so that the same string can be deleted and re-added again
                $component->unlink_string($r->stringid);    // this is needed because there can be two strings with
                                                            // the same timemodified, one deleted, one not
                continue;
            }
            if ($fullinfo) {
                $extra = new stdclass();
                foreach ($r as $property => $value) {
                    if (!in_array($property, array('stringid', 'text', 'timemodified', 'deleted'))) {
                        $extra->{$property} = $value;
                    }
                }
            } else {
                $extra = null;
            }
            // we force here so in case of two string with the same timemodified, the higher id wins
            $component->add_string(new mlang_string($r->stringid, $r->text, $r->timemodified, $r->deleted, $extra), true);
        }
        $rs->close();

        return $component;
    }

    /**
     * Calculate a identifier of a given component name, language and version
     *
     * Such identifier can be used as a key in component collections (associative arrays).
     *
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param mlang_version $version
     * @return string
     */
    public static function calculate_identifier($name, $lang, mlang_version $version) {
        return md5($name . '#' . $lang . '@' . $version->code);
    }

    /**
     * Returns the current identifier of the component
     *
     * Every component is identified by its branch, lang and name. This method returns md5 hash of
     * a concatenation of these three values.
     *
     * @return string
     */
    public function get_identifier() {
        return self::calculate_identifier($this->name, $this->lang, $this->version);
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
     * Returns array of all string identifiers in the component
     *
     * @return array of string identifiers
     */
    public function get_string_keys() {
        return array_keys($this->strings);
    }

    /**
     * Returns the number of strings in the component
     *
     * Beware - if deleted strings are loaded into this component, they are counted, too.
     *
     * @return int
     */
    public function get_number_of_strings() {
        return count($this->strings);
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
            throw new coding_exception('You are trying to add a string \''.$string->id.'\' that already exists in this component. If this is intentional, use the \'force\' parameter');
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

    /**
     * Exports the string into a file in Moodle PHP format ($string array)
     *
     * @param string $filepath full path of the file to write into
     * @param string $phpdoc optional custom PHPdoc block for the file
     * @return false if unable to open a file
     */
    public function export_phpfile($filepath, $phpdoc = null) {
        if (! $f = fopen($filepath, 'w')) {
            return false;
        }
        fwrite($f, <<<EOF
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


EOF
        );
        if (empty($phpdoc)) {
            $branch = $this->version->branch;
            $lang   = $this->lang;
            $name   = $this->name;
            fwrite($f, <<<EOF
/**
 * Strings for component '$name', language '$lang', branch '$branch'
 *
 * @package   $this->name
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


EOF
            );
        } else {
            fwrite($f, $phpdoc);
        }
        fwrite($f, "defined('MOODLE_INTERNAL') || die();\n\n");
        foreach ($this->get_iterator() as $string) {
            fwrite($f, '$string[\'' . $string->id . '\'] = ');
            fwrite($f, var_export($string->text, true));
            fwrite($f, ";\n");
        }
        fclose($f);
    }

    /**
     * Returns the relative path to the file where the component should be exported to
     *
     * This respects the component language and version. For Moodle 1.6 - 1.9, string files
     * are put into common lang/xx_utf8 directory. Since Moodle 2.0, every plugin type
     * holds its own strings itself. This may either return the path in the source tree
     * (if $treeish param is true) or a path using a directory common for all components.
     *
     * For example, for Workshop module this returns 'lang/xx_utf8/workshop.php' in 1.x,
     * 'mod/workshop/lang/xx/workshop.php' in treeish 2.x and 'lang/xx/workshop.php' in
     * non-treeish (flat) 2.x
     *
     * @param bool $treeish shall the path respect the component location in the source code tree
     * @return string relative path to the file
     */
    public function get_phpfile_location($treeish=true) {
        global $CFG;

        if ($this->version->code <= mlang_version::MOODLE_19) {
            // Moodle 1.x
            return 'lang/' . $this->lang . '_utf8/' . $this->name . '.php';

        } else {
            // Moodle 2.x
            if ($treeish) {
                debugging('The method get_phpfile_location() may produce wrong results as '.
                    'it is unable to differentiate core plugins from activity modules. '.
                    'Using normalize_component() is not reliable much because it depends '.
                    'on the site version and may be wrong for older/newer versions');
                list($type, $plugin) = normalize_component($this->name);
                if ($type === 'core') {
                    return 'lang/' . $this->lang . '/' . $this->name . '.php';
                } else {
                    $abspath = get_plugin_directory($type, $plugin);
                    if (substr($abspath, 0, strlen($CFG->dirroot)) !== $CFG->dirroot) {
                        throw new coding_exception('Plugin directory outside dirroot', $abspath);
                    }
                    $relpath = substr($abspath, strlen($CFG->dirroot) + 1);
                    return $relpath . '/lang/' . $this->lang . '/' . $this->name . '.php';
                }
            } else {
                return 'lang/' . $this->lang . '/' . $this->name . '.php';
            }
        }
    }

    /**
     * Prunes the strings, keeping just those defined in given $mask as well
     *
     * This may be used to get rid of strings that are not defined in another component.
     * Typically can be used to clean the translation from strings that are not defined in
     * the master English pack.
     * Beware - if the string is defined in $mask as deleted, it will be kept in this regardless
     * its state.
     *
     * @param mlang_component $mask master component to compare strings with
     * @return int number of removed strings
     */
    public function intersect(mlang_component $mask) {
        $removed = 0;
        $masked = array_flip($mask->get_string_keys());
        foreach (array_keys($this->strings) as $key) {
            if (! isset($masked[$key])) {
                $this->unlink_string($key);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Returns timemodified stamp of the most recent string in the component
     *
     * @return int timestamp, 0 if the component is empty
     */
    public function get_recent_timemodified() {
        $recent = 0;
        foreach ($this->get_iterator() as $string) {
            if ($string->timemodified > $recent) {
                $recent = $string->timemodified;
            }
        }
        return $recent;
    }

    /**
     * Clean all strings from debris
     *
     * @see mlang_string::clean_text()
     */
    public function clean_texts() {

        if ($this->version->code < mlang_version::MOODLE_20) {
            $format = 1;
        } else {
            $format = 2;
        }

        foreach ($this->get_iterator() as $string) {
            $string->clean_text($format);
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

    /** @var extra information about the string */
    public $extra = null;

    /** @var mlang_component we are part of */
    public $component;

    /**
     * @param string $id string identifier
     * @param string $text string text
     * @param int $timemodified
     * @param bool $deleted
     * @param stdclass $extra
     */
    public function __construct($id, $text='', $timemodified=null, $deleted=0, stdclass $extra=null) {
        if (is_null($timemodified)) {
            $timemodified = time();
        }
        $this->id           = $id;
        $this->text         = $text;
        $this->timemodified = $timemodified;
        $this->deleted      = $deleted;
        $this->extra        = $extra;
    }

    /**
     * Returns true if the two given strings should be considered as different, false otherwise.
     *
     * Deleted strings are considered equal, regardless the actual text
     *
     * @param mlang_string $a
     * @param mlang_string $b
     * @return bool
     */
    public static function differ(mlang_string $a, mlang_string $b) {
        if ($a->deleted and $b->deleted) {
            return false;
        }
        if (is_null($a->text) or is_null($b->text)) {
            if (is_null($a->text) and is_null($b->text)) {
                return false;
            } else {
                return true;
            }
        }
        if (trim($a->text) === trim($b->text)) {
            return false;
        }
        return true;
    }

    /**
     * Clean the string text from debris and make sure it has an expected format
     *
     * @see self::fix_syntax()
     * @param int $format the string syntax revision (1 for Moodle 1.x, 2 for Moodle 2.x)
     */
    public function clean_text($format=2) {
        $this->text = self::fix_syntax($this->text, $format);
    }

    /**
     * Given a string text, returns it being formatted properly for storing in AMOS repository
     *
     * We need to know for what branch the string should be prepared due to internal changes in
     * format required by get_string()
     * - for get_string() in Moodle 1.6 - 1.9 use $format == 1
     * - for get_string() in Moodle 2.0 and higher use $format == 2
     *
     * Typical usages of this methods:
     *  $t = mlang_string::fix_syntax($t);          // sanity new translations of 2.x strings
     *  $t = mlang_string::fix_syntax($t, 1);       // sanity legacy 1.x strings
     *  $t = mlang_string::fix_syntax($t, 2, 1);    // convert format of 1.x strings into 2.x
     *
     * Backward converting 2.x format into 1.x is not supported
     *
     * @param string $text string text to be fixed
     * @param int $format target get_string() format version
     * @param int $from which format version does the text come from, defaults to the same as $format
     * @return string
     */
    public static function fix_syntax($text, $format=2, $from=null) {
        if (is_null($from)) {
            $from = $format;
        }

        // common filter
        $clean = trim($text);
        $search = array(
            // remove \r if it is part of \r\n
            '/\r(?=\n)/',

            // control characters to be replaced with \n
            // LINE TABULATION, FORM FEED, CARRIAGE RETURN, END OF TRANSMISSION BLOCK,
            // END OF MEDIUM, SUBSTITUTE, BREAK PERMITTED HERE, NEXT LINE, START OF STRING,
            // STRING TERMINATOR and Unicode character categorys Zl and Zp
            '/[\x{0B}-\r\x{17}\x{19}\x{1A}\x{82}\x{85}\x{98}\x{9C}\p{Zl}\p{Zp}]/u',

            // control characters to be removed
            // NULL, ENQUIRY, ACKNOWLEDGE, BELL, SHIFT {OUT,IN}, DATA LINK ESCAPE,
            // DEVICE CONTROL {ONE,TWO,THREE,FOUR}, NEGATIVE ACKNOWLEDGE, SYNCHRONOUS IDLE, ESCAPE,
            // DELETE, PADDING CHARACTER, HIGH OCTET PRESET, NO BREAK HERE, INDEX,
            // {START,END} OF SELECTED AREA, CHARACTER TABULATION {SET,WITH JUSTIFICATION},
            // LINE TABULATION SET, PARTIAL LINE {FORWARD,BACKWARD}, REVERSE LINE FEED,
            // SINGLE SHIFT {TWO,THREE}, DEVICE CONTROL STRING, PRIVATE USE {ONE,TWO},
            // SET TRANSMIT STATE, MESSAGE WAITING, {START,END} OF GUARDED AREA,
            // {SINGLE {GRAPHIC,} CHARACTER,CONTROL SEQUENCE} INTRODUCER, OPERATING SYSTEM COMMAND,
            // PRIVACY MESSAGE, APPLICATION PROGRAM COMMAND, ZERO WIDTH {,NO-BREAK} SPACE,
            // REPLACEMENT CHARACTER
            '/[\0\x{05}-\x{07}\x{0E}-\x{16}\x{1B}\x{7F}\x{80}\x{81}\x{83}\x{84}\x{86}-\x{93}\x{95}-\x{97}\x{99}-\x{9B}\x{9D}-\x{9F}\x{200B}\x{FEFF}\x{FFFD}]++/u',
        );
        $replace = array(
            '',
            "\n",
            '',
        );
        $clean = preg_replace($search, $replace, $clean);

        if (($format === 2) && ($from === 2)) {
            // sanity translations of 2.x strings
            $clean = str_replace("\\", '', $clean); // delete all slashes
            $clean = preg_replace("/\n{3,}/", "\n\n\n", $clean); // collapse runs of blank lines

        } elseif (($format === 2) && ($from === 1)) {
            // convert 1.x string into 2.x format
            $clean = preg_replace("/\n{3,}/", "\n\n\n", $clean); // collapse runs of blank lines
            $clean = preg_replace('/%+/', '%', $clean); // collapse % characters
            $clean = str_replace('\$', '@@@___XXX_ESCAPED_DOLLAR__@@@', $clean); // remember for later
            $clean = str_replace("\\", '', $clean); // delete all slashes
            $clean = preg_replace('/(^|[^{])\$a\b(\->[a-zA-Z0-9_]+)?/', '\\1{$a\\2}', $clean); // wrap placeholders
            $clean = str_replace('@@@___XXX_ESCAPED_DOLLAR__@@@', '$', $clean);
            $clean = str_replace('&#36;', '$', $clean);

        } elseif (($format === 1) && ($from === 1)) {
            // sanity legacy 1.x strings
            $clean = preg_replace("/\n{3,}/", "\n\n", $clean); // collapse runs of blank lines
            $clean = str_replace('\$', '@@@___XXX_ESCAPED_DOLLAR__@@@', $clean);
            $clean = str_replace("\\", '', $clean); // delete all slashes
            $clean = str_replace('$', '\$', $clean); // escape all embedded variables
            // unescape placeholders: only $a and $a->something are allowed. All other $variables are left escaped
            $clean = preg_replace('/\\\\\$a\b(\->[a-zA-Z0-9_]+)?/', '$a\\1', $clean); // unescape placeholders
            $clean = str_replace('@@@___XXX_ESCAPED_DOLLAR__@@@', '\$', $clean);
            $clean = str_replace('"', "\\\"", $clean); // add slashes for "
            $clean = preg_replace('/%+/', '%', $clean); // collapse % characters
            $clean = str_replace('%', '%%', $clean); // duplicate %

        } else {
            throw new mlang_exception('Unknown get_string() format version');
        }
        return $clean;
    }
}

/**
 * Staging area is a collection of components to be committed into the strings repository
 *
 * After obtaining a new instance and adding some components into it, you should either call commit()
 * or clear(). Otherwise, the copies of staged strings remain in PHP memory and they are not
 * garbage collected because of the circular reference component-string.
 */
class mlang_stage {

    /** @var array of mlang_component */
    protected $components = array();

    /**
     * Adds a copy of the given component into the staging area
     *
     * @param mlang_component $component
     * @param bool $force replace the previously staged string if there is such if there is already
     * @return void
     */
    public function add(mlang_component $component, $force=false) {
        $cid = $component->get_identifier();
        if (!isset($this->components[$cid])) {
            $this->components[$cid] = new mlang_component($component->name, $component->lang, $component->version);
        }
        foreach ($component->get_iterator() as $string) {
            $this->components[$cid]->add_string(clone($string), $force);
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
     * @param int|null $basetimestamp the timestamp to rebase against, null for the most recent
     * @param bool $deletemissing if true, then all strings that are in repository but not in stage will be marked as to be deleted
     * @param int $deletetimestamp if $deletemissing is true, what timestamp to use when removing strings (defaults to current)
     */
    public function rebase($basetimestamp=null, $deletemissing=false, $deletetimestamp=null) {
        if(!is_bool($deletemissing)) {
            throw new coding_exception('Incorrect type of the parameter $deletemissing');
        }
        foreach ($this->components as $cx => $component) {
            $cap = mlang_component::from_snapshot($component->name, $component->lang, $component->version, $basetimestamp, true);
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
                if ($stagedstring->deleted && empty($capstring->deleted)) {
                    // the staged string object is the removal record - will be committed
                    continue;
                }
                if (empty($stagedstring->deleted) && $capstring->deleted) {
                    // re-adding a deleted string - will be committed
                    continue;
                }
                if (!mlang_string::differ($stagedstring, $capstring)) {
                    // the staged string is the same as the most recent one in the repository
                    $component->unlink_string($stagedstring->id);
                    continue;
                }
                if ($stagedstring->timemodified < $capstring->timemodified) {
                    // the staged string is older than the cap, do not keep it
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
     * Meta information are the columns in amos_commits table: source, userinfo, commithash etc.
     *
     * @param string $message commit message
     * @param array $meta optional meta information
     * @param bool $skiprebase if true, do not rebase before committing
     * @param int $timecommitted timestamp of the commit, defaults to now
     */
    public function commit($message='', array $meta=null, $skiprebase=false, $timecommitted=null) {
        global $DB;

        if (empty($skiprebase)) {
            $this->rebase();
        }
        if (empty($this->components)) {
            // nothing to commit
            return;
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $commit = new stdclass();
            if (!empty($meta)) {
                foreach ($meta as $field => $value) {
                    $commit->{$field} = $value;
                }
            }
            $commit->commitmsg = trim($message);
            if (is_null($timecommitted)) {
                $timecommitted = time();
            }
            $commit->timecommitted = $timecommitted;
            $commit->id = $DB->insert_record('amos_commits', $commit);

            foreach ($this->components as $cx => $component) {
                foreach ($component->get_iterator() as $string) {
                    $record = new stdclass();
                    $record->commitid   = $commit->id;
                    $record->branch     = $component->version->code;
                    $record->lang       = $component->lang;
                    $record->component  = $component->name;
                    $record->stringid   = $string->id;
                    $record->text       = $string->text;
                    $record->timemodified = $string->timemodified;
                    $record->deleted    = $string->deleted;

                    $DB->insert_record('amos_repository', $record);
                }
            }
            $transaction->allow_commit();
            $this->clear();
        } catch (Exception $e) {
            // this is here in order not to clear the stage, just re-throw the exception
            $transaction->rollback($e);
        }
    }

    /**
     * Remove all components that do not belong to any of the given languages or the branch is not translatable via AMOS
     *
     * @param array $keeplangs (string)langcode => (string)langcode - list of languages to keep, 'X' means all languages
     */
    public function prune(array $keeplangs) {
        foreach ($this->components as $cx => $component) {
            if (empty($component->version->translatable)) {
                // commits not allowed into this branch via AMOS web interface
                $component->clear();
                unset($this->components[$cx]);
                continue;
            }
            if (empty($keeplangs['X']) and empty($keeplangs[$component->lang])) {
                $component->clear();
                unset($this->components[$cx]);
                continue;
            }
        }
    }

    /**
     * Returns the iterator over components
     */
    public function get_iterator() {
        return new mlang_iterator($this->components);
    }

    /**
     * Returns the staged component or null if not known
     *
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param mlang_version $version
     * @return mlang_component|null
     */
    public function get_component($name, $lang, mlang_version $version) {
        $cid = mlang_component::calculate_identifier($name, $lang, $version);
        if (!isset($this->components[$cid])) {
            return null;
        }
        return $this->components[$cid];
    }

    /**
     * Checks if the stage contains some component or a given component
     *
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param mlang_version $version
     * @return bool
     */
    public function has_component($name=null, $lang=null, mlang_version $version=null) {
        if (is_null($name) and is_null($lang) and is_null($version)) {
            return (!empty($this->components));
        } else {
            $cid = mlang_component::calculate_identifier($name, $lang, $version);
            return (isset($this->components[$cid]));
        }
    }

    /**
     * Returns the number of strings and the list of languages and components in the stage
     *
     * Languages and components lists are returned as strings, slash is used as a delimiter
     * and there is heading and trailing slash, too.
     *
     * @return array of (int)strings, (string)languages, (string)components
     */
    public static function analyze(mlang_stage $stage) {
        $strings = 0;
        $languages = array();
        $components = array();

        foreach ($stage->get_iterator() as $component) {
            if ($s = $component->get_number_of_strings()) {
                $strings += $s;
                if (!isset($components[$component->name])) {
                    $components[$component->name] = true;
                }
                if (!isset($languages[$component->lang])) {
                    $languages[$component->lang] = true;
                }
            }
        }
        $languages = '/'.implode('/', array_keys($languages)).'/';
        $components = '/'.implode('/', array_keys($components)).'/';

        return array($strings, $languages, $components);
    }

    /**
     * Propagates staged changes to other branches
     *
     * @param array $versions the list of {@link mlang_version} instances
     * @return int number of propagated changes
     */
    public function propagate(array $versions) {

        $numofpropagated = 0;

        // make sure the list of target branches is unique and indexed by version code
        $xversions = array();
        foreach ($versions as $version) {
            $xversions[$version->code] = $version;
        }
        $versions = $xversions;

        if (empty($versions)) {
            return 0;
        }

        // iterate over all currently staged components
        foreach ($this->components as $sourceidx => $source) {
            $sourcecommittedenglish = mlang_component::from_snapshot($source->name, 'en', $source->version);

            // propagate the source component into all other target branches
            foreach ($versions as $version) {
                if ($version->code == $source->version->code) {
                    // does not make sense to propagate to itself
                    continue;
                }

                $targetcommittedenglish = mlang_component::from_snapshot($source->name, 'en', $version);

                // check if the target component is staged, too
                $current = $this->get_component($source->name, $source->lang, $version);

                // iterate over all strings in the source component
                foreach ($source->get_iterator() as $string) {
                    // if the string is already staged on the target branch, do not do anything
                    if (!is_null($current) and $current->has_string($string->id)) {
                        continue;
                    }
                    if ($string->deleted) {
                        // should not happen via www but just in case
                        continue;
                    }

                    // make sure there is no different translation of this string staged
                    foreach ($this->components as $tempidx => $temp) {
                        if ($tempidx == $sourceidx) {
                            continue;
                        }
                        if ($temp->has_string($string->id)) {
                            if (mlang_string::differ($temp->get_string($string->id), $string)) {
                                continue 2;
                            }
                        }
                    }

                    // make sure that the English originals match on both versions
                    if (is_null($sourcecommittedenglish->get_string($string->id))) {
                        debugging('Staged translation without the English string - this should not happed');
                        continue;
                    }
                    if (is_null($targetcommittedenglish->get_string($string->id))) {
                        // the English string does not exist on the target branch, do not propagate
                        continue;
                    }
                    if (mlang_string::differ($targetcommittedenglish->get_string($string->id), $sourcecommittedenglish->get_string($string->id))) {
                        continue;
                    }

                    // ok, now it should be safe to stage the source string onto the target branch
                    $temp = new mlang_component($source->name, $source->lang, $version);
                    $temp->add_string(new mlang_string($string->id, $string->text));
                    $this->add($temp);
                    $temp->clear();
                    $numofpropagated++;
                }
            }
        }

        return $numofpropagated;
    }
}

/**
 * Storageable staging area
 */
class mlang_persistent_stage extends mlang_stage {

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
     * @return mlang_persistent_stage instance
     */
    public static function instance_for_user($userid, $stageid) {
        $stage = new mlang_persistent_stage($userid, $stageid);
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
        if (empty($userid) or empty($stageid)) {
            throw new coding_exception('Persistance stage identification failed');
        }
        $this->userid = $userid;
        $this->stageid = $stageid;
    }

    /**
     * Make sure the storage is ready for {@link store()} call
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

/**
 * A stash is a snapshot of a stage
 */
class mlang_stash {

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

    /** @var mlang_stage stored in the stash */
    protected $stage;

    /** @var string serialized $stage */
    protected $stageserialized;

    // PUBLIC API

    /**
     * Factory method returning new instance of the stash from the passed stage
     *
     * @param mlang_stage $stage the stage to be stashed
     * @param int $ownerid the user id of the stash owner or 0 for a stash without owner
     * @param string $name optional stash name
     * @return mlang_stash instance
     */
    public static function instance_from_stage(mlang_stage $stage, $ownerid=0, $name='') {
        $instance = new mlang_stash($ownerid);
        $instance->name = $name;
        $instance->set_stage($stage);
        return $instance;
    }

    /**
     * Factory method returning stash instance previously pushed into the stash pool
     *
     * @param int $id stash id
     * @return mlang_stash instance
     */
    public static function instance_from_id($id) {
        global $DB;

        if (empty($id)) {
            throw new coding_exception('Invalid stash identifier');
        }

        $record = $DB->get_record('amos_stashes', array('id' => $id), '*', MUST_EXIST);

        $instance = new mlang_stash($record->ownerid);
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
     * For every user, AMOS keeps a single instance of mlang_stash to store the most recent
     * stage state. This behaves as a backup of the staged strings before they are committed.
     *
     * @param mlang_persistent_stage $stage
     */
    public static function autosave(mlang_persistent_stage $stage) {
        global $DB;

        $instance = new mlang_stash($stage->userid);
        $instance->name = 'AUTOSAVE';
        $instance->set_stage($stage);
        $instance->hash = 'xxxxautosaveuser'.$stage->userid;

        if ($id = $DB->get_field('amos_stashes', 'id', array('hash' => $instance->hash, 'ownerid' => $stage->userid))) {
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

        list($strings, $languages, $components) = mlang_stage::analyze($this->stage);

        if (is_null($this->id)) {
            $record                 = new stdclass();
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
            $record                 = new stdclass();
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
     * @param mlang_stage $stage stage to put the stashed translation into
     */
    public function apply(mlang_stage $stage) {

        foreach ($this->stage->get_iterator() as $component) {
            $stage->add($component, true);
        }
    }

    /**
     * Removes the stash from the stash pool
     */
    public function drop() {
        global $DB;

        if (!$DB->count_records('amos_stashes', array('id' => $this->id, 'hash' => $this->hash, 'ownerid' => $this->ownerid))) {
            throw new coding_exception('Attempt to delete non-existing stash (record)');
        }
        if (!is_writable($this->get_storage_filename())) {
            throw new coding_exception('Attempt to delete non-existing stash (file)');
        }

        $DB->delete_records('amos_stashes', array('id' => $this->id, 'hash' => $this->hash, 'ownerid' => $this->ownerid));
        // if the stash file is not referenced any more, delete it
        if (!$DB->record_exists('amos_stashes', array('hash' => $this->hash))) {
            $filename = $this->get_storage_filename();
            unlink($filename);
            @rmdir(dirname($filename));
            @rmdir(dirname(dirname($filename)));
        }
    }

    /**
     * Reads the stashed strings from the file, sets $this->stage
     *
     * This should be called exclusively by {@link self::instance_from_id()}
     *
     * @see self::save_into_file()
     */
    public function load_from_file() {

        if (!is_null($this->stageserialized) or !is_null($this->stage)) {
            throw new coding_exception('Already loaded stash can not be overwritten');
        }

        $filename = $this->get_storage_filename();

        if (!is_readable($filename)) {
            throw new coding_exception('Unable to read stash storage '.$filename);
        }

        $this->stage = unserialize(file_get_contents($filename));
    }

    // INTERNAL API

    /**
     * Public construction not allowed, use a factory method
     */
    protected function __construct($ownerid) {
        $this->ownerid = $ownerid;
        $this->timecreated = time();
    }

    /**
     * Sets the stashed stage
     *
     * @param mlang_stage $stage
     */
    protected function set_stage(mlang_stage $stage) {
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
        if (is_null($this->ownerid) or is_null($this->stageserialized) or is_null($this->timecreated)) {
            throw new coding_exception('Unable to calculate stash identifier');
        }
        $this->hash = md5($this->ownerid.'@'.$this->timecreated.'#'.$this->stageserialized.'%'.rand());
    }

    /**
     * Dumps the stash contents into a file in moodledata/amos/stashes/
     *
     * @see self::load_from_file()
     */
    protected function save_into_file() {

        if (empty($this->stageserialized)) {
            throw new coding_exception('Stage not prepared before saving the file');
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

        if (empty($this->hash) or strlen($this->hash) < 4) {
            throw new coding_exception('Invalid stash hash');
        }

        $subdir1 = substr($this->hash, 0, 2);
        $subdir2 = substr($this->hash, 2, 2);

        return $CFG->dataroot."/amos/stashes/$subdir1/$subdir2/".$this->hash;
    }
}

/**
 * Provides information about Moodle versions and corresponding branches
 *
 * Do not modify the returned instances, they are not cloned during coponent copying.
 */
class mlang_version {
    /** internal version codes stored in database */
    const MOODLE_16 = 1600;
    const MOODLE_17 = 1700;
    const MOODLE_18 = 1800;
    const MOODLE_19 = 1900;
    const MOODLE_20 = 2000;
    const MOODLE_21 = 2100;
    const MOODLE_22 = 2200;
    const MOODLE_23 = 2300;

    /** @var int internal code of the version */
    public $code;

    /** @var string  human-readable label of the version */
    public $label;

    /** @var string the name of the corresponding CVS/git branch */
    public $branch;

    /** @var string the name of the directory under http://download.moodle.org/langpack/ */
    public $dir;

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
     * Factory method
     *
     * @param string $dir like '2.1'
     * @return mlang_version|null
     */
    public static function by_dir($dir) {
        foreach (self::versions_info() as $ver) {
            if ($ver['dir'] == $dir) {
                return new mlang_version($ver);
            }
        }
        return null;
    }

    /**
     * Get a list of all known versions and information about them
     *
     * @return array of mlang_version
     */
    public static function list_all() {
        $list = array();
        foreach (self::versions_info() as $ver) {
            $list[$ver['code']] = new mlang_version($ver);
        }
        return $list;
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
     * Holds the information about Moodle branches
     *
     * code         - internal integer code to be stored in database
     * label        - human readable version number
     * branch       - the name of the branch in git
     * dir          - the name of the directory under http://download.moodle.org/langpack/
     * translatable - allow commits into the AMOS repository on this branch
     * current      - use the version by default in the translator
     *
     * @return array of array
     */
    protected static function versions_info() {
        return array(
            array(
                'code'          => self::MOODLE_23,
                'label'         => '2.3dev',
                'branch'        => 'MOODLE_23_STABLE',
                'dir'           => '2.3',
                'translatable'  => true,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_22,
                'label'         => '2.2',
                'branch'        => 'MOODLE_22_STABLE',
                'dir'           => '2.2',
                'translatable'  => true,
                'current'       => true,
            ),
            array(
                'code'          => self::MOODLE_21,
                'label'         => '2.1',
                'branch'        => 'MOODLE_21_STABLE',
                'dir'           => '2.1',
                'translatable'  => true,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_20,
                'label'         => '2.0',
                'branch'        => 'MOODLE_20_STABLE',
                'dir'           => '2.0',
                'translatable'  => true,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_19,
                'label'         => '1.9',
                'branch'        => 'MOODLE_19_STABLE',
                'dir'           => '1.9',
                'translatable'  => false,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_18,
                'label'         => '1.8',
                'branch'        => 'MOODLE_18_STABLE',
                'dir'           => '1.8',
                'translatable'  => false,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_17,
                'label'         => '1.7',
                'branch'        => 'MOODLE_17_STABLE',
                'dir'           => '1.7',
                'translatable'  => false,
                'current'       => false,
            ),
            array(
                'code'          => self::MOODLE_16,
                'label'         => '1.6',
                'branch'        => 'MOODLE_16_STABLE',
                'dir'           => '1.6',
                'translatable'  => false,
                'current'       => false,
            ),
        );
    }
}

/**
 * Class providing various AMOS-related tools
 */
class mlang_tools {

    /** return stati of {@see self::execute()} */
    const STATUS_OK                     =  0;
    const STATUS_SYNTAX_ERROR           = -1;
    const STATUS_UNKNOWN_INSTRUCTION    = -2;

    /**
     * Returns a list of all languages known in the strings repository
     *
     * The language must have defined its international name in langconfig.
     * This method takes the value from the most recent branch and time stamp.
     *
     * @param bool $english shall the English be included?
     * @param bool $usecache can the internal cache be used?
     * @param bool $showcode append language code to the name
     * @return array (string)langcode => (string)langname
     */
    public static function list_languages($english=true, $usecache=true, $showcode=true) {
        global $DB;
        static $cache = null;

        if (empty($usecache) or is_null($cache)) {
            $cache = array();
            $sql = "SELECT lang AS code, text AS name
                      FROM {amos_repository}
                     WHERE component = ?
                       AND stringid  = ?
                       AND deleted   = 0
                  ORDER BY branch DESC, text, timemodified DESC";
            $rs = $DB->get_recordset_sql($sql, array('langconfig', 'thislanguageint'));
            foreach ($rs as $lang) {
                if (!isset($cache[$lang->code])) {
                    // use the first returned value, all others are historical records
                    $value = $lang->name;
                    if ($showcode) {
                        $value .= ' (' . $lang->code . ')';
                    }
                    $cache[$lang->code] = $value;
                }
            }
            $rs->close();
        }

        asort($cache);

        if ($english) {
            return $cache;
        } else {
            return array_diff_key($cache, array('en' => 'English'));
        }
    }

    /**
     * Returns the list of all known components
     *
     * The component must exist in English on at least one branch to be returned.
     *
     * @param bool $usecache can the internal cache be used?
     * @return array (string)componentname => undefined, the values of the array may change later if needed, do not rely on them
     */
    public static function list_components($usecache=true) {
        global $DB;
        static $cache = null;

        if (empty($usecache) or is_null($cache)) {
            $cache = array();
            $sql = "SELECT component
                      FROM {amos_repository}
                     WHERE lang = ?
                  GROUP BY component ORDER BY component";
            $cache = array_flip(array_keys($DB->get_records_sql($sql, array('en'))));
        }

        return $cache;

    }

    /**
     * For the given user, returns a list of languges she is allowed commit into
     *
     * Language code 'X' has a special meaning - the user is allowed to edit all languages.
     *
     * @param mixed $userid user id
     * @return array of (string)langcode => (string)langcode
     */
    public static function list_allowed_languages($userid) {
        global $DB;

        $records = $DB->get_records('amos_translators', array('userid' => $userid));
        $langs = array();
        foreach ($records as $record) {
            $langs[$record->lang] = $record->lang;
        }
        return $langs;
    }

    /**
     * Returns the tree of all known components
     *
     * @param array $conditions can specify branch, lang and component to filter
     * @return array [branch][language][component] => true
     */
    public static function components_tree(array $conditions=null) {
        global $DB;

        $where = array();
        $params = array();
        if (!empty($conditions['branch'])) {
            list($subsql, $subparams) = $DB->get_in_or_equal($conditions['branch']);
            $where[] = 'branch '.$subsql;
            $params  = array_merge($params, $subparams);
        }
        if (!empty($conditions['lang'])) {
            list($subsql, $subparams) = $DB->get_in_or_equal($conditions['lang']);
            $where[] = 'lang '.$subsql;
            $params  = array_merge($params, $subparams);
        }
        if (!empty($conditions['component'])) {
            list($subsql, $subparams) = $DB->get_in_or_equal($conditions['component']);
            $where[] = 'component '.$subsql;
            $params  = array_merge($params, $subparams);
        }
        if (!empty($where)) {
            $where = "WHERE " . implode(" AND ", $where) . "\n";
        }
        $sql = "SELECT branch,lang,component
                  FROM {amos_repository}\n";
        if (!empty($where)) {
            $sql .= $where;
        }
        $sql .= "GROUP BY branch,lang,component
                 ORDER BY branch,lang,component";
        $rs = $DB->get_recordset_sql($sql, $params);
        $tree = array();
        foreach ($rs as $record) {
            if (!isset($tree[$record->branch])) {
                $tree[$record->branch] = array();
            }
            if (!isset($tree[$record->branch][$record->lang])) {
                $tree[$record->branch][$record->lang] = array();
            }
            $tree[$record->branch][$record->lang][$record->component] = true;
        }
        $rs->close();

        return $tree;
    }

    /**
     * Given a text, extracts AMOS script lines from it as array of commands
     *
     * See {@link http://docs.moodle.org/dev/Languages/AMOS} for the specification
     * of the script. Basically it is a block of lines starting with "AMOS BEGIN" line and
     * ending with "AMOS END" line. "AMOS START" is an alias for "AMOS BEGIN". Each instruction
     * in the script must be on a separate line.
     *
     * @param string $text
     * @return array of the script lines
     */
    public static function extract_script_from_text($text) {

        if (!preg_match('/^.*\bAMOS\s+(BEGIN|START)\s+(.+)\s+AMOS\s+END\b.*$/sm', $text, $matches)) {
            return array();
        }

        // collapse all whitespace into single space
        $script = preg_replace('/\s+/', ' ', trim($matches[2]));

        // we need explicit list of known commands so that this parser can handle onliners well
        $cmds = array('MOV', 'CPY', 'HLP', 'REM');

        // put new line character before every known command
        $cmdsfrom = array();
        $cmdsto   = array();
        foreach ($cmds as $cmd) {
            $cmdsfrom[] = "$cmd ";
            $cmdsto[]   = "\n$cmd ";
        }
        $script = str_replace($cmdsfrom, $cmdsto, $script);

        // make array of non-empty lines
        $lines = array_filter(array_map('trim', explode("\n", $script)));

        return array_values($lines);
    }

    /**
     * Executes the given instruction
     *
     * TODO AMOS script uses the new proposed component naming style, also known as frankenstyle. AMOS repository,
     * however, still uses the legacy names of components. Therefore we are translating new notation into the legacy
     * one here. This may change in the future.
     *
     * @param string $instruction in form of 'CMD arguments'
     * @param mlang_version $version strings branch to execute instruction on
     * @param int $timestamp effective time of the execution
     * @return int|mlang_stage mlang_stage to commit, 0 if success and there is nothing to commit, error code otherwise
     */
    public static function execute($instruction, mlang_version $version, $timestamp=null) {
        $spcpos = strpos($instruction, ' ');
        if ($spcpos === false) {
            $cmd = trim($instruction);
            $arg = null;
        } else {
            $cmd = trim(substr($instruction, 0, $spcpos));
            $arg = trim(substr($instruction, $spcpos + 1));
        }
        switch ($cmd) {
        case 'CPY':
            // CPY [sourcestring,sourcecomponent],[targetstring,targetcomponent]
            if (preg_match('/\[(.+),(.+)\]\s*,\s*\[(.+),(.+)\]/', $arg, $matches)) {
                array_map('trim', $matches);
                $fromcomponent = self::legacy_component_name($matches[2]);
                $tocomponent = self::legacy_component_name($matches[4]);
                if ($fromcomponent and $tocomponent) {
                    return self::copy_string($version, $matches[1], $fromcomponent, $matches[3], $tocomponent, $timestamp);
                } else {
                    return self::STATUS_SYNTAX_ERROR;
                }
            } else {
                return self::STATUS_SYNTAX_ERROR;
            }
            break;
        case 'MOV':
            // MOV [sourcestring,sourcecomponent],[targetstring,targetcomponent]
            if (preg_match('/\[(.+),(.+)\]\s*,\s*\[(.+),(.+)\]/', $arg, $matches)) {
                array_map('trim', $matches);
                $fromcomponent = self::legacy_component_name($matches[2]);
                $tocomponent = self::legacy_component_name($matches[4]);
                if ($fromcomponent and $tocomponent) {
                    return self::move_string($version, $matches[1], $fromcomponent, $matches[3], $tocomponent, $timestamp);
                } else {
                    return self::STATUS_SYNTAX_ERROR;
                }
            } else {
                return self::STATUS_SYNTAX_ERROR;
            }
            break;
        case 'HLP':
            // HLP feedback/preview.html,[preview_hlp,mod_feedback]
            if (preg_match('/(.+),\s*\[(.+),(.+)\]/', $arg, $matches)) {
                array_map('trim', $matches);
                $helpfile = clean_param($matches[1], PARAM_PATH);
                $tocomponent = self::legacy_component_name($matches[3]);
                $tostring = $matches[2];
                if ($tostring !== clean_param($tostring, PARAM_STRINGID)) {
                    return self::STATUS_SYNTAX_ERROR;
                }
                if ($helpfile and $tocomponent and $tostring) {
                    return self::migrate_helpfile($version, $helpfile, $tostring, $tocomponent, $timestamp);
                } else {
                    return self::STATUS_SYNTAX_ERROR;
                }
            } else {
                return self::STATUS_SYNTAX_ERROR;
            }
            break;
        case 'REM':
            // todo send message to subscribed users
            return self::STATUS_OK;
            break;
        // WARNING: If a new command is added here, it must be also put into the list of known
        // commands in self::extract_script_from_text(). It is not nice but we use new line
        // as the delimiter and git may strip new lines if the script is part of the subject line.
        default:
            return self::STATUS_UNKNOWN_INSTRUCTION;
        }
    }

    /**
     * Merges all strings from one component to another and fixes syntax if needed
     *
     * If the string already exists in the target component, it is skipped (even
     * if it is set as deleted there). Does not modify the source component.
     *
     * @param mlang_component $source component to take strings from
     * @param mlang_component $target component to add strings to
     * @return void modifies $target component
     */
    public static function merge(mlang_component $source, mlang_component $target) {

        if ($source->version->code <= mlang_version::MOODLE_19) {
            $sourceformat = 1;
        } else {
            $sourceformat = 2;
        }

        if ($target->version->code <= mlang_version::MOODLE_19) {
            throw new mlang_exception('Can not merge into Moodle 1.x branches');
        } else {
            $targetformat = 2;
        }

        foreach ($source->get_iterator() as $string) {
            $stringid = clean_param($string->id, PARAM_STRINGID);
            if (empty($stringid)) {
                throw new mlang_exception('Invalid string identifier '.s($string->id));
            }
            if (!$target->has_string($stringid)) {
                $text = mlang_string::fix_syntax($string->text, $targetformat, $sourceformat);
                $target->add_string(new mlang_string($stringid, $text));
            }
        }
    }

    /**
     * Given an array of lines of git-diff output, get the list of affected strings
     *
     * @see PARAM_STRINGID
     * @param array $diff array of lines as obtained from exec() call
     * @return array list of string identifiers affected by that diff
     */
    public static function get_affected_strings(array $diff) {

        $affected = array();
        $pattern  = '|^[+-]\$string\[\'([a-zA-Z][a-zA-Z0-9\.:/_-]*)\'\]\s*=|';
        foreach ($diff as $line) {
            if (preg_match($pattern, $line, $matches)) {
                $affected[$matches[1]] = true;
            }
        }

        return array_keys($affected);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Internal implementation
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Copy one string to another at the given version branch for all languages in the repository
     *
     * Deleted strings are not copied. If the target string already exists (and is not deleted), it is
     * not overwritten.
     *
     * @param mlang_version $version to execute copying on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponet target component name
     * @param int $timestamp effective timestamp of the copy, null for now
     * @return mlang_stage to be committed
     */
    protected static function copy_string(mlang_version $version, $fromstring, $fromcomponent, $tostring, $tocomponent, $timestamp=null) {
        $stage = new mlang_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = mlang_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, array($fromstring));
            $to = mlang_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, array($tostring));
            if ($from->has_string($fromstring) and !$to->has_string($tostring)) {
                $to->add_string(new mlang_string($tostring, $from->get_string($fromstring)->text, $timestamp));
                $stage->add($to);
            }
            $from->clear();
            $to->clear();
        }
        return $stage;
    }

    /**
     * Move the string to another at the given version branch for all languages in the repository
     *
     * Deleted strings are not moved. If the target string already exists (and is not deleted), it is
     * not overwritten.
     *
     * @param mlang_version $version to execute moving on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponet target component name
     * @param int $timestamp effective timestamp of the move, null for now
     * @return mlang_stage to be committed
     */
    protected static function move_string(mlang_version $version, $fromstring, $fromcomponent, $tostring, $tocomponent, $timestamp=null) {
        $stage = new mlang_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = mlang_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, array($fromstring));
            $to = mlang_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, array($tostring));
            if ($src = $from->get_string($fromstring)) {
                $from->add_string(new mlang_string($fromstring, $from->get_string($fromstring)->text, $timestamp, true), true);
                $stage->add($from);
                if (!$to->has_string($tostring)) {
                    $to->add_string(new mlang_string($tostring, $src->text, $timestamp));
                    $stage->add($to);
                }
            }
            $from->clear();
            $to->clear();
        }
        return $stage;
    }

    /**
     * Migrate help file into a help string if such one does not exist yet
     *
     * This is a temporary method and will be dropped once we have all English helps migrated. It does not do anything
     * yet. It is intended to be run once upon a checkout of 1.9 language files prepared just for this purpose.
     *
     * @param mixed         $helpfile
     * @param mixed         $tostring
     * @param mixed         $tocomponent
     * @param mixed         $timestamp
     * @return mlang_stage
     */
    protected static function migrate_helpfile($version, $helpfile, $tostring, $tocomponent, $timestamp=null) {
        global $CFG;
        require_once($CFG->dirroot . '/local/amos/cli/config.php');

        $stage = new mlang_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $fullpath = AMOS_REPO_LANGS . '/' . $lang . '_utf8/help/' . $helpfile;
            if (! is_readable($fullpath)) {
                continue;
            }
            $helpstring = file_get_contents($fullpath);
            $helpstring = preg_replace('|<h1>.*</h1>|i', '', $helpstring);
            $helpstring = iconv('UTF-8', 'UTF-8//IGNORE', $helpstring);
            $helpstring = trim($helpstring);
            if (empty($helpstring)) {
                continue;
            }
            $to = mlang_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, array($tostring));
            if (!$to->has_string($tostring)) {
                $to->add_string(new mlang_string($tostring, $helpstring, $timestamp));
                $stage->add($to);
            }
            $to->clear();
        }
        return $stage;
    }

    /**
     * Given a newstyle component name (aka frankenstyle), returns the legacy style name
     *
     * @param string $newstyle name like core, core_admin, mod_workshop or auth_ldap
     * @return string|false legacy style like moodle, admin, workshop or auth_ldap, false in case of error
     */
    protected static function legacy_component_name($newstyle) {
        $newstyle = trim($newstyle);
        if (preg_match('/[^a-z_\.-]/', $newstyle)) {
            // only letters and underscore should be allowed - blame component 'moodle.org' for the dot
            return false;
        }
        if ($newstyle == 'core') {
            return 'moodle';
        }
        if (substr($newstyle, 0, 5) == 'core_') {
            return substr($newstyle, 5);
        }
        if (substr($newstyle, 0, 4) == 'mod_') {
            return substr($newstyle, 4);
        }
        return $newstyle;
    }
}
