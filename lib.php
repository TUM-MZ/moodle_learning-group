<?php
/**
 * Create a new entry in the learning_group_sessions table
 */
require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * Definitions for setting notification types
 */
/**
 * Utility definitions
 */
define('MDL_F2F_ICAL', 1);
define('MDL_F2F_TEXT', 2);
define('MDL_F2F_BOTH', 3);
define('MDL_F2F_INVITE', 4);
define('MDL_F2F_CANCEL', 8);
/**
 * Definitions for use in forms
 */
define('MDL_F2F_INVITE_BOTH', 7); // Send a copy of both 4+1+2
define('MDL_F2F_INVITE_TEXT', 6); // Send just a plain email 4+2
define('MDL_F2F_INVITE_ICAL', 5); // Send just a combined text/ical message 4+1
define('MDL_F2F_CANCEL_BOTH', 11); // Send a copy of both 8+2+1
define('MDL_F2F_CANCEL_TEXT', 10); // Send just a plan email 8+2
define('MDL_F2F_CANCEL_ICAL', 9); // Send just a combined text/ical message 8+1

// Name of the custom field where the manager's email address is stored
define('MDL_MANAGERSEMAIL_FIELD', 'managersemail');
// Calendar-related constants
define('CALENDAR_MAX_NAME_LENGTH', 15);
define('F2F_CAL_NONE', 0);
define('F2F_CAL_COURSE', 1);
define('F2F_CAL_SITE', 2);

// Signup status codes (remember to update $MDL_F2F_STATUS)
define('MDL_F2F_STATUS_USER_CANCELLED', 10);
// SESSION_CANCELLED is not yet implemented
define('MDL_F2F_STATUS_SESSION_CANCELLED', 20);
define('MDL_F2F_STATUS_DECLINED', 30);
define('MDL_F2F_STATUS_REQUESTED', 40);
define('MDL_F2F_STATUS_APPROVED', 50);
define('MDL_F2F_STATUS_WAITLISTED', 60);
define('MDL_F2F_STATUS_BOOKED', 70);
define('MDL_F2F_STATUS_NO_SHOW', 80);
define('MDL_F2F_STATUS_PARTIALLY_ATTENDED', 90);
define('MDL_F2F_STATUS_FULLY_ATTENDED', 100);
// This array must match the status codes above, and the values
// must equal the end of the constant name but in lower case
global $MDL_F2F_STATUS;
$MDL_F2F_STATUS = array(
    MDL_F2F_STATUS_USER_CANCELLED => 'user_cancelled',
//  SESSION_CANCELLED is not yet implemented
//    MDL_F2F_STATUS_SESSION_CANCELLED    => 'session_cancelled',
    MDL_F2F_STATUS_DECLINED => 'declined',
    MDL_F2F_STATUS_REQUESTED => 'requested',
    MDL_F2F_STATUS_APPROVED => 'approved',
    MDL_F2F_STATUS_WAITLISTED => 'waitlisted',
    MDL_F2F_STATUS_BOOKED => 'booked',
    MDL_F2F_STATUS_NO_SHOW => 'no_show',
    MDL_F2F_STATUS_PARTIALLY_ATTENDED => 'partially_attended',
    MDL_F2F_STATUS_FULLY_ATTENDED => 'fully_attended',
);
function learning_group_add_session($session, $sessiondates)
{
    global $USER, $DB;

    $session->timecreated = time();
    $session = cleanup_session_data($session);

    $session->userid = $USER->id;
    $session->id = $DB->insert_record('learning_group_sessions', $session);

    if (empty($sessiondates)) {
        // Insert a dummy date record
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;

        $DB->insert_record('learning_group_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;

            $DB->insert_record('learning_group_dates', $date);
        }
    }

    //create any calendar entries
    $session->sessiondates = $sessiondates;
    learning_group_update_calendar_entries($session);

    return $session->id;
}

/**
 * Modify an entry in the learning_group_sessions table
 */
function learning_group_update_session($session, $sessiondates)
{
    global $DB;

    $session->timemodified = time();
    $session = cleanup_session_data($session);
    $transaction = $DB->start_delegated_transaction();

    $DB->update_record('learning_group_sessions', $session);
    $DB->delete_records('learning_group_dates', array('sessionid' => $session->id));

    if (empty($sessiondates)) {
        // Insert a dummy date record
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        $DB->insert_record('learning_group_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('learning_group_dates', $date);
        }
    }

    // Update any calendar entries
    $session->sessiondates = $sessiondates;
    learning_group_update_calendar_entries($session);

    $transaction->allow_commit();

    return learning_group_update_attendees($session);
}

/**
 * Update calendar entries for a given session
 *
 * @param int $session ID of session to update event for
 * @param int $learning_group ID of learning_group activity (optional)
 */
function learning_group_update_calendar_entries($session, $learning_group = null)
{
    global $USER, $DB;

    return true;

    //remove from all calendars
    learning_group_delete_user_calendar_events($session, 'booking');
    learning_group_delete_user_calendar_events($session, 'session');
    learning_group_remove_session_from_calendar($session, $learning_group->course);
    learning_group_remove_session_from_calendar($session, SITEID);


    //add to NEW calendartype
    if ($learning_group->usercalentry) {
        //get ALL enrolled/booked users
        $users = learning_group_get_attendees($session->id);
        if (!in_array($USER->id, $users)) {
            learning_group_add_session_to_calendar($session, $learning_group, 'user', $USER->id, 'session');
        }

        foreach ($users as $user) {
            $eventtype = $user->statuscode == MDL_F2F_STATUS_BOOKED ? 'booking' : 'session';
            learning_group_add_session_to_calendar($session, $learning_group, 'user', $user->id, $eventtype);
        }
    }

    if ($learning_group->showoncalendar == F2F_CAL_COURSE) {
        learning_group_add_session_to_calendar($session, $learning_group, 'course');
    } else if ($learning_group->showoncalendar == F2F_CAL_SITE) {
        learning_group_add_session_to_calendar($session, $learning_group, 'site');
    }

    return true;
}

/**
 * Update attendee list status' on booking size change
 */
function learning_group_update_attendees($session)
{
    global $USER, $DB;


    // Update user status'
    $users = learning_group_get_attendees($session->id);

   // TO Do in case like waitinglist etc

    return $session->id;
}

/**
 * Delete entry from the learning_group_sessions table along with all
 * related details in other tables
 *
 * @param object $session Record from learning_group_sessions
 */
function learning_group_delete_session($session)
{
    global $CFG, $DB;


    // Cancel user signups (and notify users)
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {learning_group_signups} s
            WHERE
                s.sessionid = ?
        ", array($session->id));

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (learning_group_user_cancel($session, $user->userid, true)) {
                learning_group_send_cancellation_notice($session, $user->userid);
            } else {
                return false; // Cannot rollback since we notified users already
            }
        }
    }

    $transaction = $DB->start_delegated_transaction();

    // Remove entries from the teacher calendars
    $DB->delete_records_select('event', "modulename = 'learning_group' AND
                                         eventtype = 'learning_groupsession' AND
                                         instance = ? AND description LIKE ?",
        array($learning_group->id, "%attendees.php?s={$session->id}%"));

    if ($learning_group->showoncalendar == F2F_CAL_COURSE) {
        // Remove entry from course calendar
        learning_group_remove_session_from_calendar($session, $learning_group->course);
    } else if ($learning_group->showoncalendar == F2F_CAL_SITE) {
        // Remove entry from site-wide calendar
        learning_group_remove_session_from_calendar($session, SITEID);
    }

    // Delete session details
    $DB->delete_records('learning_group_sessions', array('id' => $session->id));

    $DB->delete_records('learning_group_dates', array('sessionid' => $session->id));


    $DB->delete_records('learning_group_signups', array('sessionid' => $session->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Prepare the user data to go into the database.
 */
function cleanup_session_data($session)
{

    // Only numbers allowed here
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $MAX_CAPACITY = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    } elseif ($session->capacity > $MAX_CAPACITY) {
        $session->capacity = $MAX_CAPACITY;
    }

    // Get the decimal point separator
    setlocale(LC_MONETARY, get_string('locale', 'langconfig'));
    $localeinfo = localeconv();
    $symbol = $localeinfo['decimal_point'];
    if (empty($symbol)) {
        // Cannot get the locale information, default to en_US.UTF-8
        $symbol = '.';
    }


    return $session;
}

/**
 * Converts hours to minutes
 */
function learning_group_hours_to_minutes($hours)
{
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {
        // e.g. "1:45" => 105 minutes
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    } else {
        // e.g. "1.75" => 105 minutes
        return round($hours * 60.0);
    }
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param object $session       Record from the learning_group_sessions table
 * @param string $eventtype     Type of event to update
 */
function learning_group_update_user_calendar_events($session, $eventtype)
{
    global $DB;

    $learning_group = $DB->get_record('learning_group', array('id' => $session->learning_group));

    if (empty($learning_group->usercalentry) || $learning_group->usercalentry == 0) {
        return true;
    }

    $users = learning_group_delete_user_calendar_events($session, $eventtype);

    // Add this session to these users' calendar
    foreach ($users as $user) {
        learning_group_add_session_to_calendar($session, $learning_group, 'user', $user->userid, $eventtype);
    }
    return true;
}

/**
 * Delete all user level calendar events for a face to face session
 *
 * @param class $session    Record from the learning_group_sessions table
 * @param string $eventtype  Type of the event (booking or session)
 *
 * @return array    $users      Array of users who had the event deleted
 */
function learning_group_delete_user_calendar_events($session, $eventtype)
{
    global $CFG, $DB;

    $whereclause = "modulename = 'learning_group' AND
                    eventtype = 'learning_group$eventtype' AND
                    instance = ?";

    $whereparams = array($session->learning_group);

    if ('session' == $eventtype) {
        $likestr = "%attendees.php?s={$session->id}%";
        $like = $DB->sql_like('description', '?');
        $whereclause .= " AND $like";

        $whereparams[] = $likestr;
    }

    // Users calendar
    $users = $DB->get_records_sql("SELECT DISTINCT userid
        FROM {event}
        WHERE $whereclause", $whereparams);

    if ($users && count($users) > 0) {
        // Delete the existing events
        $DB->delete_records_select('event', $whereclause, $whereparams);
    }

    return $users;
}

/**
 * Remove all entries in the course calendar which relate to this session.
 *
 * @param class $session    Record from the learning_group_sessions table
 * @param integer $userid   ID of the user
 */
function learning_group_remove_session_from_calendar($session, $courseid = 0, $userid = 0)
{
    global $DB;

    $params = array($userid, $session->id);

    return $DB->delete_records_select('event', "modulename = 'learning_group' AND
                                                userid = ? AND
                                                uuid = ?", $params);
}

/**
 * Return all of a users' submissions to a learning_group
 *
 * @param integer $learning_groupid
 * @param integer $userid
 * @param boolean $includecancellations
 * @return array submissions | false No submissions
 */
function learning_group_get_user_submissions($sessionid, $userid, $includecancellations = false)
{
    global $CFG, $DB;
    $whereclause = "s.id = ? AND su.userid = ?";
    $whereparams = array($sessionid, $userid);

    // If not show cancelled, only show requested and up status'
    if (!$includecancellations) {
        $whereparams = array_merge($whereparams, array(MDL_F2F_STATUS_REQUESTED, MDL_F2F_STATUS_NO_SHOW));
    }
    //TODO fix mailedconfirmation, timegraded, timecancelled, etc
    return $DB->get_record_sql("
        SELECT
            su.id,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.mailedreminder,
            su.statuscode,
            s.timemodified,
            0 as timecancelled,
            su.notificationtype
        FROM
            {learning_group_sessions} s
        JOIN
            {learning_group_signups} su
         ON su.sessionid = s.id
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ", $whereparams);
}

/**
 * Get all records from learning_group_sessions for a given learning_group activity and location
 *
 * @param integer $learning_groupid ID of the activity
 * @param string $location location filter (optional)
 */
function learning_group_get_sessions($location = '')
{
    global $CFG, $DB;

    $fromclause = "FROM {learning_group_sessions} s";
    $locationwhere = '';
    $locationparams = array();
    if (!empty($location)) {
        $fromclause = "FROM {learning_group_session_data} d
                       JOIN {learning_group_sessions} s ON s.id = d.sessionid";
        $locationwhere .= " AND d.data = ?";
        $locationparams[] = $location;
    }
    $sessions = $DB->get_records_sql("SELECT s.*
                                   $fromclause
                        LEFT OUTER JOIN (SELECT sessionid, min(timestart) AS mintimestart
                                           FROM {learning_group_dates} GROUP BY sessionid) m ON m.sessionid = s.id
                               ORDER BY s.datetimeknown, m.mintimestart", $locationparams);

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            $sessions[$key]->sessiondates = learning_group_get_session_dates($value->id);
        }
    }
    return $sessions;
}

/**
 * Converts minutes to hours
 */
function learning_group_minutes_to_hours($minutes)
{

    if (!intval($minutes)) {
        return 0;
    }

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    } else {
        return $minutes;
    }
}

/**
 * Get all of the dates for a given session
 */
function learning_group_get_session_dates($sessionid)
{
    global $DB;
    $ret = array();

    if ($dates = $DB->get_records('learning_group_dates', array('sessionid' => $sessionid), 'timestart')) {
        $i = 0;
        foreach ($dates as $date) {
            $ret[$i++] = $date;
        }
    }

    return $ret;
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param class $session record from the learning_group_sessions table
 * @param integer $timenow current time
 */
function learning_group_has_session_started($session, $timenow)
{

    if (!$session->datetimeknown) {
        return false; // no date set
    }

    foreach ($session->sessiondates as $date) {
        if ($date->timestart < $timenow) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param class $session record from the learning_group_sessions table
 * @param integer $timenow current time
 */
function learning_group_is_session_in_progress($session, $timenow)
{
    if (!$session->datetimeknown) {
        return false;
    }
    foreach ($session->sessiondates as $date) {
        if ($date->timefinish > $timenow && $date->timestart < $timenow) {
            return true;
        }
    }
    return false;
}

/**
 * Return number of attendees signed up to a learning_group session
 *
 * @param integer $session_id
 * @param integer $status MDL_F2F_STATUS_* constant (optional)
 * @return integer
 */
function learning_group_get_num_attendees($session_id, $status = MDL_F2F_STATUS_BOOKED)
{
    global $CFG, $DB;

    $sql = 'SELECT count(su.id)
        FROM
            {learning_group_signups} su
        WHERE
            sessionid = ?';

    // for the session, pick signups that haven't been superceded, or cancelled
    return (int)$DB->count_records_sql($sql, array($session_id, $status));
}

/**
 * Get a record from the learning_group_sessions table
 *
 * @param integer $sessionid ID of the session
 */
function learning_group_get_session($sessionid)
{
    global $DB;
    $session = $DB->get_record('learning_group_sessions', array('id' => $sessionid));

    if ($session) {
        $session->sessiondates = learning_group_get_session_dates($sessionid);
    }

    return $session;
}

/**
 * Get list of users attending a given session
 *
 * @access public
 * @param integer Session ID
 * @return array
 */
function learning_group_get_attendees($sessionid)
{
    global $CFG, $DB;
    $records = $DB->get_records_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            su.statuscode AS statuscode,
            u.firstname,
            u.lastname,
            u.email,
            su.notificationtype
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
        ORDER BY
            su.created ASC
    ", array($sessionid));

    return $records;
}

/**
 * Print the details of a session
 *
 * @param object $session         Record from learning_group_sessions
 * @param boolean $showcapacity   Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return         Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup     Hide any messages relating to signing up
 */
function learning_group_print_session($session, $showcapacity, $calendaroutput = false, $return = false, $hidesignup = false, $joinroom = false)
{
    global $CFG, $DB;

    $table = new html_table();
    $table->width = "100%";
    $table->summary = get_string('sessionsdetailstablesummary', 'block_learning_group');
    $table->attributes['class'] = 'generaltable f2fsession';
    $table->align = array('right', 'left');
    if ($calendaroutput) {
        $table->tablealign = 'left';
    }


    $table->data[] = array(get_string('title', 'block_learning_group'), $session->title);

    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'block_learning_group'));
    if ($session->datetimeknown) {
        $html = '';
        foreach ($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= html_writer::empty_tag('br');
            }
            $timestart = userdate($date->timestart, get_string('strftimedatetime'));
            $timefinish = userdate($date->timefinish, get_string('strftimedatetime'));
            $html .= "$timestart &ndash; $timefinish";
        }
        $table->data[] = array($strdatetime, $html);
    } else {
        $table->data[] = array($strdatetime, html_writer::tag('i', get_string('wait-listed', 'block_learning_group')));
    }

    $signupcount = learning_group_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        if ($session->allowoverbook) {
            $table->data[] = array(get_string('capacity', 'block_learning_group'), $session->capacity . ' (' . strtolower(get_string('allowoverbook', 'block_learning_group')) . ')');
        } else {
            $table->data[] = array(get_string('capacity', 'block_learning_group'), $session->capacity);
        }
    } elseif (!$calendaroutput) {
        $table->data[] = array(get_string('seatsavailable', 'block_learning_group'), max(0, $placesleft));
    }


    // Display waitlist notification
    if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
        $table->data[] = array('', get_string('userwillbewaitlisted', 'block_learning_group'));
    }


    if (!empty($session->details)) {
        $details = clean_text($session->details, FORMAT_HTML);
        $table->data[] = array(get_string('details', 'block_learning_group'), $details);
    }
    if ($joinroom) {
    $param = array('s' => $session->id);
    $target = new moodle_url('/blocks/learning_group/joinroom.php', $param);
    $param = array('type'=>'button',
        'value'=>get_string('joinmeeting','block_learning_group'),
        'name'=>'btnname',
        'onclick' => 'window.open(\''.$target->out(false).'\', \'btnname\',
                                                 \'menubar=0,location=0,scrollbars=0,resizable=0,width=900,height=900\', 0);',
    );
    $table->data[] = array(get_string('joinmeeting', 'block_learning_group'), html_writer::empty_tag('input', $param));
    }

    // Display trainers
    $trainerroles = learning_group_get_trainer_roles();

    if ($trainerroles) {
        // Get trainers
        $trainers = learning_group_get_trainers($session->id);

        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->name;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainer_names = array();
            foreach ($trainers[$role] as $trainer) {
                $trainer_url = new moodle_url('/user/view.php', array('id' => $trainer->id));
                $trainer_names[] = html_writer::link($trainer_url, fullname($trainer));
            }

            $table->data[] = array($rolename, implode(', ', $trainer_names));
        }
    }

    return html_writer::table($table, $return);
}

/**
 * Return array of trainer roles configured for face-to-face
 *
 * @return  array
 */
function learning_group_get_trainer_roles()
{
    global $CFG, $DB;

    // Check that roles have been selected
    if (empty($CFG->learning_group_session_roles)) {
        return false;
    }

    // Parse roles
    $cleanroles = clean_param($CFG->learning_group_session_roles, PARAM_SEQUENCE);
    $roles = explode(',', $cleanroles);
    list($rolesql, $params) = $DB->get_in_or_equal($roles);

    // Load role names
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {role} r
        WHERE
            r.id {$rolesql}
        AND r.id <> 0
    ", $params);

    // Return roles and names
    if (!$rolenames) {
        return array();
    }

    return $rolenames;
}

/**
 * Returns true if the user has registered for a session in the given
 * learning_group activity
 *
 * @global class $USER used to get the current userid
 * @returns integer The session id that we signed up for, false otherwise
 */
function learning_group_check_signup($sessionid)
{

    global $USER;

    if ($submission = learning_group_get_user_submissions($sessionid, $USER->id)) {
        return $submission->sessionid;
    } else {
        return false;
    }
}

/**
 * Returns true if the user has registered for a session in the given
 * learning_group activity
 *
 * @global class $USER used to get the current userid
 * @returns integer The session id that we signed up for, false otherwise
 */
function learning_group_session_has_capacity($session)
{
    if (learning_group_get_num_attendees($session->id) < $session->capacity) {
        return true;
    } else {
        return false;
    }
}

/**
 * Add a record to the learning_group submissions table and sends out an
 * email confirmation
 *
 * @param class $session record from the learning_group_sessions table
 * @param class $learning_group record from the learning_group table
 * @param class $course record from the course table
 * @param string $discountcode code entered by the user
 * @param integer $notificationtype type of notifications to send to user
 * @see {{MDL_F2F_INVITE}}
 * @param integer $statuscode Status code to set
 * @param integer $userid user to signup
 * @param bool $notifyuser whether or not to send an email confirmation
 * @param bool $displayerrors whether or not to return an error page on errors
 */
function learning_group_user_signup($session, $notificationtype, $statuscode, $userid = false,
                                    $notifyuser = true)
{

    global $CFG, $DB;

    // Get user id
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    $return = false;
    $timenow = time();

    // Check to see if a signup already exists
    if ($existingsignup = $DB->get_record('learning_group_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {
        // Otherwise, prepare a signup object
        $usersignup = new stdclass;
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->mailedreminder = 0;
    $usersignup->statuscode = $statuscode;
    $usersignup->notificationtype = $notificationtype;


    //   begin_sql();

    // Update/insert the signup record
    if (!empty($usersignup->id)) {
        $success = $DB->update_record('learning_group_signups', $usersignup);
    } else {
        $usersignup->id = $DB->insert_record('learning_group_signups', $usersignup);
        $success = (bool)$usersignup->id;
    }

    if (!$success) {
        //rollback_sql();
        print_error('error:couldnotupdatef2frecord', 'learning_group');
        return false;
    }

    // Work out which status to use

    // If approval not required

    // If approval required

    // Get current status (if any)
    //$current_status =  $DB->get_field('learning_group_signups_status', 'statuscode', array('signupid' => $usersignup->id, 'superceded' => 0));


    // Add to user calendar -- if learning_group usercalentry is set to true
    learning_group_add_session_to_calendar($session, 'user', $userid, 'booking');


    // If session has already started, do not send a notification
    if (learning_group_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification
    if ($notifyuser) {
                $error = learning_group_send_confirmation_notice( $session, $userid, $notificationtype, $statuscode);
        }


        if (!empty($error)) {
            // rollback_sql();
            print_error($error, 'learning_group');
            return false;
        }

        if (!$DB->update_record('learning_group_signups', $usersignup)) {
            //rollback_sql();
            print_error('error:couldnotupdatef2frecord', 'learning_group');
            return false;
        }


    //commit_sql();
    return true;
}

/**
 * Update the signup status of a particular signup
 *
 * @param integer $signupid ID of the signup to be updated
 * @param integer $statuscode Status code to be updated to
 * @param integer $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 * @param bool $usetransaction Set to true if database transactions are to be used
 *
 * @returns integer ID of newly created signup status, or false
 *
 */
function learning_group_update_signup_status($sign_up_id, $statuscode)
{   global $CFG, $DB;
    $transaction = $DB->start_delegated_transaction();
    $signup = $DB->get_record('learning_group_signups', array('id' => $sign_up_id));
    $signup->statuscode = $statuscode;
    $DB->update_record('learning_group_signups', $signup);
    $transaction->allow_commit();

    return true; // $statuscode id should be returned
}

/**
 * Send a confirmation email to the user and manager
 *
 * @param class $learning_group record from the learning_group table
 * @param class $session record from the learning_group_sessions table
 * @param integer $userid ID of the recipient of the email
 * @param integer $notificationtype Type of notifications to be sent @see {{MDL_F2F_INVITE}}
 * @param boolean $iswaitlisted If the user has been waitlisted
 * @returns string Error message (or empty string if successful)
 */
function learning_group_send_confirmation_notice($session, $userid, $notificationtype, $statuscode)
{
    $learning_group = new stdclass;

    if ($statuscode == MDL_F2F_STATUS_REQUESTED) {
        $postsubject = $session->title;
        $posttext = get_string("setting:defaultrequestinstrmngrdefault","block_learning_group");
    } else {
        $postsubject = $session->title;
        $posttext = get_string("setting:defaultrequestinstrmngrdefault","block_learning_group");

        // Don't send an iCal attachement when we don't know the date!
        $notificationtype |= MDL_F2F_TEXT; // add a text notification
        $notificationtype &= ~MDL_F2F_ICAL; // remove the iCalendar notification
    }

    // Set invite bit
    $notificationtype |= MDL_F2F_INVITE;

    return learning_group_send_notice($postsubject, $posttext,
        $notificationtype, $learning_group, $session, $userid);
}


/**
 * Common code for sending confirmation and cancellation notices
 *
 * @param string $postsubject Subject of the email
 * @param string $posttext Plain text contents of the email
 * @param string $posttextmgrheading Header to prepend to $posttext in manager email
 * @param string $notificationtype The type of notification to send
 * @see {{MDL_F2F_INVITE}}
 * @param class $learning_group record from the learning_group table
 * @param class $session record from the learning_group_sessions table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function learning_group_send_notice($postsubject, $posttext,
                                    $notificationtype,$learning_group, $session, $userid)
{
    global $CFG, $DB;
    $user = $DB->get_record('user', array('id' => $userid));
    if (!$user) {
        return 'error:invaliduserid';
    }

    if (empty($postsubject) || empty($posttext)) {
        $postsubject = $session->title;
        $posttext = get_string("setting:defaultrequestinstrmngrdefault","block_learning_group");//get_config('block_learning_group', 'learning_group_request_body');
    }

    // If no notice type is defined (TEXT or ICAL)
    if (!($notificationtype & MDL_F2F_BOTH)) {
        // If none, make sure they at least get a text email
        $notificationtype |= MDL_F2F_TEXT;
    }

    // If we are cancelling, check if ical cancellations are disabled
    if (($notificationtype & MDL_F2F_CANCEL) &&
        get_config(NULL, 'learning_group_disableicalcancel')
    ) {
        $notificationtype |= MDL_F2F_TEXT; // add a text notification
        $notificationtype &= ~MDL_F2F_ICAL; // remove the iCalendar notification
    }





    // Fill-in the email placeholders
    $postsubject = learning_group_email_substitutions($postsubject, $session->title,
        $user, $session, $session->id);
    $posttext = learning_group_email_substitutions($posttext,  $session->title,
        $user, $session, $session->id);

    $posttextmgrheading = learning_group_email_substitutions($posttextmgrheading, $learning_group->name, $learning_group->reminderperiod,
        $user, $session, $session->id);

    $posthtml = ''; // FIXME
    if ($fromaddress = get_config(NULL, 'learning_group_fromaddress')) {
        $from = new stdClass();
        $from->maildisplay = true;
        $from->email = $fromaddress;
    } else {
        $from = null;
    }


    // Send plain text email
    if ($notificationtype & MDL_F2F_TEXT) {
        if (!email_to_user($user, $from, $postsubject, $posttext, $posthtml)) {
            return 'error:cannotsendconfirmationuser';
        }
    }





    return '';
}

/**
 * Returns the human readable code for a
 *
 * @param int $statuscode One of the MDL_F2F_STATUS* constants
 * @return string Human readable code
 */
function learning_group_get_status($statuscode)
{
    global $MDL_F2F_STATUS;


    // Get code
    $string = $MDL_F2F_STATUS[$statuscode];

    // Check to make sure the status array looks to be up-to-date
    if (constant('MDL_F2F_STATUS_' . strtoupper($string)) != $statuscode) {
        print_error('F2F status code array does not appear to be up-to-date: ' . $statuscode);
    }

    return $string;
}

/**
 * Get session cancellations
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function learning_group_get_cancellations($sessionid)
{
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $instatus = array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_REQUESTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);
    // Nasty SQL follows:
    // Load currently cancelled users,
    // include most recent booked/waitlisted time also
    $sql = "
            SELECT
                u.id,
                su.id AS signupid,
                u.firstname,
                u.lastname,
                su.created AS timesignedup
            FROM
                {learning_group_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                su.sessionid = ?
            GROUP BY
                su.id,
                u.id,
                u.firstname,
                u.lastname,
                timesignedup
            ORDER BY
                {$fullname},
                timesignedup
    ";
    $params = array_merge(array(MDL_F2F_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session cancellations
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function learning_group_get_requests($sessionid)
{
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $instatus = array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_REQUESTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);
    // Nasty SQL follows:
    // Load currently cancelled users,
    // include most recent booked/waitlisted time also
    $sql = "
            SELECT
                u.id,
                su.id AS signupid,
                u.firstname,
                u.lastname,
                su.created AS timesignedup
            FROM
                {learning_group_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                su.sessionid = ?
            GROUP BY
                su.id,
                u.id,
                u.firstname,
                u.lastname,
                timesignedup
            ORDER BY
                {$fullname},
                timesignedup
    ";
    $params = array_merge(array(MDL_F2F_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session declined requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function learning_group_get_declines($sessionid)
{
    global $CFG, $DB;

    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

    $params = array($sessionid, MDL_F2F_STATUS_DECLINED);

    $sql = "SELECT u.id, su.id AS signupid, u.firstname, u.lastname,
                   ss.timecreated AS timerequested
              FROM {learning_group_signups} su
              JOIN {learning_group_signups_status} ss ON su.id=ss.signupid
              JOIN {user} u ON u.id = su.userid
             WHERE su.sessionid = ? AND ss.superceded != 1 AND ss.statuscode = ?
          ORDER BY $fullname, ss.timecreated";
    return $DB->get_records_sql($sql, $params);
}

/**
 * learning_group assignment candidates
 */
class learning_group_candidate_selector extends user_selector_base
{
    protected $sessionid;

    public function __construct($name, $options)
    {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search)
    {
        // Protection against receiving a list with all users
        if (false && strlen($search) < 4 && !$this->validatinguserids) {
            $groupname = get_string('potentialusers', 'role', 0);

            return array($groupname => array());
        }
        global $DB;
        /// All non-signed up system users
        list($wherecondition, $params) = $this->search_sql($search, '{user}');

        $fields = 'SELECT id, firstname, lastname, email';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
                  FROM {user}
                 WHERE $wherecondition
                   AND id NOT IN
                       (
                       SELECT u.id
                         FROM {learning_group_signups} s
                         JOIN {user} u ON u.id=s.userid
                        WHERE s.sessionid = :sessid
                       )
               ";
        $order = " ORDER BY lastname ASC, firstname ASC";
        $params = array_merge($params, array('sessid' => $this->sessionid, 'statusbooked' => MDL_F2F_STATUS_BOOKED));

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('potentialusers', 'role', count($availableusers));

        return array($groupname => $availableusers);
    }

    protected function get_options()
    {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'blocks/learning_group/lib.php';
        return $options;
    }
}

/**
 * learning_group assignment candidates
 */
class learning_group_existing_selector extends user_selector_base
{
    protected $sessionid;

    public function __construct($name, $options)
    {
        $this->sessionid = $options['sessionid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param <type> $search
     * @return array
     */
    public function find_users($search)
    {
        global $DB;
        //by default wherecondition retrieves all users except the deleted, not confirmed and guest
        list($wherecondition, $whereparams) = $this->search_sql($search, 'u');

        $fields = 'SELECT
                        u.id,
                        su.id AS submissionid,
                        u.firstname,
                        u.lastname,
                        u.email,
                        su.notificationtype';
        $countfields = 'SELECT COUNT(1)';
        $sql = "
            FROM
                {learning_group_sessions} s
            JOIN
                {learning_group_signups} su
             ON s.id = su.sessionid
            JOIN
                {user} u
             ON u.id = su.userid
            WHERE
                $wherecondition
            AND s.id = :sessid2
        ";
        $order = " ORDER BY su.created";
        $params = array('sessid1' => $this->sessionid, 'statusbooked' => MDL_F2F_STATUS_BOOKED, 'statuswaitlisted' => MDL_F2F_STATUS_WAITLISTED);
        $params = array_merge($params, $whereparams);
        $params['sessid2'] = $this->sessionid;
        $params['statusapproved'] = MDL_F2F_STATUS_APPROVED;
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, $params);

        if (empty($availableusers)) {
            return array();
        }

        $groupname = get_string('existingusers', 'role', count($availableusers));
        return array($groupname => $availableusers);
    }

    protected function get_options()
    {
        $options = parent::get_options();
        $options['sessionid'] = $this->sessionid;
        $options['file'] = 'blocks/learning_group/lib.php';
        return $options;
    }
}

/**
 * Cancel a user who signed up earlier
 *
 */
function learning_group_user_cancel($session, $userid = false, $forcecancel = false, &$errorstr = null)
{
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    // if $forcecancel is set, cancel session even if already occurred
    // used by learning_group_delete_session()
    if (!$forcecancel) {
        $timenow = time();
        // don't allow user to cancel a session that has already occurred
        if (learning_group_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'block_learning_group');
            return false;
        }
    }

    if (learning_group_user_cancel_submission($session->id, $userid)) {
        learning_group_remove_session_from_calendar($session, 0, $userid);

        learning_group_update_attendees($session);

        return true;
    }

    $errorstr = get_string('error:cancelbooking', 'block_learning_group');
    return false;
}

/**
 * Cancel users' submission to a block_learning_group session
 */
function learning_group_user_cancel_submission($sessionid, $userid)
{
    global $DB;

    $DB->delete_records('learning_group_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    return true; // not signed up, nothing to do
}

/**
 * Send a confirmation email to the user and manager regarding the
 * cancellation
 *
 * @param class block_learning_group record from the block_learning_group table
 * @param class $session record from the block_learning_group table
 * @param integer $userid ID of the recipient of the email
 * @returns string Error message (or empty string if successful)
 */
function learning_group_send_cancellation_notice($session, $userid)
{
    global $DB;
    /*
    $learning_group = "";
    $postsubject = $learning_group->cancellationsubject;
    $posttext = $learning_group->cancellationmessage;
    $posttextmgrheading = $learning_group->cancellationinstrmngr;
    */

    // Lookup what type of notification to send
    $notificationtype = $DB->get_field('learning_group_signups', 'notificationtype',
        array('sessionid' => $session->id, 'userid' => $userid));

    // Set cancellation bit
    $notificationtype |= MDL_F2F_CANCEL;

    return learning_group_send_notice($postsubject, $posttext, $posttextmgrheading,
        $notificationtype, $session, $userid);
}

//Update Adobe Connect scoID in Session Table
/**
 * Modify an entry in the learning_group_sessions table
 */
function learning_group_create_adobe_instance($session)
{
    global $DB;

    $session->timemodified = time();
    $session = cleanup_session_data($session);
    $transaction = $DB->start_delegated_transaction();

    $DB->update_record('learning_group_sessions', $session);
    $transaction->allow_commit();
    return true;
}

/**
 *
 * @param class $session          Record from the learning_group_session table
 * @param class $eventname        Name to display for this event
 * @param string $calendartype     Which calendar to add the event to (user, course, site)
 * @param int $userid           Optional param for user calendars
 * @param string $eventtype        Optional param for user calendar (booking/session)
 */
function learning_group_add_session_to_calendar($session, $calendartype = 'none', $userid = 0, $eventtype = 'site')
{
    global $CFG, $DB;

    if (empty($session->datetimeknown)) {
        return true; //date unkown, can't add to calendar
    }


    $description = '';
    $description .= learning_group_print_session($session, false, true, true);
    $linkurl = new moodle_url('/blocks/learning_group/signup.php', array('s' => $session->id));
    $linktext = get_string('signupforthissession', 'block_learning_group');

    if ($calendartype == 'site') {
        $courseid = SITEID;
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'user') {
        $courseid = 0;
        $urlvar = ($eventtype == 'session') ? 'attendees' : 'signup';
        $linkurl = $CFG->wwwroot . "/blocks/learning_group/" . $urlvar . ".php?s=$session->id";
        $description .= get_string("calendareventdescription{$eventtype}", 'block_learning_group', $linkurl);
    } else {
        return true;
    }


    $shortname = substr($session->title, 0, CALENDAR_MAX_NAME_LENGTH);


    $result = true;
    foreach ($session->sessiondates as $date) {
        $newevent = new stdClass();
        $newevent->name = $shortname;
        $newevent->description = $description;
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = $courseid;
        $newevent->groupid = 0;
        $newevent->userid = $userid;
        $newevent->uuid = "{$session->id}";
        $newevent->instance = $session->id;
        $newevent->modulename = 0;
        $newevent->eventtype = "{$eventtype}";
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        if ($calendartype == 'user' && $eventtype == 'booking') {
            //Check for and Delete the 'created' calendar event to reduce multiple entries for the same event
            $DB->delete_records('event', array('name' => $shortname, 'userid' => $userid,
                'instance' => $session->id, 'eventtype' => 'site'));
        }

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}


/**
 * Subsitute the placeholders in email templates for the actual data
 *
 * Expects the following parameters in the $data object:
 * - datetimeknown
 * - details
 * - discountcost
 * - duration
 * - normalcost
 * - sessiondates
 *
 * @access  public
 * @param   string  $msg            Email message
 * @param   string  block_learning_group F2F name
 * @param   int     $reminderperiod Num business days before event to send reminder
 * @param   obj     $user           The subject of the message
 * @param   obj     $data           Session data
 * @param   int     $sessionid      Session ID
 * @return  string
 */
function learning_group_email_substitutions($msg, $sessiontitle, $user, $data, $sessionid) {
    global $CFG, $DB;

    if (empty($msg)) {
        return '';
    }

    if ($data->datetimeknown) {
        // Scheduled session
        $sessiondate = userdate($data->sessiondates[0]->timestart, get_string('strftimedate'));
        $starttime = userdate($data->sessiondates[0]->timestart, get_string('strftimetime'));
        $finishtime = userdate($data->sessiondates[0]->timefinish, get_string('strftimetime'));

        $alldates = '';
        foreach ($data->sessiondates as $date) {
            if ($alldates != '') {
                $alldates .= "\n";
            }
            $alldates .= userdate($date->timestart, get_string('strftimedate')).', ';
            $alldates .= userdate($date->timestart, get_string('strftimetime')).
                ' to '.userdate($date->timefinish, get_string('strftimetime'));
        }
    }
    else {
        // Wait-listed session
        $sessiondate = get_string('unknowndate', 'block_learning_group');
        $alldates    = get_string('unknowndate', 'block_learning_group');
        $starttime   = get_string('unknowntime', 'block_learning_group');
        $finishtime  = get_string('unknowntime', 'block_learning_group');
    }

    $msg = str_replace(get_string('placeholder:learning_group_title', 'block_learning_group'), $data->title, $msg);
    $msg = str_replace(get_string('placeholder:firstname', 'block_learning_group'), $user->firstname, $msg);
    $msg = str_replace(get_string('placeholder:lastname', 'block_learning_group'), $user->lastname, $msg);
    $msg = str_replace(get_string('placeholder:alldates', 'block_learning_group'), $alldates, $msg);
    $msg = str_replace(get_string('placeholder:sessiondate', 'block_learning_group'), $sessiondate, $msg);
    $msg = str_replace(get_string('placeholder:starttime', 'block_learning_group'), $starttime, $msg);
    $msg = str_replace(get_string('placeholder:finishtime', 'block_learning_group'), $finishtime, $msg);
    if (empty($data->details)) {
        $msg = str_replace(get_string('placeholder:details', 'block_learning_group'), '', $msg);
    }
    else {
        $msg = str_replace(get_string('placeholder:details', 'block_learning_group'), html_to_text($data->details), $msg);
    }

    // Replace more meta data
    $msg = str_replace(get_string('placeholder:attendeeslink', 'block_learning_group'), $CFG->wwwroot.'/blocks/learning_group/signup.php?approve=true&s='.$data->id, $msg);



    return $msg;
}
/**
 * Return the email address of the user's manager if it is
 * defined. Otherwise return an empty string.
 *
 * @param integer $userid User ID of the staff member
 */
function learning_group_get_manageremail($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => MDL_MANAGERSEMAIL_FIELD));
    if ($fieldid) {
        return $DB->get_field('user_info_data', 'data', array('userid' => $userid, 'fieldid' => $fieldid));
    }
    else {
        return ''; // No custom field => no manager's email
    }
}
/**
 * Escapes data of the text datatype in ICAL documents.
 *
 * See RFC2445 or http://www.kanzaki.com/docs/ical/text.html or a more readable definition
 */
function learning_group_ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ','),
        array('\\\\', '\n', '\;', '\,'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one
    // whitespace after the newline that does the wrapping
    $text = wordwrap($text, 75, "\n ", true);

    return $text;
}
function learning_group_ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}
/**
 * Returns the ICAL data for a block_learning_group meeting.
 *
 * @param integer $method The method, @see {{MDL_F2F_INVITE}}
 * @param object block_learning_group A face-to-face object containing activity details
 * @param object $session A session object containing session details
 * @return string Filename of the attachment in the temp directory
 */
function learning_group_get_ical_attachment($method, $session, $user) {
    global $CFG, $DB;

    // First, generate all the VEVENT blocks
    $VEVENTS = '';
    foreach ($session->sessiondates as $date) {
        // Date that this representation of the calendar information was created -
        // we use the time the session was created
        // http://www.kanzaki.com/docs/ical/dtstamp.html
        $DTSTAMP = learning_group_ical_generate_timestamp($session->timecreated);

        // UIDs should be globally unique
        $urlbits = parse_url($CFG->wwwroot);
        $sql = "SELECT COUNT(*)
            FROM {learning_group_signups} su
            WHERE su.userid = ?
                AND su.sessionid = ?
                AND su.statuscode = ? ";
        $params = array($user->id, $session->id, MDL_F2F_STATUS_USER_CANCELLED);


        $UID =
            $DTSTAMP .
            '-' . substr(md5($CFG->siteidentifier . $session->id . $date->id), -8) .   // Unique identifier, salted with site identifier
            '-' . $DB->count_records_sql($sql, $params) .                              // New UID if this is a re-signup
            '@' . $urlbits['host'];                                                    // Hostname for this moodle installation

        $DTSTART = learning_group_ical_generate_timestamp($date->timestart);
        $DTEND   = learning_group_ical_generate_timestamp($date->timefinish);

        // FIXME: currently we are not sending updates if the times of the
        // sesion are changed. This is not ideal!
        $SEQUENCE = ($method & MDL_F2F_CANCEL) ? 1 : 0;

        $SUMMARY     = learning_group_ical_escape($session->title);
        $DESCRIPTION = learning_group_ical_escape($session->details, true);


        $ORGANISEREMAIL = get_config(NULL, 'learning_group_fromaddress');

        $ROLE = 'REQ-PARTICIPANT';
        $CANCELSTATUS = '';
        if ($method & MDL_F2F_CANCEL) {
            $ROLE = 'NON-PARTICIPANT';
            $CANCELSTATUS = "\nSTATUS:CANCELLED";
        }

        $icalmethod = ($method & MDL_F2F_INVITE) ? 'REQUEST' : 'CANCEL';

        // FIXME: if the user has input their name in another language, we need
        // to set the LANGUAGE property parameter here
        $USERNAME = fullname($user);
        $MAILTO   = $user->email;

        // The extra newline at the bottom is so multiple events start on their
        // own lines. The very last one is trimmed outside the loop
        $VEVENTS .= <<<EOF
BEGIN:VEVENT
UID:{$UID}
DTSTAMP:{$DTSTAMP}
DTSTART:{$DTSTART}
DTEND:{$DTEND}
SEQUENCE:{$SEQUENCE}
SUMMARY:{$SUMMARY}
LOCATION:{$LOCATION}
DESCRIPTION:{$DESCRIPTION}
CLASS:PRIVATE
TRANSP:OPAQUE{$CANCELSTATUS}
ORGANIZER;CN={$ORGANISEREMAIL}:MAILTO:{$ORGANISEREMAIL}
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$ROLE};PARTSTAT=NEEDS-ACTION;
 RSVP=FALSE;CN={$USERNAME};LANGUAGE=en:MAILTO:{$MAILTO}
END:VEVENT

EOF;
    }

    $VEVENTS = trim($VEVENTS);

    // TODO: remove the hard-coded timezone!
    $template = <<<EOF
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//Moodle//NONSGML block_learning_group//EN
VERSION:2.0
METHOD:{$icalmethod}
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Tzfile/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZNAME:NZST
DTSTART:19700405T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=4
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:NZDT
DTSTART:19700928T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE
{$VEVENTS}
END:VCALENDAR
EOF;

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    file_put_contents($tempfilepathname, $template);
    return $tempfilename;
}
