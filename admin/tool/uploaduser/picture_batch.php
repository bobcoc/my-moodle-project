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
 * 批量上传用户头像 - 根据lastname字段匹配
 *
 * @package    tool_uploaduser
 * @copyright  2025 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/gdlib.php');
require_once('picture_batch_form.php');

define('PIX_FILE_UPDATED', 0);
define('PIX_FILE_ERROR', 1);
define('PIX_FILE_SKIPPED', 2);

admin_externalpage_setup('tooluploaduserpicturesbatch');

require_capability('tool/uploaduser:uploaduserpictures', context_system::instance());

$returnurl = new moodle_url('/admin/tool/uploaduser/picture_batch.php');

// 页面标题
$struploadpictures = get_string('uploadpicturesbatch', 'tool_uploaduser');

$PAGE->set_url($returnurl);
$PAGE->set_title($struploadpictures);
$PAGE->set_heading($struploadpictures);

echo $OUTPUT->header();
echo $OUTPUT->heading($struploadpictures);

// 显示说明信息
echo html_writer::div(get_string('uploadpicturesbatch_help', 'tool_uploaduser'), 'alert alert-info');

$mform = new admin_uploadpicture_batch_form();

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($formdata = $mform->get_data()) {
    // 处理上传
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);

    // 创建临时目录
    $zipdir = make_temp_directory('userpicbatch');
    $dstfile = $zipdir . '/images.zip';

    if (!$mform->save_file('userpicturesfile', $dstfile, true)) {
        echo $OUTPUT->notification(get_string('uploadpicture_cannotmovezip', 'tool_uploaduser'), 'notifyproblem');
    } else {
        $fp = get_file_packer('application/zip');
        $unzipresult = $fp->extract_to_pathname($dstfile, $zipdir);
        
        if (!$unzipresult) {
            echo $OUTPUT->notification(get_string('uploadpicture_cannotunzip', 'tool_uploaduser'), 'notifyproblem');
            @remove_dir($zipdir);
        } else {
            // 删除zip文件
            @unlink($dstfile);

            $results = array(
                'total' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'notfound' => 0
            );

            $overwrite = !empty($formdata->overwritepicture);

            // 开始显示处理过程
            echo html_writer::start_tag('div', array('class' => 'uploadresults'));
            echo html_writer::tag('h4', get_string('processingfiles', 'tool_uploaduser'));
            echo html_writer::start_tag('ul', array('class' => 'uploadlog'));

            process_directory_batch($zipdir, $overwrite, $results);

            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');

            // 删除临时目录
            remove_dir($zipdir);

            // 显示统计结果
            echo html_writer::start_tag('div', array('class' => 'uploadstats alert alert-success'));
            echo html_writer::tag('h4', get_string('uploadstats', 'tool_uploaduser'));
            echo html_writer::tag('p', get_string('totalfiles', 'tool_uploaduser') . ': ' . $results['total']);
            echo html_writer::tag('p', get_string('usersupdated', 'tool_uploaduser') . ': ' . $results['updated'], 
                array('class' => 'text-success'));
            echo html_writer::tag('p', get_string('picturesskipped', 'tool_uploaduser') . ': ' . $results['skipped']);
            echo html_writer::tag('p', get_string('usersnotfound', 'tool_uploaduser') . ': ' . $results['notfound'], 
                array('class' => 'text-warning'));
            echo html_writer::tag('p', get_string('errors', 'tool_uploaduser') . ': ' . $results['errors'], 
                array('class' => $results['errors'] > 0 ? 'text-danger' : ''));
            echo html_writer::end_tag('div');

            echo html_writer::tag('div', 
                $OUTPUT->single_button($returnurl, get_string('uploadmore', 'tool_uploaduser'), 'get'),
                array('class' => 'continuebutton'));
        }
    }
} else {
    // 显示表单
    $mform->display();
}

echo $OUTPUT->footer();

// ==================== 内部函数 ====================

/**
 * 递归处理目录中的图片文件
 *
 * @param string $dir 目录路径
 * @param bool $overwrite 是否覆盖已有头像
 * @param array $results 统计结果(引用传递)
 */
function process_directory_batch($dir, $overwrite, &$results) {
    global $OUTPUT;
    
    if (!($handle = opendir($dir))) {
        echo html_writer::tag('li', get_string('uploadpicture_cannotprocessdir', 'tool_uploaduser'), 
            array('class' => 'text-danger'));
        return;
    }

    while (false !== ($item = readdir($handle))) {
        if ($item != '.' && $item != '..') {
            if (is_dir($dir . '/' . $item)) {
                process_directory_batch($dir . '/' . $item, $overwrite, $results);
            } else if (is_file($dir . '/' . $item)) {
                $result = process_file_batch($dir . '/' . $item, $overwrite);
                switch ($result['status']) {
                    case PIX_FILE_ERROR:
                        $results['errors']++;
                        if (isset($result['notfound']) && $result['notfound']) {
                            $results['notfound']++;
                        }
                        break;
                    case PIX_FILE_UPDATED:
                        $results['updated']++;
                        break;
                    case PIX_FILE_SKIPPED:
                        $results['skipped']++;
                        break;
                }
                $results['total']++;
            }
        }
    }
    closedir($handle);
}

/**
 * 处理单个图片文件
 *
 * @param string $file 文件完整路径
 * @param bool $overwrite 是否覆盖已有头像
 * @return array 包含状态和信息的数组
 */
function process_file_batch($file, $overwrite) {
    global $DB, $OUTPUT;

    $pathinfo = pathinfo(cleardoubleslashes($file));
    $basename = $pathinfo['basename'];
    
    // 检查扩展名
    if (!isset($pathinfo['extension'])) {
        return array('status' => PIX_FILE_ERROR, 'message' => '');
    }
    
    $extension = strtolower($pathinfo['extension']);
    $supportedexts = array('png', 'jpg', 'jpeg', 'gif');
    
    if (!in_array($extension, $supportedexts)) {
        return array('status' => PIX_FILE_ERROR, 'message' => '');
    }

    // 从文件名提取lastname
    $lastname = $pathinfo['filename'];

    // 查找用户
    $user = $DB->get_record('user', array('lastname' => $lastname, 'deleted' => 0));

    if (!$user) {
        $message = get_string('uploadpicture_usernotfound', 'tool_uploaduser', 
            (object)array('userfield' => 'lastname', 'uservalue' => $lastname));
        echo html_writer::tag('li', 
            html_writer::tag('strong', $basename) . ': ' . $message,
            array('class' => 'text-danger'));
        return array('status' => PIX_FILE_ERROR, 'notfound' => true);
    }

    // 检查是否已有头像
    $haspicture = $DB->get_field('user', 'picture', array('id' => $user->id));
    if ($haspicture && !$overwrite) {
        $message = get_string('uploadpicture_userskipped', 'tool_uploaduser', $user->username);
        echo html_writer::tag('li', 
            html_writer::tag('strong', $basename) . ' (' . fullname($user) . '): ' . $message,
            array('class' => 'text-muted'));
        return array('status' => PIX_FILE_SKIPPED);
    }

    // 处理图片
    $context = context_user::instance($user->id);
    $newrev = process_new_icon($context, 'user', 'icon', 0, $file);

    if ($newrev) {
        $DB->set_field('user', 'picture', $newrev, array('id' => $user->id));
        $message = get_string('uploadpicture_userupdated', 'tool_uploaduser', 
            $user->username . ' (' . fullname($user) . ')');
        echo html_writer::tag('li', 
            html_writer::tag('strong', $basename) . ': ' . $message,
            array('class' => 'text-success'));
        return array('status' => PIX_FILE_UPDATED);
    } else {
        $message = get_string('uploadpicture_cannotsave', 'tool_uploaduser', $user->username);
        echo html_writer::tag('li', 
            html_writer::tag('strong', $basename) . ': ' . $message,
            array('class' => 'text-danger'));
        return array('status' => PIX_FILE_ERROR);
    }
}
