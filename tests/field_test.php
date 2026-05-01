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

namespace profilefield_autocomplete;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/field/autocomplete/field.class.php');

/**
 * Unit tests for the autocomplete profile field.
 *
 * @package    profilefield_autocomplete
 * @copyright  2026 Catalyst IT Australia
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \profile_field_autocomplete
 */
final class field_test extends advanced_testcase {
    /**
     * Tests the field when options are plain values (one per line).
     */
    public function test_field_plain_values(): void {
        $this->resetAfterTest();

        $param1 = "apple\nbanana\norange";

        $fielddata = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'autocomplete',
            'shortname' => 'testfield',
            'name' => 'Test Field',
            'param1' => $param1,
            'param2' => '0',
        ]);

        // Config: options and keyvaluemap are populated correctly.
        $field = new \profile_field_autocomplete(0, 0, $fielddata);

        $this->assertArrayHasKey('apple', $field->options);
        $this->assertArrayHasKey('banana', $field->options);
        $this->assertArrayHasKey('orange', $field->options);

        // For plain values the stored key and display label are the same.
        $this->assertSame('apple', $field->keyvaluemap['apple']);
        $this->assertSame('banana', $field->keyvaluemap['banana']);
        $this->assertSame('orange', $field->keyvaluemap['orange']);

        // Edit_save_data_preprocess: single value.
        $stored = $field->edit_save_data_preprocess('apple', new \stdClass());
        $this->assertSame('apple', $stored);

        // Edit_save_data_preprocess: multiple values as array.
        $stored = $field->edit_save_data_preprocess(['apple', 'orange'], new \stdClass());
        $this->assertSame('apple, orange', $stored);

        // Display_data: single plain value.
        $fielddatawithdata = clone $fielddata;
        $fielddatawithdata->hasuserdata = true;
        $fielddatawithdata->data = 'apple';
        $fielddatawithdata->dataformat = FORMAT_HTML;
        $field2 = new \profile_field_autocomplete(0, 1, $fielddatawithdata);
        $this->assertSame('apple', $field2->display_data());

        // Display_data: multiple plain values stored as a comma-separated string.
        $fielddatawithdata->param2 = '1';
        $fielddatawithdata->data = 'apple, orange';
        $field3 = new \profile_field_autocomplete(0, 1, $fielddatawithdata);
        $this->assertSame('apple, orange', $field3->display_data());

        // DB roundtrip: store a value and read it back.
        $user = $this->getDataGenerator()->create_user();

        $fieldobj = new \profile_field_autocomplete($fielddata->id, $user->id);
        $usernew = (object)['id' => $user->id, 'profile_field_testfield' => 'banana'];
        $fieldobj->edit_save_data($usernew);

        $reloaded = new \profile_field_autocomplete($fielddata->id, $user->id);
        $this->assertSame('banana', $reloaded->data);
        $this->assertSame('banana', $reloaded->display_data());
    }

    /**
     * Tests the field when options are key;value pairs (one per line).
     */
    public function test_field_key_value(): void {
        $this->resetAfterTest();

        $param1 = "a;Apple\nb;Banana\nc;Cherry";

        $fielddata = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'autocomplete',
            'shortname' => 'testfield',
            'name' => 'Test Field',
            'param1' => $param1,
            'param2' => '0',
        ]);

        // Config: options use key as array key and label as value.
        $field = new \profile_field_autocomplete(0, 0, $fielddata);

        $this->assertArrayHasKey('a', $field->options);
        $this->assertSame('Apple', $field->options['a']);
        $this->assertArrayHasKey('b', $field->options);
        $this->assertSame('Banana', $field->options['b']);
        $this->assertArrayHasKey('c', $field->options);
        $this->assertSame('Cherry', $field->options['c']);

        // Keyvaluemap maps key to display label.
        $this->assertSame('Apple', $field->keyvaluemap['a']);
        $this->assertSame('Banana', $field->keyvaluemap['b']);
        $this->assertSame('Cherry', $field->keyvaluemap['c']);

        // Edit_save_data_preprocess: input is the key, stored value is the key.
        $stored = $field->edit_save_data_preprocess('a', new \stdClass());
        $this->assertSame('a', $stored);

        // Edit_save_data_preprocess: multiple keys as array.
        $stored = $field->edit_save_data_preprocess(['a', 'c'], new \stdClass());
        $this->assertSame('a, c', $stored);

        // Display_data: stored key resolves to display label.
        $fielddatawithdata = clone $fielddata;
        $fielddatawithdata->hasuserdata = true;
        $fielddatawithdata->data = 'a';
        $fielddatawithdata->dataformat = FORMAT_HTML;
        $field2 = new \profile_field_autocomplete(0, 1, $fielddatawithdata);
        $this->assertSame('Apple', $field2->display_data());

        // Display_data: multiple stored keys.
        $fielddatawithdata->param2 = '1';
        $fielddatawithdata->data = 'a, c';
        $field3 = new \profile_field_autocomplete(0, 1, $fielddatawithdata);
        $this->assertSame('Apple, Cherry', $field3->display_data());

        // DB roundtrip: store a key and read back with display label.
        $user = $this->getDataGenerator()->create_user();

        $fieldobj = new \profile_field_autocomplete($fielddata->id, $user->id);
        $usernew = (object)['id' => $user->id, 'profile_field_testfield' => 'b'];
        $fieldobj->edit_save_data($usernew);

        $reloaded = new \profile_field_autocomplete($fielddata->id, $user->id);
        $this->assertSame('b', $reloaded->data);
        $this->assertSame('Banana', $reloaded->display_data());
    }

    /**
     * Tests that display_data falls back gracefully when a stored value is no longer in the options mapping.
     */
    public function test_display_data_missing_option_fallback(): void {
        $this->resetAfterTest();

        // Field originally has options a, b, c.
        $fielddata = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'autocomplete',
            'shortname' => 'testfield',
            'name' => 'Test Field',
            'param1' => "a;Apple\nb;Banana\nc;Cherry",
            'param2' => '0',
        ]);

        // Store value 'b' for a user.
        $user = $this->getDataGenerator()->create_user();
        $fieldobj = new \profile_field_autocomplete($fielddata->id, $user->id);
        $usernew = (object)['id' => $user->id, 'profile_field_testfield' => 'b'];
        $fieldobj->edit_save_data($usernew);

        // Now simulate the option 'b' being removed from the field config.
        $fielddata->param1 = "a;Apple\nc;Cherry";

        // Display_data should not error and should fall back to the raw stored value.
        $fielddatawithdata = clone $fielddata;
        $fielddatawithdata->hasuserdata = true;
        $fielddatawithdata->data = 'b';
        $fielddatawithdata->dataformat = FORMAT_HTML;
        $field = new \profile_field_autocomplete(0, $user->id, $fielddatawithdata);
        $this->assertSame('b', $field->display_data());
    }
}
