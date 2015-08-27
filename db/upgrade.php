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
 * AMOS upgrade scripts
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_local_amos_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2010090103) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_stashes');
        upgrade_plugin_savepoint(true, 2010090103, 'local', 'amos');
    }

    if ($oldversion < 2010090107) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_hidden_requests');
        upgrade_plugin_savepoint(true, 2010090107, 'local', 'amos');
    }

    if ($oldversion < 2010110400) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_greylist');
        upgrade_plugin_savepoint(true, 2010110400, 'local', 'amos');
    }

    if ($oldversion < 2011010600) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_contributions');
        upgrade_plugin_savepoint(true, 2011010600, 'local', 'amos');
    }

    if ($oldversion < 2011011000) {
        require_once(dirname(dirname(__FILE__)).'/mlanglib.php');

        // convert legacy stashes that were pull-requested
        $stashids = $DB->get_records('amos_stashes', array('pullrequest' => 1), 'timemodified ASC', 'id');

        foreach ($stashids as $stashrecord) {
            $stash = mlang_stash::instance_from_id($stashrecord->id);

            // split the stashed components into separate packages by their language
            $stage = new mlang_stage();
            $langstages = array();  // (string)langcode => (mlang_stage)
            $stash->apply($stage);
            foreach ($stage->get_iterator() as $component) {
                $lang = $component->lang;
                if (!isset($langstages[$lang])) {
                    $langstages[$lang] = new mlang_stage();
                }
                $langstages[$lang]->add($component);
            }
            $stage->clear();
            unset($stage);

            // create new contribution record for every language and attach a new stash to it
            foreach ($langstages as $lang => $stage) {
                if (!$stage->has_component()) {
                    // this should not happen, but...
                    continue;
                }
                $copy = new mlang_stage();
                foreach ($stage->get_iterator() as $component) {
                    $copy->add($component);
                }
                $copy->rebase();
                if ($copy->has_component()) {
                    $tostatus = 0;  // new
                } else {
                    $tostatus = 30; // nothing left after rebase - consider it accepted
                }

                $langstash = mlang_stash::instance_from_stage($stage, 0, $stash->name);
                $langstash->message = $stash->message;
                $langstash->push();

                $contribution               = new stdClass();
                $contribution->authorid     = $stash->ownerid;
                $contribution->lang         = $lang;
                $contribution->assignee     = null;
                $contribution->subject      = $stash->name;
                $contribution->message      = $stash->message;
                $contribution->stashid      = $langstash->id;
                $contribution->status       = $tostatus;
                $contribution->timecreated  = $stash->timemodified;
                $contribution->timemodified = null;

                $contribution->id = $DB->insert_record('amos_contributions', $contribution);

                // add a comment there
                $comment = new stdClass();
                $comment->contextid = SITEID;
                $comment->commentarea = 'amos_contribution';
                $comment->itemid = $contribution->id;
                $comment->content = 'This contribution was automatically created during the conversion of legacy pull-requested stashes.';
                $comment->format = 0;
                $comment->userid = 2;
                $comment->timecreated = time();
                $DB->insert_record('comments', $comment);
            }
            $stash->drop();
        }

        upgrade_plugin_savepoint(true, 2011011000, 'local', 'amos');
    }

    if ($oldversion < 2011011001) {

        $table = new xmldb_table('amos_stashes');

        $field = new xmldb_field('shared');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('pullrequest');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $table = new xmldb_table('amos_hidden_requests');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2011011001, 'local', 'amos');
    }

    // Add new table amos_snapshot
    if ($oldversion < 2013040400) {

        $table = new xmldb_table('amos_snapshot');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('branch', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lang', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stringid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('repoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ix_repoid', XMLDB_KEY_FOREIGN, array('repoid'), 'amos_repository', array('id'));

        $table->add_index('ix_snapshot', XMLDB_INDEX_UNIQUE, array('component', 'lang', 'branch', 'stringid'));
        $table->add_index('ix_lang', XMLDB_INDEX_NOTUNIQUE, array('lang'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2013040400, 'local', 'amos');
    }

    // Add new table amos_filter_usage
    if ($oldversion < 2013101500) {
        $table = new xmldb_table('amos_filter_usage');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sesskey', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userlang', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currentlang', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usercountry', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ismaintainer', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usesdefaultversion', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usesdefaultlang', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('numofversions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('numoflanguages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('numofcomponents', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showmissingonly', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showhelpsonly', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('withsubstring', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('substringregex', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('substringcasesensitive', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('withstringid', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('stringidpartial', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showstagedonly', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showgreylistedonly', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showwithoutgreylisted', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('page', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Amos savepoint reached.
        upgrade_plugin_savepoint(true, 2013101500, 'local', 'amos');
    }

    // Add table amos_texts
    if ($oldversion < 2013121900) {
        $table = new xmldb_table('amos_texts');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('texthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('text', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('ix_texthash', XMLDB_INDEX_UNIQUE, array('texthash'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2013121900, 'local', 'amos');
    }

    // Add field textid to amos_repository
    if ($oldversion < 2013121901) {
        $table = new xmldb_table('amos_repository');
        $field = new xmldb_field('textid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'text');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('amos_repository');
        $key = new xmldb_key('fk_text', XMLDB_KEY_FOREIGN, array('textid'), 'amos_texts', array('id'));
        $dbman->add_key($table, $key);

        upgrade_plugin_savepoint(true, 2013121901, 'local', 'amos');
    }

    // Note - after this upgrade step, it is expected that you manually run
    // cli/init-texts.php tool and then modify the database. We do not perform
    // that step here in order to prevent accidental data loss for sites
    // running their own AMOS instance.
    //
    // # ALTER TABLE mdl_amos_repository DROP COLUMN text;
    // # ALTER TABLE mdl_amos_repository ALTER COLUMN textid SET NOT NULL;
    // # VACUUM FULL VERBOSE ANALYZE;

    // Add field status to amos_translators.
    if ($oldversion < 2014100200) {
        $table = new xmldb_table('amos_translators');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lang');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2014100200, 'local', 'amos');
    }

    // Set the status values and drop the 'default 0'
    if ($oldversion < 2014100201) {

        // All current records represent regular maintainers.
        $DB->set_field('amos_translators', 'status', 0);

        $table = new xmldb_table('amos_translators');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'lang');

        $dbman->change_field_default($table, $field);

        upgrade_plugin_savepoint(true, 2014100201, 'local', 'amos');
    }

    // Add field showoutdatedonly to the table amos_filter_usage.
    if ($oldversion < 2015082700) {
        $table = new xmldb_table('amos_filter_usage');
        $field = new xmldb_field('showoutdatedonly', XMLDB_TYPE_INTEGER, '3', null, null, null, null, 'showmissingonly');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2015082700, 'local', 'amos');
    }

    return $result;
}
