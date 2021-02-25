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
 * Moodle language manipulation library
 *
 * Provides classes and functions to handle various low-level operations on Moodle
 * strings in various formats.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Base exception thrown by low level language manipulation operations
 */
class mlang_exception extends moodle_exception {

    /**
     * Constructor.
     *
     * @param string $hint short description of problem
     * @param string $debuginfo detailed information how to fix problem
     */
    public function __construct($hint, $debuginfo=null) {
        parent::__construct('err_exception', 'local_amos', '', $hint, $debuginfo);
    }
}

/**
 * Represents a collection of strings for a given component
 */
class mlang_component implements IteratorAggregate, Countable {

    /** @var the name of the component, what {@see string_manager::get_string()} uses as the second param */
    public $name;

    /** @var string language code we are part of */
    public $lang;

    /** @var mlang_version we are part of */
    public $version;

    /** @var holds instances of mlang_string in an associative array */
    protected $strings = array();

    /**
     * Constructor.
     *
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
     * @throws Exception
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
            // Empty placeholder to prevent PHP notices.
            $a = '';
            include($filepath);
        } else {
            throw new Exception('Strings definition file ' . $filepath . ' not readable');
        }
        if ($version->code <= 19) {
            // We are going to import strings for 1.x branch.
            $target = 1;
            if (is_null($format)) {
                $format = 1;
            }
            if ($format !== 1) {
                throw new coding_exception('For Moodle 1.x, only strings in legacy format are supported.');
            }
        } else {
            // We are going to import strings for 2.x branch.
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
     * @param string $name The component name.
     * @param string $lang The language code.
     * @param mlang_version $version Version of the component.
     * @param int $timestamp Time of the snapshot, defaults to the most recent one.
     * @param bool $deleted Shall deleted strings be included?
     * @param bool $fullinfo Shall full information about the string (commit messages, source etc.) be returned?
     * @param array $strnames Limit the list of loaded strings to ones in this list only.
     * @return mlang_component Component with the strings from the snapshot.
     */
    public static function from_snapshot(string $name, string $lang, mlang_version $version, ?int $timestamp = null,
            bool $deleted=false, bool $fullinfo = false, ?array $strnames = null): mlang_component {
        global $DB;

        $sql = "SELECT r.id, r.strname, r.strtext, r.since, r.timemodified";

        if ($fullinfo) {
            $sql .= ", c.id AS commitid, c.source, c.timecommitted, c.commitmsg, c.commithash, c.userid, c.userinfo";
        }

        $params = [
            'component' => $name,
            'version' => $version->code,
        ];

        if ($lang === 'en') {
            $table = 'amos_strings';
            $langsql = '';

        } else {
            $table = 'amos_translations';
            $langsql = " AND lang = :lang ";
            $params['lang'] = $lang;
        }

        $sql .= " FROM {" . $table . "} r";

        if ($fullinfo) {
            $sql .= " LEFT JOIN {amos_commits} c ON (r.commitid = c.id)";
        }

        $sql .= "   WHERE component = :component
                      $langsql
                      AND since <= :version";

        if (!empty($strnames)) {
            list($strsql, $strparams) = $DB->get_in_or_equal($strnames, SQL_PARAMS_NAMED);
            $sql .= " AND strname $strsql";
            $params += $strparams;
        }

        if (!empty($timestamp)) {
            $sql .= " AND timemodified <= :timemodified";
            $params['timemodified'] = $timestamp;
        }

        $rs = $DB->get_recordset_sql($sql, $params);

        $latest = [];

        foreach ($rs as $r) {
            if (!isset($latest[$r->strname])) {
                $latest[$r->strname] = $r;

            } else {
                $s = $latest[$r->strname];

                if ($r->since < $s->since) {
                    continue;
                }

                if (($r->since == $s->since) && ($r->timemodified < $s->timemodified)) {
                    continue;
                }

                if (($r->since == $s->since) && ($r->timemodified == $s->timemodified) && ($r->id < $s->id)) {
                    continue;
                }

                $latest[$r->strname] = $r;
            }
        }

        $rs->close();

        if (!$deleted) {
            // Prune the deleted strings. Keep in mind to do it only this late here and not in SQL.
            // The string could have been added, deleted and re-added again. We need to know what the latest one is.
            foreach ($latest as $strname => $str) {
                if ($str->strtext === null) {
                    unset($latest[$strname]);
                }
            }
        }

        ksort($latest);

        $component = new mlang_component($name, $lang, $version);

        foreach ($latest as $str) {
            $extra = null;

            if ($fullinfo) {
                $extra = (object)[];

                foreach ($str as $property => $value) {
                    if (!in_array($property, ['strname', 'strtext', 'timemodified'])) {
                        $extra->{$property} = $value;
                    }
                }
            }

            $component->add_string(new mlang_string($str->strname, $str->strtext, $str->timemodified,
                $str->strtext === null, $extra), true);
        }

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

    // phpcs:disable moodle.NamingConventions.ValidFunctionName

    /**
     * Returns an external iterator over strings in the component.
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->strings);
    }

    // phpcs:enable

    /**
     * Return the number of strings in the component.
     *
     * @return int
     */
    public function count() {
        return count($this->strings);
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
     * Beware - if deleted strings are loaded into this component, they are counted too - unless non-translatable
     * strings are excluded.
     *
     * @param bool $forstats Counting strings for stats purposes, so non-translatable strings are excluded
     * @return int
     */
    public function get_number_of_strings($forstats = false) {

        if (!$forstats) {
            return count($this->strings);

        } else {
            $result = 0;

            foreach ($this->strings as $string) {
                if ($string->should_be_included_in_stats()) {
                    $result++;
                }
            }

            return $result;
        }
    }

    /**
     * Adds new string into the collection
     *
     * @param mlang_string $string to add
     * @param bool $force if true, existing string will be replaced
     * @throws coding_exception when trying to add existing component without $force
     * @return void
     */
    public function add_string(mlang_string $string, $force=false) {

        if (!$force && isset($this->strings[$string->id])) {
            throw new coding_exception('You are trying to add a string \'' . $string->id .
                '\' that already exists in this component. If this is intentional, use the \'force\' parameter');
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


EOF
        );
        if (empty($phpdoc)) {
            $version = $this->version->label;
            $lang   = $this->lang;
            $name   = $this->name;
            fwrite($f, <<<EOF
/**
 * Strings for component '$name', language '$lang', version '$version'.
 *
 * @package     $this->name
 * @category    string
 * @copyright   1999 Martin Dougiamas and contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


EOF
            );

        } else {
            fwrite($f, $phpdoc);
        }

        fwrite($f, "defined('MOODLE_INTERNAL') || die();\n\n");
        ksort($this->strings);
        foreach ($this as $string) {
            if ($this->name === 'langconfig' && $string->id === 'parentlanguage') {
                $knownlangs = mlang_tools::list_languages();
                if ($string->text !== '' && !isset($knownlangs[$string->text])) {
                    fwrite($f, "// Warning: this parentlanguage value is not a valid language code!\n");
                    fwrite($f, '// $string[\'' . $string->id . '\'] = ');
                    fwrite($f, var_export($string->text, true));
                    fwrite($f, ";\n");
                    continue;
                }
            }
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

        if ($this->version->code <= 19) {
            // Moodle 1.x.
            return 'lang/' . $this->lang . '_utf8/' . $this->name . '.php';

        } else {
            // Moodle 2.x.
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
     * Prunes all strings that have the same text as in the reference component
     *
     * This may be used to get rid of strings that are defined in another component
     * and have the same text value. Typical usage is the post-merge cleanup of the en_fix
     * language pack.
     * Beware - if the string is defined in $reference as deleted, it will be kept in this
     * regardless its state and value. Our strings that are already deleted are not
     * affected.
     *
     * @param mlang_component $reference master component to compare strings with
     * @return array list of removed string ids
     */
    public function complement(mlang_component $reference) {

        $removed = [];

        foreach ($this->strings as $id => $string) {
            if ($string->deleted) {
                // Do not affect our strings that are already deleted.
                continue;
            }
            if (!$reference->has_string($id)) {
                // Do not affect strings not present in $reference.
                // See {@see self::intersect()} if you want to get rid of such strings.
                continue;
            }
            if (mlang_string::differ($string, $reference->get_string($id))) {
                // Do not affect strings that are considered as different from the ones
                // in the $reference.
                continue;
            }

            $this->unlink_string($id);
            $removed[] = $id;
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
        foreach ($this as $string) {
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

        if ($this->version->code <= 19) {
            $format = 1;
        } else {
            $format = 2;
        }

        foreach ($this as $string) {
            $string->clean_text($format);
        }
    }

    /**
     * Fix the mlang_version after waking up from the serialization.
     */
    public function __wakeup() {

        if ($this->version->code >= 1600) {
            $this->version = mlang_version::by_code($this->version->code / 100);

        } else {
            $this->version = mlang_version::by_code($this->version->code);
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

    /** @var boolean should we skip some cleaning */
    public $nocleaning = false;

    /**
     * Constructor.
     *
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
        if ($a->nocleaning || $b->nocleaning) {
            if ($a->text === $b->text) {
                return false;
            }
        } else {
            if (trim($a->text) === trim($b->text)) {
                return false;
            }
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
        if ($this->nocleaning) {
            $this->text = self::fix_syntax_minimal($this->text);
        } else {
            $this->text = self::fix_syntax($this->text, $format);
        }
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

        // Common filter.
        $clean = trim($text);
        $search = array(
            // phpcs:disable moodle.Commenting.InlineComment
            // Remove \r if it is part of \r\n.
            '/\r(?=\n)/',

            // Control characters to be replaced with \n.
            // LINE TABULATION, FORM FEED, CARRIAGE RETURN, END OF TRANSMISSION BLOCK,
            // END OF MEDIUM, SUBSTITUTE, BREAK PERMITTED HERE, NEXT LINE, START OF STRING,
            // STRING TERMINATOR and Unicode character categorys Zl and Zp
            '/[\x{0B}-\r\x{17}\x{19}\x{1A}\x{82}\x{85}\x{98}\x{9C}\p{Zl}\p{Zp}]/u',

            // Control characters to be removed.
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
            '/[\0\x{05}-\x{07}\x{0E}-\x{16}\x{1B}\x{7F}\x{80}\x{81}\x{83}\x{84}\x{86}-\x{93}\x{95}-\x{97}\x{99}-\x{9B}' .
                '\x{9D}-\x{9F}\x{200B}\x{FEFF}\x{FFFD}]++/u',

            // Remove trailing whitespace at the end of lines in a multiline string.
            '/[ \t]+(?=\n)/',
            // phpcs:enable
        );
        $replace = array(
            '',
            "\n",
            '',
            '',
        );
        $clean = preg_replace($search, $replace, $clean);

        if (($format === 2) && ($from === 2)) {
            // Clean up translations of 2.x strings.
            $clean = preg_replace("/\n{3,}/", "\n\n\n", $clean);

        } else if (($format === 2) && ($from === 1)) {
            // Convert 1.x string into 2.x format.
            $clean = preg_replace("/\n{3,}/", "\n\n\n", $clean);
            $clean = preg_replace('/%+/', '%', $clean);
            $clean = str_replace('\$', '@@@___XXX_ESCAPED_DOLLAR__@@@', $clean);
            $clean = str_replace("\\", '', $clean);
            $clean = preg_replace('/(^|[^{])\$a\b(\->[a-zA-Z0-9_]+)?/', '\\1{$a\\2}', $clean);
            $clean = str_replace('@@@___XXX_ESCAPED_DOLLAR__@@@', '$', $clean);
            $clean = str_replace('&#36;', '$', $clean);

        } else if (($format === 1) && ($from === 1)) {
            // Clean up legacy 1.x strings.
            $clean = preg_replace("/\n{3,}/", "\n\n", $clean);
            $clean = str_replace('\$', '@@@___XXX_ESCAPED_DOLLAR__@@@', $clean);
            $clean = str_replace("\\", '', $clean);
            $clean = str_replace('$', '\$', $clean);
            $clean = preg_replace('/\\\\\$a\b(\->[a-zA-Z0-9_]+)?/', '$a\\1', $clean);
            $clean = str_replace('@@@___XXX_ESCAPED_DOLLAR__@@@', '\$', $clean);
            $clean = str_replace('"', "\\\"", $clean);
            $clean = preg_replace('/%+/', '%', $clean);
            $clean = str_replace('%', '%%', $clean);

        } else {
            throw new mlang_exception('Unknown get_string() format version');
        }
        return $clean;
    }

    /**
     * Making minimal sanitize of string, no trims or double lines deletion
     *
     * @param string $text string text to be fixed
     * @return string
     */
    public static function fix_syntax_minimal($text) {
        $search = array(
            // phpcs:disable moodle.Commenting.InlineComment
            // Remove \r if it is part of \r\n.
            '/\r(?=\n)/',

            // Control characters to be replaced with \n.
            // LINE TABULATION, FORM FEED, CARRIAGE RETURN, END OF TRANSMISSION BLOCK,
            // END OF MEDIUM, SUBSTITUTE, BREAK PERMITTED HERE, NEXT LINE, START OF STRING,
            // STRING TERMINATOR and Unicode character categorys Zl and Zp
            '/[\x{0B}-\r\x{17}\x{19}\x{1A}\x{82}\x{85}\x{98}\x{9C}\p{Zl}\p{Zp}]/u',

            // Control characters to be removed.
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
            // phpcs:enable
            '/[\0\x{05}-\x{07}\x{0E}-\x{16}\x{1B}\x{7F}\x{80}\x{81}\x{83}\x{84}\x{86}-\x{93}\x{95}-\x{97}\x{99}-\x{9B}' .
                '\x{9D}-\x{9F}\x{200B}\x{FEFF}\x{FFFD}]++/u',
        );
        $replace = array(
            '',
            "\n",
            '',
        );
        $clean = preg_replace($search, $replace, $text);
        return $clean;
    }

    /**
     * Should the string be counted when calculating the translation stats.
     *
     * @return bool
     */
    public function should_be_included_in_stats() {

        if ($this->deleted) {
            return false;
        }

        if (substr($this->id, -5) === '_link') {
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
 * garbage collected because of the circular reference component-string.
 */
class mlang_stage implements IteratorAggregate, Countable {

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
        foreach ($component as $string) {
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

        if (!is_bool($deletemissing)) {
            throw new coding_exception('Incorrect type of the parameter $deletemissing');
        }

        foreach ($this->components as $cx => $component) {
            $cap = mlang_component::from_snapshot($component->name, $component->lang, $component->version, $basetimestamp, true);
            if ($deletemissing) {
                if (empty($deletetimestamp)) {
                    $deletetimestamp = time();
                }
                foreach ($cap as $existing) {
                    $stagedstring = $component->get_string($existing->id);
                    if (is_null($stagedstring)) {
                        $tobedeleted = clone($existing);
                        $tobedeleted->deleted = true;
                        $tobedeleted->timemodified = $deletetimestamp;
                        $component->add_string($tobedeleted);
                    }
                }
            }
            foreach ($component as $stagedstring) {
                $capstring = $cap->get_string($stagedstring->id);
                if (is_null($capstring)) {
                    // The staged string does not exist in the repository yet - will be committed.
                    continue;
                }
                if ($stagedstring->deleted && empty($capstring->deleted)) {
                    // The staged string object is the removal record - will be committed.
                    continue;
                }
                if (empty($stagedstring->deleted) && $capstring->deleted) {
                    // Re-adding a deleted string - will be committed.
                    continue;
                }
                if (!mlang_string::differ($stagedstring, $capstring)) {
                    // The staged string is the same as the most recent one in the repository.
                    $component->unlink_string($stagedstring->id);
                    continue;
                }
                if ($stagedstring->timemodified < $capstring->timemodified) {
                    // The staged string is older than the cap, do not keep it.
                    $component->unlink_string($stagedstring->id);
                    continue;
                }
            }
            // Unstage the whole component if it is empty.
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
     * @param bool $clear clear the stage after it is committed
     * @return int Commit id or 0 if nothing committed.
     */
    public function commit($message='', array $meta=null, $skiprebase=false, $timecommitted=null, $clear=true): int {
        global $DB;

        if (empty($skiprebase)) {
            $this->rebase();
        }
        if (empty($this->components)) {
            return 0;
        }

        $purgecaches = false;

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
                if ($component->lang === 'en' || $component->name === 'langconfig') {
                    // There is a chance that a new component or a new language (or language name) is introduced by the commit.
                    $purgecaches = true;
                }

                foreach ($component as $string) {
                    $record = [
                        'component' => $component->name,
                        'strname' => $string->id,
                        'strtext' => ($string->deleted ? null : $string->text),
                        'since' => $component->version->code,
                        'timemodified' => $string->timemodified,
                        'commitid' => $commit->id,
                    ];

                    if ($component->lang === 'en') {
                        $table = 'amos_strings';

                    } else {
                        $table = 'amos_translations';
                        $record['lang'] = $component->lang;
                    }

                    $DB->insert_record($table, $record);
                }
            }

            $transaction->allow_commit();

            if ($purgecaches) {
                $cache = cache::make('local_amos', 'lists');
                $cache->purge();
            }

            if ($clear) {
                $this->clear();
            }

            return $commit->id;

        } catch (Exception $e) {
            // This is here in order not to clear the stage, just re-throw the exception.
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
                // Commits not allowed into this branch via AMOS web interface.
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

    // phpcs:disable moodle.NamingConventions.ValidFunctionName

    /**
     * Returns an external iterator over components in the stage.
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->components);
    }

    // phpcs:enable

    /**
     * Return the count of components in the stage.
     *
     * @return int
     */
    public function count() {
        return count($this->components);
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
     * @param mlang_stage $stage Stage to be analyzed
     * @return array of (int)strings, (string)languages, (string)components
     */
    public static function analyze(mlang_stage $stage) {
        $strings = 0;
        $languages = array();
        $components = array();

        foreach ($stage as $component) {
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
     * Change the language of the staged component
     *
     * @param string $from
     * @param string $to
     */
    public function change_lang($from, $to) {

        foreach ($this->components as $component) {
            if ($component->lang === $from) {
                $component->lang = $to;
            }
        }
    }

    /**
     * Exports the stage into a zip file and sends it to browser
     *
     * @param string $filename filename to send
     */
    public function send_zip($filename) {
        global $CFG, $USER;

        $tmpdir = $CFG->dataroot . '/amos/temp/send-zip/' . md5(time() . '-' . $USER->id . '-'. random_string(20));
        $zipfile = $tmpdir . '.zip';
        remove_dir($tmpdir);
        check_dir_exists($tmpdir);

        $files = array();

        foreach ($this as $component) {
            if ($component->get_number_of_strings() > 0) {
                $zipfilepath = $component->version->dir . '/' . $component->lang . '/' . $component->name . '.php';
                $realfilepath = $tmpdir . '/' . $zipfilepath;
                $files[$zipfilepath] = $realfilepath;
                check_dir_exists(dirname($realfilepath));
                $component->export_phpfile($realfilepath);
            }
        }
        $packer = get_file_packer('application/zip');
        $packer->archive_to_pathname($files, $zipfile);
        remove_dir($tmpdir);
        send_file($zipfile, $filename, null, 0, false, true, 'application/zip', true);
        unlink($zipfile);
        die();
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

        foreach ($this->stage as $component) {
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
        // If the stash file is not referenced any more, delete it.
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
     * This should be called exclusively by {@see self::instance_from_id()}
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

    /** @var int Branch code of the version: 20, 21, ... 39, 310, 311, 400, ... */
    public $code;

    /** @var string Human-readable label of the version: 2.0, 3.9, 3.10, 3.11, 4.0dev, DEV, ... */
    public $label;

    /** @var string Name of the corresponding Git branch: MOODLE_39_STABLE, MOODLE_310_STABLE, ... */
    public $branch;

    /** @var string Name of the directory under https://download.moodle.org/langpack/ - 3.8, 3.9, 3.10, ... */
    public $dir;

    /** @var bool Allow translations of strings on this branch? */
    public $translatable;

    /** @var bool Is this a version that translators should focus on? Deprecated - use {@see self::latest_version()} instead. */
    public $current;

    /**
     * Get instance by the branch code.
     *
     * @param int $code Branch code of the version: 20, 21, ... 39, 310, 311, 400, ...
     * @return mlang_version
     */
    public static function by_code($code) {

        if (preg_match('/^(\d)(\d)$/', $code, $m)) {
            return static::create_or_reuse_instance((int) $code, (string) ($m[1] . '.' . $m[2]));

        } else if (preg_match('/^(\d){3,}$/', $code)) {
            $x = floor($code / 100);
            $y = $code - $x * 100;
            return static::create_or_reuse_instance((int) $code, (string) ($x . '.' . $y));

        } else {
            throw new mlang_exception('Unexpected version code');
        }
    }

    /**
     * Get instance by branch name.
     *
     * @param string $branch Branch name like 'MOODLE_310_STABLE'
     * @return mlang_version
     */
    public static function by_branch($branch) {

        if (preg_match('/^MOODLE_(\d{2,})_STABLE$/', $branch, $m)) {
            return self::by_code($m[1]);

        } else {
            throw new mlang_exception('Unexpected branch name');
        }
    }

    /**
     * Get instance by directory name (which mostly matches the label, too).
     *
     * @param string $dir like '3.1' or '3.10'
     * @return mlang_version|null
     */
    public static function by_dir($dir) {

        if (preg_match('/^(\d+)\.(\d+)$/', $dir, $m)) {
            if (version_compare($dir, '3.9', '<=')) {
                return self::by_code($m[1] * 10 + $m[2]);

            } else {
                return self::by_code($m[1] * 100 + $m[2]);
            }

        } else {
            throw new mlang_exception('Unexpected dir name');
        }
    }

    /**
     * Get a list of all known versions and information about them.
     *
     * @return array of mlang_version indexed by version code
     */
    public static function list_all(): array {

        $codes = get_config('local_amos', 'branchesall');
        $codes = array_filter(array_map('trim', explode(',', $codes)));
        sort($codes, SORT_NUMERIC);
        $list = [];

        foreach ($codes as $code) {
            $list[$code] = self::by_code($code);
        }

        return $list;
    }

    /**
     * List of versions starting since the given branch code, optionally up to the given end (inclusive).
     *
     * @param int $start The branch code of the first version in the returned list.
     * @param int $end Optional branch code of the last version in the returned list
     * @return array mlang_version[] indexed by version code
     */
    public static function list_range(int $start, ?int $end = null): array {

        $result = [];

        foreach (self::list_all() as $mver) {
            if ($mver->code < $start) {
                continue;
            }

            if ($end !== null && $mver->code > $end) {
                break;
            }

            $result[$mver->code] = $mver;
        }

        return $result;
    }

    /**
     * List of versions that are supported upstream.
     *
     * AMOS will track for English strings on these branches and generate installer language packs for them.
     *
     * @return array
     */
    public static function list_supported(): array {

        return self::list_range(get_config('local_amos', 'branchsupported'));
    }

    /**
     * Return the most recent known version.
     *
     * @return mlang_version
     */
    public static function latest_version() {

        $all = static::list_all();

        return array_pop($all);
    }

    /**
     * Return the oldest known version.
     *
     * @return mlang_version
     */
    public static function oldest_version() {

        $all = static::list_all();

        return array_shift($all);
    }

    /**
     * Used by factory methods to create instances of this class.
     *
     * @param int $code
     * @param string $dir
     */
    protected static function create_or_reuse_instance(int $code, string $dir): mlang_version {
        static $instances = [];

        if (!isset($instances[$code])) {
            $instances[$code] = new static($code, $dir);
        }

        return $instances[$code];
    }

    /**
     * To be used by {@see self::create_or_reuse_instance()} only.
     *
     * @param int $code
     * @param string $dir
     */
    protected function __construct(int $code, string $dir) {

        $this->code = $code;
        $this->dir = $dir;
        $this->label = $dir;
        $this->branch = 'MOODLE_' . $code . '_STABLE';
        $this->translatable = ($code >= 20);
        $this->current = false;
    }
}

/**
 * Class providing various AMOS-related tools
 */
class mlang_tools {

    /** Success return status of {@see self::execute()} */
    const STATUS_OK = 0;

    /** Error return status of {@see self::execute()} */
    const STATUS_SYNTAX_ERROR = -1;

    /** Error return status of {@see self::execute()} */
    const STATUS_UNKNOWN_INSTRUCTION = -2;

    /**
     * Returns a list of all languages known in the strings repository
     *
     * The language must have defined its international name in langconfig.
     * This method takes the value from the most recent branch and time stamp.
     *
     * @param bool $english shall the English be included?
     * @param bool $usecache can the internal cache be used?
     * @param bool $showcode append language code to the name
     * @param bool $showlocal also show the language name in the language itself
     * @return array (string)langcode => (string)langname
     */
    public static function list_languages($english=true, $usecache=true, $showcode=true, $showlocal=false) {
        global $DB;

        $cache = cache::make('local_amos', 'lists');
        $langs = false;

        if ($usecache) {
            $langs = $cache->get('languages');
        }

        if ($langs === false) {
            $langs = [];
            $sql = "
                    SELECT 'en' AS code, strname, strtext AS name, since, timemodified
                      FROM {amos_strings}
                     WHERE component = 'langconfig'
                       AND (strname = 'thislanguageint' OR strname = 'thislanguage')
                       AND strtext IS NOT NULL

                     UNION

                    SELECT lang AS code, strname, strtext AS name, since, timemodified
                      FROM {amos_translations}
                     WHERE component = 'langconfig'
                       AND (strname = 'thislanguageint' OR strname = 'thislanguage')
                       AND strtext IS NOT NULL

                  ORDER BY since DESC, strname DESC, timemodified DESC, name";

            $rs = $DB->get_recordset_sql($sql);

            foreach ($rs as $lang) {
                if (!isset($langs[$lang->code][$lang->strname])) {
                    // Use the first returned value, all others are historical records.
                    $langs[$lang->code][$lang->strname] = $lang->name;
                }
            }

            $rs->close();
            asort($langs);
            $cache->set('languages', $langs);
        }

        $result = [];

        foreach ($langs as $code => $names) {
            $lang = $names['thislanguageint'] ?? '???';

            if ($showlocal && $code !== 'en' && substr($code, 0, 3) !== 'en_') {
                $lang .= ' / ' . ($names['thislanguage'] ?? '???');
            }

            if ($showcode) {
                $lang .= ' [' . $code . ']';
            }

            $result[$code] = $lang;
        }

        if ($english) {
            return $result;
        } else {
            return array_diff_key($result, ['en' => '']);
        }
    }

    /**
     * Returns the list of all known components and the first version in which they have a string.
     *
     * The component must exist in English to be returned.
     *
     * @return array (string)component name => (int)since branch code
     */
    public static function list_components() {
        global $DB;

        $cache = cache::make('local_amos', 'lists');
        $components = $cache->get('components');

        if ($components === false) {

            $sql = "SELECT component, MIN(since) AS since
                      FROM {amos_strings}
                  GROUP BY component
                  ORDER BY component";

            $rs = $DB->get_recordset_sql($sql);
            $components = [];

            foreach ($rs as $record) {
                $components[$record->component] = $record->since;
            }

            $rs->close();
            $cache->set('components', $components);
        }

        return $components;
    }

    /**
     * For the given user, returns a list of languages she/he is allowed commit into.
     *
     * Language code 'X' has a special meaning - the user is allowed to edit all languages.
     *
     * @param int $userid User id, defaults to the current user
     * @return array List of (string)langcode => (string)langcode
     */
    public static function list_allowed_languages(?int $userid = null): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot.'/local/amos/locallib.php');

        if ($userid === null) {
            $userid = $USER->id;
        }

        $records = $DB->get_records('amos_translators', ['userid' => $userid, 'status' => AMOS_USER_MAINTAINER]);
        $langs = [];

        foreach ($records as $record) {
            $langs[$record->lang] = $record->lang;
        }

        return $langs;
    }

    /**
     * Given a text, extracts AMOS script lines from it as array of commands
     *
     * See {@link https://docs.moodle.org/dev/Languages/AMOS} for the specification
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

        // Collapse all whitespace into single space.
        $script = preg_replace('/\s+/', ' ', trim($matches[2]));

        // We need explicit list of known commands so that this parser can handle one-liners well.
        $cmds = array('MOV', 'CPY', 'FCP', 'HLP', 'REM');

        // Put new line character before every known command.
        $cmdsfrom = array();
        $cmdsto   = array();
        foreach ($cmds as $cmd) {
            $cmdsfrom[] = "$cmd ";
            $cmdsto[]   = "\n$cmd ";
        }
        $script = str_replace($cmdsfrom, $cmdsto, $script);

        // Make array of non-empty lines.
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
            // phpcs:disable Squiz.PHP.CommentedOutCode
            case 'CPY':
                // CPY [sourcestring,sourcecomponent],[targetstring,targetcomponent].
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
            case 'FCP':
                // FCP [sourcestring,sourcecomponent],[targetstring,targetcomponent].
                if (preg_match('/\[(.+),(.+)\]\s*,\s*\[(.+),(.+)\]/', $arg, $matches)) {
                    array_map('trim', $matches);
                    $fromcomponent = self::legacy_component_name($matches[2]);
                    $tocomponent = self::legacy_component_name($matches[4]);
                    if ($fromcomponent and $tocomponent) {
                        return self::forced_copy_string($version, $matches[1], $fromcomponent,
                            $matches[3], $tocomponent, $timestamp);
                    } else {
                        return self::STATUS_SYNTAX_ERROR;
                    }
                } else {
                    return self::STATUS_SYNTAX_ERROR;
                }
                break;
            case 'MOV':
                // MOV [sourcestring,sourcecomponent],[targetstring,targetcomponent].
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
                // HLP feedback/preview.html,[preview_hlp,mod_feedback].
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
                // Todo send message to subscribed users.
                return self::STATUS_OK;
                break;
                // WARNING: If a new command is added here, it must be also put into the list of known
                // commands in self::extract_script_from_text(). It is not nice but we use new line
                // as the delimiter and git may strip new lines if the script is part of the subject line.
            default:
                return self::STATUS_UNKNOWN_INSTRUCTION;
            // phpcs:enable
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

        if ($source->version->code <= 19) {
            $sourceformat = 1;
        } else {
            $sourceformat = 2;
        }

        if ($target->version->code <= 19) {
            throw new mlang_exception('Can not merge into Moodle 1.x branches');
        } else {
            $targetformat = 2;
        }

        foreach ($source as $string) {
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

    /**
     * Automatically backport translations to lower versions if they apply.
     *
     * A typical use case is when plugin English strings are registered on version X and translated. And then the X - 1
     * version is registered. Without backporting, that translations would exist since X only. But we want the
     * translations of identical strings be backported from X to X -1 automatically.
     *
     * Backporting does not happen on 'en' and 'en_fix' languages.
     *
     * @param string $componentname the name of the component
     * @param array $languages optional list of language codes (defaults to all languages)
     */
    public static function backport_translations($componentname, array $languages = []) {
        global $DB;

        // Find all the candidate strings for backporting. That is strings available in English since version X but with
        // translation available only since X + n.
        $params = [
            'component' => $componentname,
        ];

        if (empty($languages)) {
            // Perform backporting in all language packs but en_fix.
            $langsql = "<> :excludelang0";
            $langparams = ['excludelang0' => 'en_fix'];

        } else {
            // Check to see if there is some language left after removing en_fix and quite early eventually.
            $languages = array_diff($languages, ['en', 'en_fix']);

            if (empty($languages)) {
                return;
            }

            [$langsql, $langparams] = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED);
        }

        $params += $langparams;

        $sql = "SELECT t.lang, s.strname, s.englishsince, MIN(t.since) AS translatedsince
                  FROM (
                           SELECT component, strname, MIN(since) AS englishsince
                             FROM {amos_strings}
                            WHERE component = :component
                         GROUP BY component, strname
                       ) s
             LEFT JOIN {amos_translations} t
                    ON s.component = t.component
                   AND s.strname = t.strname
                 WHERE t.lang $langsql
              GROUP BY t.lang, s.component, s.strname, s.englishsince
                HAVING s.englishsince < MIN(t.since)";

        $candidates = [];
        $rs = $DB->get_recordset_sql($sql, $params);

        foreach ($rs as $r) {
            $candidates[] = $r;
        }

        $rs->close();

        foreach ($candidates as $candidate) {
            foreach (mlang_version::list_range($candidate->englishsince, $candidate->translatedsince) as $branchto) {
                // Get the snapshot of the English strings on the version we are backporting to.
                $englishto = mlang_component::from_snapshot($componentname, 'en', $branchto, null, false, false,
                    [$candidate->strname]);

                if (!$englishto->has_string()) {
                    // There is no non-deleted English string on this version yet. Go higher.
                    continue;
                }

                $transto = mlang_component::from_snapshot($componentname, $candidate->lang, $branchto, null, false, false,
                    [$candidate->strname]);

                // Seek for higher versions to see if there is some translation we can backport from.
                foreach (mlang_version::list_range($branchto->code + 1, $candidate->translatedsince) as $branchfrom) {
                    $englishfrom = mlang_component::from_snapshot($componentname, 'en', $branchfrom,
                        null, false, false, [$candidate->strname]);
                    $transfrom = mlang_component::from_snapshot($componentname, $candidate->lang, $branchfrom,
                        null, false, false, [$candidate->strname]);

                    self::merge($transfrom, $transto);
                    $transto->intersect($englishto);

                    // Make sure that the English originals are equal.
                    foreach ($transto as $transtostring) {
                        $englishfromstring = $englishfrom->get_string($transtostring->id);
                        $englishtostring = $englishto->get_string($transtostring->id);
                        if ($englishfromstring === null
                                || $englishtostring === null
                                || mlang_string::differ($englishfromstring, $englishtostring)) {
                            $transto->unlink_string($transtostring->id);
                        }
                    }

                    // If there is something, commit it now and reload the snapshot.
                    if ($transto->has_string()) {
                        $stage = new mlang_stage();
                        $stage->add($transto);
                        $stage->commit(sprintf('Backport %s/%s/%s translation from %s to %s', $candidate->lang, $componentname,
                            $candidate->strname, $branchfrom->dir, $branchto->dir), [
                                'source' => 'backport',
                                'userinfo' => 'AMOS-bot <amos@moodle.org>',
                            ]);
                        $transto->clear();
                        $transto = mlang_component::from_snapshot($componentname, $candidate->lang, $branchto, null, false, false,
                            [$candidate->strname]);
                    }

                    $englishfrom->clear();
                    $transfrom->clear();
                }
            }
        }
    }

    /**
     * Copy one string to another at the given version branch for all languages in the repository
     *
     * Deleted strings are not copied. If the target string already exists (and is not deleted), it is
     * not overwritten - compare with {@see self::forced_copy_string()}
     *
     * @param mlang_version $version to execute copying on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the copy, null for now
     * @return mlang_stage to be committed
     */
    protected static function copy_string(mlang_version $version, $fromstring, $fromcomponent,
            $tostring, $tocomponent, $timestamp=null) {

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
     * Copy one string to another at the given version branch for all languages in the repository
     *
     * Deleted strings are not copied. The target string is always overwritten, even if it already exists
     * or it is deleted - compare with {@see self::copy_string()}
     *
     * @param mlang_version $version to execute copying on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the copy, null for now
     * @return mlang_stage to be committed
     */
    protected static function forced_copy_string(mlang_version $version, $fromstring, $fromcomponent,
            $tostring, $tocomponent, $timestamp=null) {

        $stage = new mlang_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = mlang_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, array($fromstring));
            $to = mlang_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, array($tostring));
            if ($from->has_string($fromstring)) {
                $to->add_string(new mlang_string($tostring, $from->get_string($fromstring)->text, $timestamp), true);
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
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the move, null for now
     * @return mlang_stage to be committed
     */
    protected static function move_string(mlang_version $version, $fromstring, $fromcomponent,
            $tostring, $tocomponent, $timestamp=null) {

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
     * @param mlang_version $version
     * @param string $helpfile
     * @param string $tostring
     * @param string $tocomponent
     * @param int|null $timestamp
     * @return mlang_stage
     */
    protected static function migrate_helpfile($version, $helpfile, $tostring, $tocomponent, $timestamp=null) {
        global $CFG;
        // phpcs:ignore moodle.Files.RequireLogin
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

        // See {@see PARAM_COMPONENT}.
        if (!preg_match('/^[a-z]+(_[a-z][a-z0-9_]*)?[a-z0-9]+$/', $newstyle)) {
            return false;
        }

        if (strpos($newstyle, '__') !== false) {
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
