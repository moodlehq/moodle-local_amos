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
 * Defines {@see \local_amos\privacy\provider} class.
 *
 * @package     local_amos
 * @category    privacy
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Privacy API implementation for the AMOS plugin.
 *
 * @copyright  2018 David Mudrák <david@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe all the places where the AMOS plugin stores some personal data.
     *
     * @param collection $collection Collection of items to add metadata to.
     * @return collection Collection with our added items.
     */
    public static function get_metadata(collection $collection) : collection {

        $collection->add_database_table('amos_commits', [
           'commitmsg' => 'privacy:metadata:db:amoscommits:commitmsg',
           'timecommitted' => 'privacy:metadata:db:amoscommits:timecommitted',
           'userinfo' => 'privacy:metadata:db:amoscommits:userinfo',
        ], 'privacy:metadata:db:amoscommits');

        $collection->add_database_table('amos_translators', [
           'lang' => 'privacy:metadata:db:amostranslators:lang',
           'status' => 'privacy:metadata:db:amostranslators:status',
        ], 'privacy:metadata:db:amostranslators');

        $collection->add_database_table('amos_stashes', [
           'id' => 'privacy:metadata:db:amosstashes:id',
           'languages' => 'privacy:metadata:db:amosstashes:languages',
           'components' => 'privacy:metadata:db:amosstashes:components',
           'strings' => 'privacy:metadata:db:amosstashes:strings',
           'timecreated' => 'privacy:metadata:db:amosstashes:timecreated',
           'timemodified' => 'privacy:metadata:db:amosstashes:timemodified',
           'name' => 'privacy:metadata:db:amosstashes:name',
           'message' => 'privacy:metadata:db:amosstashes:message',
        ], 'privacy:metadata:db:amosstashes');

        $collection->add_database_table('amos_contributions', [
           'lang' => 'privacy:metadata:db:amoscontributions:lang',
           'subject' => 'privacy:metadata:db:amoscontributions:subject',
           'message' => 'privacy:metadata:db:amoscontributions:message',
           'stashid' => 'privacy:metadata:db:amoscontributions:stashid',
           'status' => 'privacy:metadata:db:amoscontributions:status',
           'timecreated' => 'privacy:metadata:db:amoscontributions:timecreated',
           'timemodified' => 'privacy:metadata:db:amoscontributions:timemodified',
        ], 'privacy:metadata:db:amoscontributions');

        $collection->add_database_table('amos_preferences', [
           'name' => 'privacy:metadata:db:amospreferences:name',
           'value' => 'privacy:metadata:db:amospreferences:value',
        ], 'privacy:metadata:db:amospreferences');

        $collection->add_subsystem_link('core_comment', [], 'privacy:metadata:subsystem:comment');

        $collection->add_external_location_link('languagepacks', [
           'firstname' => 'privacy:metadata:external:languagepacks:firstname',
           'lastname' => 'privacy:metadata:external:languagepacks:lastname',
           'email' => 'privacy:metadata:external:languagepacks:email',
        ], 'privacy:metadata:external:languagepacks');

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the specified user.
     *
     * @param int $userid ID of the user.
     * @return contextlist List of contexts containing the user's personal data.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {

        $contextlist = new contextlist();
        $contextlist->add_system_context();

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        // Commit authors.
        $sql = "SELECT userid FROM {amos_commits}";
        $userlist->add_from_sql('userid', $sql, []);

        // AMOS contributors.
        $sql = "SELECT userid FROM {amos_translators}";
        $userlist->add_from_sql('userid', $sql, []);

        // Users' preferences.
        $sql = "SELECT userid FROM {amos_preferences}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export personal data stored in the given contexts.
     *
     * @param approved_contextlist $contextlist List of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();

        $syscontextapproved = false;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->id == SYSCONTEXTID) {
                $syscontextapproved = true;
                break;
            }
        }

        if (!$syscontextapproved) {
            return;
        }

        static::export_user_data_strings($user->id);
        static::export_user_data_contributions($user->id);
        static::export_user_data_credits($user->id);
        static::export_user_data_stashes($user->id);
        static::export_user_data_preferences($user->id);
    }

    /**
     * Export strings translated by the user.
     *
     * @param int $userid
     */
    protected static function export_user_data_strings(int $userid) {
        global $DB;

        $sql = "SELECT c.id AS commitid, c.commitmsg, c.timecommitted, c.userinfo, c.source, c.commithash,
                       t.since, t.lang, t.component, t.strname, t.strtext
                  FROM {amos_commits} c
                  JOIN {amos_translations} t ON t.commitid = c.id
                 WHERE c.userid = :userid
              ORDER BY c.timecommitted";

        $params = [
            'userid' => $userid,
        ];

        $rs = $DB->get_recordset_sql($sql, $params);
        $prevcommitid = 0;
        $data = (object) [];
        $writer = writer::with_context(\context_system::instance());
        $subcontext = ['AMOS', get_string('strings', 'local_amos')];

        foreach ($rs as $r) {
            if ($prevcommitid != $r->commitid) {
                // Write what we have.
                if (!empty($data->strings)) {
                    $writer->export_data(array_merge($subcontext, [get_string('privacy:commitnumber', 'local_amos',
                        $prevcommitid)]), $data);
                }

                // Start gathering new data.
                $data = (object) [
                    'commitid' => $r->commitid,
                    'commitmsg' => $r->commitmsg,
                    'timecommitted' => transform::datetime($r->timecommitted),
                    'userinfo' => $r->userinfo,
                    'source' => $r->source,
                    'commithash' => $r->commithash,
                    'strings' => [],
                ];

                $prevcommitid = $r->commitid;
            }

            $data->strings[] = [
                'since' => $r->since,
                'lang' => $r->lang,
                'component' => $r->component,
                'deleted' => transform::yesno($r->strtext === null),
                'stringname' => $r->strname,
                'strtext' => $r->strtext,
            ];
        }

        // Write what remains.
        $writer->export_data(array_merge($subcontext, [get_string('privacy:commitnumber', 'local_amos', $prevcommitid)]), $data);

        $rs->close();
    }

    /**
     * Export strings translated by the user.
     *
     * @param int $userid
     */
    protected static function export_user_data_contributions(int $userid) {
        global $DB;

        $writer = writer::with_context(\context_system::instance());

        $sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified
                  FROM {amos_contributions} c
                 WHERE c.authorid = :userid
              ORDER BY c.timecreated";

        $params = [
            'userid' => $userid,
        ];

        foreach ($DB->get_records_sql($sql, $params) as $contrib) {
            $subcontext = [
                'AMOS',
                get_string('contributions', 'local_amos'),
                get_string('privacy:contribnumber', 'local_amos', $contrib->id),
            ];

            $contrib->timecreated = transform::datetime($contrib->timecreated);
            $contrib->timemodified = transform::datetime($contrib->timemodified);
            $contrib->status = get_string('contribstatus'.$contrib->status, 'local_amos');

            $writer->export_data($subcontext, $contrib);

            $stash = \mlang_stash::instance_from_id($contrib->stashid);
            $stage = new \mlang_stage();
            $stash->apply($stage);

            $strings = [];

            foreach ($stage as $component) {
                foreach ($component as $string) {
                    $strings[] = [
                        'branch' => $string->component->version->code,
                        'lang' => $string->component->lang,
                        'component' => $string->component->name,
                        'deleted' => transform::yesno($string->deleted),
                        'stringid' => $string->id,
                        'stringtext' => $string->text,
                    ];
                }
            }

            if ($strings) {
                $writer->export_related_data($subcontext, 'strings', $strings);
            }

            \core_comment\privacy\provider::export_comments(\context_system::instance(), 'local_amos', 'amos_contribution',
                $contrib->id, $subcontext);
        }
    }

    /**
     * Export translations credits for the user.
     *
     * @param int $userid
     */
    protected static function export_user_data_credits(int $userid) {
        global $DB;

        $data = [];

        foreach ($DB->get_records('amos_translators', ['userid' => $userid]) as $credit) {
            $add = (object) [
                'lang' => $credit->lang,
            ];

            if ($add->lang === 'X') {
                $add->lang = '* ('.get_string('any').')';
            }

            if ($credit->status == AMOS_USER_MAINTAINER) {
                $add->is_maintainer = transform::yesno(true);

            } else if ($credit->status == AMOS_USER_CONTRIBUTOR) {
                $add->is_contributor = transform::yesno(true);

            } else {
                // This should not happen but who knows what the future brings us.
                $add->status = $credit->status;
            }

            $data[] = $add;
        }

        if ($data) {
            writer::with_context(\context_system::instance())->export_related_data(['AMOS'], 'credits', $data);
        }
    }

    /**
     * Export user's stashes.
     *
     * @param int $userid
     */
    protected static function export_user_data_stashes(int $userid) {
        global $DB;

        $writer = writer::with_context(\context_system::instance());

        $stashes = $DB->get_records('amos_stashes', ['ownerid' => $userid], 'timecreated',
            'id, languages, components, strings, timecreated, timemodified, name, message');

        foreach ($stashes as $stashdata) {
            $subcontext = [
                'AMOS',
                get_string('stashes', 'local_amos'),
                get_string('privacy:stashnumber', 'local_amos', $stashdata->id),
            ];

            $stashdata->timecreated = transform::datetime($stashdata->timecreated);
            $stashdata->timemodified = transform::datetime($stashdata->timemodified);

            $writer->export_data($subcontext, $stashdata);

            $stash = \mlang_stash::instance_from_id($stashdata->id);
            $stage = new \mlang_stage();
            $stash->apply($stage);

            $strings = [];

            foreach ($stage as $component) {
                foreach ($component as $string) {
                    $strings[] = [
                        'branch' => $string->component->version->code,
                        'lang' => $string->component->lang,
                        'component' => $string->component->name,
                        'deleted' => transform::yesno($string->deleted),
                        'stringid' => $string->id,
                        'stringtext' => $string->text,
                    ];
                }
            }

            if ($strings) {
                $writer->export_related_data($subcontext, 'strings', $strings);
            }
        }
    }

    /**
     * Export user's preferences.
     *
     * @param int $userid
     */
    protected static function export_user_data_preferences(int $userid) {
        global $DB;

        $writer = writer::with_context(\context_system::instance());

        $data = (object) [];

        foreach ($DB->get_records('amos_preferences', ['userid' => $userid], 'name', 'id, name, value') as $preference) {
            $data->{$preference->name} = $preference->value;
        }

        $writer->export_data([
            'AMOS',
            get_string('preferences', 'core'),
        ], $data);
    }

    /**
     * Delete personal data for all users in the context.
     *
     * @param context $context Context to delete personal data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Not implemented yet.
    }

    /**
     * Delete personal data for the user in a list of contexts.
     *
     * @param approved_contextlist $contextlist List of contexts to delete data from.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        // Not implemented yet.
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        // Not implemented yet.
    }
}
