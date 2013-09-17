<?php



require_once dirname(dirname(dirname(__FILE__))).'/config.php';
require_once 'lib.php';
require_login(1);
global $DB, $USER;

/**
 * Load and validate base data
 */
// Face-to-face session ID
$s  = required_param('s', PARAM_INT);

// Take attendance
$takeattendance    = optional_param('takeattendance', false, PARAM_BOOL);
// Cancel request
$cancelform        = optional_param('cancelform', false, PARAM_BOOL);
// Face-to-face activity to return to
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

// Load data
if (!$session = learning_group_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'learning_group');
}
/**
 * Print page header
 */
#add_to_log($course->id, 'learning_group', 'view attendees', "view.php?id=$cm->id", $learning_group->id, $cm->id);

$pagetitle = format_string($session->title);

$PAGE->set_url('/blocks/learning_group/attendees.php', array('s' => $s));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($session->title);

$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo $OUTPUT->box_start();

// Load attendees
$attendees = learning_group_get_attendees($session->id);

// Load cancellations
$cancellations = learning_group_get_cancellations($session->id);


/**
 * Capability checks to see if the current user can view this page
 *
 * This page is a bit of a special case in this respect as there are four uses for this page.
 *
 * 1) Viewing attendee list
 *   - Requires mod/learning_group:viewattendees capability in the course
 *
 * 2) Viewing cancellation list
 *   - Requires mod/learning_group:viewcancellations capability in the course
 *
 * 3) Taking attendance
 *   - Requires mod/learning_group:takeattendance capabilities in the course
 *
 */

#$context = context_course::instance($course->id);
#$contextmodule = context_module::instance($cm->id);
// Actions the user can perform
#$can_view_attendees = has_capability('mod/learning_group:viewattendees', $context);
#$can_take_attendance = has_capability('mod/learning_group:takeattendance', $context);
#$can_view_cancellations = has_capability('mod/learning_group:viewcancellations', $context);
#$can_view_session = $can_view_attendees || $can_take_attendance || $can_view_cancellations;
$can_view_attendees = true;
$can_take_attendance = true;
$can_view_cancellations = true;
$can_view_session = $can_view_attendees || $can_take_attendance || $can_view_cancellations;
$can_approve_requests = false;

$requests = array();
$declines = array();

// If a user can take attendance, they can approve staff's booking requests
if ($can_take_attendance) {
    $requests = learning_group_get_requests($session->id);
}

// If requests found (but not in the middle of taking attendance), show requests table
if ($requests && !$takeattendance) {
    $can_approve_requests = true;
}

// Check the user is allowed to view this page
if (!$can_view_attendees && !$can_take_attendance && !$can_approve_requests && !$can_view_cancellations) {
    print_error('nopermissions', '', "{$CFG->wwwroot}/mod/learning_group/view.php?id={$cm->id}", get_string('view'));
}

// Check user has permissions to take attendance
if ($takeattendance && !$can_take_attendance) {
    print_error('nopermissions', '', '', get_capability_string('mod/learning_group:takeattendance'));
}


/**
 * Handle submitted data
 */
if ($form = data_submitted()) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $return = "{$CFG->wwwroot}/blocks/learning_group/attendees.php?s={$s}&backtoallsessions={$backtoallsessions}";

    if ($cancelform) {
        redirect($return);
    }
    elseif (!empty($form->requests)) {
        // Approve requests
        if ($can_approve_requests && learning_group_approve_requests($form)) {
            add_to_log($course->id, 'learning_group', 'approve requests', "view.php?id=$cm->id", $learning_group->id, $cm->id);
        }

        redirect($return);
    }
    elseif ($takeattendance) {
        if (learning_group_take_attendance($form)) {
            add_to_log($course->id, 'learning_group', 'take attendance', "view.php?id=$cm->id", $learning_group->id, $cm->id);
        } else {
            add_to_log($course->id, 'learning_group', 'take attendance (FAILED)', "view.php?id=$cm->id", $face->id, $cm->id);
        }
        redirect($return.'&takeattendance=1');
    }
}



/**
 * Print page content
 */
// If taking attendance, make sure the session has already started
if ($takeattendance && $session->datetimeknown && !learning_group_has_session_started($session, time())) {
    $link = "{$CFG->wwwroot}/blocks/learning_group/attendees.php?s={$session->id}";
    print_error('error:canttakeattendanceforunstartedsession', 'block_learning_group', $link);
}

#echo $OUTPUT->heading(format_string($learning_group->name));

if ($can_view_session) {
    echo learning_group_print_session($session, true);
}


/**
 * Print attendees (if user able to view)
 */
if ($can_view_attendees || $can_take_attendance) {
    if ($takeattendance) {
        $heading = get_string('takeattendance', 'block_learning_group');
    } else {
        $heading = get_string('attendees', 'block_learning_group');
    }

    echo $OUTPUT->heading($heading);

    if (empty($attendees)) {
        echo $OUTPUT->notification(get_string('nosignedupusers', 'block_learning_group'));
    }
    else {
        if ($takeattendance) {
            $attendees_url = new moodle_url('attendees.php', array('s' => $s, 'takeattendance' => '1'));
            echo html_writer::start_tag('form', array('action' => $attendees_url, 'method' => 'post'));
            echo html_writer::tag('p', get_string('attendanceinstructions', 'learning_group'));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => $USER->sesskey));
            echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 's', 'value' => $s));
            echo html_writer::empty_tag('input', array('type' => 'hidden', ' name' => 'backtoallsessions', 'value' => $backtoallsessions)) . '</p>';

            // Prepare status options array
            $status_options = array();
            foreach ($MDL_F2F_STATUS as $key => $value) {
                if ($key <= MDL_F2F_STATUS_BOOKED) {
                    continue;
                }

                $status_options[$key] = get_string('status_'.$value, 'block_learning_group');
            }
        }

        $table = new html_table();
        $table->head = array(get_string('name'));
        $table->summary = get_string('attendeestablesummary', 'block_learning_group');
        $table->align = array('left');
        $table->size = array('100%');

        if ($takeattendance) {
            $table->head[] = get_string('currentstatus', 'block_learning_group');
            $table->align[] = 'center';
            $table->head[] = get_string('attendedsession', 'block_learning_group');
            $table->align[] = 'center';
        }
        else {


            $table->head[] = get_string('attendance', 'block_learning_group');
            $table->align[] = 'center';
        }


        foreach ($attendees as $attendee) {
            $data = array();
            $attendee_url = new moodle_url('/user/view.php', array('id' => $attendee->id));
            $data[] = html_writer::link($attendee_url, format_string(fullname($attendee)));
            if ($takeattendance) {
                // Show current status
                $data[] = get_string('status_'.learning_group_get_status($attendee->statuscode), 'block_learning_group');

                $optionid = 'submissionid_'.$attendee->submissionid;
                $status = $attendee->statuscode;
                $select = html_writer::select($status_options, $optionid, $status);
                $data[] = $select;
            }
            else {
                $data[] = get_string('status_'.learning_group_get_status($attendee->statuscode), 'block_learning_group');
            }
            $table->data[] = $data;
        }

        echo html_writer::table($table);

        if ($takeattendance) {
            echo html_writer::start_tag('p');
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('saveattendance', 'block_learning_group')));
            echo '&nbsp;' . html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancelform', 'value' => get_string('cancel')));
            echo html_writer::end_tag('p') . html_writer::end_tag('form');
        }
        else {
            // Actions
            print html_writer::start_tag('p');
            if ($can_take_attendance && $session->datetimeknown && learning_group_has_session_started($session, time())) {
                // Take attendance
                $attendance_url = new moodle_url('attendees.php', array('s' => $session->id, 'takeattendance' => '1', 'backtoallsessions' => $backtoallsessions));
                echo html_writer::link($attendance_url, get_string('takeattendance', 'block_learning_group')) . ' - ';
            }
        }
    }

    if (!$takeattendance) {

        if($session->userid == $USER->id || is_siteadmin()){
            // Add/remove attendees
            $editattendees_link = new moodle_url('editattendees.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions));
            echo html_writer::link($editattendees_link, get_string('addremoveattendees', 'block_learning_group')) . ' - ';
        }
    }
}

// Go back
$url = new moodle_url('/blocks/learning_group/view.php');
if ($backtoallsessions) {
    $url = new moodle_url('/mod/learning_group/view.php', array('f' => $learning_group->id, 'backtoallsessions' => $backtoallsessions));
}
echo html_writer::link($url, get_string('goback', 'block_learning_group')) . html_writer::end_tag('p');


/**
 * Print unapproved requests (if user able to view)
 */
if ($can_approve_requests) {
    echo html_writer::empty_tag('br', array('id' => 'unapproved'));
    if (!$requests) {
        echo $OUTPUT->notification(get_string('noactionableunapprovedrequests', 'learning_group'));
    }
    else {
        $can_book_user = (learning_group_session_has_capacity($session, $contextmodule) || $session->allowoverbook);

        $OUTPUT->heading(get_string('unapprovedrequests', 'learning_group'));

        if (!$can_book_user) {
            echo html_writer::tag('p', get_string('cannotapproveatcapacity', 'learning_group'));
        }


        $action = new moodle_url('attendees.php', array('s' => $s));
        echo html_writer::start_tag('form', array('action' => $action->out(), 'method' => 'post'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => $USER->sesskey));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 's', 'value' => $s));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'backtoallsessions', 'value' => $backtoallsessions)) . html_writer::end_tag('p');

        $table = new html_table();
        $table->width = "100%";
        $table->summary = get_string('requeststablesummary', 'learning_group');
        $table->head = array(get_string('name'), get_string('timerequested', 'learning_group'),
                            get_string('decidelater', 'learning_group'), get_string('decline', 'learning_group'), get_string('approve', 'learning_group'));
        $table->align = array('left', 'center', 'center', 'center', 'center');

        foreach ($requests as $attendee) {
            $data = array();
            $attendee_link = new moodle_url('/user/view.php', array('id' => $attendee->id, 'course' => $course->id));
            $data[] = html_writer::link($attendee_link, format_string(fullname($attendee)));
            $data[] = userdate($attendee->timerequested, get_string('strftimedatetime'));
            $data[] = html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'requests['.$attendee->id.']', 'value' => '0', 'checked' => 'checked'));
            $data[] = html_writer::empty_tag('input', array('type' => 'radio', 'name' => 'requests['.$attendee->id.']', 'value' => '1'));
            $disabled = ($can_book_user) ? array() : array('disabled' => 'disabled');
            $data[] = html_writer::empty_tag('input', array_merge(array('type' => 'radio', 'name' => 'requests['.$attendee->id.']', 'value' => '2'), $disabled));
            $table->data[] = $data;
        }

        echo html_writer::table($table);

        echo html_writer::tag('p', html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('updaterequests', 'learning_group'))));
        echo html_writer::end_tag('form');
    }
}


/**
 * Print cancellations (if user able to view)
 */
if (!$takeattendance && $can_view_cancellations && $cancellations) {

    echo html_writer::empty_tag('br');
    echo $OUTPUT->heading(get_string('cancellations', 'learning_group'));

    $table = new html_table();
    $table->summary = get_string('cancellationstablesummary', 'learning_group');
    $table->head = array(get_string('name'), get_string('timesignedup', 'learning_group'),
                         get_string('timecancelled', 'learning_group'), get_string('cancelreason', 'learning_group'));
    $table->align = array('left', 'center', 'center');

    foreach ($cancellations as $attendee) {
        $data = array();
        $attendee_link = new moodle_url('/user/view.php', array('id' => $attendee->id, 'course' => $course->id));
        $data[] = html_writer::link($attendee_link, format_string(fullname($attendee)));
        $data[] = userdate($attendee->timesignedup, get_string('strftimedatetime'));
        $data[] = userdate($attendee->timecancelled, get_string('strftimedatetime'));
        $data[] = format_string($attendee->cancelreason);
        $table->data[] = $data;
    }
    echo html_writer::table($table);
}

/**
 * Print page footer
 */
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
