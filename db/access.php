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
 * Capability definitions for AMOS local plugin
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Ability to set-up AMOS portal and assign translators of languages.
    'local/amos:manage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [],
    ],

    // Ability to stage translations using the translation tool.
    'local/amos:stage' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Ability to execute a given AMOScript and get the result in the stage.
    'local/amos:execute' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [],
    ],

    // Ability to commit the stage into AMOS repository.
    'local/amos:commit' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [],
    ],

    // Ability to stash a stage and to contribute.
    'local/amos:stash' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Ability to import translated strings from uploaded file and stage them.
    'local/amos:importfile' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Ability to import strings (including the English ones) directly into the repository.
    // This is intended mainly for the web service users.
    'local/amos:importstrings' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [],
    ],

    // Ability to use Google Translate services.
    'local/amos:usegoogle' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Ability to convert an existing contribution to a new contribution with different language.
    'local/amos:changecontriblang' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'legacy' => [],
    ],
];
