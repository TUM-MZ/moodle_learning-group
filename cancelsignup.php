<?php

require_once '../../config.php';
require_once 'lib.php';
require_once 'cancelsignup_form.php';
require_login(1);

global $DB;

$s  = required_param('s', PARAM_INT); // learning group session ID
$confirm           = optional_param('confirm', false, PARAM_BOOL);

if (!$session = learning_group_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'block_learning_group');
}

$returnurl = "$CFG->wwwroot/blocks/learning_group/view.php";


$mform = new block_learning_group_cancelsignup_form(null, compact('s', 'backtoallsessions'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'block_learning_group', $returnurl);
    }

    $timemessage = 4;

    $errorstr = '';
    if (learning_group_user_cancel($session, false, false, $errorstr)) {

        $message = get_string('bookingcancelled', 'block_learning_group');

        if ($session->datetimeknown) {
            // TODO in Case you want to send canel notice
            //$error = learning_group_send_cancellation_notice( $session, $USER->id);
            if (empty($error)) {
                if ($session->datetimeknown) {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsentmgr', 'block_learning_group');
                }
                else {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsent', 'block_learning_group');
                }
            } else {
                print_error($error, 'block_learning_group');
            }
        }

        redirect($returnurl, $message, $timemessage);
    }
    else {
        add_to_log($course->id, 'block_learning_group', "cancel booking (FAILED)", "cancelsignup.php?s=$session->id");
        redirect($returnurl, $errorstr, $timemessage);
    }

    redirect($returnurl);
}

$pagetitle = format_string($session->title);

$PAGE->set_url('/blocks/learning_group/cancelsignup.php', array('s' => $s, 'confirm' => $confirm));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($session->title);

echo $OUTPUT->header();

$heading = get_string('cancelbookingfor', 'block_learning_group', $session->title);

$signedup = learning_group_check_signup($session->id);

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($signedup) {
    learning_group_print_session($session, true);
    $mform->display();
}
else {
    print_error('notsignedup', 'block_learning_group', $returnurl);
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
