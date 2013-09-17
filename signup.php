<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'signup_form.php';
require_login(1);

global $DB, $USER;

$s = required_param('s', PARAM_INT); // learning_group session ID
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

if (!$session = learning_group_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'learning_group');
}
if (isset($_GET['approve'])) {
    $submission = learning_group_get_user_submissions($s, $USER->id, $includecancellations = false);
    if ($submission->statuscode == 40) {
        learning_group_update_signup_status($submission->id, 70);
        redirect(new moodle_url($CFG->wwwroot . '/blocks/learning_group/attendees.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions)));
    }
}


$returnurl = "$CFG->wwwroot/blocks/learning_group/view.php";


$pagetitle = format_string($session->title);
$PAGE->set_pagelayout('standard');

$PAGE->set_url('/mod/learning_group/signup.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($session->title);

// Guests can't signup for a session, so offer them a choice of logging in or going back.
if (isguestuser()) {
    $loginurl = $CFG->wwwroot . '/login/index.php';
    if (!empty($CFG->loginhttps)) {
        $loginurl = str_replace('http:', 'https:', $loginurl);
    }

    echo $OUTPUT->header();
    $out = html_writer::tag('p', get_string('guestsno', 'learning_group')) .
        html_writer::empty_tag('br') .
        html_writer::tag('p', get_string('continuetologin', 'learning_group'));
    echo $OUTPUT->confirm($out, $loginurl, get_referer(false));
    echo $OUTPUT->footer();
    exit();
}

$manageremail = false;
if (get_config(NULL, 'learning_group_addchangemanageremail')) {
    $manageremail = learning_group_get_manageremail($USER->id);
}


$mform = new mod_learning_group_signup_form(null, compact('s', 'backtoallsessions', 'manageremail', 'showdiscountcode'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'learning_group', $returnurl);
    }

    // User can not update Manager's email (depreciated functionality)
    if (!empty($fromform->manageremail)) {
        add_to_log($course->id, 'learning_group', 'update manageremail (FAILED)', "signup.php?s=$session->id", $learning_group->id, $cm->id);
    }

    // Get signup type
    if (learning_group_get_num_attendees($session->id) < $session->capacity) {
        // Save available
        $statuscode = MDL_F2F_STATUS_BOOKED;
    } else {
        $statuscode = MDL_F2F_STATUS_WAITLISTED;
    }

    if (!learning_group_session_has_capacity($session) && (!$session->allowoverbook)) {
        print_error('sessionisfull', 'learning_group', $returnurl);
    } else if (learning_group_get_user_submissions($session->id, $USER->id)) {
        print_error('alreadysignedup', 'learning_group', $returnurl);
    } else if ($submissionid = learning_group_user_signup($session, $fromform->notificationtype, $statuscode,false,false)) {
        add_to_log($course->id, 'learning_group', 'signup', "signup.php?s=$session->id", $session->id, $cm->id);

        $message = get_string('bookingcompleted', 'learning_group');
        if ($session->datetimeknown && $learning_group->confirmationinstrmngr) {
            $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('confirmationsentmgr', 'learning_group');
        } else {
            $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('confirmationsent', 'learning_group');
        }

        $timemessage = 4;
        redirect($returnurl, $message, $timemessage);
    } else {
        add_to_log($course->id, 'learning_group', 'signup (FAILED)', "signup.php?s=$session->id", $session->id, $cm->id);
        print_error('error:problemsigningup', 'learning_group', $returnurl);
    }

    redirect($returnurl);
} else if ($manageremail !== false) {
    // Set values for the form
    $toform = new stdClass();
    $toform->manageremail = $manageremail;
    $mform->set_data($toform);
}

echo $OUTPUT->header();

$heading = $session->title;

$signedup = learning_group_check_signup($session->id);

if ($signedup and $signedup != $session->id) {
    print_error('error:signedupinothersession', 'learning_group', $returnurl);
}

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

$timenow = time();

if ($session->datetimeknown && learning_group_has_session_started($session, $timenow)) {
    $inprogress_str = get_string('cannotsignupsessioninprogress', 'learning_group');
    $over_str = get_string('cannotsignupsessionover', 'learning_group');

    $errorstring = learning_group_is_session_in_progress($session, $timenow) ? $inprogress_str : $over_str;

    echo html_writer::empty_tag('br') . $errorstring;
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    exit;
}

if (!$signedup && !learning_group_session_has_capacity($session) && (!$session->allowoverbook)) {
    print_error('sessionisfull', 'learning_group', $returnurl);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer($course);
    exit;
}

echo learning_group_print_session($session, true, false, false, false, $signedup);

if ($signedup) {
    if ( $session->userid != $USER->id ) {
        // Cancellation link
        echo html_writer::link(new moodle_url('cancelsignup.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions)), get_string('cancelbooking', 'block_learning_group'), array('title' => get_string('cancelbooking', 'block_learning_group')));
        echo ' &ndash; ';
    }
    // See attendees link
    echo html_writer::link(new moodle_url('attendees.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions)), get_string('seeattendees', 'block_learning_group'), array('title' => get_string('seeattendees', 'block_learning_group')));

    echo html_writer::empty_tag('br') . html_writer::link($returnurl, get_string('goback', 'block_learning_group'), array('title' => get_string('goback', 'block_learning_group')));
} else {
    // Signup form
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
