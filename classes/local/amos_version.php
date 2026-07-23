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
 * Provides information about Moodle versions and corresponding branches
 *
 * Do not modify the returned instances, they are not cloned during coponent copying.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_version {
    /** @var int Branch code of the version: 20, 21, ... 39, 310, 311, 400, ... */
    public $code;

    /** @var string Human-readable label of the version: 2.0, 3.9, 3.10, 3.11, 4.0dev, DEV, ... */
    public $label;

    /** @var string Name of the corresponding Git branch: MOODLE_39_STABLE, MOODLE_310_STABLE, ... */
    public $branch;

    /** @var string Name of the directory under https://download.moodle.org/langpack/ - 3.8, 3.9, 3.10, ... */
    public $dir;

    /** @var bool Allow translations of strings on this branch? */
    public $translatable;

    /** @var bool Is this a version that translators should focus on? Deprecated - use {@see self::latest_version()} instead. */
    public $current;

    /**
     * Get instance by the branch code.
     *
     * @param int $code Branch code of the version: 20, 21, ... 39, 310, 311, 400, ...
     * @return amos_version
     */
    public static function by_code($code) {

        if (preg_match('/^(\d)(\d)$/', $code, $m)) {
            return static::create_or_reuse_instance((int) $code, (string) ($m[1] . '.' . $m[2]));
        } else if (preg_match('/^(\d){3,}$/', $code)) {
            $x = floor($code / 100);
            $y = $code - $x * 100;
            return static::create_or_reuse_instance((int) $code, (string) ($x . '.' . $y));
        } else {
            throw new amos_exception('Unexpected version code');
        }
    }

    /**
     * Get instance by branch name.
     *
     * @param string $branch Branch name like 'MOODLE_310_STABLE'
     * @return amos_version
     */
    public static function by_branch($branch) {

        if (preg_match('/^MOODLE_(\d{2,})_STABLE$/', $branch, $m)) {
            return self::by_code($m[1]);
        } else {
            throw new amos_exception('Unexpected branch name');
        }
    }

    /**
     * Get instance by directory name (which mostly matches the label, too).
     *
     * @param string $dir like '3.1' or '3.10'
     * @return amos_version|null
     */
    public static function by_dir($dir) {

        if (preg_match('/^(\d+)\.(\d+)$/', $dir, $m)) {
            if (version_compare($dir, '3.9', '<=')) {
                return self::by_code($m[1] * 10 + $m[2]);
            } else {
                return self::by_code($m[1] * 100 + $m[2]);
            }
        } else {
            throw new amos_exception('Unexpected dir name');
        }
    }

    /**
     * Get a list of all known versions and information about them.
     *
     * @return array of amos_version indexed by version code
     */
    public static function list_all(): array {

        $codes = get_config('local_amos', 'branchesall');
        $codes = array_filter(array_map('trim', explode(',', $codes)));
        sort($codes, SORT_NUMERIC);
        $list = [];

        foreach ($codes as $code) {
            $list[$code] = self::by_code($code);
        }

        return $list;
    }

    /**
     * List of versions starting since the given branch code, optionally up to the given end (inclusive).
     *
     * @param int $start The branch code of the first version in the returned list.
     * @param int $end Optional branch code of the last version in the returned list
     * @return array amos_version[] indexed by version code
     */
    public static function list_range(int $start, ?int $end = null): array {

        $result = [];

        foreach (self::list_all() as $mver) {
            if ($mver->code < $start) {
                continue;
            }

            if ($end !== null && $mver->code > $end) {
                break;
            }

            $result[$mver->code] = $mver;
        }

        return $result;
    }

    /**
     * List of versions that are supported upstream.
     *
     * AMOS will track for English strings on these branches and generate installer language packs for them.
     *
     * @return array
     */
    public static function list_supported(): array {

        return self::list_range(get_config('local_amos', 'branchsupported'));
    }

    /**
     * Return the most recent known version.
     *
     * @return amos_version
     */
    public static function latest_version() {

        $all = static::list_all();

        return array_pop($all);
    }

    /**
     * Return the oldest known version.
     *
     * @return amos_version
     */
    public static function oldest_version() {

        $all = static::list_all();

        return array_shift($all);
    }

    /**
     * Used by factory methods to create instances of this class.
     *
     * @param int $code
     * @param string $dir
     */
    protected static function create_or_reuse_instance(int $code, string $dir): amos_version {
        static $instances = [];

        if (!isset($instances[$code])) {
            $instances[$code] = new static($code, $dir);
        }

        return $instances[$code];
    }

    /**
     * To be used by {@see self::create_or_reuse_instance()} only.
     *
     * @param int $code
     * @param string $dir
     */
    protected function __construct(int $code, string $dir) {

        $this->code = $code;
        $this->dir = $dir;
        $this->label = $dir;
        $this->branch = 'MOODLE_' . $code . '_STABLE';
        $this->translatable = ($code >= 20);
        $this->current = false;
    }
}
