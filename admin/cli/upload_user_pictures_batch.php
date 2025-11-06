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
 * 批量上传学生头像的CLI脚本
 *
 * 根据照片文件名(如202510xxx.png)匹配用户的lastname字段并设置头像
 *
 * @package    core
 * @subpackage cli
 * @copyright  2025 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/gdlib.php');

// 获取CLI选项
list($options, $unrecognized) = cli_get_params(
    array(
        'help' => false,
        'path' => '',
        'overwrite' => false,
        'preview' => false,
    ),
    array(
        'h' => 'help',
        'p' => 'path',
        'o' => 'overwrite',
        'v' => 'preview',
    )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['path'])) {
    $help = "批量上传学生头像

根据照片文件名(如202510xxx.png)匹配用户的lastname字段并设置头像。

选项:
-h, --help              显示此帮助信息
-p, --path=PATH         照片文件所在目录的路径(必需)
-o, --overwrite         覆盖已有头像(默认: 否)
-v, --preview           预览模式,仅显示将要处理的文件,不实际上传

示例:
\$ php admin/cli/upload_user_pictures_batch.php --path=/path/to/photos
\$ php admin/cli/upload_user_pictures_batch.php --path=/path/to/photos --overwrite
\$ php admin/cli/upload_user_pictures_batch.php --path=/path/to/photos --preview
";

    echo $help;
    die;
}

// 验证路径
$photopath = rtrim($options['path'], '/\\');
if (!is_dir($photopath)) {
    cli_error("错误: 指定的路径不存在或不是一个目录: {$photopath}");
}

$overwrite = $options['overwrite'];
$preview = $options['preview'];

// 统计信息
$stats = array(
    'total' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => 0,
    'notfound' => 0,
);

echo "开始批量上传学生头像...\n";
echo "照片目录: {$photopath}\n";
echo "覆盖已有头像: " . ($overwrite ? '是' : '否') . "\n";
echo "预览模式: " . ($preview ? '是' : '否') . "\n";
echo str_repeat('-', 70) . "\n";

// 扫描目录中的图片文件
$supportedExtensions = array('png', 'jpg', 'jpeg', 'gif');
$files = scandir($photopath);

if ($files === false) {
    cli_error("错误: 无法读取目录: {$photopath}");
}

foreach ($files as $filename) {
    // 跳过 . 和 ..
    if ($filename == '.' || $filename == '..') {
        continue;
    }

    $filepath = $photopath . DIRECTORY_SEPARATOR . $filename;

    // 只处理文件,不处理目录
    if (!is_file($filepath)) {
        continue;
    }

    // 检查文件扩展名
    $pathinfo = pathinfo($filename);
    if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), $supportedExtensions)) {
        continue;
    }

    $stats['total']++;

    // 从文件名中提取学号(lastname)
    // 文件名格式: 202510xxx.png
    $basename = $pathinfo['filename'];
    $lastname = $basename;

    echo "\n处理文件: {$filename}\n";
    echo "  提取的lastname: {$lastname}\n";

    // 查找匹配的用户
    $user = $DB->get_record('user', array('lastname' => $lastname, 'deleted' => 0));

    if (!$user) {
        echo "  ❌ 错误: 未找到lastname为 '{$lastname}' 的用户\n";
        $stats['notfound']++;
        continue;
    }

    echo "  ✓ 找到用户: {$user->firstname} {$user->lastname} (ID: {$user->id}, 用户名: {$user->username})\n";

    // 检查是否已有头像
    $haspicture = $DB->get_field('user', 'picture', array('id' => $user->id));
    if ($haspicture && !$overwrite) {
        echo "  ⊘ 跳过: 用户已有头像且未设置覆盖选项\n";
        $stats['skipped']++;
        continue;
    }

    if ($preview) {
        echo "  ⚡ 预览模式: 将会为此用户设置头像\n";
        $stats['updated']++;
        continue;
    }

    // 处理并设置用户头像
    try {
        $context = context_user::instance($user->id);
        $newrev = process_new_icon($context, 'user', 'icon', 0, $filepath);

        if ($newrev) {
            $DB->set_field('user', 'picture', $newrev, array('id' => $user->id));
            echo "  ✓ 成功: 已更新用户头像\n";
            $stats['updated']++;
        } else {
            echo "  ❌ 错误: 无法处理图片文件\n";
            $stats['errors']++;
        }
    } catch (Exception $e) {
        echo "  ❌ 错误: " . $e->getMessage() . "\n";
        $stats['errors']++;
    }
}

// 输出统计信息
echo "\n" . str_repeat('=', 70) . "\n";
echo "处理完成!\n";
echo str_repeat('-', 70) . "\n";
echo "统计信息:\n";
echo "  总文件数: {$stats['total']}\n";
echo "  成功更新: {$stats['updated']}\n";
echo "  跳过: {$stats['skipped']}\n";
echo "  未找到用户: {$stats['notfound']}\n";
echo "  错误: {$stats['errors']}\n";
echo str_repeat('=', 70) . "\n";

if ($preview) {
    echo "\n注意: 这是预览模式,没有实际更新头像。请去掉 --preview 选项来执行实际上传。\n";
}

exit(0);
