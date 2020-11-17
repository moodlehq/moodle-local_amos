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
 * Provides {@see \local_amos\output\filter} class.
 *
 * @package     local_amos
 * @category    output
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

/**
 * Represent the AMOS translator filter and its settings.
 *
 * @copyright   2010 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter implements \renderable, \templatable {

    /** @var array list of setting names */
    public $fields = [];

    /** @var \moodle_url */
    public $handler = null;

    /** @var string lazyform name */
    public $lazyformname = null;

    /** @var \moodle_url */
    protected $permalink = null;

    /** @var object */
    protected $datadefault = null;

    /** @var object|null */
    protected $datasubmitted = null;

    /** @var object|null */
    protected $datapermalink = null;

    /**
     * Creates the filter and sets the default filter values
     *
     * @param \moodle_url $handler filter form action URL
     */
    public function __construct(\moodle_url $handler) {

        $this->fields = [
            'version',
            'language',
            'component',
            'missing',
            'outdated',
            'has',
            'helps',
            'substring',
            'stringid',
            'stringidpartial',
            'stagedonly',
            'app',
            'last',
            'page',
            'substringregex',
            'substringcs',
        ];

        $this->lazyformname = 'amosfilter';
        $this->handler = $handler;
        $this->datasubmitted = $this->get_data_submitted();
        $this->datadefault = $this->get_data_default();
        $this->datapermalink = $this->get_data_permalink();
    }

    /**
     * Returns the filter data
     *
     * @return object
     */
    public function get_data() {

        $data = (object) [];

        $default = $this->datadefault;
        $submitted = $this->datasubmitted;
        $permalink = $this->datapermalink;

        foreach ($this->fields as $field) {
            if (isset($submitted->{$field})) {
                $data->{$field} = $submitted->{$field};

            } else if (isset($permalink->{$field})) {
                $data->{$field} = $permalink->{$field};

            } else {
                $data->{$field} = $default->{$field};
            }
        }

        return $data;
    }

    /**
     * Returns the default values of the filter fields.
     *
     * @return object
     */
    protected function get_data_default() {

        $data = (object) array_merge((array) $this->get_data_user_default(), (array) $this->get_data_session_default());

        if (empty($data->version)) {
            $data->version = \mlang_version::latest_version()->code;
        }

        if (empty($data->language)) {
            $currentlanguage = current_language();

            if ($currentlanguage === 'en') {
                $currentlanguage = 'en_fix';
            }

            $data->language = [$currentlanguage];
        }

        $data->component = $data->component ?? ['moodle'];
        $data->missing = $data->missing ?? false;
        $data->outdated = $data->outdated ?? false;
        $data->has = $data->has ?? false;
        $data->helps = $data->helps ?? false;
        $data->substring = $data->substring ?? '';
        $data->stringidpartial = $data->stringidpartial ?? false;
        $data->substringregex = $data->substringregex ?? false;
        $data->substringcs = $data->substringcs ?? false;
        $data->stringid = $data->stringid ?? '';
        $data->stagedonly = $data->stagedonly ?? false;
        $data->app = $data->app ?? false;
        $data->last = $data->last ?? true;
        $data->page = $data->page ?? 1;

        return $data;
    }

    /**
     * Returns the form data as submitted by the user
     *
     * @return object|null
     */
    protected function get_data_submitted() {

        $issubmitted = optional_param('__lazyform_' . $this->lazyformname, false, PARAM_BOOL);

        if (!$issubmitted) {
            return null;
        }

        require_sesskey();

        $data = (object) [];

        if (optional_param('resetmydefault', false, PARAM_BOOL)) {
            $userdefault = $this->get_data_user_default();
            $this->set_data_session_default($userdefault);
            return $userdefault;
        }

        $data->version = null;
        $data->last = optional_param('flast', false, PARAM_BOOL);
        if (!$data->last) {
            $fver = optional_param('fver', null, PARAM_INT);
            if ($fver !== null) {
                foreach (\mlang_version::list_all() as $version) {
                    if ($version->code == $fver) {
                        $data->version = $version->code;
                    }
                }
            }
        }

        $data->language = array();
        $flng = optional_param_array('flng', null, PARAM_SAFEDIR);
        if (is_array($flng)) {
            foreach ($flng as $language) {
                $data->language[] = $language;
            }
        }

        $data->component = array();
        $fcmp = optional_param_array('fcmp', null, PARAM_FILE);
        if (is_array($fcmp)) {
            foreach ($fcmp as $component) {
                $data->component[] = $component;
            }
        }

        $data->missing = optional_param('fmis', false, PARAM_BOOL);
        $data->outdated = optional_param('fout', false, PARAM_BOOL);
        $data->has = optional_param('fhas', false, PARAM_BOOL);
        $data->helps = optional_param('fhlp', false, PARAM_BOOL);
        $data->substring = optional_param('ftxt', '', PARAM_RAW);
        $data->substringregex = optional_param('ftxr', false, PARAM_BOOL);
        $data->substringcs = optional_param('ftxs', false, PARAM_BOOL);
        $data->stringidpartial = optional_param('fsix', false, PARAM_BOOL);

        if ($data->stringidpartial) {
            $data->stringid = trim(optional_param('fsid', '', PARAM_NOTAGS));
        } else {
            $data->stringid = trim(optional_param('fsid', '', PARAM_STRINGID));
        }

        $data->stagedonly = optional_param('fstg', false, PARAM_BOOL);
        $data->app = optional_param('fapp', false, PARAM_BOOL);

        if ($data->missing and $data->outdated) {
            // It does not make sense to have both.
            $data->missing = false;
        }

        $this->set_data_session_default($data);

        if (optional_param('saveasmydefault', false, PARAM_BOOL)) {
            $this->set_data_user_default($data);
        }

        $data->page = optional_param('fpg', 1, PARAM_INT);

        return $data;
    }

    /**
     * Returns the form data as set by explicit permalink
     *
     * @see self::set_permalink()
     * @return object|null
     */
    protected function get_data_permalink() {

        $ispermalink = optional_param('t', false, PARAM_INT);

        if (empty($ispermalink)) {
            return null;
        }
        $data = (object) [];

        $data->version = [];
        $fver = optional_param('v', '', PARAM_RAW);

        if ($fver !== 'l') {
            $fver = explode(',', $fver);
            $fver = clean_param_array($fver, PARAM_INT);
            if (!empty($fver) && is_array($fver)) {
                $fver = max($fver);
                foreach (\mlang_version::list_all() as $version) {
                    if ($version->code == $fver) {
                        $data->version = $version->code;
                    }
                }
            }

        } else {
            $data->last = 1;
        }

        $data->language = array();
        $flng = optional_param('l', '', PARAM_RAW);

        if ($flng == '*') {
            // All languages.
            foreach (\mlang_tools::list_languages(false) as $langcode => $langname) {
                $data->language[] = $langcode;
            }

        } else {
            $flng = explode(',', $flng);
            $flng = clean_param_array($flng, PARAM_SAFEDIR);

            if (!empty($flng) and is_array($flng)) {
                foreach ($flng as $language) {
                    if (!empty($language)) {
                        $data->language[] = $language;
                    }
                }
            }
        }

        $data->app = false;

        $data->component = array();
        $fcmp = optional_param('c', '', PARAM_RAW);

        if ($fcmp == '*std') {
            // Standard components.
            $data->component = array();
            foreach (local_amos_standard_plugins() as $plugins) {
                $data->component = array_merge($data->component, array_keys($plugins));
            }

        } else if ($fcmp == '*app') {
            // Mobile App components.
            $data->component = array_keys(local_amos_app_plugins());
            $data->app = true;
            $data->last = 1;

        } else if ($fcmp == '*') {
            // All components.
            $data->component = array_keys(\mlang_tools::list_components());

        } else {
            $fcmp = explode(',', $fcmp);
            $fcmp = clean_param_array($fcmp, PARAM_FILE);
            if (!empty($fcmp) and is_array($fcmp)) {
                foreach ($fcmp as $component) {
                    $data->component[] = $component;
                }
            }
        }

        $data->missing = optional_param('m', false, PARAM_BOOL);
        $data->outdated = optional_param('u', false, PARAM_BOOL);
        $data->has = optional_param('x', false, PARAM_BOOL);
        $data->helps = optional_param('h', false, PARAM_BOOL);
        $data->substring = optional_param('s', '', PARAM_RAW);
        $data->substringregex = optional_param('r', false, PARAM_BOOL);
        $data->substringcs = optional_param('i', false, PARAM_BOOL);
        $data->stringidpartial = optional_param('p', false, PARAM_BOOL);

        if ($data->stringidpartial) {
            $data->stringid = trim(optional_param('d', '', PARAM_NOTAGS));
        } else {
            $data->stringid = trim(optional_param('d', '', PARAM_STRINGID));
        }

        $data->stagedonly = optional_param('g', false, PARAM_BOOL);
        $data->app = optional_param('a', $data->app, PARAM_BOOL);

        // Reset the paginator to the first page for permalinks.
        $data->page = 1;

        return $data;
    }

    /**
     * Return a permanent link for the current filter data.
     *
     * @param \moodle_url $baseurl
     * @return \moodle_url $permalink
     */
    public function get_permalink(?\moodle_url $baseurl = null): \moodle_url {

        $baseurl = $baseurl ?: new \moodle_url('/local/amos/view.php');

        $fdata = $this->get_data();
        $permalink = new \moodle_url($baseurl, ['t' => time()]);

        if ($fdata->last) {
            $permalink->param('v', 'l');
        } else {
            $permalink->param('v', $fdata->version);
        }

        // List of languages or '*' if all are selected.
        $all = \mlang_tools::list_languages(false);

        foreach ($fdata->language as $selected) {
            unset($all[$selected]);
        }

        if (empty($all)) {
            $permalink->param('l', '*');
        } else {
            $permalink->param('l', implode(',', $fdata->language));
        }

        unset($all);

        // List of components or '*' if all are selected.
        $all = \mlang_tools::list_components();
        $app = array_keys(local_amos_app_plugins());
        $app = array_combine($app, $app);
        foreach ($fdata->component as $selected) {
            unset($all[$selected]);
            unset($app[$selected]);
        }

        // The '*std' cannot be preselected because it depends on version.
        if (empty($all)) {
            $permalink->param('c', '*');

        } else if (empty($app)) {
            $permalink->param('c', '*app');

        } else {
            $permalink->param('c', implode(',', $fdata->component));
        }

        unset($all);

        $permalink->param('s', $fdata->substring);
        $permalink->param('d', $fdata->stringid);

        if ($fdata->missing) {
            $permalink->param('m', 1);
        }

        if ($fdata->outdated) {
            $permalink->param('u', 1);
        }

        if ($fdata->has) {
            $permalink->param('x', 1);
        }

        if ($fdata->helps) {
            $permalink->param('h', 1);
        }

        if ($fdata->substringregex) {
            $permalink->param('r', 1);
        }

        if ($fdata->substringcs) {
            $permalink->param('i', 1);
        }

        if ($fdata->stringidpartial) {
            $permalink->param('p', 1);
        }

        if ($fdata->stagedonly) {
            $permalink->param('g', 1);
        }

        if ($fdata->app) {
            $permalink->param('a', 1);
        }

        return $permalink;
    }

    /**
     * Export the filter form data for template.
     *
     * @param \renderer_base $output
     * @return object
     */
    public function export_for_template(\renderer_base $output) {

        $filterdata = $this->get_data();

        $result = [
            'formaction' => $this->handler->out(false),
            'lazyformname' => $this->lazyformname,
            'filterdata' => $filterdata,
            'flng' => $this->export_for_template_flng($output, $filterdata),
            'fcmp' => $this->export_for_template_fcmp($output, $filterdata),
            'fver' => $this->export_for_template_fver($output, $filterdata),
        ];

        return $result;
    }

    /**
     * Export template data for language selector.
     *
     * @return array
     */
    protected function export_for_template_flng(\renderer_base $outpup, object $filterdata): array {

        $flng = [
            'options' => [],
            'numselected' => 0,
            'currentlanguage' => current_language(),
        ];

        if ($flng['currentlanguage'] === 'en') {
            $flng['currentlanguage'] = 'en_fix';
        }

        foreach (\mlang_tools::list_languages(false, true, true, true) as $langcode => $langname) {
            $option = [
                'value' => $langcode,
                'text' => $langname,
                'selected' => false,
            ];

            if (in_array($langcode, $filterdata->language)) {
                $flng['numselected']++;
                $option['selected'] = true;
            }

            $flng['options'][] = $option;
        }

        return $flng;
    }

    /**
     * Export template data for component selector.
     *
     * @return array
     */
    protected function export_for_template_fcmp(\renderer_base $outpup, object $filterdata): array {

        $fcmp = [
            'options' => [
                'core' => [],
                'standard' => [],
                'contrib' => [],
            ],
            'numselected' => 0,
        ];

        $options = [
            'core' => [],
            'standard' => [],
            'contrib' => [],
        ];

        $standard = [];
        foreach (local_amos_standard_plugins() as $plugins) {
            // Merging standard plugins from all versions.
            $standard = array_merge($standard, $plugins);
        }

        // Categorize components into Core, Standard or Add-ons.
        foreach (\mlang_tools::list_components() as $componentname => $since) {
            if (isset($standard[$componentname])) {
                if ($standard[$componentname] === 'core' || substr($standard[$componentname], 0, 5) === 'core_') {
                    $options['core'][$componentname] = $standard[$componentname];

                } else {
                    $options['standard'][$componentname] = $standard[$componentname];
                }

            } else {
                $options['contrib'][$componentname] = $componentname;
            }

            $sinceversion[$componentname] = \mlang_version::by_code($since)->label . '+';
        }

        asort($options['core']);
        asort($options['standard']);
        asort($options['contrib']);

        $mobileapp = local_amos_app_plugins();

        foreach (['core', 'standard', 'contrib'] as $type) {
            foreach ($options[$type] as $componentname => $componentlabel) {
                $option = [
                    'name' => $componentname,
                    'label' => $componentlabel,
                    'selected' => false,
                    'type' => $type,
                    'typename' => get_string('type' . $type . 'badge', 'local_amos'),
                    'since' => $sinceversion[$componentname],
                    'app' => isset($mobileapp[$componentname]),
                ];

                if (in_array($componentname, $filterdata->component)) {
                    $fcmp['numselected']++;
                    $option['selected'] = true;
                }

                $fcmp['options'][$type][] = $option;
            }
        }

        return $fcmp;
    }

    /**
     * Export template data for version selector.
     *
     * @return array
     */
    protected function export_for_template_fver(\renderer_base $outpup, object $filterdata): array {

        $fver = [
            'options' => [],
        ];

        foreach (\mlang_version::list_all() as $version) {
            $option = [
                'value' => $version->code,
                'text' => $version->label,
                'selected' => ($version->code == $filterdata->version),
            ];

            if ($version->code == $filterdata->version) {
                $option['selected'] = true;
            }

            $fver['options'][] = $option;
        }

        return $fver;
    }

    /**
     * Set the current filter data as the user's default data for this session.
     */
    protected function set_data_session_default(object $data) {
        global $SESSION;

        $SESSION->local_amos = $SESSION->local_amos ?? (object)[];
        $SESSION->local_amos->filter_session_default_data = json_encode($data);
    }

    /**
     * Check to see if we have default session data set via {@see self::set_data_session_default()}
     *
     * @return object;
     */
    protected function get_data_session_default(): object {
        global $SESSION;

        if (isset($SESSION->local_amos->filter_session_default_data)) {
            $data = json_decode($SESSION->local_amos->filter_session_default_data);

            if (!is_object($data)) {
                $data = (object) [];
            }

        } else {
            $data = (object) [];
        }

        unset($data->page);

        return $data;
    }

    /**
     * Set the current filter data as the user's default data.
     *
     */
    protected function set_data_user_default(object $data) {
        global $DB, $USER, $SESSION;

        $data = fullclone($data);
        $data->_formatrevision = 1;
        $data = json_encode($data);

        $current = $DB->get_record('amos_preferences', [
            'userid' => $USER->id,
            'name' => 'filter_default',
        ]);

        if (!$current) {
            $DB->insert_record('amos_preferences', [
                'userid' => $USER->id,
                'name' => 'filter_default',
                'value' => $data,
            ]);

        } else {
            $DB->update_record('amos_preferences', [
                'id' => $current->id,
                'value' => $data,
            ]);
        }

        $SESSION->local_amos = $SESSION->local_amos ?? (object)[];
        $SESSION->local_amos->filter_user_default_data = $data;
    }

    /**
     * Load the user's default data previously set by {@see self::set_data_user_default()}.
     *
     * @return object
     */
    protected function get_data_user_default(): object {
        global $DB, $USER, $SESSION;

        $SESSION->local_amos = $SESSION->local_amos ?? (object)[];

        if (isset($SESSION->local_amos->filter_user_default_data)) {
            $data = json_decode($SESSION->local_amos->filter_user_default_data);

            if (is_object($data)) {
                unset($data->page);
                return $data;
            }
        }

        $pref = $DB->get_field('amos_preferences', 'value', [
            'userid' => $USER->id,
            'name' => 'filter_default',
        ]);

        if (empty($pref)) {
            $SESSION->local_amos->filter_user_default_data = json_encode((object) []);
            return (object) [];
        }

        $data = json_decode($pref);

        if (!is_object($data)) {
            $SESSION->local_amos->filter_user_default_data = json_encode((object) []);
            return (object) [];
        }

        if (!$data->_formatrevision == 1) {
            $SESSION->local_amos->filter_user_default_data = json_encode((object) []);
            return (object) [];
        }

        unset($data->page);

        $SESSION->local_amos->filter_user_default_data = json_encode($data);

        return $data;
    }
}
