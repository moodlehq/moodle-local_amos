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
 * English strings for AMOS local module
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['about'] = '<p>AMOS is a central repository of Moodle strings and their history. It tracks the addition of English strings into Moodle code, gathers translations, handles common translation tasks and generates language packages to be deployed on Moodle servers.</p>
<p>See <a href="https://docs.moodle.org/dev/AMOS_manual">AMOS documentation</a> for more information.</p>';
$string['amos'] = 'AMOS translation toolkit';
$string['amos:changecontriblang'] = 'Change language of contributed strings';
$string['amos:commit'] = 'Commit the staged strings into the main repository';
$string['amos:execute'] = 'Execute the given AMOScript';
$string['amos:importfile'] = 'Import translations from uploaded file and stage them';
$string['amos:importstrings'] = 'Import strings (including the English ones) directly into the main repository';
$string['amos:manage'] = 'Manage AMOS portal';
$string['amos:stage'] = 'Use AMOS translation tool and stage the strings';
$string['amos:stash'] = 'Store the current stage into the persistent stash';
$string['amos:usegoogle'] = 'Use Google Translate services';
$string['applangindexfile'] = 'Moodle App LangIndex file location';
$string['applangindexfile_desc'] = 'Complete Url where to download the file. It will be done by a cron task.';
$string['commitbutton'] = 'Commit';
$string['commitkeepstaged'] = 'Keep strings staged';
$string['commitmessage'] = 'Commit message';
$string['commitmessageempty'] = 'Please fill the commit message';
$string['commitstage'] = 'Commit staged strings';
$string['commitstage_help'] = 'Permanently store all staged translations in AMOS repository. Stage is automatically pruned and rebased before it is committed. Only committable strings are stored. That means that only translations below highlighted in green will be stored. The stage is cleared after the commit.';
$string['committableall'] = 'all languages';
$string['committablenone'] = 'no languages allowed - please contact AMOS manager';
$string['componentsall'] = 'All';
$string['componentsapp'] = 'Moodle App';
$string['componentsenlarge'] = 'Enlarge';
$string['componentsnone'] = 'None';
$string['componentsstandard'] = 'Standard';
$string['confirmaction'] = 'This can not be undone. Are you sure?';
$string['contribaccept'] = 'Accept';
$string['contribactions'] = 'Contributed translation actions';
$string['contribactions_help'] = 'Depending on your rights and the contribution workflow, you can have some of the following actions available:

* Apply - copy the contributed translation into your stage, does not modify the contribution record
* Assign to me - set yourself as the contribution assignee, that is the person resposible for the contribution review and integration
* Unassign - set nobody as the contribution assignee
* Start review - assign the new contribution to yourself, set its status to \'In review\' and copy the submitted translation into your stage
* Accept - mark the contribution as accepted
* Reject - mark the contribution as rejected, please describe the reasons in a comment.

The contributor is informed by email whenever the status of his contribution changes.';
$string['contribactions_link'] = 'AMOS#Contributing_to_a_language_pack';
$string['contribapply'] = 'Apply';
$string['contribassignee'] = 'Assignee';
$string['contribassigneenone'] = '-';
$string['contribassigntome'] = 'Assign to me';
$string['contribauthor'] = 'Author';
$string['contribclosedno'] = 'Hide resolved contributions';
$string['contribclosedyes'] = 'Show resolved contributions';
$string['contribcomponents'] = 'Components';
$string['contribid'] = 'ID';
$string['contribincomingnone'] = 'No incoming contributions';
$string['contribincomingsome'] = 'Incoming contributions ({$a})';
$string['contriblanguage'] = 'Language';
$string['contriblanguagebutton'] = 'Convert';
$string['contriblanguagechange'] = 'Fixing wrong contribution language';
$string['contriblanguagechange_help'] = 'If the contribution has been submitted to the wrong language pack by mistake, select the correct language in the dropdown menu below then click the Convert button.';
$string['contriblanguagereport'] = 'Reporting wrong contribution language';
$string['contriblanguagereport_help'] = 'If this contribution has been submitted to the wrong language pack by mistake, please copy and paste the contribution URL into an email to `translation@moodle.org`. The contribution will then be moved to the correct language.';
$string['contriblanguagewrong'] = 'Wrong language?';
$string['contribreject'] = 'Reject';
$string['contribresign'] = 'Unassign';
$string['contribstaged'] = 'Staged contribution <a href="contrib.php?id={$a->id}">#{$a->id}</a> by {$a->author}';
$string['contribstagedinfo'] = 'Staged contribution';
$string['contribstagedinfo_help'] = 'The stage contains strings that were contributed by a community member. The language pack maintainers are supposed to review them and then set their status to either Accepted (if there were committed) or Rejected (if they can\'t be included in the official language pack for some reason).';
$string['contribstagedinfo_link'] = 'AMOS#Contributing_to_a_language_pack';
$string['contribstartreview'] = 'Start review';
$string['contribstatus'] = 'Status';
$string['contribstatus_help'] = 'Workflow of a contributed translation consists of following states:

* New - the contribution has been submitted but it was not reviewed yet
* In review - the contribution has been assigned to a language pack maintainer and was staged for review
* Rejected - the language pack maintainer has rejected the contribution and probably left an explanation in a comment
* Accepted - the contribution has been accepted by the language pack maintainer';
$string['contribstatus_link'] = 'AMOS#Contributing_to_a_language_pack';
$string['contribstatus0'] = 'New';
$string['contribstatus10'] = 'In review';
$string['contribstatus20'] = 'Rejected';
$string['contribstatus30'] = 'Accepted';
$string['contribstrings'] = 'Strings';
$string['contribstringseq'] = '{$a->orig} new';
$string['contribstringsnone'] = '{$a->orig} (all of them are already translated in the lang pack)';
$string['contribstringssome'] = '{$a->orig} ({$a->same} of them already have more recent translation)';
$string['contribsubject'] = 'Subject';
$string['contribsubmittednone'] = 'No submitted contributions';
$string['contribsubmittedsome'] = 'Your contributions ({$a})';
$string['contribtimemodified'] = 'Modified';
$string['contribnotif'] = '[AMOS] Contribution notification (#{$a->id})';
$string['contribnotifaccepted'] = '{$a->fullname} has accepted your contributed translation
#{$a->id} {$a->subject}

---------------------------------------------------------------------
Contribution page: {$a->contriburl}';
$string['contribnotifcommented'] = '{$a->fullname} has commented on the contributed translation
#{$a->id} {$a->subject}
---------------------------------------------------------------------

{$a->message}

---------------------------------------------------------------------
Contribution page: {$a->contriburl}';
$string['contribnotifconverted'] = '{$a->fullname} has converted your contributed translation
#{$a->id} {$a->subject}

Thanks for your contribution. However, it seems it was submitted to the wrong
language pack by mistake. Thus your contribution has been rejected from the
wrong language pack and moved to the correct language pack for review by the
language pack maintainer. No action from you is required.

In future, please double-check that you have selected your own language before
you start translating strings.

---------------------------------------------------------------------
Original contribution page: {$a->contriborigurl}
New contribution page: {$a->contribnewurl}';
$string['contribnotifrejected'] = '{$a->fullname} has rejected your contributed translation
#{$a->id} {$a->subject}

---------------------------------------------------------------------
Contribution page: {$a->contriburl}';
$string['contribnotifsubmitted'] = '{$a->fullname} has submitted a new contributed translation
#{$a->id} {$a->subject}
---------------------------------------------------------------------

{$a->message}

* Language: {$a->language}
* Components: {$a->components}
* Strings: {$a->strings}

---------------------------------------------------------------------
Contribution page: {$a->contriburl}';
$string['contribnotifpending'] = 'There is a pending contributed translation requiring your action
#{$a->id} {$a->subject}

As the language pack maintainer, you are supposed to review and eventually
commit all submitted contributions. When done, please mark them as accepted or
rejected.

See {$a->docsurl} for more details.
---------------------------------------------------------------------
Contribution page: {$a->contriburl}';
$string['contribute'] = 'Contribute';
$string['contributenow'] = 'Contribute now!';
$string['contributestats'] = 'Total of <strong>{$a->count}</strong> strings translated by community members have been submitted into AMOS so far.';
$string['contributethanks'] = 'Many thanks to {$a->listcontributors} for their recent contributions!';
$string['contributethankslist'] = '{$a->contributor1}, {$a->contributor2}, {$a->contributor3} and {$a->contributor4}';
$string['contributions'] = 'Contributions';
$string['creditsaddmaintainer'] = 'Add maintainer';
$string['creditsaddcontributor'] = 'Add contributor';
$string['creditsdelmaintainer'] = 'Remove maintainer';
$string['creditsdelcontributor'] = 'Remove contributor';
$string['creditscontact'] = 'Send message';
$string['creditscontributors'] = 'Other contributors';
$string['creditsmaintainedby'] = 'Maintained by';
$string['creditsnomaintainer'] = 'No maintainer at the moment. <a href="{$a->url}">Become one!</a>';
$string['creditsthanks'] = 'On this page, we wish to thank everyone who has contributed to Moodle translations. Their work has made the spread of Moodle across the world  possible.';
$string['creditstitlelong'] = 'Language pack maintainers and contributors';
$string['creditstitleshort'] = 'Credits';
$string['diff'] = 'Compare';
$string['diffaction'] = 'If a difference is detected';
$string['diffaction1'] = 'Stage both translations on their branches';
$string['diffaction2'] = 'Stage the more recent translation on both branches';
$string['diffmode'] = 'Stage strings if';
$string['diffmode1'] = 'English strings have changed but translated ones have not';
$string['diffmode2'] = 'English strings have not changed but translated ones have';
$string['diffmode3'] = 'Either English or translated strings have changed (but not both)';
$string['diffmode4'] = 'Both English and translated strings have changed';
$string['diffprogress'] = 'Comparing selected branches';
$string['diffprogressdone'] = 'Total of {$a} differences found';
$string['diffstringmode'] = 'Switch diff mode';
$string['diffstrings'] = 'Compare strings at two branches';
$string['diffstrings_help'] = 'This will compare all strings at the two selected branches. If there is difference in strings on the branches, both versions are staged. You can then use "Edit staged strings" feature to review and fix the changes as needed.';
$string['diffversions'] = 'Versions';
$string['err_exception'] = 'Error: {$a}';
$string['err_invalidlangcode'] = 'Invalid language code';
$string['err_parser'] = 'Parsing error: {$a}';
$string['filtercmp'] = 'Components';
$string['filtercmpnothingselected'] = 'Please select some component';
$string['filtercmp_desc'] = 'Show strings of these components';
$string['filterlng'] = 'Languages';
$string['filterlngnothingselected'] = 'Please select some language';
$string['filterlng_desc'] = 'Display translations in these languages';
$string['filtermis'] = 'Miscellaneous';
$string['filtermis_desc'] = 'Additional conditions on strings to display';
$string['filtermisfglo'] = 'greylisted strings only';
$string['filtermisfhas'] = 'translated strings only';
$string['filtermisfhlp'] = 'help strings only';
$string['filtermisfmis'] = 'missing and outdated strings only';
$string['filtermisfapp'] = 'only strings used in the Moodle App';
$string['filtermisfapp_help'] = 'This string is used in Moodle Apps as {$a}';
$string['filtermisfout'] = 'outdated strings only';
$string['filtermisfstg'] = 'staged strings only';
$string['filtermisfwog'] = 'without greylisted strings';
$string['filtersid'] = 'String identifier';
$string['filtersid_desc'] = 'The key in the array of strings';
$string['filtersidpartial'] = 'partial match';
$string['filtertxt'] = 'Substring';
$string['filtertxt_desc'] = 'String must contain given text';
$string['filtertxtcasesensitive'] = 'case-sensitive';
$string['filtertxtregex'] = 'regex';
$string['filterver'] = 'Versions';
$string['filtervernothingselected'] = 'Please select some version';
$string['filterver_desc'] = 'Show strings from these Moodle versions';
$string['found'] = 'Found: {$a->found} &nbsp;&nbsp;&nbsp; Missing: {$a->missing} ({$a->missingonpage})';
$string['foundinfo'] = 'Number of found strings';
$string['foundinfo_help'] = 'Shows the total number of rows in the translator table, number of missing translations and number of missing translations at the current page.';
$string['googletranslate'] = 'ask google';
$string['gotofirst'] = 'go to the first page';
$string['gotoprevious'] = 'go to the previous page';
$string['greylisted'] = 'Greylisted strings';
$string['greylisted_help'] = 'For legacy reasons, a Moodle language pack may contain strings that are no longer used but have not yet been deleted. These strings are \'greylisted\'. Once it has been confirmed that a greylisted string is no longer used, it is removed from the language pack.

If you notice a greylisted string which is still being used in Moodle, please inform us by a forum post in Translating Moodle course at this site. Otherwise, you can save valuable time by translating strings which are most likely used in Moodle and ignoring greylisted strings.';
$string['greylistedwarning'] = 'greylisted';
$string['importfile'] = 'Import translated strings from file';
$string['importfile_help'] = 'If you have your strings translated offline, you can stage them via this form.

* The file must be valid Moodle PHP strings definition file. Look at `/lang/en/` directory of your Moodle installation for examples.
* Name of the file must match the one with English strings definitons for the given component (like `moodle.php`, `assignment.php` or `enrol_manual.php`).

All strings found in the file will be staged for the selected version and language.

Multiple PHP files can be processed at once if you put them into a ZIP file.';
$string['importfile_link'] = 'local/amos/importfile';
$string['language'] = 'Language';
$string['languages'] = 'Languages';
$string['languagesall'] = 'All';
$string['languagesnone'] = 'None';
$string['lastavailable'] = 'Latest available version';
$string['log'] = 'Log';
$string['logfilterbranch'] = 'Versions';
$string['logfiltercommithash'] = 'git hash';
$string['logfiltercommitmsg'] = 'Commit message contains';
$string['logfiltercommits'] = 'Commit filter';
$string['logfiltercommittedafter'] = 'Committed after';
$string['logfiltercommittedbefore'] = 'Committer before';
$string['logfiltercomponent'] = 'Components';
$string['logfilterlang'] = 'Languages';
$string['logfiltershow'] = 'Show filtered commits and strings';
$string['logfiltersource'] = 'Source';
$string['logfiltersourceamos'] = 'amos (web based translator)';
$string['logfiltersourceautomerge'] = 'automerge (translation copied from another branch)';
$string['logfiltersourcebot'] = 'bot (bulk operations executed by a script)';
$string['logfiltersourcecommitscript'] = 'commitscript (AMOScript in the commit message)';
$string['logfiltersourcefixdrift'] = 'fixdrift (fixed AMOS-git drift)';
$string['logfiltersourcegit'] = 'git (git mirror of Moodle source code and 1.x packs)';
$string['logfiltersourceimport'] = 'import (imported strings for a contributed plugin)';
$string['logfiltersourcerevclean'] = 'revclean (reverse clean-up process)';
$string['logfilterstringid'] = 'String identifier';
$string['logfilterstrings'] = 'String filter';
$string['logfilterusergrp'] = 'Committer';
$string['logfilterusergrpor'] = ' or ';
$string['maintainers'] = 'Maintainers';
$string['markuptodate'] = 'Marking the translation as up-to-date';
$string['markuptodate_help'] = 'AMOS detected that the string may be outdated as the English version was modified after it had been translated. Review the translation. If you find it up-to-date, click the checkbox. Edit it otherwise.';
$string['markuptodate_link'] = 'local/amos/outdatedstrings';
$string['markuptodatelabel'] = 'Mark as up-to-date';
$string['merge'] = 'Merge';
$string['mergestrings'] = 'Merge strings from another branch';
$string['mergestrings_help'] = 'This will pick and stage all strings from the source branch that are not translated yet in the target branch and are used there. You can use this tool to copy a translated string into all other versions of the pack. Only language pack maintainers can use this tool.';
$string['mergestrings_link'] = 'local/amos/merge';
$string['messageprovider:checker'] = 'Language pack checker results';
$string['messageprovider:contribution'] = 'Contributed translations';
$string['morefilteringoptions'] = 'More options';
$string['newlanguage'] = 'New language';
$string['nodiffs'] = 'No differences found';
$string['nofiletoimport'] = 'Please provide a file to import from.';
$string['nologsfound'] = 'No strings found, please modify filters';
$string['nostringsfound'] = 'No strings found';
$string['nostringsfoundonpage'] = 'No strings found on page {$a}';
$string['nostringtoimport'] = 'No valid string found in the file, or no version matching. Make sure the file has correct filename and is properly formatted. It could also be there\'s a version mismatch, e.g. you\'re submitting for 3.5 but there\'s no 3.5 version of the plugin.';
$string['nothingtomerge'] = 'The source branch does not contain any new strings that would be missing at the target branch. Nothing to merge.';
$string['nothingtostage'] = 'The operation did not return any string that could be staged.';
$string['novalidzip'] = 'Unable to extract the ZIP file.';
$string['numofcommitsabovelimit'] = 'Found {$a->found} commits matching the commit filter, using {$a->limit} most recent';
$string['numofcommitsunderlimit'] = 'Found {$a->found} commits matching the commit filter';
$string['numofmatchingstrings'] = 'Within that, {$a->strings} modifications in {$a->commits} commits match the string filter';
$string['outdatednotcommitted'] = 'Outdated string';
$string['outdatednotcommitted_help'] = 'AMOS detected that the string may be outdated as the English version was modified after it had been translated. Please review the translation.';
$string['outdatednotcommittedwarning'] = 'Outdated';
$string['ownstashactions'] = 'Stash actions';
$string['ownstashactions_help'] = '* Apply - copy the translated strings from the stash into the stage and keep the stash unmodified. If the string is already in the stage, it is overwritten with the stashed one.
* Pop - move the translated strings from the stash into the stage and drop the stash (that is Apply and Drop).
* Drop - throw away the stashed strings.
* Submit - opens a form for submitting the stash to the official language maintainers so they can include your contribution in the official language pack.';
$string['ownstashes'] = 'Your stashes';
$string['ownstashes_help'] = 'This is a list of all your stashes. ';
$string['ownstashesnone'] = 'No own stashes found';
$string['permalink'] = 'Permalink';
$string['placeholder'] = 'Placeholders';
$string['placeholder_help'] = 'Placeholders are special statements like `{$a}` or `{$a->something}` within the string. They are replaced with a value when the string is actually printed.

It is important to copy them exactly as they are in the original string. Do not translate them nor change their left-to-right orientation.';
$string['placeholderwarning'] = 'placeholders';
$string['pluginclasscore'] = 'Core subsystems';
$string['pluginclassnonstandard'] = 'Non-standard plugins';
$string['pluginclassstandard'] = 'Standard plugins';
$string['pluginname'] = 'AMOS';
$string['presetcommitmessage'] = 'Contributed translation #{$a->id} by {$a->author}';
$string['presetcommitmessage2'] = 'Merged missing strings from {$a->source} to {$a->target} branch';
$string['presetcommitmessage3'] = 'Fixing differences between {$a->versiona} and {$a->versionb}';
$string['privacy:commitnumber'] = 'Commit {$a}';
$string['privacy:contribnumber'] = 'Contribution {$a}';
$string['privacy:filterusage'] = 'Filter usage';
$string['privacy:stashnumber'] = 'Stash {$a}';
$string['privacy:metadata:db:amoscommits'] = 'Keeps all commit related meta-data.';
$string['privacy:metadata:db:amoscommits:commitmsg'] = 'Commit message provided by the user.';
$string['privacy:metadata:db:amoscommits:timecommitted'] = 'Timestamp of the commit.';
$string['privacy:metadata:db:amoscommits:userinfo'] = 'User identification as provided in the commit source location, e.g. in the Git.';
$string['privacy:metadata:db:amoscontributions'] = 'Contributed translations from the community members.';
$string['privacy:metadata:db:amoscontributions:lang'] = 'The code of the language this contribution belongs to.';
$string['privacy:metadata:db:amoscontributions:message'] = 'Initial message describing the submission.';
$string['privacy:metadata:db:amoscontributions:stashid'] = 'Internal identifier of the attached stash.';
$string['privacy:metadata:db:amoscontributions:status'] = 'The workflow status of the contribution".';
$string['privacy:metadata:db:amoscontributions:subject'] = 'Subject of the contributed submission.';
$string['privacy:metadata:db:amoscontributions:timecreated'] = 'Timestamp of when the contribution record was created.';
$string['privacy:metadata:db:amoscontributions:timemodified'] = 'Timestamp of when the contribution record was recently modified.';
$string['privacy:metadata:db:amosfilterusage'] = 'Log of AMOS translator filter usage.';
$string['privacy:metadata:db:amosfilterusage:currentlang'] = 'The current session language.';
$string['privacy:metadata:db:amosfilterusage:ismaintainer'] = 'Does the user have permission to commit to the AMOS repository?';
$string['privacy:metadata:db:amosfilterusage:numofcomponents'] = 'Number of selected components';
$string['privacy:metadata:db:amosfilterusage:numoflanguages'] = 'Number of selected languages';
$string['privacy:metadata:db:amosfilterusage:numofversions'] = 'Number of selected versions';
$string['privacy:metadata:db:amosfilterusage:showexistingonly'] = 'Should only existing translations be displayed?';
$string['privacy:metadata:db:amosfilterusage:showgreylistedonly'] = 'Should only greylisted string be displayed?';
$string['privacy:metadata:db:amosfilterusage:showhelpsonly'] = 'Should only help strings be displayed?';
$string['privacy:metadata:db:amosfilterusage:showmissingonly'] = 'Should only missing and outdated strings be displayed?';
$string['privacy:metadata:db:amosfilterusage:showoutdatedonly'] = 'Should only outdated strings be displayed?';
$string['privacy:metadata:db:amosfilterusage:showstagedonly'] = 'Should only staged strings be displayed?';
$string['privacy:metadata:db:amosfilterusage:showwithoutgreylisted'] = 'Should greylisted strings be removed from the results?';
$string['privacy:metadata:db:amosfilterusage:stringidpartial'] = 'Is partial match for stringid requested?';
$string['privacy:metadata:db:amosfilterusage:substringcasesensitive'] = 'Should the substring be considered as case sensitive?';
$string['privacy:metadata:db:amosfilterusage:substringregex'] = 'Should the substring be considered as a regex?';
$string['privacy:metadata:db:amosfilterusage:timesubmitted'] = 'Timestamp of when the filter form was submitted';
$string['privacy:metadata:db:amosfilterusage:usercountry'] = 'User\'s country code as selected in their profile';
$string['privacy:metadata:db:amosfilterusage:userlang'] = 'User\'s preferred language from their profile';
$string['privacy:metadata:db:amosfilterusage:usesdefaultlang'] = 'Did the user use the pre-selected languages?';
$string['privacy:metadata:db:amosfilterusage:usesdefaultversion'] = 'Did the user use the pre-selected versions in the form?';
$string['privacy:metadata:db:amosfilterusage:withstringid'] = 'Was the stringid specified?';
$string['privacy:metadata:db:amosfilterusage:withsubstring'] = 'Was a substring to search for specified?';
$string['privacy:metadata:db:amosstashes'] = 'Keeps the information about the stashes in the stashpool';
$string['privacy:metadata:db:amosstashes:components'] = 'List of components that are stored in the stashed stage';
$string['privacy:metadata:db:amosstashes:id'] = 'Internal identifier of the stash';
$string['privacy:metadata:db:amosstashes:languages'] = 'List of languages that are contained in the stashed stage';
$string['privacy:metadata:db:amosstashes:message'] = 'The full message describing the stash, used as the default commit message when the stash is submitted as a contributed translation.';
$string['privacy:metadata:db:amosstashes:name'] = 'The name or title of the stash';
$string['privacy:metadata:db:amosstashes:strings'] = 'The title of the stash';
$string['privacy:metadata:db:amosstashes:timecreated'] = 'Timestamp of when the stash was created';
$string['privacy:metadata:db:amosstashes:timemodified'] = 'Timestamp of when the record was recently modified';
$string['privacy:metadata:db:amostranslators'] = 'Describes the relationship between a language pack and a user. This can be either maintainer (has rights to commits to the language pack) or explicit contributor (displayed at the credits page even without submitted contributions).';
$string['privacy:metadata:db:amostranslators:lang'] = 'The language code that the translator is allowed to translate, or asterisk for all languages.';
$string['privacy:metadata:db:amostranslators:status'] = 'Determines whether the user is a maintainer (0) or an explicit contributor (1) of the language pack.';
$string['privacy:metadata:external:languagepacks'] = 'Translation contributions are exported into ZIP files that are distributted globally and installed into Moodle sites.';
$string['privacy:metadata:external:languagepacks:email'] = 'Contributor email address may be included in the language pack files.';
$string['privacy:metadata:external:languagepacks:firstname'] = 'Contributor first name may be included in the language pack files.';
$string['privacy:metadata:external:languagepacks:lastname'] = 'Contributor last name may be included in the language pack files.';
$string['privacy:metadata:subsystem:comment'] = 'Describe how the comment subsystem is used by the plugin.';
$string['privileges'] = 'Your privileges';
$string['privilegesnone'] = 'You have read-only access to public information.';
$string['processing'] = 'Processing ...';
$string['propagate'] = 'Propagate translations';
$string['propagate_help'] = 'Staged translations can be propagated to selected branches. AMOS iterates over the list of staged translations and tries to apply them to each selected target branch. Propagation is not possible if:

* the English originals of the string are different on the source and target branches,
* the string is staged several times with different translation';
$string['propagatednone'] = 'No translations propagated';
$string['propagatedsome'] = '{$a} staged translations propagated';
$string['propagaterun'] = 'Propagate';
$string['quicklinks'] = 'Quick links';
$string['quicklinks_amos'] = 'AMOS translator';
$string['quicklinks_forum'] = 'Translation forum';
$string['quicklinks_manual'] = 'User manual';
$string['quicklinks_newcomers'] = 'Help for newcomers';
$string['requestactions'] = 'Action';
$string['requestactions_help'] = '* Apply - copy the translated strings from the pull request into your stage. If the string is already in the stage, it is overwritten with the stashed one.
* Hide - blocks the pull request so that it is not displayed to you any more.';
$string['savefilter'] = 'Save filter settings';
$string['script'] = 'AMOScript';
$string['script_help'] = 'AMOScript is a set of instructions to execute over the strings repository.';
$string['scriptexecute'] = 'Execute and stage result';
$string['sourceversion'] = 'Source version';
$string['stage'] = 'Stage';
$string['stageactions'] = 'Stage actions';
$string['stageactions_help'] = '* Edit staged strings - modifies the translator filter settings so that only staged translations are displayed.
* Prune non-committable strings - unstage all translations that you are not allowed to commit. Stage is pruned automatically before it is committed.
* Rebase - unstage all translations that either do not modify the current translation or are older than the most recent translation in the repository. Stage is rebased automatically before it is committed.
* Unstage all - clears the stage, all staged translations are lost.';
$string['stagedownload'] = 'Download';
$string['stageedit'] = 'Edit staged strings';
$string['stagetoolopen'] = 'Go to the stage';
$string['stageprune'] = 'Prune non-committable';
$string['stagerebase'] = 'Rebase';
$string['stagestringsnocommit'] = 'There are {$a->staged} staged strings';
$string['stagestringsnone'] = 'There are no staged strings';
$string['stagestringssome'] = 'There are {$a->staged} staged strings, {$a->committable} of them can be committed';
$string['stagesubmit'] = 'Send strings to language pack maintainers';
$string['stagetranslation'] = 'Translation';
$string['stagetranslation_help'] = 'Displays the staged translation to be committed. The background color of the cell means:

* Green - you have added a missing translation and you are allowed to commit it.
* Yellow - you have modified a string and you are allowed to commit the change.
* Blue - you have modified the translation or added a missing translation but you are not allowed to commit it into the given language.
* No color - the staged translation is the same as the current one and therefore will not be committed.';
$string['stageunstageall'] = 'Unstage all';
$string['stashactions'] = 'Save work in progress';
$string['stashactions_help'] = 'Stash is a snapshot of the current stage. Stashes can be submitted to the official language pack maintainers for inclusion into the language pack.';
$string['stashapply'] = 'Apply';
$string['stashautosave'] = 'Automatically saved backup stash';
$string['stashautosave_help'] = 'This stash contains the most recent snapshot of your stage. You can use it as a backup for cases when all strings are unstaged by accident, for example. Use \'Apply\' action to copy all stashed strings back into the stage (will overwrite the string if it is already staged).';
$string['stashcomponents'] = '<span>Components:</span> {$a}';
$string['stashdownload'] = 'Download';
$string['stashdrop'] = 'Drop';
$string['stashes'] = 'Stashes';
$string['stashlanguages'] = '<span>Languages:</span> {$a}';
$string['stashpop'] = 'Pop';
$string['stashpush'] = 'Push all staged strings into a new stash';
$string['stashstrings'] = '<span>Number of strings:</span> {$a}';
$string['stashsubmit'] = 'Submit to maintainers';
$string['stashsubmitdetails'] = 'Submitting details';
$string['stashsubmitmessage'] = 'Message';
$string['stashsubmitsubject'] = 'Subject';
$string['stashtitle'] = 'Stash title';
$string['stashtitledefault'] = 'Work in progress - {$a->time}';
$string['stringhistory'] = 'History';
$string['strings'] = 'Strings';
$string['submitting'] = 'Submitting a contribution';
$string['submitting_help'] = 'This will send translated strings to official language maintainers. They will be able to apply your work into their stage, review it and eventually commit. Please provide a message for them describing your work and why you would like to see your contribution included.';
$string['targetversion'] = 'Target version';
$string['timeline'] = 'timeline';
$string['translatortool'] = 'Translator';
$string['translatortoolopen'] = 'Open AMOS translator';
$string['translatortranslation'] = 'Translation';
$string['translatortranslation_help'] = 'Click the cell to turn it into the input editor. Insert the translation and click outside the cell to stage the translation. The background color of the cell means:

* Green - the string is already translated, you can eventually modify the translation.
* Yellow - the string may be out-dated. The English original was probably modified after the string had been translated.
* Red - the string is not translated yet.
* Blue - you have modified the translation and it is now staged.
* Grey - AMOS can\'t be used to translate this string. For example strings for Moodle 1.9 must be edited via the legacy CVS access only.

Language pack maintainers can see a small red symbol in the corner of the cells they are allowed to commit.';
$string['typecontrib'] = 'Non-standard plugins';
$string['typecore'] = 'Core subsystems';
$string['typestandard'] = 'Standard plugins';
$string['unableenfixaddon'] = 'English fixes allowed for standard plugins only';
$string['unableenfixcountries'] = 'Country names are copied from ISO 3166-1';
$string['unableunmaintained'] = 'The language pack \'{$a}\' has no maintainer at the moment, so translation contributions cannot be accepted. Please consider volunteering to become \'{$a}\' language pack maintainer.';
$string['unstage'] = 'Unstage';
$string['unstageconfirm'] = 'Really?';
$string['unstaging'] = 'Unstaging';
$string['untranslate'] = 'untranslate';
$string['untranslateconfirm'] = '<p>You are going to remove existing translation of the string <code>{$a->stringid}</code>, component <code>{$a->component}</code>, from all the versions of the language pack <code>{$a->language}</code>.</p><p>Are you sure?</p>';
$string['untranslatetitle'] = 'Removing translation from the language pack';
$string['untranslating'] = 'Untranslating';
$string['version'] = 'Version';
