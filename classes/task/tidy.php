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
 * Local stuff for Moodle Kent
 *
 * @package    local_filesystem
 * @copyright  2017 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_filesystem\task;

/**
 * Clean up any files that don't belong here.
 */
class tidy extends \core\task\scheduled_task
{
    public function get_name() {
        return 'Filesystem Cleanup';
    }

    public function execute() {
        global $CFG;

        $config = (object)get_config('local_filesystem');
        if (!isset($config->tidyfs) || !$config->tidyfs) {
            return true;
        }

        $fs = get_file_storage();
        $filesystem = $fs->get_file_system();
        if (!($filesystem instanceof \local_filesystem\file_system)) {
            return true;
        }

        raise_memory_limit(MEMORY_UNLIMITED);

        // Generate an array of hashes.
        $hashes = [];
        $connected = $filesystem->get_connected_systems();
        foreach ($connected as $dist) {
            $db = \local_kent\helpers::get_db($CFG->kent->environment, $dist);
            if (!$db) {
                throw new \moodle_exception("Invalid connected_file_systems config: {$dist} is not a valid MIM system.");
            }

            $rs = $db->get_recordset('files', null, '', 'id,contenthash');
            foreach ($rs as $obj) {
                $hashes[] = $obj->contenthash;
            }
            $rs->close();

            $hashes = array_unique($hashes);
        }

        if (empty($hashes)) {
            // Something is wrong, bail out.
            throw new \file_exception('No hashes found.');
        }

        foreach ($filesystem->traverse_directory($CFG->filedir) as $file) {
            [$fullpath, $contenthash] = $file;

            // Check this file is needed.
            if (!in_array($contenthash, $hashes)) {
                cli_writeln("Trashing file {$fullpath}...");
                $filesystem->execute_task_trash($contenthash);
            }
        }
        return true;
    }
}
