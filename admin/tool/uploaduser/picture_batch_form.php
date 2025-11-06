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
 * 批量上传用户头像表单
 *
 * @package    tool_uploaduser
 * @copyright  2025 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class admin_uploadpicture_batch_form extends moodleform {
    
    /**
     * 表单定义
     */
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        // 上传设置
        $mform->addElement('header', 'settingsheader', get_string('upload'));

        // 文件选择器
        $options = array();
        $options['accepted_types'] = array('.zip');
        $mform->addElement('filepicker', 'userpicturesfile', 
            get_string('file'), 'size="40"', $options);
        $mform->addRule('userpicturesfile', null, 'required');
        $mform->addHelpButton('userpicturesfile', 'uploadpicturesbatch_file', 'tool_uploaduser');

        // 覆盖选项
        $choices = array(0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'overwritepicture', 
            get_string('uploadpicture_overwrite', 'tool_uploaduser'), $choices);
        $mform->setType('overwritepicture', PARAM_INT);
        $mform->setDefault('overwritepicture', 0);
        $mform->addHelpButton('overwritepicture', 'uploadpicture_overwrite', 'tool_uploaduser');

        // 按钮
        $this->add_action_buttons(true, get_string('uploadpictures', 'tool_uploaduser'));
    }
}
