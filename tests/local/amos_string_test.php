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
 * Unit tests for the {@see \local_amos\local\amos_string} class.
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(amos_string::class)]
final class amos_string_test extends \advanced_testcase {
    /**
     * Sanity 1.x string
     * - all variables but $a placeholders must be escaped because the string is eval'ed
     * - all ' and " must be escaped
     * - all single % must be converted into %% for backwards compatibility
     */
    public function test_fix_syntax_sanity_v1_strings(): void {
        $this->assertEquals(amos_string::fix_syntax('No change', 1), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(amos_string::fix_syntax('Completed 100%% of work', 1), 'Completed 100%% of work');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks", 1), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks", 1), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks", 1), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline", 1), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline", 1), "FORM FEED\nnewline");
        $this->assertEquals(
            amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 1),
            "END OF TRANSMISSION BLOCK\nnewline"
        );
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline", 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline", 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline", 1), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline", 1), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 1), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 1), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines", 1), "Empty\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace \t \nat \nmultilines  ", 1),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Escape $variable names', 1), 'Escape \$variable names');
        $this->assertEquals(amos_string::fix_syntax('Escape $alike names', 1), 'Escape \$alike names');
        $this->assertEquals(amos_string::fix_syntax('String $a placeholder', 1), 'String $a placeholder');
        $this->assertEquals(amos_string::fix_syntax('Escaped \$a', 1), 'Escaped \$a');
        $this->assertEquals(amos_string::fix_syntax('Wrapped {$a}', 1), 'Wrapped {$a}');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a', 1), 'Trailing $a');
        $this->assertEquals(amos_string::fix_syntax('$a leading', 1), '$a leading');
        $this->assertEquals(amos_string::fix_syntax('Hit $a-times', 1), 'Hit $a-times');
        $this->assertEquals(amos_string::fix_syntax('This is $a_book', 1), 'This is \$a_book');
        $this->assertEquals(amos_string::fix_syntax('Bye $a, ttyl', 1), 'Bye $a, ttyl');
        $this->assertEquals(amos_string::fix_syntax('Object $a->foo placeholder', 1), 'Object $a->foo placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar', 1), 'Trailing $a->bar');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>', 1), '<strong>AMOS</strong>');
        $this->assertEquals(
            amos_string::fix_syntax('<a href="http://localhost">AMOS</a>', 1),
            '<a href=\"http://localhost\">AMOS</a>'
        );
        $this->assertEquals(
            amos_string::fix_syntax('<a href=\"http://localhost\">AMOS</a>', 1),
            '<a href=\"http://localhost\">AMOS</a>'
        );
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote", 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t", 1), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?', 1), 'Delete role \"$a->role\"?');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character", 1),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 1),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 1),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character", 1),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 1),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 1),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 1),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 1),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 1),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 1),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 1),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 1),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 1),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 1),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character", 1),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 1),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 1),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 1),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 1),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 1),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 1),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 1),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character",
                1
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 1),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 1),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 1),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 1),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 1),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 1),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 1),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 1),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 1),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 1),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 1),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 1),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 1),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character",
                1
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 1),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 1),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 1),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 1),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 1),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 1),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character", 1),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 1),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    /**
     * Sanity 2.x string
     * - the string is not eval'ed any more - no need to escape $variables
     * - placeholders can be only {$a} or {$a->something} or {$a->some_thing}, nothing else
     * - quoting marks are not escaped
     * - percent signs are not duplicated any more, reverting them into single (is it good idea?)
     */
    public function test_fix_syntax_sanity_v2_strings(): void {
        $this->assertEquals(amos_string::fix_syntax('No change'), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work'), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax('%%%% HEADER %%%%'), '%%%% HEADER %%%%');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks"), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks"), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks"), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline"), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline"), "FORM FEED\nnewline");
        $this->assertEquals(amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline"), "END OF TRANSMISSION BLOCK\nnewline");
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline"), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline"), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline"), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline"), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline"), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline"), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline"), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline"), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines"), "Empty\n\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  "),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Do not escape $variable names'), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape $alike names'), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Not $a placeholder'), 'Not $a placeholder');
        $this->assertEquals(amos_string::fix_syntax('String {$a} placeholder'), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a}'), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('{$a} leading'), '{$a} leading');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a'), 'Trailing $a');
        $this->assertEquals(amos_string::fix_syntax('$a leading'), '$a leading');
        $this->assertEquals(amos_string::fix_syntax('Not $a->foo placeholder'), 'Not $a->foo placeholder');
        $this->assertEquals(amos_string::fix_syntax('Object {$a->foo} placeholder'), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar'), 'Trailing $a->bar');
        $this->assertEquals(amos_string::fix_syntax('Invalid $a-> placeholder'), 'Invalid $a-> placeholder');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>'), '<strong>AMOS</strong>');
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote"), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t"), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?'), 'Delete role "$a->role"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?'), 'Delete role \"$a->role\"?');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character"),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character"),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character"),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character"),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character"),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character"),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character"),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character"),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character"),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character"),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character"),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character"),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character"),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character"),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character"),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character"),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character"),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character"),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character"),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character"),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character"),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character"),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character"
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character"),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character"),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character"),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character"),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character"),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character"),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character"),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character"),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character"),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character"),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character"),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character"),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character"),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character"
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character"),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character"),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character"),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character"),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character"),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character"),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character"),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character"),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    /**
     * Converting 1.x strings into 2.x strings
     * - unescape all variables
     * - wrap all placeholders in curly brackets
     * - unescape quoting marks
     * - collapse percent signs
     */
    public function test_fix_syntax_converting_from_v1_to_v2(): void {
        $this->assertEquals(amos_string::fix_syntax('No change', 2, 1), 'No change');
        $this->assertEquals(amos_string::fix_syntax('Completed 100% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax('Completed 100%% of work', 2, 1), 'Completed 100% of work');
        $this->assertEquals(amos_string::fix_syntax("Windows\r\nsucks", 2, 1), "Windows\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Linux\nsucks", 2, 1), "Linux\nsucks");
        $this->assertEquals(amos_string::fix_syntax("Mac\rsucks", 2, 1), "Mac\nsucks");
        $this->assertEquals(amos_string::fix_syntax("LINE TABULATION\x0Bnewline", 2, 1), "LINE TABULATION\nnewline");
        $this->assertEquals(amos_string::fix_syntax("FORM FEED\x0Cnewline", 2, 1), "FORM FEED\nnewline");
        $this->assertEquals(
            amos_string::fix_syntax("END OF TRANSMISSION BLOCK\x17newline", 2, 1),
            "END OF TRANSMISSION BLOCK\nnewline"
        );
        $this->assertEquals(amos_string::fix_syntax("END OF MEDIUM\x19newline", 2, 1), "END OF MEDIUM\nnewline");
        $this->assertEquals(amos_string::fix_syntax("SUBSTITUTE\x1Anewline", 2, 1), "SUBSTITUTE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("BREAK PERMITTED HERE\xC2\x82newline", 2, 1), "BREAK PERMITTED HERE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("NEXT LINE\xC2\x85newline", 2, 1), "NEXT LINE\nnewline");
        $this->assertEquals(amos_string::fix_syntax("START OF STRING\xC2\x98newline", 2, 1), "START OF STRING\nnewline");
        $this->assertEquals(amos_string::fix_syntax("STRING TERMINATOR\xC2\x9Cnewline", 2, 1), "STRING TERMINATOR\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zl\xE2\x80\xA8newline", 2, 1), "Unicode Zl\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Unicode Zp\xE2\x80\xA9newline", 2, 1), "Unicode Zp\nnewline");
        $this->assertEquals(amos_string::fix_syntax("Empty\n\n\n\n\n\nlines", 2, 1), "Empty\n\n\nlines");
        $this->assertEquals(
            amos_string::fix_syntax("Trailing   \n  whitespace\t\nat \nmultilines  ", 2, 1),
            "Trailing\n  whitespace\nat\nmultilines"
        );
        $this->assertEquals(amos_string::fix_syntax('Do not escape $variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$variable names', 2, 1), 'Do not escape $variable names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape $alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$alike names', 2, 1), 'Do not escape $alike names');
        $this->assertEquals(amos_string::fix_syntax('Do not escape \$a names', 2, 1), 'Do not escape $a names');
        $this->assertEquals(amos_string::fix_syntax('String $a placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('String {$a} placeholder', 2, 1), 'String {$a} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a', 2, 1), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('$a leading', 2, 1), '{$a} leading');
        $this->assertEquals(amos_string::fix_syntax('$a', 2, 1), '{$a}');
        $this->assertEquals(amos_string::fix_syntax('$a->single', 2, 1), '{$a->single}');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->foobar', 2, 1), 'Trailing {$a->foobar}');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a}', 2, 1), 'Trailing {$a}');
        $this->assertEquals(amos_string::fix_syntax('Hit $a-times', 2, 1), 'Hit {$a}-times');
        $this->assertEquals(amos_string::fix_syntax('This is $a_book', 2, 1), 'This is $a_book');
        $this->assertEquals(amos_string::fix_syntax('Object $a->foo placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Object {$a->foo} placeholder', 2, 1), 'Object {$a->foo} placeholder');
        $this->assertEquals(amos_string::fix_syntax('Trailing $a->bar', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(amos_string::fix_syntax('Trailing {$a->bar}', 2, 1), 'Trailing {$a->bar}');
        $this->assertEquals(amos_string::fix_syntax('Invalid $a-> placeholder', 2, 1), 'Invalid {$a}-> placeholder');
        $this->assertEquals(amos_string::fix_syntax('<strong>AMOS</strong>', 2, 1), '<strong>AMOS</strong>');
        $this->assertEquals(amos_string::fix_syntax("'Murder!', she wrote", 2, 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\'Murder!\', she wrote", 2, 1), "'Murder!', she wrote");
        $this->assertEquals(amos_string::fix_syntax("\t  Trim Hunter  \t\t", 2, 1), 'Trim Hunter');
        $this->assertEquals(amos_string::fix_syntax('Delete role "$a->role"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(amos_string::fix_syntax('Delete role \"$a->role\"?', 2, 1), 'Delete role "{$a->role}"?');
        $this->assertEquals(amos_string::fix_syntax('See &#36;CFG->foo', 2, 1), 'See $CFG->foo');
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\0 NULL control character", 2, 1),
            'Delete ASCII NULL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x05 ENQUIRY control character", 2, 1),
            'Delete ASCII ENQUIRY control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x06 ACKNOWLEDGE control character", 2, 1),
            'Delete ASCII ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x07 BELL control character", 2, 1),
            'Delete ASCII BELL control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0E SHIFT OUT control character", 2, 1),
            'Delete ASCII SHIFT OUT control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x0F SHIFT IN control character", 2, 1),
            'Delete ASCII SHIFT IN control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x10 DATA LINK ESCAPE control character", 2, 1),
            'Delete ASCII DATA LINK ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x11 DEVICE CONTROL ONE control character", 2, 1),
            'Delete ASCII DEVICE CONTROL ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x12 DEVICE CONTROL TWO control character", 2, 1),
            'Delete ASCII DEVICE CONTROL TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x13 DEVICE CONTROL THREE control character", 2, 1),
            'Delete ASCII DEVICE CONTROL THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x14 DEVICE CONTROL FOUR control character", 2, 1),
            'Delete ASCII DEVICE CONTROL FOUR control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x15 NEGATIVE ACKNOWLEDGE control character", 2, 1),
            'Delete ASCII NEGATIVE ACKNOWLEDGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x16 SYNCHRONOUS IDLE control character", 2, 1),
            'Delete ASCII SYNCHRONOUS IDLE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x1B ESCAPE control character", 2, 1),
            'Delete ASCII ESCAPE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ASCII\x7F DELETE control character", 2, 1),
            'Delete ASCII DELETE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x80 PADDING CHARACTER control character", 2, 1),
            'Delete ISO 8859 PADDING CHARACTER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x81 HIGH OCTET PRESET control character", 2, 1),
            'Delete ISO 8859 HIGH OCTET PRESET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x83 NO BREAK HERE control character", 2, 1),
            'Delete ISO 8859 NO BREAK HERE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x84 INDEX control character", 2, 1),
            'Delete ISO 8859 INDEX control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x86 START OF SELECTED AREA control character", 2, 1),
            'Delete ISO 8859 START OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x87 END OF SELECTED AREA control character", 2, 1),
            'Delete ISO 8859 END OF SELECTED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x88 CHARACTER TABULATION SET control character", 2, 1),
            'Delete ISO 8859 CHARACTER TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x89 CHARACTER TABULATION WITH JUSTIFICATION control character",
                2,
                1
            ),
            'Delete ISO 8859 CHARACTER TABULATION WITH JUSTIFICATION control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8A LINE TABULATION SET control character", 2, 1),
            'Delete ISO 8859 LINE TABULATION SET control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8B PARTIAL LINE FORWARD control character", 2, 1),
            'Delete ISO 8859 PARTIAL LINE FORWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8C PARTIAL LINE BACKWARD control character", 2, 1),
            'Delete ISO 8859 PARTIAL LINE BACKWARD control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8D REVERSE LINE FEED control character", 2, 1),
            'Delete ISO 8859 REVERSE LINE FEED control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8E SINGLE SHIFT TWO control character", 2, 1),
            'Delete ISO 8859 SINGLE SHIFT TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x8F SINGLE SHIFT THREE control character", 2, 1),
            'Delete ISO 8859 SINGLE SHIFT THREE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x90 DEVICE CONTROL STRING control character", 2, 1),
            'Delete ISO 8859 DEVICE CONTROL STRING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x91 PRIVATE USE ONE control character", 2, 1),
            'Delete ISO 8859 PRIVATE USE ONE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x92 PRIVATE USE TWO control character", 2, 1),
            'Delete ISO 8859 PRIVATE USE TWO control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x93 SET TRANSMIT STATE control character", 2, 1),
            'Delete ISO 8859 SET TRANSMIT STATE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x95 MESSAGE WAITING control character", 2, 1),
            'Delete ISO 8859 MESSAGE WAITING control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x96 START OF GUARDED AREA control character", 2, 1),
            'Delete ISO 8859 START OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x97 END OF GUARDED AREA control character", 2, 1),
            'Delete ISO 8859 END OF GUARDED AREA control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete ISO 8859\xC2\x99 SINGLE GRAPHIC CHARACTER INTRODUCER control character",
                2,
                1
            ),
            'Delete ISO 8859 SINGLE GRAPHIC CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9A SINGLE CHARACTER INTRODUCER control character", 2, 1),
            'Delete ISO 8859 SINGLE CHARACTER INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9B CONTROL SEQUENCE INTRODUCER control character", 2, 1),
            'Delete ISO 8859 CONTROL SEQUENCE INTRODUCER control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9D OPERATING SYSTEM COMMAND control character", 2, 1),
            'Delete ISO 8859 OPERATING SYSTEM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9E PRIVACY MESSAGE control character", 2, 1),
            'Delete ISO 8859 PRIVACY MESSAGE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete ISO 8859\xC2\x9F APPLICATION PROGRAM COMMAND control character", 2, 1),
            'Delete ISO 8859 APPLICATION PROGRAM COMMAND control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xE2\x80\x8B ZERO WIDTH SPACE control character", 2, 1),
            'Delete Unicode ZERO WIDTH SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax(
                "Delete Unicode\xEF\xBB\xBF ZERO WIDTH NO-BREAK SPACE control character",
                2,
                1
            ),
            'Delete Unicode ZERO WIDTH NO-BREAK SPACE control character'
        );
        $this->assertEquals(
            amos_string::fix_syntax("Delete Unicode\xEF\xBF\xBD REPLACEMENT CHARACTER control character", 2, 1),
            'Delete Unicode REPLACEMENT CHARACTER control character'
        );
    }

    /**
     * Test the {@see amos_string::should_be_included_in_stats()} results.
     */
    public function test_should_be_included_in_stats(): void {
        $this->resetAfterTest();

        $this->assertTrue((new amos_string('one', 'One'))->should_be_included_in_stats());
        $this->assertTrue((new amos_string('one_link', 'foo'))->should_be_included_in_stats());
        $this->assertFalse((new amos_string('del', '', null, true))->should_be_included_in_stats());
    }
}
