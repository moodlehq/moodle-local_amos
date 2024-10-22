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
 * Provides {@see \local_amos\local\source_code} class.
 *
 * @package     local_amos
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;

defined('MOODLE_INTERNAL') || die();

if (!defined('T_ML_COMMENT')) {
    define('T_ML_COMMENT', T_COMMENT);
} else {
    define('T_DOC_COMMENT', T_ML_COMMENT);
}

require_once($CFG->libdir.'/filelib.php');

/**
 * Provides access to the contents of a ZIP package with a plugin's source code.
 *
 * Based on the \local_plugins\local\amos\source_code, customised to be used when importing strings from a plugin.
 *
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_code {

    /** @var string full path to the directory with the contents of the package */
    protected $basepath;

    /** @var array with parsed metadata from the plugin's version.php */
    protected $versionphp;

    /**
     * Instantiate the class.
     *
     * @param string $path full path to the directory containing an unpacked source code of a plugin
     */
    public function __construct($path) {

        if (!is_dir($path)) {
            throw new \coding_exception('non-existing plugin path', $path);
        }

        $this->basepath = $path;
        $this->versionphp = static::parse_version_php($path.'/version.php');
    }

    /**
     * Get as much information from existing version.php as possible.
     *
     * @param string $fullpath full path to the version.php file
     * @return array of found meta-info declarations
     */
    protected static function parse_version_php($fullpath) {

        $content = static::get_stripped_file_contents($fullpath);

        preg_match_all('#\$((plugin|module)\->(version|maturity|release|requires))=()(\d+(\.\d+)?);#m', $content, $matches1);
        preg_match_all('#\$((plugin|module)\->(maturity))=()(MATURITY_\w+);#m', $content, $matches2);
        preg_match_all('#\$((plugin|module)\->(release))=([\'"])(.*?)\4;#m', $content, $matches3);
        preg_match_all('#\$((plugin|module)\->(component))=([\'"])(.+?_.+?)\4;#m', $content, $matches4);

        if (count($matches1[1]) + count($matches2[1]) + count($matches3[1]) + count($matches4[1])) {
            $info = array_combine(
                array_merge($matches1[3], $matches2[3], $matches3[3], $matches4[3]),
                array_merge($matches1[5], $matches2[5], $matches3[5], $matches4[5])
            );

        } else {
            $info = array();
        }

        return $info;
    }

    /**
     * Returns bare PHP code from the given file.
     *
     * Returns contents without PHP opening and closing tags, text outside php code, comments and extra whitespaces.
     *
     * @param string $fullpath full path to the file
     * @return string
     */
    protected static function get_stripped_file_contents($fullpath) {

        $source = file_get_contents($fullpath);
        $tokens = token_get_all($source);
        $output = '';
        $doprocess = false;
        foreach ($tokens as $token) {
            if (is_string($token)) {
                // Simple one character token.
                $id = -1;
                $text = $token;
            } else {
                // Token array.
                list($id, $text) = $token;
            }
            switch ($id) {
                case T_WHITESPACE:
                case T_COMMENT:
                case T_ML_COMMENT:
                case T_DOC_COMMENT:
                    // Ignore whitespaces, inline comments, multiline comments and docblocks.
                    break;
                case T_OPEN_TAG:
                    // Start processing.
                    $doprocess = true;
                    break;
                case T_CLOSE_TAG:
                    // Stop processing.
                    $doprocess = false;
                    break;
                default:
                    // Anything else is within PHP tags, return it as is.
                    if ($doprocess) {
                        $output .= $text;
                        if ($text === 'function') {
                            // Explicitly keep the whitespace that would be ignored.
                            $output .= ' ';
                        }
                    }
                    break;
            }
        }

        return $output;
    }

    /**
     * Return the parsed info from the plugin's version.php file.
     *
     * @return array
     */
    public function get_version_php() {
        return $this->versionphp;
    }

    /**
     * Locates all language files included in the package that should be sent to AMOS
     *
     * For all plugins, it looks up for the English strings file in the lang/en/ folder.
     * Additionally for activity modules, the string files of eventual subplugins are
     * returned, too.
     *
     * @return array of (string)pluginname => (string)relative path of the file => (string)the file contents
     */
    public function get_included_string_files() {
        global $CFG;

        $files = array();

        $component = $this->versionphp['component'];
        [$plugintype, $pluginname] = \core_component::normalize_component($component);

        $fullnamefile = 'lang/en/'.$component.'.php';
        if (is_readable($this->basepath.'/'.$fullnamefile)) {
            $files[$component][$fullnamefile] = file_get_contents($this->basepath.'/'.$fullnamefile);

        } else {
            $modnamefile = 'lang/en/'.$pluginname.'.php';
            if (is_readable($this->basepath.'/'.$modnamefile)) {
                $files[$component][$modnamefile] = file_get_contents($this->basepath.'/'.$modnamefile);
            }
        }

        $subplugins = [];
        $subpluginsfilejson = $this->basepath.'/db/subplugins.json';
        if (is_readable($subpluginsfilejson)) {
            $subplugins = (array) json_decode(file_get_contents($subpluginsfilejson))->plugintypes;
        } else {
            $subpluginsfile = $this->basepath.'/db/subplugins.php';
            if (is_readable($subpluginsfile)) {
                $subplugins = static::get_subplugins_from_file($subpluginsfile);
            }
        }
        if ($subplugins) {
            $parentpath = static::plugin_type_relative_path($plugintype);

            foreach ($subplugins as $subplugintype => $subpluginpath) {
                if ($subpluginpath !== clean_param($subpluginpath, PARAM_SAFEPATH)) {
                    continue;
                }
                if (strpos($subpluginpath, $parentpath.$pluginname) !== 0) {
                    // Subplugin not within the parent plugin.
                    continue;
                }
                $subpluginpath = substr($subpluginpath, 1 + strlen($parentpath.$pluginname));

                if (is_dir($this->basepath.'/'.$subpluginpath)) {
                    $list = get_list_of_plugins($subpluginpath, '', $this->basepath);
                    foreach ($list as $subpluginname) {
                        $subplugincomponent = $subplugintype.'_'.$subpluginname;
                        if ($subplugincomponent !== clean_param($subplugincomponent, PARAM_COMPONENT)) {
                            continue;
                        }
                        $subfile = $subpluginpath.'/'.$subpluginname.'/lang/en/'.$subplugincomponent.'.php';
                        if (is_readable($this->basepath.'/'.$subfile)) {
                            $files[$subplugincomponent][$subfile] = file_get_contents($this->basepath.'/'.$subfile);
                        }
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Return a path to the root dir of all plugins of the given type, relative to the dirroot.
     *
     * @param string $plugintype
     * @return string without the leading slash and with the trailing slash, e.g. 'admin/tool/'
     */
    protected static function plugin_type_relative_path($plugintype) : string {
        global $CFG;

        static $plugintyperoots = [];

        if (empty($plugintyperoots)) {
            foreach (\core_component::get_plugin_types() as $type => $fullpath) {
                $plugintyperoots[$type] = substr($fullpath, strlen($CFG->dirroot));

                if (substr($plugintyperoots[$type], 0, 1) === '/') {
                    $plugintyperoots[$type] = substr($plugintyperoots[$type], 1);
                }

                if (substr($plugintyperoots[$type], -1, 1) !== '/') {
                    $plugintyperoots[$type] .= '/';
                }
            }
        }

        if (!isset($plugintyperoots[$plugintype])) {
            throw new \coding_exception('unknown standard plugin type');
        }

        return $plugintyperoots[$plugintype];
    }

    /**
     * Extracts the declaration of subplugins without actually including the file
     *
     * @param string $path the full path to the subplugin.php file
     * @return array of (string)subplugintype => (string)subpluginpath
     */
    protected static function get_subplugins_from_file($path) {

        if (!is_readable($path)) {
            throw new \coding_exception('No such file', $path);
        }

        $subplugins = array();
        $text = file_get_contents($path);

        preg_match_all("/('|\")([a-z][a-z0-9_]*[a-z0-9])\\1\s*=>\s*('|\")([a-z][a-zA-Z0-9\/_-]*)\\3/", $text, $matches);

        if (!empty($matches[2]) && !empty($matches[4])) {
            foreach ($matches[2] as $ix => $subplugintype) {
                $subplugins[$subplugintype] = $matches[4][$ix];
            }
        }

        if (empty($subplugins)) {
            preg_match_all("/\\\$subplugins\s*\[\s*('|\")([a-z][a-z0-9_]*[a-z0-9])\\1\s*\]\s*=\s*('|\")([a-z][a-zA-Z0-9\/_-]*)\\3/",
                $text, $matches);

            if (!empty($matches[2]) && !empty($matches[4])) {
                foreach ($matches[2] as $ix => $subplugintype) {
                    $subplugins[$subplugintype] = $matches[4][$ix];
                }
            }
        }

        return $subplugins;
    }
}
