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
 * Represents a collection of strings for a given component
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_component implements \Countable, \IteratorAggregate {
    /** @var the name of the component, what {@see string_manager::get_string()} uses as the second param */
    public $name;

    /** @var string language code we are part of */
    public $lang;

    /** @var amos_version we are part of */
    public $version;

    /** @var amos_string[] holds instances of amos_string in an associative array */
    protected $strings = [];

    /**
     * Constructor.
     *
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param amos_version $version
     */
    public function __construct($name, $lang, amos_version $version) {

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
     * @param amos_version $version the branch to put this string on
     * @param int $timemodified use this as a timestamp of string modification instead of the filemtime()
     * @param string $name use this as a component name instead of guessing from the file name
     * @param int $format in what string format the file is written - 1 for 1.x string, 2 for 2.x strings
     * @throws Exception
     * @return amos_component
     */
    public static function from_phpfile(
        $filepath,
        $lang,
        amos_version $version,
        $timemodified = null,
        $name = null,
        $format = null
    ) {
        if (empty($name)) {
            $name = self::name_from_filename($filepath);
        }
        $component = new amos_component($name, $lang, $version);
        unset($string);
        if (is_readable($filepath)) {
            if (empty($timemodified)) {
                $timemodified = filemtime($filepath);
            }
            // Empty placeholder to prevent PHP notices.
            $a = '';
            include($filepath);
        } else {
            throw new \Exception('Strings definition file ' . $filepath . ' not readable');
        }
        if ($version->code <= 19) {
            // We are going to import strings for 1.x branch.
            $target = 1;
            if (is_null($format)) {
                $format = 1;
            }
            if ($format !== 1) {
                throw new \coding_exception('For Moodle 1.x, only strings in legacy format are supported.');
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
                $value = amos_string::fix_syntax($value, $target, $format);
                $component->add_string(new amos_string($id, $value, $timemodified), true);
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
     * @param amos_version $version Version of the component.
     * @param int|null $timestamp Time of the snapshot, defaults to the most recent one.
     * @param bool $deleted Shall deleted strings be included?
     * @param bool $fullinfo Shall full information about the string (commit messages, source etc.) be returned?
     * @param array|null $strnames Limit the list of loaded strings to ones in this list only.
     * @return amos_component Component with the strings from the snapshot.
     */
    public static function from_snapshot(
        string $name,
        string $lang,
        amos_version $version,
        ?int $timestamp = null,
        bool $deleted = false,
        bool $fullinfo = false,
        ?array $strnames = null
    ): amos_component {
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
            [$strsql, $strparams] = $DB->get_in_or_equal($strnames, SQL_PARAMS_NAMED);
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

        $component = new amos_component($name, $lang, $version);

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

            $component->add_string(new amos_string(
                $str->strname,
                $str->strtext,
                $str->timemodified,
                $str->strtext === null,
                $extra
            ), true);
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
     * @param amos_version $version
     * @return string
     */
    public static function calculate_identifier($name, $lang, amos_version $version) {
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
     * @return \ArrayIterator
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->strings);
    }

    // phpcs:enable

    /**
     * Return the number of strings in the component.
     *
     * @return int
     */
    public function count(): int {
        return count($this->strings);
    }

    /**
     * Returns the string object or null if not known
     *
     * @param string $id string identifier
     * @return amos_string|null
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
    public function has_string($id = null) {
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
     * @param amos_string $string to add
     * @param bool $force if true, existing string will be replaced
     * @throws coding_exception when trying to add existing component without $force
     * @return void
     */
    public function add_string(amos_string $string, $force = false) {

        if (!$force && isset($this->strings[$string->id])) {
            throw new \coding_exception('You are trying to add a string \'' . $string->id .
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
                $knownlangs = amos_tools::list_languages();
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
    public function get_phpfile_location($treeish = true) {
        global $CFG;

        if ($this->version->code <= 19) {
            // Moodle 1.x.
            return 'lang/' . $this->lang . '_utf8/' . $this->name . '.php';
        } else {
            // Moodle 2.x.
            if ($treeish) {
                debugging('The method get_phpfile_location() may produce wrong results as ' .
                    'it is unable to differentiate core plugins from activity modules. ' .
                    'Using normalize_component() is not reliable much because it depends ' .
                    'on the site version and may be wrong for older/newer versions');
                [$type, $plugin] = \core_component::normalize_component($this->name);
                if ($type === 'core') {
                    return 'lang/' . $this->lang . '/' . $this->name . '.php';
                } else {
                    $abspath = get_plugin_directory($type, $plugin);
                    if (substr($abspath, 0, strlen($CFG->dirroot)) !== $CFG->dirroot) {
                        throw new \coding_exception('Plugin directory outside dirroot', $abspath);
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
     * the English pack.
     * Beware - if the string is defined in $mask as deleted, it will be kept in this regardless
     * its state.
     *
     * @param amos_component $mask component to compare strings with
     * @return int number of removed strings
     */
    public function intersect(amos_component $mask) {
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
     * @param amos_component $reference component to compare strings with
     * @return array list of removed string ids
     */
    public function complement(amos_component $reference) {

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
            if (amos_string::differ($string, $reference->get_string($id))) {
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
     * @see amos_string::clean_text()
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
     * Fix the amos_version after waking up from the serialization.
     */
    public function __wakeup() {

        if ($this->version->code >= 1600) {
            $this->version = amos_version::by_code($this->version->code / 100);
        } else {
            $this->version = amos_version::by_code($this->version->code);
        }
    }
}
