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
 * Core file system class definition.
 *
 * @package   local_filesystem
 * @copyright 2017 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_filesystem\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filestorage/file_system_filedir.php');

/**
 * File system class used for low level access to real files in filedir.
 *
 * @package   local_filesystem
 * @category  files
 * @copyright 2017 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_system extends \file_system_filedir {
    /**
     * An object of plugin settings.
     *
     * @param  \stdClass $contenthash See above.
     */
    protected $settings = [];

    /**
     * An array containing all systems that are connected to the same file system as this installation.
     *
     * @param  array $contenthash See above.
     */
    protected $connectedsystems = [];

    /**
     * Perform any custom setup for this type of file_system.
     */
    public function __construct() {
        parent::__construct();

        $this->settings = (object)get_config('local_filesystem');
        $this->connectedsystems = [$this->settings->uniqid];

        // Grab the config for this filedir and update if needs be.
        if (!file_exists("{$this->filedir}/config.db")) {
            // Create a brand new config file.
            $config = ['version' => 1.0, 'systems' => $this->connectedsystems];
            file_put_contents("{$this->filedir}/config.db", serialize($config));
        } else {
            // Grab a copy of the connected systems.
            $contents = file_get_contents("{$this->filedir}/config.db");
            $config = unserialize($contents);
            $this->connectedsystems = $config['systems'];

            // Add us to the conected systems variable if we are not in it.
            if (!in_array($this->settings->uniqid, $this->connectedsystems)) {
                $config['systems'][] = $this->settings->uniqid;
                file_put_contents("{$this->filedir}/config.db", serialize($config));
            }
        }
    }

    /**
     * Get the old directory to the stored file, including the path to the
     * filedir, and the directory which the file is actually in.
     *
     * @param string $contenthash The content hash
     * @return string The full path to the content directory
     */
    protected function get_olddir_from_hash(string $contenthash): string {
        return $this->settings->oldfiledir . DIRECTORY_SEPARATOR . $this->get_contentdir_from_hash($contenthash);
    }

    /**
     * Migrate a file from the old directory.
     *
     * @param  string  $oldpath         Path of old file
     * @param  string  $contenthash     Content hash
     * @return void
     */
    public function migrate(string $oldpath, string $contenthash): void {
        // Yes it is, pull it over!
        $this->add_file_from_path($oldpath, $contenthash);

        // Remove the old file.
        $prev = ignore_user_abort(true);
        @unlink($oldpath);
        ignore_user_abort($prev);
    }

    /**
     * Get the full path for the specified hash, including the path to the filedir.
     *
     * @param string $contenthash The content hash
     * @param bool $fetchifnotfound Whether to attempt to fetch from the remote path if not found.
     * @return string The full path to the content file
     */
    protected function get_local_path_from_hash($contenthash, $fetchifnotfound = false) {
        $path = parent::get_local_path_from_hash($contenthash, $fetchifnotfound);

        // Try content recovery.
        if (!empty($this->settings->oldfiledir) && !is_readable($path)) {
            // Is it in the old file directory?
            $oldpath = $this->get_olddir_from_hash($contenthash);
            if (is_readable($oldpath)) {
                if ($fetchifnotfound) {
                    // Yes it is, pull it over!
                    $this->add_file_from_path($oldpath, $contenthash);

                    // Remove the old file.
                    $prev = ignore_user_abort(true);
                    @unlink($oldpath);
                    ignore_user_abort($prev);
                } else {
                    $path = $oldpath;
                }
            }
        }

        return $path;
    }

    /**
     * Marks pool file as candidate for deleting.
     *
     * @param string $contenthash
     */
    public function remove_file($contenthash) {
        if (!$this->is_file_readable_remotely_by_hash($contenthash)) {
            // The file wasn't found in the first place. Just ignore it.
            return;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            $this->execute_task_trash($contenthash);
            return;
        }

        // Create an adhoc task to delete this file.
        $task = new \local_filesystem\task\trash();
        $task->set_custom_data(array(
            'contenthash' => $contenthash
        ));
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Is the given hash removable in all attached systems?
     * This is expensive, so we call it by task.
     *
     * @return bool
     */
    protected function is_file_removable($contenthash) {
        global $CFG;

        foreach ($this->connectedsystems as $system) {
            $db = \local_kent\helpers::get_db($CFG->kent->environment, $system);
            if (!$db) {
                throw new file_exception("Invalid connected_file_systems config: {$system} is not a valid MIM system.");
            }

            // Check the file records.
            if ($db->record_exists('files', array('contenthash' => $contenthash))) {
                return false;
            }
        }

        return true;
    }

    /**
     * When called by the task, this will trash a file.
     */
    public function execute_task_trash(string $contenthash): void {
        if (!self::is_file_removable($contenthash)) {
            // Don't remove the file - it's still in use.
            return;
        }

        // Check again.
        if (!$this->is_file_readable_remotely_by_hash($contenthash)) {
            // The file wasn't found in the first place. Just ignore it.
            return;
        }

        $trashpath  = $this->get_trash_fulldir_from_hash($contenthash);
        $trashfile  = $this->get_trash_fullpath_from_hash($contenthash);
        $contentfile = $this->get_local_path_from_hash($contenthash, true);

        if (!is_dir($trashpath)) {
            mkdir($trashpath, $this->dirpermissions, true);
        }

        if (file_exists($trashfile)) {
            // A copy of this file is already in the trash.
            // Remove the old version.
            unlink($contentfile);
            return;
        }

        // Move the contentfile to the trash, and fix permissions as required.
        rename($contentfile, $trashfile);

        // Fix permissions, only if needed.
        $currentperms = octdec(substr(decoct(fileperms($trashfile)), -4));
        if ((int)$this->filepermissions !== $currentperms) {
            chmod($trashfile, $this->filepermissions);
        }
    }

    /**
     * Helper method to safely traverse the file directory without using much memory.
     *
     * @param  string $dir The directory to traverse.
     */
    public function traverse_directory(string $dir) {
        $handle = opendir($dir);

        while ($file = readdir($handle)) {
            if ($file == "." && $file == "..") {
                continue;
            }

            $fullpath = "{$dir}/{$file}";
            if (is_dir($fullpath)) {
                yield from $this->traverse_directory($fullpath);
            } else if (strlen($contenthash) !== 40) {
                yield [$fullpath, $file];
            }
        }
    }
}
