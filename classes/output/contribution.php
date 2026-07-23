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
 * Provides class {@see \local_amos\output\contribution}.
 *
 * @package     local_amos
 * @category    output
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Represents renderable contribution infor
 */
class contribution implements \renderable {
    /** Newly submitted contribution. */
    const STATE_NEW = 0;

    /** Contribution under review. */
    const STATE_REVIEW = 10;

    /** Rejected contribution. */
    const STATE_REJECTED = 20;

    /** Accepted submission. */
    const STATE_ACCEPTED = 30;

    /** @var \stdClass */
    public $info;
    /** @var \stdClass */
    public $author;
    /** @var \stdClss */
    public $assignee;
    /** @var string */
    public $language;
    /** @var string */
    public $components;
    /** @var int number of strings */
    public $strings;
    /** @var int number of strings after rebase */
    public $stringsreb;

    /**
     * Constructor.
     *
     * @param \stdClass $info Contribution data.
     * @param \stdClass $author Contributor user record, if known.
     * @param \stdClass $assignee Assignee user record, if known.
     */
    public function __construct(\stdClass $info, \stdClass $author = null, \stdClass $assignee = null) {
        global $DB;

        $this->info = $info;

        if (empty($author)) {
            $this->author = $DB->get_record('user', ['id' => $info->authorid]);
        } else {
            $this->author = $author;
        }

        if (empty($assignee) && !empty($info->assignee)) {
            $this->assignee = $DB->get_record('user', ['id' => $info->assignee]);
        } else {
            $this->assignee = $assignee;
        }
    }
}
