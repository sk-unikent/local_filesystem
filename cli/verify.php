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
 * Verifies the integrity of the filesystem.
 *
 * @package    local_filesystem
 * @copyright  2017 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

cli_writeln("Verifying file system integrity...");

$fs = get_file_storage();
$filesystem = $fs->get_file_system();

// Check files exist.
$rs = $DB->get_recordset('files', null, '', 'id,contenthash');
foreach ($rs as $obj) {
    $l1 = $obj->contenthash[0] . $obj->contenthash[1];
    $l2 = $obj->contenthash[2] . $obj->contenthash[3];
    $path = "{$CFG->filedir}/{$l1}/{$l2}/{$obj->contenthash}";
    if (!file_exists($path)) {
        cli_writeln("{$path}: file not found!");
    }
}
$rs->close();

// Check sha1 sums.
foreach ($filesystem->traverse_directory($CFG->filedir) as $file) {
    [$fullpath, $contenthash] = $file;

    $filehash = sha1_file($fullpath);
    if ($filehash != $contenthash) {
        cli_writeln("Error verifying {$fullpath}: Mis-matched hash ({$filehash} on disk vs {$contenthash})");
    }
}

cli_writeln("Verification complete!");
