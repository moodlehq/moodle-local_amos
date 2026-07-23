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
 * Class providing various AMOS-related tools
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_tools {
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
    public static function list_languages($english = true, $usecache = true, $showcode = true, $showlocal = false) {
        global $DB;

        $cache = \cache::make('local_amos', 'lists');
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

            uasort($langs, function ($a, $b) {
                return strcmp(
                    $a['thislanguageint'] ?? $a['thislanguage'],
                    $b['thislanguageint'] ?? $b['thislanguage']
                );
            });

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

        $cache = \cache::make('local_amos', 'lists');
        $components = $cache->get('components');

        // These components are to be excluded from processing.
        $excluded = [
            'local_moodleorg',
            'theme_moodleorg',
        ];

        if ($components === false) {
            [$excludedsql, $excludedparams] = $DB->get_in_or_equal($excluded, SQL_PARAMS_QM, 'param', false);

            $sql = "SELECT component, MIN(since) AS since
                      FROM {amos_strings}
                     WHERE component $excludedsql
                  GROUP BY component
                  ORDER BY component";

            $rs = $DB->get_recordset_sql($sql, $excludedparams);
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
        require_once($CFG->dirroot . '/local/amos/locallib.php');

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
            return [];
        }

        // Collapse all whitespace into single space.
        $script = preg_replace('/\s+/', ' ', trim($matches[2]));

        // We need explicit list of known commands so that this parser can handle one-liners well.
        $cmds = ['MOV', 'CPY', 'FCP', 'HLP', 'REM'];

        // Put new line character before every known command.
        $cmdsfrom = [];
        $cmdsto   = [];
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
     * @param amos_version $version strings branch to execute instruction on
     * @param int $timestamp effective time of the execution
     * @return int|amos_stage amos_stage to commit, 0 if success and there is nothing to commit, error code otherwise
     */
    public static function execute($instruction, amos_version $version, $timestamp = null) {
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
            case 'MOV':
                // CPY [sourcestring,sourcecomponent],[targetstring,targetcomponent].
                // MOV [sourcestring,sourcecomponent],[targetstring,targetcomponent].
                if (preg_match('/\[(.+),(.+)\]\s*,\s*\[(.+),(.+)\]/', $arg, $matches)) {
                    array_map('trim', $matches);
                    $fromcomponent = self::legacy_component_name($matches[2]);
                    $tocomponent = self::legacy_component_name($matches[4]);
                    if ($fromcomponent && $tocomponent) {
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
                    if ($fromcomponent && $tocomponent) {
                        return self::forced_copy_string(
                            $version,
                            $matches[1],
                            $fromcomponent,
                            $matches[3],
                            $tocomponent,
                            $timestamp
                        );
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
                    if ($helpfile && $tocomponent && $tostring) {
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
     * @param amos_component $source component to take strings from
     * @param amos_component $target component to add strings to
     * @return void modifies $target component
     */
    public static function merge(amos_component $source, amos_component $target) {

        if ($source->version->code <= 19) {
            $sourceformat = 1;
        } else {
            $sourceformat = 2;
        }

        if ($target->version->code <= 19) {
            throw new amos_exception('Can not merge into Moodle 1.x branches');
        } else {
            $targetformat = 2;
        }

        foreach ($source as $string) {
            $stringid = clean_param($string->id, PARAM_STRINGID);
            if (empty($stringid)) {
                throw new amos_exception('Invalid string identifier ' . s($string->id));
            }
            if (!$target->has_string($stringid)) {
                $text = amos_string::fix_syntax($string->text, $targetformat, $sourceformat);
                $target->add_string(new amos_string($stringid, $text));
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

        $affected = [];
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
            foreach (amos_version::list_range($candidate->englishsince, $candidate->translatedsince) as $branchto) {
                // Get the snapshot of the English strings on the version we are backporting to.
                $englishto = amos_component::from_snapshot(
                    $componentname,
                    'en',
                    $branchto,
                    null,
                    false,
                    false,
                    [$candidate->strname]
                );

                if (!$englishto->has_string()) {
                    // There is no non-deleted English string on this version yet. Go higher.
                    continue;
                }

                $transto = amos_component::from_snapshot(
                    $componentname,
                    $candidate->lang,
                    $branchto,
                    null,
                    false,
                    false,
                    [$candidate->strname]
                );

                // Seek for higher versions to see if there is some translation we can backport from.
                foreach (amos_version::list_range($branchto->code + 1, $candidate->translatedsince) as $branchfrom) {
                    $englishfrom = amos_component::from_snapshot(
                        $componentname,
                        'en',
                        $branchfrom,
                        null,
                        false,
                        false,
                        [$candidate->strname]
                    );
                    $transfrom = amos_component::from_snapshot(
                        $componentname,
                        $candidate->lang,
                        $branchfrom,
                        null,
                        false,
                        false,
                        [$candidate->strname]
                    );

                    self::merge($transfrom, $transto);
                    $transto->intersect($englishto);

                    // Make sure that the English originals are equal.
                    foreach ($transto as $transtostring) {
                        $englishfromstring = $englishfrom->get_string($transtostring->id);
                        $englishtostring = $englishto->get_string($transtostring->id);
                        if (
                            $englishfromstring === null
                                || $englishtostring === null
                                || amos_string::differ($englishfromstring, $englishtostring)
                        ) {
                            $transto->unlink_string($transtostring->id);
                        }
                    }

                    // If there is something, commit it now and reload the snapshot.
                    if ($transto->has_string()) {
                        $stage = new amos_stage();
                        $stage->add($transto);
                        $stage->commit(sprintf(
                            'Backport %s/%s/%s translation from %s to %s',
                            $candidate->lang,
                            $componentname,
                            $candidate->strname,
                            $branchfrom->dir,
                            $branchto->dir
                        ), [
                                'source' => 'backport',
                                'userinfo' => 'AMOS-bot <amos@moodle.org>',
                            ]);
                        $transto->clear();
                        $transto = amos_component::from_snapshot(
                            $componentname,
                            $candidate->lang,
                            $branchto,
                            null,
                            false,
                            false,
                            [$candidate->strname]
                        );
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
     * @param amos_version $version to execute copying on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the copy, null for now
     * @return amos_stage to be committed
     */
    protected static function copy_string(
        amos_version $version,
        $fromstring,
        $fromcomponent,
        $tostring,
        $tocomponent,
        $timestamp = null
    ) {

        $stage = new amos_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = amos_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, [$fromstring]);
            $to = amos_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, [$tostring]);
            if ($from->has_string($fromstring) && !$to->has_string($tostring)) {
                $to->add_string(new amos_string($tostring, $from->get_string($fromstring)->text, $timestamp));
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
     * @param amos_version $version to execute copying on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the copy, null for now
     * @return amos_stage to be committed
     */
    protected static function forced_copy_string(
        amos_version $version,
        $fromstring,
        $fromcomponent,
        $tostring,
        $tocomponent,
        $timestamp = null
    ) {

        $stage = new amos_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = amos_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, [$fromstring]);
            $to = amos_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, [$tostring]);
            if ($from->has_string($fromstring)) {
                $to->add_string(new amos_string($tostring, $from->get_string($fromstring)->text, $timestamp), true);
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
     * Note: This function is not actually used any more. The MOV command is now an alias of CPY and
     * strings are kept in the language packs.
     *
     * @param amos_version $version to execute moving on
     * @param string $fromstring source string identifier
     * @param string $fromcomponent source component name
     * @param string $tostring target string identifier
     * @param string $tocomponent target component name
     * @param int $timestamp effective timestamp of the move, null for now
     * @return amos_stage to be committed
     */
    protected static function move_string(
        amos_version $version,
        $fromstring,
        $fromcomponent,
        $tostring,
        $tocomponent,
        $timestamp = null
    ) {

        $stage = new amos_stage();
        foreach (array_keys(self::list_languages(false)) as $lang) {
            $from = amos_component::from_snapshot($fromcomponent, $lang, $version, $timestamp, false, false, [$fromstring]);
            $to = amos_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, [$tostring]);
            if ($src = $from->get_string($fromstring)) {
                $from->add_string(new amos_string($fromstring, $from->get_string($fromstring)->text, $timestamp, true), true);
                $stage->add($from);
                if (!$to->has_string($tostring)) {
                    $to->add_string(new amos_string($tostring, $src->text, $timestamp));
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
     * @param amos_version $version
     * @param string $helpfile
     * @param string $tostring
     * @param string $tocomponent
     * @param int|null $timestamp
     * @return amos_stage
     */
    protected static function migrate_helpfile($version, $helpfile, $tostring, $tocomponent, $timestamp = null) {
        global $CFG;
        // phpcs:ignore moodle.Files.RequireLogin
        require_once($CFG->dirroot . '/local/amos/cli/config.php');

        $stage = new amos_stage();
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
            $to = amos_component::from_snapshot($tocomponent, $lang, $version, $timestamp, false, false, [$tostring]);
            if (!$to->has_string($tostring)) {
                $to->add_string(new amos_string($tostring, $helpstring, $timestamp));
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
