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
 * Tests for availability classes.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_availability;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;

/**
 * Tests for availability classes.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(coursetable::class)]
final class classes_test extends \advanced_testcase {
    /** @var stdClass A student. */
    protected $user;

    /** @var stdClass A test course. */
    protected $course;

    /** @var int A group id. */
    protected $groupid;

    /**
     * Setup testcase.
     */
    public function setUp(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/completionlib.php');
        parent::setUp();
        \availability_completion\condition::wipe_static_cache();
        $this->resetAfterTest(true);
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;

        set_config('enablecompletion', 1);
        $gen = $this->getDataGenerator();
        $this->setAdminUser();
        $gen->create_custom_profile_field(['shortname' => 'longtext', 'name' => 'Long text', 'datatype' => 'textarea']);
        $course = $gen->create_course(['enablecompletion' => 1]);
        $context = \context_course::instance($course->id);
        $group = $gen->create_group(['courseid' => $course->id]);
        $user = $gen->create_and_enrol($course, 'student', ['lastname' => 'Abc', 'longtext' => 'short']);
        groups_add_member($group->id, $user->id);
        $teacher = $gen->create_and_enrol($course, 'editingteacher', ['lastname' => 'Bcd']);
        groups_add_member($group->id, $teacher->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacher->id, $context);
        accesslib_clear_all_caches_for_unit_testing();

        $user2 = $gen->create_and_enrol($course, 'student', ['lastname' => 'Cdf']);
        groups_add_member($group->id, $user2->id);
        $pagegen = $gen->get_plugin_generator('mod_page');
        $pagegen->create_instance(['course' => $course->id, 'name' => 'A', 'completion' => 1]);
        $pagegen->create_instance(['course' => $course->id, 'name' => 'B', 'completion' => 1, 'visible' => false]);
        $notavailable = '{"op":"|","show":true,"c":[{"type":"group","id":' . $group->id . '}]}';

        $quiz = $gen->create_module('quiz', ['name' => "Test quiz 1", 'course' => $course->id, 'completion' => 1]);
        $modcontext = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $DB->set_field('course_modules', 'availability', $notavailable, ['id' => $modcontext->id]);

        $assign = $gen->create_module('assign', ['name' => 'Test assign 1', 'course' => $course->id, 'completion' => 1]);
        $modcontext = get_coursemodule_from_instance('assign', $assign->id, $course->id);
        $DB->set_field('course_modules', 'availability', $notavailable, ['id' => $modcontext->id]);

        for ($i = 1; $i < 30; $i++) {
            $assign = $gen->create_module('assign', ['name' => "Test assign a$i", 'course' => $course->id, 'completion' => 1]);
            $modcontext = get_coursemodule_from_instance('assign', $assign->id, $course->id);
            $DB->set_field('course_modules', 'availability', $notavailable, ['id' => $modcontext->id]);
            if ($i === 20) {
                $DB->set_field('course_modules', 'visible', false, ['id' => $modcontext->id]);
            }
        }
        $gen->create_and_enrol($course, 'student', ['lastname' => 'Efg']);
        $this->course = $course;
        $this->user = $user2;
        $this->groupid = $group->id;
        $this->setAdminUser();
        \phpunit_util::run_all_adhoc_tasks();
        \availability_completion\condition::wipe_static_cache();
        accesslib_clear_all_caches_for_unit_testing();
    }


    /**
     * Test the course table.
     */
    public function test_course_table(): void {
        $gen = $this->getDataGenerator();
        $table = new coursetable($this->course, 0, null);
        ob_start();
        $table->wrap_html_start();
        $table->out(99, false);
        $data = ob_get_clean();
        $this->assertStringContainsString($this->user->firstname, $data);
        $table = new coursetable($this->course, $this->groupid, '');
        ob_start();
        $table->wrap_html_start();
        $table->out(99, false);
        $data = ob_get_clean();
        $this->assertStringContainsString($this->user->firstname, $data);
        // TODO: If teacher is not part of group, then he/she should not have access.
        $teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher->id);
        $table = new coursetable($this->course, $this->groupid, '');
        ob_start();
        $table->wrap_html_start();
        $table->out(99, false);
        $data = ob_get_clean();
        $arr = [
            $this->user->firstname,
            $this->user->lastname,
            '/course/mod.php?update=',
            'tsort=firstname&amp;tdir=3',
            'tsort=lastname',
            'tsort=email',
            '&amp;return=1&amp;sesskey=',
            'aria-expanded="true" aria-controls="availability_r0_c16 availability_r1',
            '<td class="cell c34" id="availability_r98_c34">',
            'id="firstinitial_page-item_A',
            '<div class="rotated-text-container"><span class="rotated-text">',
            'class="userinitials size-35"',
            'header c36 text-end',
            'data-sortby="firstname',
            'Sort by First name ascending',
            '<td class="cell c5" id="availability_r1_c5"><i class="icon fa-regular fa-circle-check text-success',
            'Available',
            'r98_c4',
            'r98_c5',
            'r98_c6',
            'r98_c7',
            'r98_c8',
            'id="availability_r0_c36">31/33',
            'id="availability_r1_c36">33/33',
            'user/profile.php?id=' . $this->user->id,
        ];
        foreach ($arr as $str) {
            $this->assertStringContainsString($str, $data);
        }
        $arr = [
            'data-sortby="c6',
            'data-sortby="progress',
        ];
        foreach ($arr as $str) {
            $this->assertStringNotContainsString($str, $data);
        }
    }

    /**
     * Test the user fields table.
     */
    public function test_user_fields(): void {
        set_config("hiddenuserfields", "email");
        set_config("showuseridentity", "");
        $table = new coursetable($this->course, 0, null);
        ob_start();
        $table->wrap_html_start();
        $table->out(99, false);
        $data = ob_get_clean();
        $this->assertStringNotContainsString('tsort=email', $data);
    }

    /**
     * Test the course table download.
     */
    public function test_course_table_download(): void {
        set_config("hiddenuserfields", "country,city");
        // TODO: add longtext.
        set_config("showuseridentity", "email,address,phone1,phone2,institution,department,idnumber,longtext");
        $table = new coursetable($this->course, 0, 'csv');
        $table->download = 'csv';
        $baseurl = $table->baseurl;
        $this->assertStringContainsString('/report/availability/index.php?courseid=' . $this->course->id, $baseurl->out());
        $this->assertStringContainsString('&amp;group=0&amp;page=0&amp;items=25&amp;tifirst&amp;tilast', $baseurl->out());
        ob_start();
        $table->out(99, true);
        $data = ob_get_clean();
        $this->assertStringContainsString($this->user->firstname, $data);
        $this->assertStringContainsString('First name', $data);
        $this->assertStringContainsString('Last name', $data);
    }

    /**
     * Test the library.
     */
    public function test_library(): void {
        global $CFG;
        require_once($CFG->dirroot . '/report/availability/lib.php');
        $context = \context_course::instance($this->course->id);
        $page = new \moodle_page();
        $page->set_url(new \moodle_url('/course/view.php', ['id' => $this->course->id]));
        $page->set_context($context);
        $tree = new \global_navigation($page);
        report_availability_extend_navigation_course($tree, $this->course, $context);
    }
}
