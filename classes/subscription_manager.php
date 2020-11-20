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
 * Provides the {@link subscription_manager} class.
 *
 * @package     local_amos
 * @copyright   2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @copyright   2019 Martin Gauk <gauk@math.tu-berlin.de>
 * @copyright   2019 Jan Eberhardt <eberhardt@tu-berlin.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/local/amos/mlanglib.php');

/**
 * Manager class for accessing and updating the user's subscriptions.
 *
 * @copyright   2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @copyright   2019 Martin Gauk <gauk@math.tu-berlin.de>
 * @copyright   2019 Jan Eberhardt <eberhardt@tu-berlin.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_manager {
    /** @var int user id */
    private $userid;

    /** @var array of array (component -> language) */
    private $subscriptions;

    /** @var array */
    private $addsubscriptions;

    /** @var array */
    private $remsubscriptions;

    /**
     * Subscription manager constructor.
     *
     * @param int $userid user id
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
        $this->subscriptions = [];
        $this->addsubscriptions = [];
        $this->remsubscriptions = [];
    }

    /**
     * Fetch all subscriptions of the user.
     *
     * @return array
     */
    public function fetch_subscriptions() {
        global $DB;

        $this->subscriptions = [];
        $rows = $DB->get_records('amos_subscription', ['userid' => $this->userid]);
        foreach ($rows as $row) {
            if (!isset($this->subscriptions[$row->component])) {
                $this->subscriptions[$row->component] = [$row->lang];
            } else {
                $this->subscriptions[$row->component][] = $row->lang;
            }
        }

        return $this->subscriptions;
    }

    /**
     * Add new subscription.
     *
     * @param string $component component name
     * @param string $lang language code
     */
    public function add_subscription(string $component, string $lang) {
        $this->addsubscriptions[] = (object) ['component' => $component, 'lang' => $lang];
    }

    /**
     * Remove one subscription.
     *
     * @param string $component component name
     * @param string $lang language code
     */
    public function remove_subscription(string $component, string $lang) {
        $this->remsubscriptions[] = (object) ['component' => $component, 'lang' => $lang];
    }

    /**
     * Remove all language subscriptions of one component.
     *
     * @param string $component component name
     */
    public function remove_component_subscription(string $component) {
        $this->remsubscriptions[] = (object) ['component' => $component, 'lang' => null];
    }

    /**
     * Apply all changes to the database.
     *
     * All changes that you registered with add_subscription, remove_subscription and remove_component_subscription.
     */
    public function apply_changes() {
        global $DB;

        // Get available components and langs.
        $components = \mlang_tools::list_components();
        $langs = \mlang_tools::list_languages();

        $transaction = $DB->start_delegated_transaction();

        // Remove subscriptions.
        foreach ($this->remsubscriptions as $sub) {
            if ($sub->lang !== null) {
                $DB->delete_records('amos_subscription', [
                    'userid' => $this->userid,
                    'component' => $sub->component,
                    'lang' => $sub->lang,
                ]);
            } else {
                $DB->delete_records('amos_subscription', [
                    'userid' => $this->userid,
                    'component' => $sub->component,
                ]);
            }
        }

        // Refresh subscriptions to check for duplicates.
        $this->fetch_subscriptions();

        $inserts = [];
        // Validate component names and language codes of subscriptions that should be added.
        foreach ($this->addsubscriptions as $sub) {
            if (!isset($components[$sub->component])) {
                continue;
            }

            if (!isset($langs[$sub->lang])) {
                continue;
            }

            // Check if not already subscribed.
            if (isset($this->subscriptions[$sub->component]) &&
                    in_array($sub->lang, $this->subscriptions[$sub->component])) {
                continue;
            }

            $sub->userid = $this->userid;
            $inserts[] = $sub;
            $this->subscriptions[$sub->component][] = $sub->lang;
        }
        $DB->insert_records('amos_subscription', $inserts);

        $transaction->allow_commit();

        // Reset list of changes.
        $this->addsubscriptions = [];
        $this->remsubscriptions = [];
    }

    /**
     * Remove all subscriptions of the user.
     */
    public function remove_all_subscriptions() {
        global $DB;

        $DB->delete_records('amos_subscription', ['userid' => $this->userid]);
    }
}
