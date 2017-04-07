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
 * Trash a file if there are no objections.
 */
class trash extends \core\task\adhoc_task
{
    public function get_component() {
        return 'local_filesystem';
    }

    public function execute() {
        $data = (array)$this->get_custom_data();

        $fs = get_file_storage();
        $filesystem = $fs->get_file_system();
        $filesystem->execute_task_trash($data['contenthash']);
    }

    /**
     * Setter for $customdata.
     *
     * @param mixed $customdata (anything that can be handled by json_encode)
     * @throws \moodle_exception
     */
    public function set_custom_data($customdata) {
        if (empty($customdata['contenthash'])) {
            throw new \moodle_exception('contenthash cannot be empty!');
        }

        parent::set_custom_data($customdata);
    }
}
