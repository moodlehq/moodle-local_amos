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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI script allowing to perform bulk operations over AMOS contributions.
 *
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

$usage = "Perform bulk operations over AMOS contributed translations.

Usage:
    # php contrib-bulk.php --user=<userid> --approve

Options:
    --author=<userid>   User ID of the contributor.
    --approve           Approve all the pending contributions by the user.

";

define('AMOS_BOT_USERID', 2);

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'author' => null,
    'approve' => false,
], [
    'h' => 'help',
    'e' => 'execute',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

if (empty($options['author'])) {
    cli_error('Missing mandatory argument: author', 2);
}

$sql = "SELECT c.id, c.lang, c.subject, c.stashid, c.status, c.timecreated,
               s.components, s.strings" .
               \core_user\fields::for_userpic()->get_sql('a', false, 'author', 'authorid')->selects . "
          FROM {amos_contributions} c
          JOIN {amos_stashes} s ON (c.stashid = s.id)
          JOIN {user} a ON c.authorid = a.id
         WHERE c.status < :rejected
           AND a.id = :authorid
      ORDER BY c.timemodified";

$contributions = $DB->get_records_sql($sql, [
    'rejected' => local_amos_contribution::STATE_REJECTED,
    'authorid' => $options['author'],
]);

$stage = new mlang_stage();

foreach ($contributions as $contribution) {
    echo sprintf("#%d\t%s\t%s\t%d\n", $contribution->id, $contribution->subject, $contribution->components, $contribution->strings);

    if ($options['approve']) {
        $stash = mlang_stash::instance_from_id($contribution->stashid);
        $stash->apply($stage);

        $stage->commit('Bulk approval - contribution #' . $contribution->id, [
            'source' => 'bot',
            'userinfo' => 'AMOS-bot <amos@moodle.org>'
        ]);

        $update = [
            'id' => $contribution->id,
            'timemodified' => time(),
            'assignee' => AMOS_BOT_USERID,
            'status' => local_amos_contribution::STATE_ACCEPTED,
        ];

        $DB->update_record('amos_contributions', $update);
    }
}
