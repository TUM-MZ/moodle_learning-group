<?php
/**
 * User: peili
 * Date: 18.05.13
 * Time: 13:00
 * To change this template use File | Settings | File Templates.
 */
/* Adobe Connect */
global $CFG, $USER, $DB, $PAGE, $OUTPUT;
require_once('../../config.php');
require_once('lib.php');
require_once($CFG->dirroot . '/mod/adobeconnect/connect_class.php');
require_once($CFG->dirroot . '/mod/adobeconnect/locallib.php');
require_once($CFG->dirroot . '/mod/adobeconnect/connect_class_dom.php');
$s = optional_param('s', 0, PARAM_INT); // learning_group session ID
if ($s) {
    if (!$session = learning_group_get_session($s)) {
        print_error('error:incorrectcoursemodulesession', 'learning_group');
    }
}
$aconnect = aconnect_login();

if (!$session->adobeconnect_scoid) {
// Create Adobe Connect Meeting
    $meetinglang = "de";
    $meetingobj = new stdClass();
    $meetingobj->name = substr($session->id .' '. $session->title, 0, 60) ; // Add Id to avoid duplicate titles in adobe Connect
    $starttime = aconnect_format_date_seconds(time() + 400 * 24 * 60 * 60); // Unix TimeStamp
    $endtime = aconnect_format_date_seconds(time() + 400 * 24 * 60 * 60 + (60 * 60)); // Unix TimeStamp
    $meetfldscoid = aconnect_get_meeting_folder($aconnect);
    if (empty($starttime) or empty($endtime)) {
        $message = 'Failure (aconnect_find_timezone) in finding the +/- sign in the date timezone' .
            "\n" . date("c", $meetingobj->starttime) . "\n" . date("c", $meetingobj->endtime);
        debugging($message, DEBUG_DEVELOPER);
        return false;
    }

    $params = array('action' => 'sco-update',
        'type' => 'meeting',
        'name' => $meetingobj->name,
        'folder-id' => $meetfldscoid,
        'date-begin' => $starttime,
        'date-end' => $endtime,
        'lang' => $meetinglang
    );

    if (!empty($meetingobj->meeturl)) {
        $params['url-path'] = $meetingobj->meeturl;
    }

    if (!empty($meetingobj->templatescoid)) {
        $params['source-sco-id'] = $meetingobj->templatescoid;
    }

    $aconnect->create_request($params);


    if ($aconnect->call_success()) {
        $session->adobeconnect_scoid = aconnect_get_meeting_scoid($aconnect->_xmlresponse);
        learning_group_create_adobe_instance($session);
    } else {
        echo "No Connection to Adobe Connect Server";
        exit;
    }
}
$user = new stdClass();
$user->email = 'LearningGroup_'.$USER->email;
$user->username = 'LearningGroup_'.$USER->email;
$user->firstname = $USER->firstname;
$user->lastname = $USER->lastname;

if (!($usrprincipal = dfnvc_user_exists($aconnect, $user))) {
    $usrprincipal = aconnect_create_user($aconnect, $user);
}
// Assign test user a meeting role
aconnect_check_user_perm($aconnect, $usrprincipal, $session->adobeconnect_scoid, ADOBE_PRESENTER, true);

$session_cookie = $aconnect->user_session_cookie($user->username);

if (isset($CFG->adobeconnect_https) and (!empty($CFG->adobeconnect_https))) {

    $protocol = 'https://';
    $https = true;
}
// Include the port number only if it is a port other than 80
$port = '';

if (!empty($CFG->adobeconnect_port) and (80 != $CFG->adobeconnect_port)) {
    $port = ':' . $CFG->adobeconnect_port;
}
$meetfldscoid = aconnect_get_meeting_folder($aconnect);
$filter = array('filter-sco-id' => $session->adobeconnect_scoid);
$meeting = aconnect_meeting_exists($aconnect, $meetfldscoid, $filter);
redirect($protocol . $CFG->adobeconnect_meethost . $port. $meeting[$session->adobeconnect_scoid]->url . '?session=' . $session_cookie);
/* Ende Adobe Connect */

aconnect_logout($aconnect);
