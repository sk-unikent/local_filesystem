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
 * Online migration script.
 * You should set filedir to the new directory and set this plugin's oldfiledir to the old directory in config.php at
 * the same time to ensure no downtime.
 *
 * @package    local_filesystem
 * @copyright  2017 Skylar Kelty <S.Kelty@kent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    array(
        'from' => null
    )
);

$from = get_config('local_filesystem', 'oldfiledir');
if (!empty($options['from'])) {
    $from = $options['from'];
    set_config('oldfiledir', $from, 'local_filesystem');
}

if (empty($from) || $from == $CFG->filedir) {
    cli_error("No from directory specified, or it is the same as filedir which is wrong.");
    die;
}

$fs = get_file_storage();
$filesystem = $fs->get_file_system();

// Begin the migration!
// Any requests will be redirected to the old filedirectory anyway, and some of them will be copied inline.
// We will pre-empt the rest.
foreach ($filesystem->traverse_directory($from) as $file) {
    [$fullpath, $contenthash] = $file;

    $filesystem->migrate($fullpath, $contenthash);
}
