<?php


defined('MOODLE_INTERNAL') || die();

class mod_learning_group_renderer extends plugin_renderer_base
{
    /**
     * Builds session list table given an array of sessions
     */
    public function print_session_list_table($sessions, $viewattendees, $editsessions)
    {
        $output = '';

        $tableheader = array();

        $tableheader[] = "Titel";
        $tableheader[] = get_string('date', 'block_learning_group');
        $tableheader[] = get_string('time', 'block_learning_group');
        if ($viewattendees) {
            $tableheader[] = get_string('capacity', 'block_learning_group');
        } else {
            $tableheader[] = get_string('seatsavailable', 'block_learning_group');
        }
        $tableheader[] = get_string('status', 'block_learning_group');
        $tableheader[] = get_string('options', 'block_learning_group');

        $timenow = time();

        $table = new html_table();
        $table->width = "100%";
        $table->summary = get_string('previoussessionslist', 'block_learning_group');
        $table->head = $tableheader;
        $table->data = array();

        foreach ($sessions as $session) {

            $isbookedsession = false;
            $bookedsession = $session->bookedsession;
            $sessionstarted = false;
            $sessionfull = false;

            $sessionrow = array();
            $sessionrow[] = $session->title;


            // Dates/times
            $allsessiondates = '';
            $allsessiontimes = '';
            if ($session->datetimeknown) {
                foreach ($session->sessiondates as $date) {
                    if (!empty($allsessiondates)) {
                        $allsessiondates .= html_writer::empty_tag('br');
                    }
                    $allsessiondates .= userdate($date->timestart, get_string('strftimedate'));
                    if (!empty($allsessiontimes)) {
                        $allsessiontimes .= html_writer::empty_tag('br');
                    }
                    $allsessiontimes .= userdate($date->timestart, get_string('strftimetime')) .
                        ' - ' . userdate($date->timefinish, get_string('strftimetime'));
                }
            } else {
                $allsessiondates = get_string('wait-listed', 'block_learning_group');
                $allsessiontimes = get_string('wait-listed', 'block_learning_group');
                $sessionwaitlisted = true;
            }
            $sessionrow[] = $allsessiondates;
            $sessionrow[] = $allsessiontimes;

            // Capacity
            $signupcount = learning_group_get_num_attendees($session->id, MDL_F2F_STATUS_APPROVED);
            $stats = $session->capacity - $signupcount;
            if ($viewattendees) {
                $stats = $signupcount . ' / ' . $session->capacity;
            } else {
                $stats = max(0, $stats);
            }
            $sessionrow[] = $stats;
            // Status
            $status = get_string('bookingopen', 'block_learning_group');
            if ($session->datetimeknown && learning_group_has_session_started($session, $timenow) && learning_group_is_session_in_progress($session, $timenow)) {
                $status = get_string('sessioninprogress', 'block_learning_group');
                $sessionstarted = true;
            } elseif ($session->datetimeknown && learning_group_has_session_started($session, $timenow)) {
                $status = get_string('sessionover', 'block_learning_group');
                $sessionstarted = true;
            } elseif (isset($bookedsession->statuscode) && $bookedsession->statuscode == MDL_F2F_STATUS_REQUESTED && $session->id == $bookedsession->sessionid) {
                $status = get_string('status_requested', 'block_learning_group') . html_writer::empty_tag('br');;
                $status .= html_writer::link('signup.php?approve=true&s=' . $session->id, get_string('approve', 'block_learning_group'), array('title' => get_string('approve', 'block_learning_group'))) . html_writer::empty_tag('br');

                $isbookedsession = true;
            } elseif (isset($bookedsession->statuscode) && $bookedsession->statuscode == MDL_F2F_STATUS_BOOKED && $session->id == $bookedsession->sessionid) {

                $status = get_string('status_booked', 'block_learning_group');
                $isbookedsession = true;
            } elseif ($signupcount >= $session->capacity) {
                $status = get_string('bookingfull', 'block_learning_group');
                $sessionfull = true;
            }

            $sessionrow[] = $status;

            // Options
            $options = '';
            GLOBAL $USER;
            if ($editsessions || $session->userid == $USER->id) {
                $options .= $this->output->action_icon(new moodle_url('sessions.php', array('s' => $session->id)), new pix_icon('t/edit', get_string('edit', 'block_learning_group')), null, array('title' => get_string('editsession', 'block_learning_group'))) . ' ';
                $options .= $this->output->action_icon(new moodle_url('sessions.php', array('s' => $session->id, 'd' => 1)), new pix_icon('t/delete', get_string('delete', 'block_learning_group')), null, array('title' => get_string('deletesession', 'block_learning_group'))) . ' ';
                $options .= html_writer::empty_tag('br');
            }
            if ($isbookedsession) {
                $options .= html_writer::link('attendees.php?s=' . $session->id, get_string('attendees', 'block_learning_group'), array('title' => get_string('seeattendees', 'block_learning_group'))) . html_writer::empty_tag('br');

                $options .= html_writer::link('signup.php?s=' . $session->id, get_string('moreinfo', 'block_learning_group'), array('title' => get_string('moreinfo', 'block_learning_group'))) . html_writer::empty_tag('br');
                if ($session->userid != $USER->id) {
                    $options .= html_writer::link('cancelsignup.php?s=' . $session->id, get_string('cancelbooking', 'block_learning_group'), array('title' => get_string('cancelbooking', 'block_learning_group'))) . html_writer::empty_tag('br');
                }
                $param = array('s' => $session->id);
                $target = new moodle_url('/blocks/learning_group/joinroom.php', $param);
                $param = array('type' => 'button',
                    'value' => get_string('joinmeeting', 'block_learning_group'),
                    'name' => 'btnname',
                    'onclick' => 'window.open(\'' . $target->out(false) . '\', \'btnname\',
                                                 \'menubar=0,location=0,scrollbars=0,resizable=0,width=900,height=900\', 0);',
                );


                $options .= html_writer::empty_tag('input', $param);
            } elseif (!$sessionstarted and !$bookedsession) {
                $options .= html_writer::link('signup.php?s=' . $session->id, get_string('moreinfo', 'block_learning_group'), array('title' => get_string('moreinfo', 'block_learning_group'))) . html_writer::empty_tag('br');
                $options .= html_writer::link('signup.php?s=' . $session->id, get_string('signup', 'block_learning_group'));
            }

            $sessionrow[] = $options;

            $row = new html_table_row($sessionrow);

            // Set the CSS class for the row
            if ($sessionstarted) {
                $row->attributes = array('class' => 'dimmed_text');
            } elseif ($isbookedsession) {
                $row->attributes = array('class' => 'highlight');
            } elseif ($sessionfull) {
                $row->attributes = array('class' => 'dimmed_text');
            }

            // Add row to table
            $table->data[] = $row;
        }

        $output .= html_writer::table($table);

        return $output;
    }
}

?>
