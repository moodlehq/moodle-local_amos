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

class subscription_table extends \table_sql {

    public function __construct($uniqueid)
    {
        global $USER;

        parent::__construct($uniqueid);

        $this->define_columns([
            'component',
            'lang'
        ]);
        $this->define_headers([
            get_string('component', 'local_amos'),
            get_string('languages', 'local_amos')
        ]);
    }

    public function query_db($pagesize, $useinitialsbar = true)
    {
        global $USER;

        $manager = new subscription_manager($USER->id);
        $subs = $manager->fetch_subscriptions();

        $this->pagesize($pagesize, count($subs));
        foreach ($subs as $component => $langarray) {
            $this->rawdata[] = [
                'component' => $component,
                'lang' => $langarray
            ];
        }
    }

    public function col_component($sub) {
        return $sub->component;
    }

    public function col_lang($sub) {
        $icons = [];
        foreach ($sub->lang as $lc) {
            $query_string = sprintf('c=%s&l=%s&m=0', $sub->component, $lc);
            $attributes = ['class' => 'langcode'];
            $icons[] = \html_writer::link(
                $this->baseurl . '?' . $query_string,
                $lc . ' &times;',
                $attributes
            );
        }
        return join(' ', $icons);
    }

}