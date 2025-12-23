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
 * Course table.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_availability;

use context_course;
use core\output\html_writer;
use core\url;
use core_availability\info_module;
use core_table\sql_table;
use core_user\fields;
use stdClass;

/**
 * Course table.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetable extends sql_table {
    /** @var array All course modules. */
    private array $modules = [];

    /** @var int Total count of course modules. */
    private int $cntactivities = 0;

    /** @var int Current course id. */
    private readonly int $courseid;

    /** @var int Counter for visible modules. */
    private int $counter = 0;

    /** @var array identity fields. */
    private array $identityfields = [];

    /**
     * Constructor.
     *
     * @param course $course
     * @param int $currentgroup
     * @param string $download
     */
    public function __construct($course, $currentgroup, $download) {
        parent::__construct('availability');
        $this->courseid = $course->id;
        $this->download = ($download != '');

        // Define the base URL with parameters.
        $params = [
            'courseid' => $course->id,
            'group' => $currentgroup,
            'page' => optional_param('page', 0, PARAM_INT),
            'items' => optional_param('items', 25, PARAM_INT),
            'tifirst' => optional_param('tifirst', '', PARAM_ALPHA),
            'tilast' => optional_param('tilast', '', PARAM_ALPHA),
        ];
        $this->define_baseurl(new url('/report/availability/index.php', $params));

        // Build the SQL query parts.
        [$eparams, $fields, $from, $where, $identityfields] = $this->build_sql_parts($course, $currentgroup);
        $this->identityfields = $identityfields;
        $this->set_sql($fields, $from, $where, $eparams);
        $columns = [];
        $headers = [];

        // Include a user picture column if not in download mode.
        if (!$this->download) {
            $columns[] = 'userpicture';
            $headers[] = '';
            $this->column_nosort[] = 'userpicture';
        }

        // Add the full name column.
        $columns[] = 'fullname';
        $headers[] = get_string('fullname');

        foreach ($identityfields as $field) {
            $columns[] = $field;
            $headers[] = get_string($field);
        }

        // Process modules from modinfo.
        $modinfo = get_fast_modinfo($course);
        $i = 0;
        $seskey = sesskey();
        foreach ($modinfo->get_cms() as $cm) {
            $colname = "mod_{$i}";
            $columns[] = $colname;
            $this->column_nosort[] = $colname;
            $editurl = new url('/course/mod.php', ['update' => $cm->id, 'return' => true, 'sesskey' => $seskey]);
            $url = html_writer::link($editurl, shorten_text(format_string($cm->name, true)));
            $tag = html_writer::tag('span', $url, ['class' => 'rotated-text']);
            $headers[] = html_writer::tag('div', $tag, ['class' => 'rotated-text-container']);
            $this->modules[] = $cm;
            $i++;
        }

        $this->cntactivities = $i;

        // Add the progress column.
        $columns[] = 'progress';
        $headers[] = get_string('available', 'report_availability');
        $this->column_nosort[] = 'progress';

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->column_class('progress', 'text-end');
        $this->initialbars(true);
        $this->sortable(true, 'firstname', SORT_ASC);
    }

    /**
     * Format each row for display.
     *
     * @param array|object $row row of data from db.
     * @return array Formatted row for the table.
     */
    public function format_row($row) {
        global $OUTPUT;
        $arr = [];
        foreach (array_keys($this->columns) as $column) {
            if (preg_match('/mod_([\d]+)/', (string) $column, $matches)) {
                $field = intval($matches[1]);
                $formatted = self::pix_icon($field, $row->id);
            } else if (in_array($column, $this->identityfields)) {
                $formatted = $row->$column;
            } else if ($column === 'userpicture') {
                $formatted = $OUTPUT->user_picture($row);
            } else if ($column === 'progress') {
                $formatted = $this->counter . '/' . $this->cntactivities;
                $this->counter = 0;
            } else {
                $name = "col_{$column}";
                $formatted = method_exists($this, $name) ? $this->$name($row) : $this->other_cols($column, $row);
            }

            $arr[$column] = $formatted;
        }

        return $arr;
    }

    /**
     * Build sql.
     *
     * @param stdClass $course
     * @param int $groupid
     */
    private function build_sql_parts($course, $groupid): array {
        $context = context_course::instance($course->id);

        [$esql, $eparams] = get_enrolled_sql($context, '', $groupid);
        $p = explode(PHP_EOL, (string) $esql);

        // Extract prefix.
        $pre = strstr(str_replace('SELECT DISTINCT ', '', $p[0]), '.', true);

        // Identity fields.
        $identityfields = fields::get_identity_fields($context);
        if ($identityfields) {
            // Remove custom profile fields.
            $removefields = array_column(profile_get_custom_fields(), 'shortname');
            $identityfields = array_diff($identityfields, $removefields);
        } else {
            $identityfields = [];
        }

        // Fields for user picture.
        $fields = fields::for_userpic()->including(...$identityfields)->get_sql($pre, false, '', '', false)->selects;

        // Extract from.
        $p[1] = str_replace('FROM ', '', $p[1]);

        // Extract where.
        $x = count($p) - 1;
        $where = str_replace('WHERE ', '', $p[$x]);

        // Remove select/where and keep rest.
        unset($p[$x]);
        unset($p[0]);
        $from = implode(PHP_EOL, $p);
        return [$eparams, $fields, $from, $where, $identityfields];
    }

    /**
     * Pix icon.
     *
     * @param int $modid
     * @param int $userid
     * @param string $txt
     */
    private function pix_icon(int $modid, int $userid, string $txt = ''): string {
        global $OUTPUT;
        $available = info_module::is_user_visible($this->modules[$modid]->id, $userid);
        $condition = $available ? 'correct' : 'incorrect';
        if ($available) {
            $this->counter++;
        }

        return $this->is_downloading() ? $available : $OUTPUT->pix_icon('i/grade_' . $condition, $txt);
    }
}
