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
 * Defines parsers of various formats of Moodle string files
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/mlanglib.php');

/**
 * Every parser must implement this interface
 */
interface mlang_parser {

    /**
     * Returns singleton instance of the parser
     *
     * Direct contruction and cloning of instances should not be allowed
     */
    public static function get_instance();

    /**
     * Parses data and adds strings into given component
     *
     * @param mixed $data data to parse, typically a file content
     * @param mlang_component $component component to add strings to
     * @param int $format the data format on the input, defaults to the one used since 2.0
     */
    public function parse($data, mlang_component $component, $format=2);
}

/**
 * Factory class for obtaining a parser
 */
class mlang_parser_factory {

    /**
     * Returns an instance of parser for the given format of data
     *
     * @param string $format format of data like 'php', 'xml', 'csv' etc (alphanumerical only)
     * @return instance of a class implementing {@see mlang_parser} interface
     */
    public static function get_parser($format) {

        $format = clean_param($format, PARAM_ALPHANUM);
        $classname = 'mlang_'.$format.'_parser';
        if (class_exists($classname)) {
            return call_user_func("$classname::get_instance");

        } else {
            throw new coding_error('No such parser implemented');
        }
    }
}

/**
 * Exception thrown by parsers
 */
class mlang_parser_exception extends mlang_exception {

    /**
     * @param string $hint short description of problem
     * @param string $debuginfo detailed information how to fix problem
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct($hint, $debuginfo);
    }
}

/**
 * Parser of Moodle strings defined as associative array
 *
 * Moodle core just includes this file format directly as normal PHP code. However
 * for security reasons, we must not do this for files uploaded by anonymous users.
 * This parser reconstructs the associative $string array without actually including
 * the file.
 */
class mlang_php_parser implements mlang_parser {

    /** @var holds the singleton instance of self */
    private static $instance = null;

    /**
     * Prevents direct creation of object
     */
    private function __construct() {
    }

    /**
     * Prevent from cloning the instance
     */
    public function __clone() {
        throw new coding_exception('Cloning os singleton is not allowed');
    }

    /**
     * @return singleton instance of mlang_php_parser
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new mlang_php_parser();
        }
        return self::$instance;
    }

    /**
     * Parses the given data in Moodle PHP string format
     *
     * @param string $data definition of the associative array
     * @param mlang_component $component component to add strings to
     * @param int $format the data format on the input, defaults to the one used since 2.0
     * @return void
     */
    public function parse($data, mlang_component $component, $format=2) {

        $strings = $this->extract_strings($data);
        foreach ($strings as $id => $text) {
            $cleaned = clean_param($id, PARAM_STRINGID);
            if ($cleaned !== $id) {
                continue;
            }
            $text = mlang_string::fix_syntax($text, 2, $format);
            $component->add_string(new mlang_string($id, $text));
        }
    }

    /**
     * Low level parsing method
     *
     * @param string $data
     * @return array
     */
    protected function extract_strings($data) {

        $strings = array(); // to be returned

        if (empty($data)) {
            return $strings;
        }

        if (!is_string($data)) {
            throw new coding_exception('Only strings can be parsed by this parser.');
        }

        // tokenize data - we expect valid PHP code
        $tokens = token_get_all($data);

        // get rid of all non-relevant tokens
        $rubbish = array(T_WHITESPACE, T_INLINE_HTML, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG);
        foreach ($tokens as $i => $token) {
            if (is_array($token)) {
                if (in_array($token[0], $rubbish)) {
                    unset($tokens[$i]);
                }
            }
        }

        $id = null;
        $text = null;
        $line = 0;
        $expect = 'STRING_VAR'; // the first expected token is '$string'

        // iterate over tokens and look for valid $string array assignment patterns
        foreach ($tokens as $token) {
            $foundtype = null;
            $founddata = null;
            if (is_array($token)) {
                $foundtype = $token[0];
                $founddata = $token[1];
                if (!empty($token[2])) {
                    $line = $token[2];
                }

            } else {
                $foundtype = 'char';
                $founddata = $token;
            }

            if ($expect == 'STRING_VAR') {
                if ($foundtype === T_VARIABLE and $founddata === '$string') {
                    $expect = 'LEFT_BRACKET';
                    continue;
                } else {
                    // allow other code at the global level
                    continue;
                    // or, if you want to be strict, throw exception
                    //throw new mlang_parser_exception('Expected T_VARIABLE $string at line '.$line);
                }
            }

            if ($expect == 'LEFT_BRACKET') {
                if ($foundtype === 'char' and $founddata === '[') {
                    $expect = 'STRING_ID';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected character [ at line '.$line);
                }
            }

            if ($expect == 'STRING_ID') {
                if ($foundtype === T_CONSTANT_ENCAPSED_STRING) {
                    $id = $this->decapsulate($founddata);
                    $expect = 'RIGHT_BRACKET';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING array key at line '.$line);
                }
            }

            if ($expect == 'RIGHT_BRACKET') {
                if ($foundtype === 'char' and $founddata === ']') {
                    $expect = 'ASSIGNMENT';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected character ] at line '.$line);
                }
            }

            if ($expect == 'ASSIGNMENT') {
                if ($foundtype === 'char' and $founddata === '=') {
                    $expect = 'STRING_TEXT';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected character = at line '.$line);
                }
            }

            if ($expect == 'STRING_TEXT') {
                if ($foundtype === T_CONSTANT_ENCAPSED_STRING) {
                    $text = $this->decapsulate($founddata);
                    $expect = 'SEMICOLON';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING array item value at line '.$line);
                }
            }

            if ($expect == 'SEMICOLON') {
                if (is_null($id) or is_null($text)) {
                    throw new mlang_parser_exception('NULL string id or value at line '.$line);
                }
                if ($foundtype === 'char' and $founddata === ';') {
                    if (!empty($id)) {
                        $strings[$id] = $text;
                    }
                    $id = null;
                    $text = null;
                    $expect = 'STRING_VAR';
                    continue;
                } else {
                    throw new mlang_parser_exception('Expected character ; at line '.$line);
                }
            }

        }

        return $strings;
    }

    /**
     * Given one T_CONSTANT_ENCAPSED_STRING, return its value without quotes
     *
     * Also processes escaped quotes inside the text.
     *
     * @param string $text value obtained by token_get_all()
     * @return string value without
     */
    protected function decapsulate($text) {

        if (strlen($text) < 2) {
            throw new mlang_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING in decapsulate()');
        }

        if (substr($text, 0, 1) == "'" and substr($text, -1) == "'") {
            // single quoted string
            $text = substr($text, 1, strlen($text) - 2);
            $text = str_replace("\'", "'", $text);
            $text = str_replace('\\\\', '\\', $text);
            return $text;

        } elseif (substr($text, 0, 1) == '"' and substr($text, -1) == '"') {
            // double quoted string
            $text = trim($text, '"');
            $text = str_replace('\"', '"', $text);
            $text = str_replace('\\\\', '\\', $text);
            return $text;

        } else {
            throw new mlang_parser_exception('Unexpected quotation in T_CONSTANT_ENCAPSED_STRING in decapsulate(): '.$text);
        }
    }
}
