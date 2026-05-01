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

/**
 * Helper for parsing autocomplete profile field options.
 *
 * @package    profilefield_autocomplete
 * @copyright  2026 Catalyst IT Australia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options {
    /**
     * Parse a param1 options string into a key => label map.
     *
     * Each line is either a plain value or a "key;label" pair.
     * Returns an array of ['key' => string, 'label' => string] entries,
     * preserving order and skipping blank lines.
     *
     * @param string $param1 Raw options string (newline-separated).
     * @return array[] Array of ['key' => string, 'label' => string].
     */
    public static function parse(string $param1): array {
        $param1 = str_replace("\r", '', $param1);
        $lines = explode("\n", $param1);
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, ';') !== false) {
                $parts = explode(';', $line, 2);
                $parsed[] = ['key' => trim($parts[0]), 'label' => trim($parts[1])];
            } else {
                $parsed[] = ['key' => $line, 'label' => $line];
            }
        }
        return $parsed;
    }
}
