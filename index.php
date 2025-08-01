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
 * Availability report
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\report_helper;

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');


// Parameters.
$courseid = required_param('courseid', PARAM_INT);
$cntitems = optional_param('items', 25, PARAM_INT);
$currentgroup = optional_param('group', null, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// Variables.
$sreport = get_string('report');

$course = get_course($courseid);
require_login($course);

$context = \context_course::instance($courseid);
require_capability('report/availability:view', $context);

if (is_null($currentgroup)) {
    if ($course->groupmode == SEPARATEGROUPS) {
        foreach (groups_get_all_groups($courseid) as $group) {
            if (groups_is_member($group->id)) {
                $currentgroup = $group->id;
                break;
            }
        }
    }
} else if (!groups_is_member($currentgroup)) {
    require_capability('moodle/site:accessallgroups', $context);
}

if (is_null($currentgroup)) {
    require_capability('moodle/site:accessallgroups', $context);
}

core_php_time_limit::raise();
raise_memory_limit(MEMORY_HUGE);

$event = \report_availability\event\report_viewed::create(['context' => $context]);
$event->trigger();

$table = new \report_availability\coursetable($course, $currentgroup, $download);
$table->is_downloading($download, $sreport, $sreport);
if ($table->is_downloading($download)) {
    $table->out(999, true);
    exit();
} else {
    $url = new moodle_url('/report/availability/index.php', ['courseid' => $courseid, 'group' => $currentgroup]);
    $PAGE->set_course($course);
    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('report');
    $PAGE->set_title($sreport);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_cacheable(false);
    echo $OUTPUT->header();
    report_helper::print_report_selector(get_string('availability', 'report_availability'));
    echo groups_allgroups_course_menu($course, $url, true, $currentgroup);
    echo '<br class="clearer"/><br/>';
    $table->out($cntitems, false);
    echo $OUTPUT->footer($course);
}
