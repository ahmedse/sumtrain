<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_sumtrain_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $sumtrain_SHOWRESULTS, $sumtrain_PUBLISH, $sumtrain_DISPLAY, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('sumtrainname', 'sumtrain'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description', 'sumtrain'));

        $mform->addElement('select', 'display', get_string("displaymode","sumtrain"), $sumtrain_DISPLAY);

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'optionhdr', get_string('options', 'sumtrain'));

        $mform->addElement('selectyesno', 'allowupdate', get_string("allowupdate", "sumtrain"));

        $mform->addElement('selectyesno', 'allowmultiple', get_string('allowmultiple', 'sumtrain'));
        if ($this->_instance) {
            if ($DB->count_records('sumtrain_answers', array('sumtrainid' => $this->_instance)) > 0) {
                // Prevent user from toggeling the number of allowed answers once there are submissions.
                $mform->freeze('allowmultiple');
            }
        }

        $mform->addElement('selectyesno', 'limitanswers', get_string('limitanswers', 'sumtrain'));
        $mform->addHelpButton('limitanswers', 'limitanswers', 'sumtrain');

        $mform->addElement('selectyesno', 'showavailable', get_string('showavailable', 'sumtrain'));
        $mform->addHelpButton('showavailable', 'showavailable', 'sumtrain');
        $mform->hideIf('showavailable', 'limitanswers', 'eq', 0);

        // by ahmed -------------------------------------------------------------------------------
        $mform->addElement('header', 'optionhdr', get_string('options', 'sumtrain'));
        $sql= "select distinctrow i.instituteid, i.institute
            from mdl_summtrain_institute as i inner join mdl_summtrain_session as s
            on i.institute= s.institute 
            and s.year= '2023'
            and s.student_groups= 'Cohort 2022-2027'";
        $options= $DB->get_records_sql_menu($sql);

        $mform->addElement('select', 'institute', get_string('institute', 'sumtrain'), $options);
        $mform->setType('institute', PARAM_INT);
        $mform->addRule('institute', get_string('required', 'sumtrain'), 'required', null, 'client');
        $mform->addHelpButton('institute', 'institute_help', 'sumtrain');

        // by ahmed [end] -------------------------------------------------------------------------------

        $repeatarray = array();
        $repeatarray[] = $mform->createElement('text', 'option', get_string('optionno', 'sumtrain'));
        $repeatarray[] = $mform->createElement('text', 'limit', get_string('limitno', 'sumtrain'));
        $repeatarray[] = $mform->createElement('hidden', 'optionid', 0);

        if ($this->_instance){
            $repeatno = $DB->count_records('sumtrain_options', array('sumtrainid'=>$this->_instance));
            $repeatno += 2;
        } else {
            $repeatno = 5;
        }

        $repeateloptions = array();
        $repeateloptions['limit']['default'] = 0;
        $repeateloptions['limit']['hideif'] = array('limitanswers', 'eq', 0);
        $repeateloptions['limit']['rule'] = 'numeric';
        $repeateloptions['limit']['type'] = PARAM_INT;

        $repeateloptions['option']['helpbutton'] = array('sumtrainoptions', 'sumtrain');
        $mform->setType('option', PARAM_CLEANHTML);

        $mform->setType('optionid', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatno,
                    $repeateloptions, 'option_repeats', 'option_add_fields', 3, null, true);

        // Make the first option required
        if ($mform->elementExists('option[0]')) {
            $mform->addRule('option[0]', get_string('atleastoneoption', 'sumtrain'), 'required', null, 'client');
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'availabilityhdr', get_string('availability'));
        $mform->addElement('date_time_selector', 'timeopen', get_string("sumtrainopen", "sumtrain"),
            array('optional' => true));

        $mform->addElement('date_time_selector', 'timeclose', get_string("sumtrainclose", "sumtrain"),
            array('optional' => true));

        $mform->addElement('advcheckbox', 'showpreview', get_string('showpreview', 'sumtrain'));
        $mform->addHelpButton('showpreview', 'showpreview', 'sumtrain');
        $mform->disabledIf('showpreview', 'timeopen[enabled]');

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'resultshdr', get_string('results', 'sumtrain'));

        $mform->addElement('select', 'showresults', get_string("publish", "sumtrain"), $sumtrain_SHOWRESULTS);

        $mform->addElement('select', 'publish', get_string("privacy", "sumtrain"), $sumtrain_PUBLISH);
        $mform->hideIf('publish', 'showresults', 'eq', 0);

        $mform->addElement('selectyesno', 'showunanswered', get_string("showunanswered", "sumtrain"));

        $mform->addElement('selectyesno', 'includeinactive', get_string('includeinactive', 'sumtrain'));
        $mform->setDefault('includeinactive', 0);

//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values){
        global $DB;
        if (!empty($this->_instance) && ($options = $DB->get_records_menu('sumtrain_options',array('sumtrainid'=>$this->_instance), 'id', 'id,text'))
               && ($options2 = $DB->get_records_menu('sumtrain_options', array('sumtrainid'=>$this->_instance), 'id', 'id,maxanswers')) ) {
            $sumtrainids=array_keys($options);
            $options=array_values($options);
            $options2=array_values($options2);

            foreach (array_keys($options) as $key){
                $default_values['option['.$key.']'] = $options[$key];
                $default_values['limit['.$key.']'] = $options2[$key];
                $default_values['optionid['.$key.']'] = $sumtrainids[$key];
            }

        }

    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Set up completion section even if checkbox is not ticked
        if (!empty($data->completionunlocked)) {
            if (empty($data->completionsubmit)) {
                $data->completionsubmit = 0;
            }
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] && $data['timeclose'] &&
                $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'sumtrain');
        }

        return $errors;
    }

    function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'sumtrain'));
        // Enable this completion rule by default.
        $mform->setDefault('completionsubmit', 1);
        return array('completionsubmit');
    }

    function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
}

