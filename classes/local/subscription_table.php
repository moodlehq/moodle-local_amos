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
 * View your subscription and change settings
 *
 * @package   local_amos
 * @tags MoodleMootDACH2019
 * @copyright 2019 Tobias Reischmann <tobias.reischmann@wi.uni-muenster.de>
 * @copyright 2019 Martin Gauk <gauk@math.tu-berlin.de>
 * @copyright 2019 Jan Eberhardt <eberhardt@tu-berlin.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;


use local_amos\subscription_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once $CFG->libdir . DIRECTORY_SEPARATOR . 'tablelib.php';
require_once $CFG->dirroot . '/local/amos/classes/subscription_manager.php';
require_once $CFG->dirroot . '/local/amos/mlanglib.php';

class subscription_table extends \flexible_table {

    public function init($baseurl, $pagesize = -1)
    {
        global $USER;

        $manager = new subscription_manager($USER->id);
        $subs = $manager->fetch_subscriptions();

        $this->define_baseurl($baseurl);
        $this->define_columns([
            'component',
            'language'
        ]);
        $this->define_headers([
            get_string('component', 'local_amos'),
            get_string('languages', 'local_amos')
        ]);
        $this->pagesize($pagesize > 0 ? $pagesize : $this->pagesize, count($subs));
        $this->sortable(true, 'component');
        $this->no_sorting('language');
        $this->setup();
    }

    public function out() {
        global $USER;
        $manager = new subscription_manager($USER->id);
        $subs = $manager->fetch_subscriptions();
        $sort = $this->get_sort_columns()['component'];
        if ($sort === SORT_ASC) {
            ksort($subs);
        } else {
            krsort($subs);
        }
        $this->start_output();
        foreach ($subs as $component => $langarray) {
            $row = $this->format_row([
                'component' => $component,
                'language' => $langarray
            ]);
            $this->add_data_keyed($row);
        }
        $this->finish_output();
    }

    public function col_component($sub) {
        global $PAGE;
        $icon = $PAGE->get_renderer('local_amos')->pix_icon(
            't/delete',
            get_string('unsubscribe', 'local_amos')
        );
        $query_string = sprintf('?c=%s&m=unsubscribe', $sub->component);
        $link = \html_writer::link($this->baseurl . $query_string, $icon, ['class' => 'unsubscribe']);
        return sprintf('%s %s', $sub->component, $link);
    }

    public function col_language($sub) {
        global $OUTPUT;

        $inplace = self::get_lang_inplace_editable($sub->component, $sub->language);
        return $OUTPUT->render_from_template('core/inplace_editable', $inplace->export_for_template($OUTPUT));
    }

    static public function get_lang_inplace_editable(string $component, array $langs) {
        $options = \mlang_tools::list_languages(false);

        $values = json_encode($langs);
        $displayvalues = implode(', ', array_map(function($lang) use ($options) {
            return (isset($options[$lang]))
                ? $options[$lang]
                : get_string('unknown_language', 'local_amos', $lang);
        }, $langs));

        $inplace = new \core\output\inplace_editable('local_amos', 'subscription', $component,
            true, $displayvalues, $values);

        $attributes = ['multiple' => true];
        $inplace->set_type_autocomplete($options, $attributes);

        return $inplace;
    }
}