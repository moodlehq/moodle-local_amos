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

namespace local_amos\local;

/**
 * Parser of Moodle strings defined as associative array
 *
 * Moodle core just includes this file format directly as normal PHP code. However
 * for security reasons, we must not do this for files uploaded by anonymous users.
 * This parser reconstructs the associative $string array without actually including
 * the file.
 *
 * @package     local_amos
 * @subpackage  amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_php_parser implements amos_parser {
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
        throw new \coding_exception('Cloning os singleton is not allowed');
    }

    /**
     * Factory method.
     *
     * @return singleton instance of amos_php_parser
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new amos_php_parser();
        }
        return self::$instance;
    }

    /**
     * Parses the given data in Moodle PHP string format
     *
     * @param string $data definition of the associative array
     * @param amos_component $component component to add strings to
     * @param int $format the data format on the input, defaults to the one used since 2.0
     * @return void
     */
    public function parse($data, amos_component $component, $format = 2) {

        $strings = $this->extract_strings($data);
        foreach ($strings as $id => $text) {
            $cleaned = clean_param($id, PARAM_STRINGID);
            if ($cleaned !== $id) {
                continue;
            }
            $text = amos_string::fix_syntax($text, 2, $format);
            $component->add_string(new amos_string($id, $text));
        }
    }

    /**
     * Low level parsing method
     *
     * @param string $data
     * @return array
     */
    protected function extract_strings($data) {

        $strings = [];

        if (empty($data)) {
            return $strings;
        }

        if (!is_string($data)) {
            throw new \coding_exception('Only strings can be parsed by this parser.');
        }

        // Tokenize data - we expect valid PHP code.
        $tokens = token_get_all($data);

        // Get rid of all non-relevant tokens.
        $rubbish = [T_WHITESPACE, T_INLINE_HTML, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG];
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
        // The first expected token is '$string'.
        $expect = 'STRING_VAR';

        // Iterate over tokens and look for valid $string array assignment patterns.
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
                if ($foundtype === T_VARIABLE && $founddata === '$string') {
                    $expect = 'LEFT_BRACKET';
                    continue;
                } else {
                    // Allow other code at the global level.
                    continue;
                }
            }

            if ($expect == 'LEFT_BRACKET') {
                if ($foundtype === 'char' && $founddata === '[') {
                    $expect = 'STRING_ID';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected character [ at line ' . $line);
                }
            }

            if ($expect == 'STRING_ID') {
                if ($foundtype === T_CONSTANT_ENCAPSED_STRING) {
                    $id = $this->decapsulate($founddata);
                    $expect = 'RIGHT_BRACKET';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING array key at line ' . $line);
                }
            }

            if ($expect == 'RIGHT_BRACKET') {
                if ($foundtype === 'char' && $founddata === ']') {
                    $expect = 'ASSIGNMENT';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected character ] at line ' . $line);
                }
            }

            if ($expect == 'ASSIGNMENT') {
                if ($foundtype === 'char' && $founddata === '=') {
                    $expect = 'STRING_TEXT';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected character = at line ' . $line);
                }
            }

            if ($expect == 'STRING_TEXT') {
                if ($foundtype === T_CONSTANT_ENCAPSED_STRING) {
                    $text = $this->decapsulate($founddata);
                    $expect = 'SEMICOLON';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING array item value at line ' . $line);
                }
            }

            if ($expect == 'SEMICOLON') {
                if (is_null($id) || is_null($text)) {
                    throw new amos_parser_exception('NULL string id or value at line ' . $line);
                }
                if ($foundtype === 'char' && $founddata === ';') {
                    if (!empty($id)) {
                        $strings[$id] = $text;
                    }
                    $id = null;
                    $text = null;
                    $expect = 'STRING_VAR';
                    continue;
                } else {
                    throw new amos_parser_exception('Expected character ; at line ' . $line);
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
            throw new amos_parser_exception('Expected T_CONSTANT_ENCAPSED_STRING in decapsulate()');
        }

        if (substr($text, 0, 1) == "'" && substr($text, -1) == "'") {
            // Single quoted string.
            $text = substr($text, 1, strlen($text) - 2);
            $text = str_replace("\'", "'", $text);
            $text = str_replace('\\\\', '\\', $text);
            return $text;
        } else if (substr($text, 0, 1) == '"' && substr($text, -1) == '"') {
            // Double quoted string.
            $text = trim($text, '"');
            $text = str_replace('\"', '"', $text);
            $text = str_replace('\\\\', '\\', $text);
            return $text;
        } else {
            throw new amos_parser_exception('Unexpected quotation in T_CONSTANT_ENCAPSED_STRING in decapsulate(): ' . $text);
        }
    }
}
