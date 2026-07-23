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
 * Staging area is a collection of components to be committed into the strings repository
 *
 * After obtaining a new instance and adding some components into it, you should either call commit()
 * or clear(). Otherwise, the copies of staged strings remain in PHP memory and they are not
 * garbage collected because of the circular reference component-string.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_stage implements \Countable, \IteratorAggregate {
    /** @var array of amos_component */
    protected $components = [];

    /**
     * Adds a copy of the given component into the staging area
     *
     * @param amos_component $component
     * @param bool $force replace the previously staged string if there is such if there is already
     * @return void
     */
    public function add(amos_component $component, $force = false) {
        $cid = $component->get_identifier();
        if (!isset($this->components[$cid])) {
            $this->components[$cid] = new amos_component($component->name, $component->lang, $component->version);
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
        $this->components = [];
    }

    /**
     * Check the staged strings against the repository cap and keep modified strings only
     *
     * @param int|null $basetimestamp the timestamp to rebase against, null for the most recent
     * @param bool $deletemissing if true, then all strings that are in repository but not in stage will be marked as to be deleted
     * @param int $deletetimestamp if $deletemissing is true, what timestamp to use when removing strings (defaults to current)
     */
    public function rebase($basetimestamp = null, $deletemissing = false, $deletetimestamp = null) {

        if (!is_bool($deletemissing)) {
            throw new \coding_exception('Incorrect type of the parameter $deletemissing');
        }

        foreach ($this->components as $cx => $component) {
            $cap = amos_component::from_snapshot($component->name, $component->lang, $component->version, $basetimestamp, true);
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
                if (!amos_string::differ($stagedstring, $capstring)) {
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
    public function commit($message = '', array $meta = null, $skiprebase = false, $timecommitted = null, $clear = true): int {
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
            $commit = new \stdclass();
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
                $cache = \cache::make('local_amos', 'lists');
                $cache->purge();
            }

            if ($clear) {
                $this->clear();
            }

            return $commit->id;
        } catch (\Exception $e) {
            // This is here in order not to clear the stage, just re-throw the exception.
            $transaction->rollback($e);
        }
    }

    /**
     * Remove all components that do not belong to any of the given languages or the branch is not translatable via AMOS
     *
     * Also removes the langconfig changes if the user does not have permission to edit it.
     *
     * @param array $keeplangs (string)langcode => (string)langcode - list of languages to keep, 'X' means all languages
     */
    public function prune(array $keeplangs) {
        foreach ($this->components as $cx => $component) {
            if ($component->name === 'langconfig') {
                if (!has_capability('local/amos:editlangconfig', \context_system::instance())) {
                    // The user is unable to edit these strings, unstage them all.
                    $component->clear();
                    unset($this->components[$cx]);
                    continue;
                }
            }
            if (empty($component->version->translatable)) {
                // Commits not allowed into this branch via AMOS web interface.
                $component->clear();
                unset($this->components[$cx]);
                continue;
            }
            if (empty($keeplangs['X']) && empty($keeplangs[$component->lang])) {
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
     * @return \ArrayIterator
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->components);
    }

    // phpcs:enable

    /**
     * Return the count of components in the stage.
     *
     * @return int
     */
    public function count(): int {
        return count($this->components);
    }

    /**
     * Returns the staged component or null if not known
     *
     * @param string $name the name of the component, eg. 'role', 'glossary', 'datafield_text' etc.
     * @param string $lang
     * @param amos_version $version
     * @return amos_component|null
     */
    public function get_component($name, $lang, amos_version $version) {
        $cid = amos_component::calculate_identifier($name, $lang, $version);
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
     * @param amos_version $version
     * @return bool
     */
    public function has_component($name = null, $lang = null, amos_version $version = null) {
        if (is_null($name) && is_null($lang) && is_null($version)) {
            return (!empty($this->components));
        } else {
            $cid = amos_component::calculate_identifier($name, $lang, $version);
            return (isset($this->components[$cid]));
        }
    }

    /**
     * Returns the number of strings and the list of languages and components in the stage
     *
     * Languages and components lists are returned as strings, slash is used as a delimiter
     * and there is heading and trailing slash, too.
     *
     * @param amos_stage $stage Stage to be analyzed
     * @return array of (int)strings, (string)languages, (string)components
     */
    public static function analyze(amos_stage $stage) {
        $strings = 0;
        $languages = [];
        $components = [];

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
        $languages = '/' . implode('/', array_keys($languages)) . '/';
        $components = '/' . implode('/', array_keys($components)) . '/';

        return [$strings, $languages, $components];
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

        $tmpdir = $CFG->dataroot . '/amos/temp/send-zip/' . md5(time() . '-' . $USER->id . '-' . random_string(20));
        $zipfile = $tmpdir . '.zip';
        remove_dir($tmpdir);
        check_dir_exists($tmpdir);

        $files = [];

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
