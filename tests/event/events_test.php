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
 * Tests for availability report events.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_availability\event;

use advanced_testcase;
use context_course;
use context_system;
use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for availability report events.
 *
 * @package   report_availability
 * @copyright eWallah.net
 * @author    Renaat Debleu <info@eWallah.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(report_viewed::class)]
final class events_test extends advanced_testcase {
    /**
     * Setup testcase.
     */
    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Test the report viewed event.
     *
     * It's not possible to use the moodle API to simulate the viewing of log report, so here we
     * simply create the event and trigger it.
     */
    public function test_report_viewed(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $event = \report_availability\event\report_viewed::create(['context' => $context]);
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $event = reset($events);

        $this->assertInstanceOf('\report_availability\event\report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/report/availability/index.php', ['course' => $course->id]);
        $this->assertEquals($url, $event->get_url());
        $this->assertEquals('Availability report viewed', $event->get_name());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test invalid context.
     */
    public function test_invalid_context(): void {
        $context = context_system::instance();
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage('Coding error detected');
        \report_availability\event\report_viewed::create(['context' => $context]);
    }

    /**
     * Test dbdir.
     */
    public function test_dbdir(): void {
        global $CFG;
        require_once($CFG->dirroot . '/report/availability/db/access.php');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $context = context_course::instance($course->id);
        $student = $gen->create_user();
        $this->assertFalse(has_capability('report/availability:view', $context, $student->id));
        $student = $gen->create_and_enrol($course, 'student');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $this->assertFalse(has_capability('report/availability:view', $context, $student->id));
        $this->assertTrue(has_capability('report/availability:view', $context, $teacher->id));
    }

    /**
     * Test invalid context.
     */
    public function test_invalid_context_sent(): void {
        $context = context_system::instance();
        $this->expectException('moodle_exception');
        $this->expectExceptionMessage('Coding error detected');
        \report_availability\event\report_viewed::create(['context' => $context]);
    }
}
