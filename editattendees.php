<?php
require_once '../../config.php';
require_once 'lib.php';
require_login(1);

global $DB, $THEME;

define('MAX_USERS_PER_PAGE', 5000);

$s              = required_param('s', PARAM_INT); // learning_group session ID
$add            = optional_param('add', 0, PARAM_BOOL);
$remove         = optional_param('remove', 0, PARAM_BOOL);
$showall        = optional_param('showall', 0, PARAM_BOOL);
$searchtext     = optional_param('searchtext', '', PARAM_TEXT); // search string
$suppressemail  = optional_param('suppressemail', false, PARAM_BOOL); // send email notifications
$previoussearch = optional_param('previoussearch', 0, PARAM_BOOL);
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT); // learning_group activity to go back to

if (!$session = learning_group_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'block_learning_group');
}


/// Check essential permissions
#require_course_login($course);
#$context = context_course::instance($course->id);
#require_capability('mod/learning_group:viewattendees', $context);

/// Get some language strings
$strsearch = get_string('search');
$strshowall = get_string('showall');
$strsearchresults = get_string('searchresults');
$strlearning_groups = get_string('modulenameplural', 'block_learning_group');
$strlearning_group = get_string('modulename', 'block_learning_group');

$errors = array();
// Get the user_selector we will need.
$potentialuserselector = new learning_group_candidate_selector('addselect', array('sessionid'=>$session->id));
$existinguserselector = new learning_group_existing_selector('removeselect', array('sessionid'=>$session->id));

// Process incoming user assignments
if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {

    $userstoassign = $potentialuserselector->get_selected_users();

    if (!empty($userstoassign)) {
        foreach ($userstoassign as $adduser) {
            if (!$adduser = clean_param($adduser->id, PARAM_INT)) {
                continue; // invalid userid
            }


            if (learning_group_get_user_submissions($session->id, $adduser)) {
                $erruser = $DB->get_record('user', array('id' => $adduser),'id, firstname, lastname');
                $errors[] = get_string('error:addalreadysignedupattendee', 'block_learning_group', fullname($erruser));
            } else {
                if (!learning_group_session_has_capacity($session)) {
                    $errors[] = get_string('full', 'learning_group');
                    break; // no point in trying to add other people
                }
                $status = MDL_F2F_STATUS_REQUESTED;
                if (!learning_group_user_signup($session, MDL_F2F_BOTH,
                $status, $adduser, !$suppressemail)) {
                    $erruser = $DB->get_record('user', array('id' => $adduser),'id, firstname, lastname');
                    $errors[] = get_string('error:addattendee', 'block_learning_group', fullname($erruser));
                }
            }
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
    }
}

// Process removing user assignments from session
if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoremove = $existinguserselector->get_selected_users();
    if (!empty($userstoremove)) {
        foreach ($userstoremove as $removeuser) {
            if (!$removeuser = clean_param($removeuser->id, PARAM_INT)) {
                continue; // invalid userid
            }

            if (learning_group_user_cancel($session, $removeuser, true, $cancelerr)) {
                // Notify the user of the cancellation if the session hasn't started yet
                $timenow = time();
                if (!$suppressemail) {
                    //learning_group_send_cancellation_notice( $session, $removeuser); // If you want to send a Cancel Notice
                }
            } else {
                $errors[] = $cancelerr;
                $erruser = $DB->get_record('user', array('id' => $removeuser),'id, firstname, lastname');
                $errors[] = get_string('error:removeattendee', 'block_learning_group', fullname($erruser));
            }
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
        // Update attendees
        learning_group_update_attendees($session);
    }
}

/// Main page
#$pagetitle = format_string($learning_group->name);

#$PAGE->set_cm($cm);
$PAGE->set_url('/blocks/learning_group/editattendees.php', array('s' => $s));
$PAGE->set_pagelayout('standard');

$PAGE->set_title($session->title);
$PAGE->set_heading($session->title);
echo $OUTPUT->header();


echo $OUTPUT->box_start();
echo $OUTPUT->heading(get_string('addremoveattendees', 'block_learning_group'));

//create user_selector form
$out = html_writer::start_tag('form', array('id' => 'assignform', 'method' => 'post', 'action' => $PAGE->url));
$out .= html_writer::start_tag('div');
$out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "previoussearch", 'value' => $previoussearch));
$out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "backtoallsessions", 'value' => $backtoallsessions));
$out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "sesskey", 'value' => sesskey()));

$table = new html_table();
$table->attributes['class'] = "generaltable generalbox boxaligncenter";
$cells = array();
$content = html_writer::start_tag('p') . html_writer::tag('label', get_string('attendees', 'block_learning_group'), array('for' => 'removeselect')) . html_writer::end_tag('p');
$content .= $existinguserselector->display(true);
$cell = new html_table_cell($content);
$cell->attributes['id'] = 'existingcell';
$cells[] = $cell;
$content = html_writer::tag('div', html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'add', 'name' => 'add', 'title' => get_string('add'), 'value' => $OUTPUT->larrow().' '.get_string('add'))), array('id' => 'addcontrols'));
$content .= html_writer::tag('div', html_writer::empty_tag('input', array('type' => 'submit', 'id' => 'remove', 'name' => 'remove', 'title' => get_string('remove'), 'value' => $OUTPUT->rarrow().' '.get_string('remove'))), array('id' => 'removecontrols'));
$cell = new html_table_cell($content);
$cell->attributes['id'] = 'buttonscell';
$cells[] = $cell;
$content = html_writer::start_tag('p') . html_writer::tag('label', get_string('potentialattendees', 'block_learning_group'), array('for' => 'addselect')) . html_writer::end_tag('p');
$content .= $potentialuserselector->display(true);
$cell = new html_table_cell($content);
$cell->attributes['id'] = 'potentialcell';
$cells[] = $cell;
$table->data[] = new html_table_row($cells);
$content = html_writer::checkbox('suppressemail', 1, $suppressemail, get_string('suppressemail', 'block_learning_group'), array('id' => 'suppressemail'));
$cell = new html_table_cell($content);
$cell->attributes['id'] = 'backcell';
$cell->attributes['colspan'] = '3';
$table->data[] = new html_table_row(array($cell));

$out .=  html_writer::table($table);

    // Get all signed up non-attendees
    $nonattendees = 0;
    $nonattendees_rs = $DB->get_recordset_sql(
         "SELECT
                u.id,
                u.firstname,
                u.lastname,
                su.statuscode as statuscode,
                u.email
            FROM
                {learning_group_sessions} s
            JOIN
                {learning_group_signups} su
             ON s.id = su.sessionid
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                s.id = ?
              AND
                su.statuscode = ?
            ORDER BY
                u.lastname, u.firstname", array($session->id, MDL_F2F_STATUS_REQUESTED)
    );

    $table = new html_table();
    $table->width = "100%";
    $table->head = array(get_string('name'), get_string('email'), get_string('status'));
    foreach ($nonattendees_rs as $user) {
        $data = array();
        $data[] = new html_table_cell(fullname($user));
        $data[] = new html_table_cell($user->email);
        $data[] = new html_table_cell(get_string('status_'.learning_group_get_status($user->statuscode), 'block_learning_group'));
        $row = new html_table_row($data);
        $table->data[] = $row;
        $nonattendees++;
    }
    $nonattendees_rs->close();
    if ($nonattendees) {
        $out .= html_writer::empty_tag('br');
        $out .=  $OUTPUT->heading(get_string('unapprovedrequests', 'block_learning_group').' ('.$nonattendees.')');
        $out .=  html_writer::table($table);
    }

    $out .= html_writer::end_tag('div') . html_writer::end_tag('form');
    echo $out;

if (!empty($errors)) {
    $msg = html_writer::start_tag('p');
    foreach ($errors as $e) {
        $msg .= $e . html_writer::empty_tag('br');
    }
    $msg .= html_writer::end_tag('p');
    echo $OUTPUT->box_start('center');
    echo $OUTPUT->notification($msg);
    echo $OUTPUT->box_end();
}

// Bottom of the page links
echo html_writer::start_tag('p');
$url = new moodle_url('/blocks/learning_group/attendees.php', array('s' => $session->id, 'backtoallsessions' => $backtoallsessions));
echo html_writer::link($url, get_string('goback', 'block_learning_group'));
echo html_writer::end_tag('p');
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
