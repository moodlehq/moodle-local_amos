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
 * Provides the {@see \local_amos\local\git} class.
 *
 * @package     local_amos
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Implements a thin wrapper for executing git commands against a given repository.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class git {

    /** @var string Full path to our repository */
    protected $repodir;

    /** @var string SSH private key to use to authenticate against remote repositories */
    protected $sshprivate;

    /**
     * Creates new worker instance.
     *
     * @param string $repodir Full path to the repository to work on
     * @param string $sshprivate SSH private key to use when accessing remote repositories
     */
    public function __construct(string $repodir, string $sshprivate='') {

        if (!is_dir($repodir)) {
            throw new \Exception('Repository directory does not exist');
        }

        $this->repodir = $repodir;
        $this->sshprivate = $sshprivate;
    }

    /**
     * Executes git with given arguments and returns the command output
     *
     * @param string $args Git command and arguments.
     * @param bool $throw Throw exception on non-zero exit status.
     * @param int $status Return status via this reference.
     * @return array output
     */
    public function exec($args, bool $throw=true, &$status=null) {

        chdir($this->repodir);
        $out = [];
        $status = 0;

        exec($this->git_shell_cmd($args), $out, $status);

        if ($status <> 0) {
            if ($throw) {
                throw new \Exception('Error executing git command (' . $status . ')', $status);
            }
        }

        return $out;
    }

    /**
     * Returns the full path to the repository root directory
     *
     * @return string
     */
    public function get_repodir() : string {
        return $this->repodir;
    }

    /**
     * Returns a shell command to execute git
     *
     * @param string $args
     * @return string
     */
    protected function git_shell_cmd(string $args) : string {

        if ($this->sshprivate) {
            $cmd = 'GIT_SSH_COMMAND=\'ssh -i '.$this->sshprivate.'\' git '.$args;

        } else {
            $cmd = 'git '.$args;
        }

        return $cmd;
    }

    /**
     * Returns true if executing git with given arguments had success exit code
     *
     * @param string $args
     * @return boolean
     */
    public function is_success(string $args) : bool {

        try {
            $this->exec($args);

        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Returns list of local branches
     *
     * @return array
     */
    public function list_local_branches() : array {

        // Git returns values like 'refs/heads/master'. We want to strip the first two.
        $list = $this->exec('for-each-ref --format="%(refname:strip=2)" '.escapeshellarg('refs/heads/'));

        return $list;
    }

    /**
     * Checks if a local branch of the given name exist.
     *
     * @param string $name branch name
     * @return bool
     */
    public function has_local_branch(string $name) : bool {
        return $this->is_success('show-ref --verify --quiet refs/heads/'.$name);
    }

    /**
     * Checks if a remote branch of the given name exist.
     *
     * Do not forget to update remotes before calling this.
     *
     * @param string $name branch name
     * @param string $remote remote name
     * @return bool
     */
    public function has_remote_branch(string $name, string $remote='origin') : bool {
        return $this->is_success('show-ref --verify --quiet refs/remotes/'.$remote.'/'.$name);
    }

    /**
     * Returns list of remote branches
     *
     * @param string $remote
     * @return array
     */
    public function list_remote_branches(string $remote='origin') : array {

        // Git returns values like 'refs/remotes/origin/master'. We want to strip the first three.
        $list = $this->exec('for-each-ref --format="%(refname:strip=3)" '.escapeshellarg('refs/remotes/'.$remote));

        // Get rid of the ref HEAD (which shows the default branch in
        // the remote repository and is one of the existing branches).
        $list = array_values(array_diff($list, ['HEAD']));

        return $list;
    }
}
