<?php



require_once('../../config.php');
require_once 'session_form.php';
require_login(1);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('create_learning_group', 'block_learning_group'));

$session = null;
$returnurl = "view.php";
$s = optional_param('s', 0, PARAM_INT); // learning_group session ID
$d = optional_param('d', 0, PARAM_INT); // delete session
$confirm = optional_param('confirm', false, PARAM_BOOL); // delete confirmation
$url = new moodle_url('/blocks/learning_group/session.php');
$PAGE->set_url($url);



if ($s) {
    if (!$session = learning_group_get_session($s)) {
        print_error('error:incorrectcoursemodulesession', 'block_learning_group');
    }


    $nbdays = count($session->sessiondates);
}

if ($d and $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (learning_group_delete_session($session)) {
        if ($session->adobeconnect_scoid) {
            require_once($CFG->dirroot . '/mod/adobeconnect/connect_class.php');
            require_once($CFG->dirroot . '/mod/adobeconnect/locallib.php');
            require_once($CFG->dirroot . '/mod/adobeconnect/connect_class_dom.php');
            $aconnect = aconnect_login();
            $params = array(
                'action' => 'sco-delete',
                'sco-id' => $session->adobeconnect_scoid,
            );

            $aconnect->create_request($params);

            if (!$aconnect->call_success()) {
                print_error('error:couldnotdeleteadobeconnectmeeting', 'block_learning_group', $returnurl);
            }
        }
        //add_to_log($course->id, 'learning_group', 'delete session', 'sessions.php?s='.$session->id, $learning_group->id, $cm->id);
    } else {
        //add_to_log($course->id, 'learning_group', 'delete session (FAILED)', 'sessions.php?s='.$session->id, $learning_group->id, $cm->id);
        print_error('error:couldnotdeletesession', 'block_learning_group', $returnurl);
    }
    redirect($returnurl);
} else if ($d) {
    learning_group_print_session($session, true);
    $optionsyes = array('sesskey' => sesskey(), 's' => $session->id, 'd' => 1, 'confirm' => 1);
    echo $OUTPUT->confirm(get_string('deletesessionconfirm', 'block_learning_group', format_string($session->title)),
        new moodle_url('sessions.php', $optionsyes),
        new moodle_url($returnurl));
}

$editoroptions = array(
    'noclean' => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES
);
$sessionid = isset($session->id) ? $session->id : 0;
$details = new stdClass();
$details->id = isset($session) ? $session->id : 0;
$details->details = isset($session->details) ? $session->details : '';
$details->detailsformat = FORMAT_HTML;
$details = file_prepare_standard_editor($details, 'details', $editoroptions, null, 'mod_learning_group', 'session', $sessionid);
$url = new moodle_url('/calendar/view.php');

$mform = new mod_learning_group_session_form(null, compact('id', 'f', 's', 'c', 'nbdays', 'customfields', 'course', 'editoroptions'));
$returnurl = "$CFG->wwwroot/blocks/learning_group/view.php";
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($session != null) { // Edit mode
    // Set values for the form
    $toform = new stdClass();
    $toform = file_prepare_standard_editor($details, 'details', $editoroptions, null, 'blocks_learning_group', 'session', $session->id);

    $toform->datetimeknown = (1 == $session->datetimeknown);
    $toform->capacity = $session->capacity;
    $toform->title = $session->title;
    $toform->public = $session->public;
    $toform->id = $session->id;
    $toform->allowoverbook = $session->allowoverbook;

    if ($session->sessiondates) {
        $i = 0;
        foreach ($session->sessiondates as $date) {
            $idfield = "sessiondateid[$i]";
            $timestartfield = "timestart[$i]";
            $timefinishfield = "timefinish[$i]";
            $toform->$idfield = $date->id;
            $toform->$timestartfield = $date->timestart;
            $toform->$timefinishfield = $date->timefinish;
            $i++;
        }
    }
    $mform->set_data($toform);
}

$PAGE->set_heading(get_string('create_learning_group', 'block_learning_group'));

echo $OUTPUT->header();

//
echo $OUTPUT->box_start();
include 'session_submit.php';

$mform->display();


echo $OUTPUT->box_end();

echo $OUTPUT->footer();
