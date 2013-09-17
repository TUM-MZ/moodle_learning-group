<?php

require_once "$CFG->dirroot/lib/formslib.php";

class mod_learning_group_signup_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;
        $manageremail = $this->_customdata['manageremail'];

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->addElement('hidden', 'backtoallsessions', $this->_customdata['backtoallsessions']);

        if ($manageremail === false) {
            $mform->addElement('hidden', 'manageremail', '');
        }
        else {
            $mform->addElement('html', get_string('manageremailinstructionconfirm', 'block_learning_group')); // instructions

            $mform->addElement('text', 'manageremail', get_string('manageremail', 'block_learning_group'), 'size="35"');
            $mform->addRule('manageremail', null, 'required', null, 'client');
            $mform->addRule('manageremail', null, 'email', null, 'client');
            $mform->setType('manageremail', PARAM_TEXT);
        }




        $options = array(MDL_F2F_BOTH => get_string('notificationboth', 'block_learning_group'),
                         MDL_F2F_TEXT => get_string('notificationemail', 'block_learning_group'),
                         MDL_F2F_ICAL => get_string('notificationical', 'block_learning_group'),
                         );
        $mform->addElement('select', 'notificationtype', get_string('notificationtype', 'block_learning_group'), $options);
        $mform->addHelpButton('notificationtype', 'notificationtype', 'block_learning_group');
        $mform->addRule('notificationtype', null, 'required', null, 'client');
        $mform->setDefault('notificationtype', 0);

        $this->add_action_buttons(true, get_string('signup', 'block_learning_group'));
    }

    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        $manageremail = $data['manageremail'];
        if (!empty($manageremail)) {
            if (!learning_group_check_manageremail($manageremail)) {
                $errors['manageremail'] = learning_group_get_manageremailformat();
            }
        }

        return $errors;
    }
}
