<?php

defined('MOODLE_INTERNAL') || die();


require_once("{$CFG->libdir}/formslib.php");
require_once('lib.php');


class mod_learning_group_session_form extends moodleform
{

    function definition()
    {
        global $CFG, $DB;

        $mform =& $this->_form;

        if ($this->_customdata) {
            $mform->addElement('hidden', 's', $this->_customdata['s']);
        }

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('text', 'title', 'Titel', 'size="60"');
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->setType('title', PARAM_RAW);
        $mform->setDefault('title', 'Mein Titel der Lenrgruppe');

        // Checkbox Public or not
        $formarray = array();
        $formarray[] = $mform->createElement('selectyesno', 'public', get_string('publiclearninggroup', 'block_learning_group'));
        $mform->addGroup($formarray, 'public_group', get_string('publiclearninggroup', 'block_learning_group'), array(' '), false);
        $mform->addGroupRule('public_group', null, 'required', null, 'client');
        $mform->setDefault('public', true);
        // Dates known
        $formarray = array();
        $formarray[] = $mform->createElement('selectyesno', 'datetimeknown', get_string('sessiondatetimeknown', 'block_learning_group'));
        $formarray[] = $mform->createElement('static', 'datetimeknownhint', '', html_writer::tag('span', get_string('datetimeknownhinttext', 'block_learning_group'), array('class' => 'hint-text')));
        $mform->addGroup($formarray, 'datetimeknown_group', get_string('sessiondatetimeknown', 'block_learning_group'), array(' '), false);
        $mform->addGroupRule('datetimeknown_group', null, 'required', null, 'client');
        $mform->setDefault('datetimeknown', false);
        $mform->addHelpButton('datetimeknown_group', 'sessiondatetimeknown', 'block_learning_group');


        $repeatarray = array();
        $repeatarray[] = & $mform->createElement('hidden', 'sessiondateid', 0);
        $repeatarray[] = & $mform->createElement('date_time_selector', 'timestart', get_string('timestart', 'block_learning_group'));
        $repeatarray[] = & $mform->createElement('date_time_selector', 'timefinish', get_string('timefinish', 'block_learning_group'));
        $checkboxelement = & $mform->createElement('checkbox', 'datedelete', '', get_string('dateremove', 'block_learning_group'));
        unset($checkboxelement->_attributes['id']); // necessary until MDL-20441 is fixed
        $repeatarray[] = $checkboxelement;
        $repeatarray[] = & $mform->createElement('html', html_writer::empty_tag('br')); // spacer
        if($this->_customdata && array_key_exists('nbdays', $this->_customdata))
            $repeatcount = $this->_customdata['nbdays'];
        else
            $repeatcount = 0;

        $repeatoptions = array();
        $repeatoptions['timestart']['disabledif'] = array('datetimeknown', 'eq', 0);
        $repeatoptions['timefinish']['disabledif'] = array('datetimeknown', 'eq', 0);
        $mform->setType('timestart', PARAM_INT);
        $mform->setType('timefinish', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatcount, $repeatoptions, 'date_repeats', 'date_add_fields',
            1, get_string('dateadd', 'block_learning_group'), true);

        $mform->addElement('text', 'capacity', get_string('capacity', 'block_learning_group'), 'size="5"');
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 10);
        $mform->addHelpButton('capacity', 'capacity', 'block_learning_group');


        $mform->addElement('editor', 'details_editor', get_string('details', 'block_learning_group'), null, $editoroptions);
        $mform->setType('details_editor', PARAM_RAW);
        $mform->addHelpButton('details_editor', 'details', 'block_learning_group');


        $this->add_action_buttons();
    }

    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);
        $dateids = $data['sessiondateid'];
        $dates = count($dateids);
        for ($i = 0; $i < $dates; $i++) {
            $starttime = $data["timestart[$i]"];
            $endtime = $data["timefinish[$i]"];
            $removecheckbox = empty($data["datedelete"]) ? array() : $data["datedelete"];
            if ($starttime > $endtime && !isset($removecheckbox[$i])) {
                $errstr = get_string('error:sessionstartafterend', 'block_learning_group');
                $errors['timestart[' . $i . ']'] = $errstr;
                $errors['timefinish[' . $i . ']'] = $errstr;
                unset($errstr);
            }
        }

        if (!empty($data['datetimeknown'])) {
            $datefound = false;
            for ($i = 0; $i < $data['date_repeats']; $i++) {
                if (empty($data['datedelete'][$i])) {
                    $datefound = true;
                    break;
                }
            }

            if (!$datefound) {
                $errors['datetimeknown'] = get_string('validation:needatleastonedate', 'block_learning_group');
            }
        }

        return $errors;
    }
}
