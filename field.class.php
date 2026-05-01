<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Autocomplete profile field
 *
 * @package    profilefield_autocomplete
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_autocomplete extends profile_field_base {
    /**
     * @var array $keyvaluemap Map of option keys to values.
     */
    public $keyvaluemap = [];

    /** @var array $options */
    public $options;

    /** @var array $datakey */
    public $datakey = [];

    /** @var bool $multiple */
    public $multiple;

    /**
     * Constructor method.
     *
     * Pulls out the options for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     * @param object $fielddata
     * @throws coding_exception
     * @throws dml_exception
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);

        // Param 1 for menu type is the options.
        $this->options = [];
        if (!empty($this->field->required)) {
            $this->options[''] = get_string('choose') . '...';
        }
        $parsed = \profilefield_autocomplete\options::parse($this->field->param1 ?? '');
        foreach ($parsed as ['key' => $key, 'label' => $label]) {
            $this->options[$key] = format_string($label, true, ['context' => context_system::instance()]);
            $this->keyvaluemap[$key] = $label;
        }

        if (isset($this->field->param2)) {
            $this->multiple = $this->field->param2 == 1;
        }

        // Set the data key (for display in form, must be value(s)).
        if ($this->data !== null) {
            $datavals = explode(', ', $this->data);
            foreach ($datavals as $val) {
                // Always use the value as the key for the autocomplete input.
                $this->datakey[] = $val;
            }
        }
    }

    /**
     * Returns the display value for the profile field (for user profile page, etc).
     *
     * @return string
     */
    public function display_data() {
        if (empty($this->data)) {
            return '';
        }
        $datavals = explode(', ', $this->data);
        $labels = [];
        foreach ($datavals as $val) {
            if (isset($this->keyvaluemap[$val])) {
                $labels[] = $this->keyvaluemap[$val];
            } else {
                // Fallback for legacy/unknown values.
                $labels[] = $val;
            }
        }
        return implode(', ', $labels);
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     *
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_add($mform) {
        $mform->addElement('autocomplete', $this->inputname, format_string($this->field->name), $this->options, [
            'multiple' => $this->multiple,
        ]);
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method.
     *
     * @param moodleform $mform Moodle form instance
     */
    public function edit_field_set_default($mform) {
        $key = $this->field->defaultdata;
        // Default can be a value or a key.
        if (isset($this->keyvaluemap) && ($k = array_search($key, $this->keyvaluemap)) !== false) {
            $defaultkey = $k;
        } else if (isset($this->options[$key])) {
            $defaultkey = $key;
        } else {
            $defaultkey = '';
        }
        $mform->setDefault($this->inputname, $defaultkey);
    }

    /**
     * The data from the form returns the key.
     *
     * This should be converted to the respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param mixed $data The key returned from the select input in the form
     * @param stdClass $datarecord The object that will be used to save the record
     * @return mixed Data or null
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        // If the field is not multiple, create an array with the unique value.
        if (!is_array($data)) {
            // Attempt to split by comma if string contains commas.
            if (is_string($data) && strpos($data, ',') !== false) {
                $data = array_map('trim', explode(',', $data));
            } else {
                $data = [$data];
            }
        }
        $values = [];
        foreach ($data as $option) {
            // Option should be the value (first part before ';').
            if (!array_key_exists($option, $this->options)) {
                // Try to match by label (for legacy/webservice input).
                $found = array_search($option, $this->keyvaluemap);
                if ($found !== false) {
                    $values[] = $found;
                } else {
                    return null;
                }
            } else {
                $values[] = $option;
            }
        }
        // Convert values into string to store it in database.
        return implode(', ', $values);
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     *
     * Overwrites the base class method.
     *
     * @param stdClass $user User object.
     */
    public function edit_load_user_data($user) {
        $user->{$this->inputname} = $this->datakey;
    }

    /**
     * HardFreeze the field if locked.
     *
     * @param moodleform $mform instance of the moodleform class
     * @throws coding_exception
     * @throws dml_exception
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }

        if ($this->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, format_string($this->datakey));
        }
    }

    /**
     * Convert external data (csv file) from value to key for processing later by edit_save_data_preprocess
     *
     * @param string|array $value one of the values in menu options.
     * @return int options key for the menu
     */
    public function convert_external_data($value) {
        if (is_array($value)) {
            $retval = [];
            foreach ($value as $item) {
                if (array_key_exists($item, $this->options)) {
                    $retval[] = $item;
                } else if (($val = array_search($item, $this->keyvaluemap)) !== false) {
                    $retval[] = $val;
                }
            }
            $retval = !empty($retval) ? $retval : false;
        } else {
            if (array_key_exists($value, $this->options)) {
                $retval = $value;
            } else {
                $retval = array_search($value, $this->keyvaluemap);
            }
        }
        // If value is not found in options then return null, so that it can be handled
        // later by edit_save_data_preprocess.
        if ($retval === false) {
            $retval = null;
        }
        return $retval;
    }

    /**
     * Return the field type and null properties.
     * This will be used for validating the data submitted by a user.
     *
     * @return array the param type and null property
     * @since Moodle 3.2
     */
    public function get_field_properties() {
        return [PARAM_TEXT, NULL_NOT_ALLOWED];
    }
}
