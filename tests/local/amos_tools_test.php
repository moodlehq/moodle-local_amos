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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../classes/local/testable_amos_tools.php');

/**
 * Unit tests for the {@see \local_amos\local\amos_tools} class.
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(amos_tools::class)]
final class amos_tools_test extends \local_amos_testcase {
    public function test_extract_script_from_text(): void {
        $noscript = 'This is text with no AMOS script';
        $emptyarray = amos_tools::extract_script_from_text($noscript);
        $this->assertTrue(empty($emptyarray));

        $oneliner = 'MDL-12345 Some message AMOS   BEGIN  MOV   [a,  b],[c,d] CPY [e,f], [g ,h]  AMOS' .
            "\t" . 'END BEGIN ignore AMOS   ';
        $script = amos_tools::extract_script_from_text($oneliner);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV [a, b],[c,d]', $script[0]);
        $this->assertEquals('CPY [e,f], [g ,h]', $script[1]);

        // phpcs:disable moodle.WhiteSpace.WhiteSpaceInStrings
        $multiline = 'This is a typical usage of AMOS script in a commit message
                    AMOS BEGIN
                     MOV a,b  
                     CPY  c,d
                    AMOS END
                   Here it can continue';
        // phpcs:enable
        $script = amos_tools::extract_script_from_text($multiline);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(2, count($script));
        $this->assertEquals('MOV a,b', $script[0]);
        $this->assertEquals('CPY c,d', $script[1]);

        // If there is no empty line between commit subject and AMOS script:.
        $oneliner2 = 'Blah blah blah AMOS   BEGIN  CMD AMOS END blah blah';
        $script = amos_tools::extract_script_from_text($oneliner2);
        $this->assertEquals(gettype($script), 'array');
        $this->assertEquals(1, count($script));
        $this->assertEquals('CMD', $script[0]);
    }

    public function test_list_languages(): void {
        $this->resetAfterTest();
        $stage = new amos_stage();
        $component = new amos_component('langconfig', 'en', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'English'));
        $component->add_string(new amos_string('thislanguage', 'English'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'cs', amos_version::by_branch('MOODLE_20_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'Czech'));
        $component->add_string(new amos_string('thislanguage', 'Česky'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'cs', amos_version::by_branch('MOODLE_19_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'CS'));
        $component->add_string(new amos_string('thislanguage', 'ČS'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'xx', amos_version::by_branch('MOODLE_21_STABLE'));
        $component->add_string(new amos_string('thislanguage', 'Xx'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('langconfig', 'yy', amos_version::by_branch('MOODLE_23_STABLE'));
        $component->add_string(new amos_string('thislanguageint', 'Yy'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering languages', ['source' => 'unittest']);

        $langs = amos_tools::list_languages(true, true, false);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 4);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertTrue(array_key_exists('en', $langs));
        $this->assertEquals($langs['en'], 'English');
        $this->assertEquals($langs['cs'], 'Czech');
        $this->assertEquals($langs['xx'], '???');
        $this->assertEquals($langs['yy'], 'Yy');

        $langs = amos_tools::list_languages(false, true, true);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 3);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertEquals($langs['cs'], 'Czech [cs]');
        $this->assertEquals($langs['xx'], '??? [xx]');
        $this->assertEquals($langs['yy'], 'Yy [yy]');

        $langs = amos_tools::list_languages(true, true, true, true);
        $this->assertEquals(gettype($langs), 'array');
        $this->assertEquals(count($langs), 4);
        $this->assertTrue(array_key_exists('cs', $langs));
        $this->assertEquals($langs['en'], 'English [en]');
        $this->assertEquals($langs['cs'], 'Czech / Česky [cs]');
        $this->assertEquals($langs['xx'], '??? / Xx [xx]');
        $this->assertEquals($langs['yy'], 'Yy / ??? [yy]');
    }

    public function test_list_components(): void {

        $this->resetAfterTest();

        $stage = new amos_stage();
        $component = new amos_component('workshop', 'en', amos_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new amos_string('modulename', 'Workshop'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('workshop', 'en', amos_version::by_branch('MOODLE_39_STABLE'));
        $component->add_string(new amos_string('modulename', 'Workshop 2.x'));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth', 'en', amos_version::by_branch('MOODLE_310_STABLE'));
        $component->add_string(new amos_string('foo', 'Bar'));
        $stage->add($component);
        $component->clear();

        // This will play no role as there is no English original.
        $component = new amos_component('assign', 'cs', amos_version::by_branch('MOODLE_38_STABLE'));
        $component->add_string(new amos_string('pluginname', 'Úkol'));
        $stage->add($component);
        $component->clear();

        $stage->commit('Registering component strings', ['source' => 'unittest']);

        $comps = amos_tools::list_components();

        $this->assertEquals(gettype($comps), 'array');
        $this->assertEquals(count($comps), 2);
        $this->assertEquals($comps['auth'], 310);
        $this->assertEquals($comps['workshop'], 38);
    }

    public function test_execution_strings(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_20_STABLE');

        // This is to prevent situation where a string is added and immediately removed in the same second. Such
        // situations are not supported yet very well in AMOS. It would require to rewrite well tuned getting component
        // from snapshot.
        $past = time() - 1;
        $component = new amos_component('auth', 'en', $version);
        $component->add_string(new amos_string('authenticate', 'Authenticate', $past));
        $component->add_string(new amos_string('ldap', 'Use LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth_ldap', 'en', $version);
        $component->add_string(new amos_string('pluginname', 'LDAP', $past));
        $stage->add($component);
        $component->clear();

        $component = new amos_component('auth', 'cs', $version);
        $component->add_string(new amos_string('authenticate', 'Autentizovat', $past));
        $component->add_string(new amos_string('ldap', 'Pouzit LDAP', $past));
        $stage->add($component);
        $component->clear();
        unset($component);

        $stage->commit('Adding some testing strings', ['source' => 'unittest']);
        unset($stage);

        $stage = amos_tools::execute('MOV [ldap,core_auth],[pluginname,auth_ldap]', $version);
        $stage->commit('Moving string ldap into auth_ldap', ['source' => 'unittest']);
        unset($stage);

        $component = amos_component::from_snapshot('auth_ldap', 'cs', $version);
        $this->assertTrue($component->has_string('pluginname'));
        $component->clear();

        $component = amos_component::from_snapshot('auth', 'cs', $version);
        // The MOV command is an alias for CPY now, it does not actually remove anything.
        $this->assertTrue($component->has_string('ldap'));
        $component->clear();

        $component = amos_component::from_snapshot('auth', 'en', $version);
        $this->assertTrue($component->has_string('ldap'));  // English string are not affected by AMOS script!
        $component->clear();

        $component = amos_component::from_snapshot('auth_ldap', 'en', $version);
        $string = $component->get_string('pluginname');
        $this->assertEquals($string->text, 'LDAP');
        unset($string);
        $component->clear();
    }

    public function test_execution_strings_move(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_20_STABLE');
        $now = time();

        // This block emulates parse-core.php.
        $component = new amos_component('admin', 'en', $version);
        $component->add_string(new amos_string('configsitepolicy', 'OLD', $now - 2));
        $stage->add($component);
        $stage->rebase($now - 2, true, $now - 2);
        $stage->commit('Committed initial English string', ['source' => 'unittest'], true, $now - 2);
        $component->clear();
        unset($component);

        // This block emulates parse-lang.php.
        $component = new amos_component('admin', 'cs', $version);
        $component->add_string(new amos_string('configsitepolicy', 'OLD in cs', $now - 1));
        $stage->add($component);
        $stage->rebase();
        $stage->commit('Committed initial Czech translation', ['source' => 'unittest'], true, $now - 1);
        $component->clear();
        unset($component);

        // This block emulates parse-core.php again later.
        // Now the string is moved in the English pack by the developer who provides AMOS script in commit message.
        // This happened in b593d49d593ee778f525b4074f5ee7978c5e2960.
        $component = new amos_component('admin', 'en', $version);
        $component->add_string(new amos_string('sitepolicy_help', 'NEW', $now));
        $component->add_string(new amos_string('configsitepolicy', 'OLD', $now, true));
        $commitmsg = 'MDL-24570 multiple sitepolicy fixes + adding new separate guest user policy
AMOS BEGIN
 MOV [configsitepolicy,core_admin],[sitepolicy_help,core_admin]
AMOS END';
        $stage->add($component);
        $stage->rebase($now, true, $now);
        $stage->commit($commitmsg, ['source' => 'unittest'], true, $now);
        $component->clear();
        unset($component);

        // Execute AMOS script if the commit message contains some.
        if ($version->code >= 20) {
            $instructions = amos_tools::extract_script_from_text($commitmsg);
            if (!empty($instructions)) {
                foreach ($instructions as $instruction) {
                    $changes = amos_tools::execute($instruction, $version, $now);
                    $changes->rebase($now);
                    $changes->commit($commitmsg, ['source' => 'commitscript'], true, $now);
                    unset($changes);
                }
            }
        }

        // The moved string is gone from English.
        $componenten = amos_component::from_snapshot('admin', 'en', $version, $now);
        $this->assertFalse($componenten->has_string('configsitepolicy'));
        $this->assertTrue($componenten->has_string('sitepolicy_help'));
        $this->assertEquals('NEW', $componenten->get_string('sitepolicy_help')->text);
        $this->assertEquals(1, $componenten->get_number_of_strings());

        // It is still present in the raw snapshot of the Czech.
        $componentcs = amos_component::from_snapshot('admin', 'cs', $version, $now);
        $this->assertTrue($componentcs->has_string('configsitepolicy'));
        $this->assertTrue($componentcs->has_string('sitepolicy_help'));
        $this->assertEquals('OLD in cs', $componentcs->get_string('configsitepolicy')->text);
        $this->assertEquals('OLD in cs', $componentcs->get_string('sitepolicy_help')->text);
        $this->assertEquals(2, $componentcs->get_number_of_strings());

        // Prune all strings not present in the English (this is what exporting ZIPs does).
        $componentcs->intersect($componenten);
        $this->assertFalse($componentcs->has_string('configsitepolicy'));
        $this->assertTrue($componentcs->has_string('sitepolicy_help'));
        $this->assertEquals('OLD in cs', $componentcs->get_string('sitepolicy_help')->text);
        $this->assertEquals(1, $componentcs->get_number_of_strings());
    }

    public function test_legacy_component_name(): void {
        $this->assertEquals(testable_amos_tools::legacy_component_name('core'), 'moodle');
        $this->assertEquals(testable_amos_tools::legacy_component_name('core_grades'), 'grades');
        $this->assertEquals(testable_amos_tools::legacy_component_name('block_foobar'), 'block_foobar');
        $this->assertEquals(testable_amos_tools::legacy_component_name('auth_oauth2'), 'auth_oauth2');
        $this->assertEquals(testable_amos_tools::legacy_component_name('mod_forum2'), 'forum2');
        $this->assertEquals(testable_amos_tools::legacy_component_name('mod_foobar'), 'foobar');
        $this->assertEquals(testable_amos_tools::legacy_component_name('moodle'), 'moodle');
        $this->assertEquals(testable_amos_tools::legacy_component_name('admin'), 'admin');
        $this->assertEquals(testable_amos_tools::legacy_component_name(' mod_whitespace  '), 'whitespace');
        $this->assertEquals(testable_amos_tools::legacy_component_name('[syntaxerr'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('syntaxerr,'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('syntax err'), false);
        $this->assertEquals(testable_amos_tools::legacy_component_name('enrol__invalid'), false);
    }

    public function test_merge_strings_from_another_component(): void {
        // Prepare two components with some strings.
        $component19 = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_19_STABLE'));
        $component19->add_string(new amos_string('first', 'First $a'));
        $component19->add_string(new amos_string('second', 'Second \"string\"'));
        $component19->add_string(new amos_string('third', 'Third'));
        $component19->add_string(new amos_string('fifth', 'Fifth \"string\"'));

        $component20 = new amos_component('test', 'xx', amos_version::by_branch('MOODLE_20_STABLE'));
        $component20->add_string(new amos_string('second', '*deleted*', null, true));
        $component20->add_string(new amos_string('third', 'Third already merged'));
        $component20->add_string(new amos_string('fourth', 'Fourth only in component20'));
        // Merge component19 into component20.
        amos_tools::merge($component19, $component20);
        // Check the results.
        $this->assertEquals(4, $component19->get_number_of_strings());
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
        $this->assertEquals('*deleted*', $component20->get_string('second')->text);
        $this->assertTrue($component20->get_string('second')->deleted);
        $this->assertEquals('Third already merged', $component20->get_string('third')->text);
        $this->assertEquals('Fourth only in component20', $component20->get_string('fourth')->text);
        $this->assertFalse($component19->has_string('fourth'));
        $this->assertEquals('Fifth "string"', $component20->get_string('fifth')->text);
        // Clear source component and make sure that strings are still in the new one.
        $component19->clear();
        unset($component19);
        $this->assertEquals(5, $component20->get_number_of_strings());
        $this->assertEquals('First {$a}', $component20->get_string('first')->text);
    }

    public function test_get_affected_strings(): void {
        $this->resetAfterTest();
        $diff = file(dirname(__DIR__) . '/fixtures/parserdata002.txt');
        $affected = amos_tools::get_affected_strings($diff);
        $this->assertEquals(count($affected), 5);
        $this->assertTrue(in_array('configdefaultuserroleid', $affected));
        $this->assertTrue(in_array('confignodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nodefaultuserrolelists', $affected));
        $this->assertTrue(in_array('nolangupdateneeded', $affected));
        $this->assertTrue(in_array('mod/something:really_nasty-like0098187.this', $affected));
    }

    public function test_execution_forced_copy(): void {
        $this->resetAfterTest();

        $this->register_language('en', 22);
        $this->register_language('cs', 22);

        $stage = new amos_stage();
        $version = amos_version::by_branch('MOODLE_22_STABLE');
        $time = time();
        $component = new amos_component('assignment', 'cs', $version);
        $component->add_string(new amos_string('pluginname', 'Úkol (2.2)', $time - 60));
        $component->add_string(new amos_string('modulename', 'Úkol', $time - 120));
        $stage->add($component);
        $component->clear();
        unset($component);
        $stage->commit('Adding some testing strings', ['source' => 'unittest']);
        unset($stage);

        $stage = amos_tools::execute('FCP [pluginname,assignment],[modulename,assignment]', $version);
        $stage->commit('Forced copy of a string', ['source' => 'unittest']);
        unset($stage);

        $component = amos_component::from_snapshot('assignment', 'cs', $version);
        $this->assertEquals('Úkol (2.2)', $component->get_string('pluginname')->text);
        $this->assertEquals('Úkol (2.2)', $component->get_string('modulename')->text);
        $component->clear();
    }

    public function test_backport_translations(): void {
        $this->resetAfterTest();

        $this->register_language('en', 20);
        $this->register_language('cs', 20);
        $this->register_language('en_fix', 20);

        $stage = new amos_stage();
        $version21 = amos_version::by_branch('MOODLE_21_STABLE');
        $version22 = amos_version::by_branch('MOODLE_22_STABLE');
        $version23 = amos_version::by_branch('MOODLE_23_STABLE');
        $version24 = amos_version::by_branch('MOODLE_24_STABLE');
        $version25 = amos_version::by_branch('MOODLE_25_STABLE');
        $time = time();

        // Register Foo plugin English strings for Moodle 2.3 and higher (pretend that happened 180 days ago).
        $component = new amos_component('foo', 'en', $version23);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('done', 'Done', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 180 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 180 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.3 strings', ['source' => 'unittest']);

        // Translate only some of the strings into Czech on 2.3.
        $component = new amos_component('foo', 'cs', $version23);
        $component->add_string(new amos_string('modulename', 'Fu', $time - 179 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 179 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.3 strings into Czech', ['source' => 'unittest']);

        // Change one and add one string in 2.4.
        $component = new amos_component('foo', 'en', $version24);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('done', 'Finished', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('end', 'End', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 90 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 90 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.4 strings', ['source' => 'unittest']);

        // Update 2.3 Czech translation.
        $component = new amos_component('foo', 'cs', $version24);
        $component->add_string(new amos_string('modulename', 'Fu', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('done', 'Ukončeno', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('end', 'Konec', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 45 * DAYSECS));
        $component->add_string(new amos_string('orphan', 'Orphan', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Translate some Foo 2.4 strings into Czech', ['source' => 'unittest']);

        $component = new amos_component('foo', 'en_fix', $version24);
        $component->add_string(new amos_string('modulename', 'Fooh', $time - 45 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Since 2.4, the module name is different', ['source' => 'unittest']);

        testable_amos_tools::backport_translations('foo');

        $component = amos_component::from_snapshot('foo', 'cs', $version23);
        // The modulename translation on 2.3 was not affected by the 2.4 version because the value is identical.
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('modulename')->timemodified);
        // This was already present.
        $this->assertTrue($component->has_string('aaa'));
        // The bbb translation was backported from 2.4 with the current timestamp.
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->get_string('bbb')->timemodified >= $time);
        // The end string is introduced in 2.4 only so it was not backported.
        $this->assertFalse($component->has_string('end'));
        // Same reason, the orphan string is not in English is it is not backported.
        $this->assertFalse($component->has_string('orphan'));
        // The 2.4 English original of "done" is different from 2.3, so 2.4 translation is not backported.
        $this->assertFalse($component->has_string('done'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'en_fix', $version23);
        // The en_fix strings are exception and not backported.
        $this->assertFalse($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'en_fix', $version24);
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'en_fix', $version25);
        // The string exists on 2.5 implicitly (as it exists since 2.4), not as a result of backporting.
        $this->assertTrue($component->has_string('modulename'));
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'cs', $version24);
        // There was no change committed for 2.4 and the original 2.3 version is used.
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('modulename')->timemodified);
        $this->assertTrue($component->has_string('aaa'));
        $this->assertEquals($time - 179 * DAYSECS, $component->get_string('aaa')->timemodified);
        // This was committed in 2.4.
        $this->assertTrue($component->has_string('bbb'));
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('bbb')->timemodified);
        $this->assertTrue($component->has_string('done'));
        $this->assertEquals('Ukončeno', $component->get_string('done')->text);
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        // Translate the missing "done" string in 2.3 cs.
        $component = new amos_component('foo', 'cs', $version23);
        $component->add_string(new amos_string('done', 'Hotovo', $time - 40 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add missing 2.3 string', ['source' => 'unittest']);

        // The added translation is valid for 2.3 only as the 2.4 version still applies even if was committed earlier.
        $component = amos_component::from_snapshot('foo', 'cs', $version23);
        $this->assertEquals('Hotovo', $component->get_string('done')->text);
        $this->assertEquals($time - 40 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        $component = amos_component::from_snapshot('foo', 'cs', $version24);
        $this->assertEquals('Ukončeno', $component->get_string('done')->text);
        $this->assertEquals($time - 45 * DAYSECS, $component->get_string('done')->timemodified);
        $component->clear();

        // New version of the Foo for Moodle 2.5 is released, strings have not changed.
        $component = new amos_component('foo', 'en', $version25);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('done', 'Finished', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('end', 'End', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 20 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 20 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.5 strings', ['source' => 'unittest']);

        testable_amos_tools::backport_translations('foo', ['en', 'cs']);

        $component = amos_component::from_snapshot('foo', 'cs', $version25);
        $this->assertTrue($component->has_string('modulename'));
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertTrue($component->has_string('aaa'));
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->has_string('done'));
        $component->clear();

        // Foo plugin is released for 2.1 too (marked as supporting 2.1 in the plugins directory).
        $component = new amos_component('foo', 'en', $version21);
        $component->add_string(new amos_string('modulename', 'Foo', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('done', 'Done', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('aaa', 'AAA', $time - 15 * DAYSECS));
        $component->add_string(new amos_string('bbb', 'BBB', $time - 15 * DAYSECS));
        $stage->add($component);
        $component->clear();
        $stage->commit('Add Foo 2.1 strings', ['source' => 'unittest']);

        // Without backporting, there would be no 2.1 Czech translation.
        $component = amos_component::from_snapshot('foo', 'cs', $version21);
        $this->assertFalse($component->has_string());

        // Backport the Czech translations.
        testable_amos_tools::backport_translations('foo');

        $component = amos_component::from_snapshot('foo', 'cs', $version21);
        $this->assertEquals('Fu', $component->get_string('modulename')->text);
        $this->assertTrue($component->get_string('modulename')->timemodified >= $time);
        $this->assertEquals('AAA', $component->get_string('aaa')->text);
        $this->assertTrue($component->get_string('aaa')->timemodified >= $time);
        $this->assertTrue($component->has_string('bbb'));
        $this->assertTrue($component->get_string('bbb')->timemodified >= $time);
        // The end string is introduced in 2.4 only so it was not backported.
        $this->assertFalse($component->has_string('end'));
        // Same reason, the orphan string is not in English is it is not backported.
        $this->assertFalse($component->has_string('orphan'));
        // The 2.3 English original of "done" is backported even if it is changed again in 2.4.
        $this->assertEquals('Hotovo', $component->get_string('done')->text);
        $this->assertTrue($component->get_string('done')->timemodified >= $time);
        $component->clear();
    }
}
