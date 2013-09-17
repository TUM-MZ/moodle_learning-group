<?php
// !!!!!!!!1aA
// philipp.seeser
// https://dev.moodle.tum.de/login/index.php
require_once('../../config.php');
require_once('renderer.php');
require_once('lib.php');
GLOBAL $USER;
require_login(1);
$url = new moodle_url('/block/learning_block/view.php');

$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('learning_group', 'block_learning_group'));
$PAGE->set_heading(get_string('learning_group', 'block_learning_group'));

echo $OUTPUT->header();

echo $OUTPUT->box_start();
print_session_list( );
?>
<?php
echo $OUTPUT->box_end();

echo $OUTPUT->footer();



// Print Session List

function print_session_list( ) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;

    $f2f_renderer = $PAGE->get_renderer('learning_group');

    $timenow = time();

    #$context = context_course::instance($courseid);
    #$viewattendees = has_capability('mod/learning_group:viewattendees', $context);
    #$editsessions = has_capability('mod/learning_group:editsessions', $context);
    $editsessions = is_siteadmin(); // SiteAdmins are always allowed to edit learning groups
    $viewattendees = true;



    #$customfields = learning_group_get_session_customfields();

    $upcomingarray = array();
    $previousarray = array();
    $upcomingtbdarray = array();

    if ($sessions = learning_group_get_sessions( ) ) {
        foreach ($sessions as $session) {
            $bookedsession = null;
            //print_r(learning_group_get_user_submissions($session->id, $USER->id));
            if ($submissions = learning_group_get_user_submissions($session->id, $USER->id)) {
                $bookedsession = $submissions;
            }

            $sessionstarted = false;
            $sessionfull = false;
            $sessionwaitlisted = false;
            $isbookedsession = false;

            $sessiondata = $session;
            $sessiondata->bookedsession = $bookedsession;


            // Is session waitlisted
            if (!$session->datetimeknown) {
                $sessionwaitlisted = true;
            }

            // Check if session is started
            if ($session->datetimeknown && learning_group_has_session_started($session, $timenow) && learning_group_is_session_in_progress($session, $timenow)) {
                $sessionstarted = true;
            }
            elseif ($session->datetimeknown && learning_group_has_session_started($session, $timenow)) {
                $sessionstarted = true;
            }

            // Put the row in the right table
            if ($bookedsession || $session->userid == $USER->id) { // $sessionstarted
                $upcomingarray[] = $sessiondata;

            }
            elseif ($sessionwaitlisted && $bookedsession) {
                $upcomingtbdarray[] = $sessiondata;
            }
            elseif($session->public){ // Normal scheduled session
                $previousarray[] = $sessiondata;

            }
        }
    }

    // Upcoming sessions
    echo $OUTPUT->heading(get_string('mysessions', 'block_learning_group'));
    if (empty($upcomingarray) && empty($upcomingtbdarray)) {
        print_string('noupcoming', 'block_learning_group');
    }
    else {
        $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        echo $f2f_renderer->print_session_list_table( $upcomingarray, $viewattendees, $editsessions);
    }

    if (true || $editsessions) { // TODO
        echo html_writer::tag('p', html_writer::link(new moodle_url('sessions.php'), get_string('addsession', 'block_learning_group')));
    }

    // Public sessions
    if (!empty($previousarray)) {
        echo $OUTPUT->heading(get_string('publicsessions', 'block_learning_group'));
        echo $f2f_renderer->print_session_list_table( $previousarray, $viewattendees, $editsessions);
    }
}
