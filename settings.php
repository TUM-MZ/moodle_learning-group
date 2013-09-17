<?php
/**
 * User: peili
 * Date: 27.05.13
 * Time: 11:50
 * To change this template use File | Settings | File Templates.
 */

/*
$settings->add(new admin_setting_heading(
    'headerconfig',
    get_string('headerconfig', 'block_learning_group'),
    get_string('descconfig', 'block_learning_group')
));

$settings->add(new admin_setting_configcheckbox(
    'block_learning_group/Allow_HTML',
    get_string('labelallowhtml', 'block_learning_group'),
    get_string('descallowhtml', 'block_learning_group'),
    '0'
));
*/

$settings->add(new admin_setting_heading('learning_group_manageremail_header', get_string('manageremailheading', 'block_learning_group'), ''));
$settings->add(new admin_setting_confightmleditor('block_learning_group/learning_group_request_body', get_string('manageremailheading', 'block_learning_group'), get_string('setting:defaultrequestinstrmngrdefault', 'block_learning_group'),get_string('setting:defaultrequestinstrmngrdefault', 'block_learning_group') , PARAM_RAW, 80,20));

