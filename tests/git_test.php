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
 * Provides {@link local_amos_git_testcase} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Test the implementation of {@link \local_amos\local\git} class.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_git_testcase extends basic_testcase {

    /**
     * Test {@link \local_amos\local\git::exec()}.
     */
    public function test_exec() {

        $repo = make_request_directory();
        $git = new \local_amos\local\git($repo);

        $out = $git->exec('init');
        $this->assertEquals('Initialized empty Git repository in '.$repo.'/.git/', $out[0]);

        file_put_contents($repo.'/README.txt', 'Hello world');
        $git->exec('add README.txt');
        $git->exec('commit -m "Adding a first file"');

        $out = $git->exec('log -n 1 --oneline');
        $this->assertTrue(strpos($out[0], 'Adding a first file') > 0);

        $this->expectException(Exception::class);
        $git->exec('add FOO.exe 2>/dev/null');
    }

    /**
     * Test {@link \local_amos\local\git::is_success()}.
     */
    public function test_is_success() {

        $repo = make_request_directory();
        $git = new \local_amos\local\git($repo);

        $git->exec('init');
        file_put_contents($repo.'/index.php', '<?php // Hello world! ?>');
        $git->exec('add .');
        $git->exec('commit -m "Initial commit"');
        $git->exec('checkout -b slave 2>/dev/null');

        $this->assertTrue($git->is_success('show-ref --verify --quiet refs/heads/master'));
        $this->assertTrue($git->is_success('show-ref --verify --quiet refs/heads/slave'));
        $this->assertFalse($git->is_success('show-ref --verify --quiet refs/heads/justice_exists'));
    }

    /**
     * Test {@link \local_amos\local\git::list_local_branches()}.
     */
    public function test_list_local_branches() {

        $repo = make_request_directory();
        $git = new \local_amos\local\git($repo);

        $git->exec('init');
        $this->assertSame([], $git->list_local_branches());

        file_put_contents($repo.'/index.php', '<?php // Hello world! ?>');
        $git->exec('add .');
        $git->exec('commit -m "Initial commit"');
        $this->assertEquals(['master'], $git->list_local_branches());

        $git->exec('checkout -b slave 2>/dev/null');
        $this->assertEquals(2, count($git->list_local_branches()));
        $this->assertContains('master', $git->list_local_branches());
        $this->assertContains('slave', $git->list_local_branches());
    }

    /**
     * Test {@link \local_amos\local\git::has_local_branch()}.
     */
    public function test_has_local_branch() {

        $repo = make_request_directory();
        $git = new \local_amos\local\git($repo);

        $git->exec('init');
        file_put_contents($repo.'/index.php', '<?php // Hello world! ?>');
        $git->exec('add .');
        $git->exec('commit -m "Initial commit"');
        $git->exec('checkout -b slave 2>/dev/null');

        $this->assertTrue($git->has_local_branch('master'));
        $this->assertTrue($git->has_local_branch('slave'));
        $this->assertFalse($git->has_local_branch('justice_exists'));
    }
}
