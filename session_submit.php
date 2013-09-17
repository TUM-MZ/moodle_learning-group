<?php


if ($fromform = $mform->get_data()) {
    $sessiondates = array();
    for ($i = 0; $i < $fromform->date_repeats; $i++) {
        if (!empty($fromform->datedelete[$i])) {
            continue; // skip this date
        }

        $timestartfield = "timestart[$i]";
        $timefinishfield = "timefinish[$i]";
        if (!empty($fromform->$timestartfield) and !empty($fromform->$timefinishfield)) {
            $date = new stdClass();
            $date->timestart = $fromform->$timestartfield;
            $date->timefinish = $fromform->$timefinishfield;
            $sessiondates[] = $date;
        }
    }
    $todb = new stdClass();
    $todb->datetimeknown = $fromform->datetimeknown;
    $todb->title = $fromform->title;
    $todb->public = $fromform->public;
    $todb->details = $fromform->details_editor[text];
    $todb->capacity = $fromform->capacity;

    if (!$c and $session != null) {
        // Now save everying to Database
        $todb->id = $session->id;
        if (!learning_group_update_session($todb, $sessiondates)) {
            $transaction->force_transaction_rollback();
            add_to_log($course->id, 'learning_group', 'update session (FAILED)', "sessions.php?s=$session->id", $learning_group->id, $cm->id);
            print_error('error:couldnotupdatesession', 'learning_group', $returnurl);
        }

        // Remove old site-wide calendar entry
        if (!learning_group_remove_session_from_calendar($session, SITEID)) {
            $transaction->force_transaction_rollback();
            print_error('error:couldnotupdatecalendar', 'learning_group', $returnurl);
        }

    } else {
        if (!$sessionid = learning_group_add_session($todb, $sessiondates)) {
            $transaction->force_transaction_rollback();
            add_to_log($course->id, 'learning_group', 'add session (FAILED)', 'sessions.php?f=' . $learning_group->id, $learning_group->id, $cm->id);
            print_error('error:couldnotaddsession', 'learning_group', $returnurl);
        }else{
            $session = learning_group_get_session($sessionid);
            learning_group_user_signup($session, MDL_F2F_ICAL, MDL_F2F_STATUS_BOOKED,false,false);
        }


    }
    redirect($returnurl);
}