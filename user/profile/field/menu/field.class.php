<?php

class profile_field_menu extends profile_field_base {
    var $options;
    var $datakey;

    /**
     * Encode data method to convert menu option to custom field data value
     * @param  mixed $data the raw custom field data menu option value
     * @return mixed the converted data value
     * RL EDIT: BJB121219 ELIS-8124
     */
    static function encode_data($data) {
        //return htmlspecialchars($data);
        return $data;
    }

    /**
     * Decode data method to convert custom field data value to menu option
     * @param  mixed $data the custom field data value
     * @return mixed the custom field value converted back to menu option value
     * RL EDIT: BJB121219 ELIS-8124
     */
    static function decode_data($data) {
        //return htmlspecialchars_decode($data);
        return $data;
    }

    /**
     * Constructor method.
     * Pulls out the options for the menu from the database and sets the
     * the corresponding key for the data if it exists
     */
    function profile_field_menu($fieldid=0, $userid=0) {
        //first call parent constructor
        $this->profile_field_base($fieldid, $userid);

        /// Param 1 for menu type is the options
        $options = explode("\n", $this->field->param1);
        $this->options = array();
        if ($this->field->required){
            $this->options[''] = get_string('choose').'...';
        }
        foreach($options as $key => $option) {
            //$this->options[$key] = format_string($option);//multilang formatting
            // RL EDIT: BJB121219 ELIS-8124
            $this->options[self::encode_data($option)] = format_string($option); // multilang formatting w/ filters
            // BJB110906: ELIS-3099, MDL-16764, ELIS-6724
        }

        /// Set the data key
        if ($this->data !== NULL) {
            //$this->datakey = (int)array_search($this->data, $this->options);
            // RL EDIT: BJB121219 ELIS-8124
            $this->data = self::encode_data(self::decode_data($this->data)); // required for default
            $this->datakey = '';
            foreach ($this->options as $key => $val) {
                if ($this->data == $key || $this->data == $val) {
                    $this->data = $key;
                    $this->datakey = $key;
                    break;
                }
            }
            //error_log("/user/profile/field/menu/field.class.php::profile_field_menu(): data: {$this->data} => datakey: {$this->datakey}");
            // BJB110906: ELIS-3099, MDL-16764, ELIS-6724
        }
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param   object   moodleform instance
     */
    function edit_field_add($mform) {
        $mform->addElement('select', $this->inputname, format_string($this->field->name), $this->options);
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method
     */
    function edit_field_set_default($mform) {
//        if (FALSE !==array_search($this->field->defaultdata, $this->options)){
//            $defaultkey = (int)array_search($this->field->defaultdata, $this->options);
//        } else {
        // RL EDIT: BJB121219 ELIS-8124
        $defaultkey = '';
        $check = self::encode_data($this->field->defaultdata);
        foreach ($this->options as $key => $val) {
            if ($check == $key) {
                $defaultkey = $key;
                break;
            }
        }
        $mform->setDefault($this->inputname, $defaultkey);
    }

    /**
     * The data from the form returns the key. This should be converted to the
     * respective option string to be saved in database
     * Overwrites base class accessor method
     * @param   mixed    $data - the key returned from the select input in the form
     * @param   stdClass $datarecord The object that will be used to save the record
     */
    function edit_save_data_preprocess($data, $datarecord) {
        //return isset($this->options[$data]) ? $this->options[$data] : NULL;
        return isset($this->options[$data]) ? $data : NULL;
        // BJB110906: ELIS-3099, MDL-16764, ELIS-6724
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     * Overwrites the base class method
     * @param   object   user object
     */
    function edit_load_user_data($user) {
        $user->{$this->inputname} = $this->datakey;
    }

    /**
     * HardFreeze the field if locked.
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            // RL EDIT: BJB121219 ELIS-8124
            $val = format_string(self::decode_data($this->datakey));
            $mform->setConstant($this->inputname, $val);
        }
    }

    /**
     * Display the data for this field
     * @return string the formatted custom field data value (i.e. thru multi-lang filters, etc.)
     * RL EDIT: BJB121219 ELIS-8124
     */
    function display_data() {
        return format_string(self::decode_data($this->data));
    }

	
    /**
     * Convert external data (csv file) from value to key for processing later
     * by edit_save_data_preprocess
     *
     * @param string $value one of the values in menu options.
     * @return int options key for the menu
     */
    function convert_external_data($value) {
        $retval = array_search($value, $this->options);

        // If value is not found in options then return null, so that it can be handled
        // later by edit_save_data_preprocess
        if ($retval === false) {
            $retval = null;
        }
        return $retval;
    }
}


