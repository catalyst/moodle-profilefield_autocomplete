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
 * Autocomplete profile field definition.
 *
 * @package    profilefield_autocomplete
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class profile_define_autocomplete
 */
class profile_define_autocomplete extends profile_define_base {
    /**
     * Adds elements to the form for creating/editing this type of profile field.
     *
     * @param moodleform $form
     * @throws coding_exception
     */
    public function define_form_specific($form) {
        // Param 1 for menu type contains the options.
        $form->addElement('textarea', 'param1', get_string('profilemenuoptions', 'admin'), ['rows' => 6, 'cols' => 40]);
        $form->setType('param1', PARAM_TEXT);

        // Add help for key;value format.
        $form->addElement('static', 'param1_help', '', get_string('profilemenuoptions_help', 'profilefield_autocomplete'));

        // Default data.
        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), 'size="50"');
        $form->setType('defaultdata', PARAM_TEXT);

        $form->addElement('advcheckbox', 'param2', get_string('multiple', 'profilefield_autocomplete'));
    }

    /**
     * Validates data for the profile field.
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function define_validate_specific($data, $files) {
        $err = [];

        $data = (object) $data;

        $parsedoptions = [];
        foreach (\profilefield_autocomplete\options::parse($data->param1) as ['key' => $key, 'label' => $label]) {
            $parsedoptions[$key] = $label;
        }

        // Check that we have at least 2 options.
        if (count($parsedoptions) < 2) {
            $err['param1'] = get_string('profilemenutoofewoptions', 'admin');
        } else if (!empty($data->defaultdata)) {
            // Check the default data exists in the options (either as key or value).
            $defaultinlabels = in_array($data->defaultdata, $parsedoptions);
            $defaultinkeys = in_array($data->defaultdata, array_keys($parsedoptions));
            if (!$defaultinlabels && !$defaultinkeys) {
                $err['defaultdata'] = get_string('profilemenudefaultnotinoptions', 'admin');
            }
        }
        return $err;
    }

    /**
     * Processes data before it is saved.
     * @param array|stdClass $data
     * @return array|stdClass
     */
    public function define_save_preprocess($data) {
        $data->param1 = str_replace("\r", '', $data->param1);

        return $data;
    }
}
