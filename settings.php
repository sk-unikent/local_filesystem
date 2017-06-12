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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_filesystem', get_string('pluginname', 'local_filesystem'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_filesystem/oldfiledir',
        get_string('setting_oldfiledir', 'local_filesystem'),
        '',
        '',
        PARAM_FILE
    ));

    $settings->add(new admin_setting_configtext(
        'local_filesystem/uniqid',
        get_string('setting_uniqid', 'local_filesystem'),
        '',
        'moodle',
        PARAM_ALPHANUM
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_filesystem/tidyfs',
        get_string('setting_tidyfs', 'local_filesystem'),
        '',
        '0'
    ));
}
