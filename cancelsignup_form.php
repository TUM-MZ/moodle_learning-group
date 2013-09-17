<?php

require_once "$CFG->dirroot/lib/formslib.php";

class block_learning_group_cancelsignup_form extends moodleform {

    function definition()
    {
        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string('cancelbooking', 'block_learning_group'));

        $mform->addElement('hidden', 's', $this->_customdata['s']);

        $mform->addElement('html', get_string('cancellationconfirm', 'block_learning_group')); // instructions


        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('yes'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancelbutton', get_string('no'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}
