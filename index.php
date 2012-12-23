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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package   mod_organizer
 * @copyright 2010 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/// Replace organizer with the name of your module and remove this line

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');
require_once('locallib.php');

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', array('id' => $id))) {
    error('Course ID is incorrect');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, 'organizer', 'view all', "index.php?id=$course->id", '');

/// Print the header

$PAGE->set_url('/mod/organizer/index.php', array('id' => $course->id));
$PAGE->navbar->add(get_string("modulenameplural", "organizer"));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);
echo $OUTPUT->header();

/// Get all the appropriate data

if (! $organizers = get_all_instances_in_course('organizer', $course)) {
    echo $OUTPUT->heading(get_string('noorganizers', 'organizer'), 2);
    echo $OUTPUT->continue_button("view.php?id=$course->id");
    echo $OUTPUT->footer();
    die();
}

$table = new html_table();

$table->head  = array();
$table->align = array();

if ($course->format == 'weeks') {
    $table->head[] = get_string('week');
    $table->align[] = 'center';
} else if ($course->format == 'topics') {
    $table->head[] = get_string('topic');
    $table->align[] = 'center';
}

$table->head[] = get_string('name');
$table->align[] = 'left';
$table->head[] = get_string('description');
$table->align[] = 'left';
$table->head[] = get_string('reg_status', 'organizer');
$table->align[] = 'left';
$table->head[] = get_string('grade');
$table->align[] = 'center';


foreach ($organizers as $organizer) {
    if (!$organizer->visible) {
        //Show dimmed if the mod is hidden
        $link = '<a class="dimmed" href="view.php?id='.$organizer->coursemodule.'">'.format_string($organizer->name).'</a>';
    } else {
        //Show normal if the mod is visible
        $link = '<a href="view.php?id='.$organizer->coursemodule.'">'.format_string($organizer->name).'</a>';
    }

    $row = array();
    if ($course->format == 'weeks' or $course->format == 'topics') {
        $row[] = $organizer->section;
    }

    $row[] = $link;
    $row[] = $organizer->intro;

    $cm = get_coursemodule_from_instance('organizer', $organizer->id, $course->id, false, MUST_EXIST);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id, MUST_EXIST);
    if (has_capability('mod/organizer:viewregistrations', $context)) {
        $a = get_counters($organizer);
        if ($organizer->isgrouporganizer) {
            $reg = get_string('mymoodle_registered_group_short', 'organizer', $a);
            $att = get_string('mymoodle_attended_group_short', 'organizer', $a);

            $str = "<p>$reg</p><p>$att</p>";
        } else {
            $reg = get_string('mymoodle_registered_short', 'organizer', $a);
            $att = get_string('mymoodle_attended_short', 'organizer', $a);

            $str = "<p>$reg</p><p>$att</p>";
        }
        $row[] = $str;
        $row[] = '-';
    } else {
        $row[] = organizer_get_overview_student($organizer, true);
        $app = get_last_user_appointment($organizer, null, false);
        if ($app) {
            $row[] = display_grade($organizer, $app->grade);
        } else {
            $row[] = '-';
        }
    }

    $table->data[] = $row;
}

echo $OUTPUT->heading(get_string('modulenameplural', 'organizer'), 2);
echo html_writer::table($table);
echo $OUTPUT->footer();

die;