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
 * Library of interface functions and constants for module organizer
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the organizer specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package   mod_organizer
 * @copyright 2011 Ivan Šakić
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once('slotlib.php');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $organizer An object from the form in mod_form.php
 * @return int The id of the newly inserted organizer record
 */
function organizer_add_instance($organizer) {
    global $DB;

    $organizer->timemodified = time();
    if (isset($organizer->enablefrom) && $organizer->enablefrom == 0) {
        unset($organizer->enablefrom);
    }

    if (isset($organizer->enableuntil) && $organizer->enableuntil == 0) {
        unset($organizer->enableuntil);
    }

    $organizer->id = $DB->insert_record('organizer', $organizer);

    organizer_grade_item_update($organizer);

    return $organizer->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $organizer An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function organizer_update_instance($organizer) {
    global $DB;

    $organizer->id = $organizer->instance;
    $organizer->timemodified = time();

    if (isset($organizer->enablefrom) && $organizer->enablefrom == 0) {
        unset($organizer->enablefrom);
        $DB->execute("UPDATE {organizer} SET enablefrom = NULL WHERE id = :id", array('id' => $organizer->id));
    }

    if (isset($organizer->enableuntil) && $organizer->enableuntil == 0) {
        unset($organizer->enableuntil);
        $DB->execute("UPDATE {organizer} SET enableuntil = NULL WHERE id = :id", array('id' => $organizer->id));
    }

    organizer_grade_item_update($organizer);

    return $DB->update_record('organizer', $organizer);
}

define('DELETE_EVENTS', 1);

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function organizer_delete_instance($id) {
    global $DB;

    if (!$organizer = $DB->get_record('organizer', array('id' => $id))) {
        return false;
    }

    $slots = $DB->get_records('organizer_slots', array('organizerid' => $id));

    foreach ($slots as $slot) {
        $apps = $DB->get_records('organizer_slot_appointments', array('slotid' => $slot->id));

        foreach ($apps as $app) {
            if (DELETE_EVENTS) {
                $DB->delete_records('event', array('id' => $app->eventid));
            }
            $DB->delete_records('organizer_slot_appointments', array('id' => $app->id));
        }

        if (DELETE_EVENTS) {
            $DB->delete_records('event', array('id' => $slot->eventid));
        }
        $DB->delete_records('organizer_slots', array('id' => $slot->id));
    }

    $DB->delete_records('organizer', array('id' => $organizer->id));

    organizer_grade_item_update($organizer);

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function organizer_user_outline($course, $user, $mod, $organizer) {
    $return = new stdClass;
    $return->time = time();
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function organizer_user_complete($course, $user, $mod, $organizer) {
    return false;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in organizer activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function organizer_print_recent_activity($course, $viewfullnames, $timestart) {
    return false; //  True if anything was printed, otherwise false
}

function organizer_get_overview_link($organizer) {
    global $CFG;
    return '<div class="name">' . get_string('modulename', 'organizer') . ': <a title="' . $organizer->name
            . '" href="' . $CFG->wwwroot . '/mod/organizer/view.php?id=' . $organizer->coursemodule . '">'
            . $organizer->name . '</a>: </div>';
}

function organizer_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'organizerheader', get_string('modulenameplural', 'organizer'));
    $mform->addElement('checkbox', 'reset_organizer_all', get_string('resetorganizerall', 'organizer'));
    $mform->addElement('checkbox', 'delete_organizer_grades', 'Delete grades from gradebook');
}

function organizer_reset_userdata($data) {
    global $DB;

    if (!$DB->count_records('organizer', array('course' => $data->courseid))) {
        return array();
    }

    $componentstr = get_string('modulenameplural', 'organizer');
    $status = array();

    $ok = true;
    if ($data->reset_organizer_all) {

        $params = array('courseid' => $data->courseid);

        $slotquery = "SELECT s.*
                    FROM {organizer_slots} s
                    INNER JOIN {organizer} m ON s.organizerid = m.id
                    WHERE m.course = :courseid";

        $appquery = "SELECT a.*
                    FROM {organizer_slot_appointments} a
                    INNER JOIN {organizer_slots} s ON a.slotid = s.id
                    INNER JOIN {organizer} m ON s.organizerid = m.id
                    WHERE m.course = :courseid";

        $slots = $DB->get_records_sql($slotquery, $params);
        $appointments = $DB->get_records_sql($appquery, $params);

        foreach ($slots as $slot) {
            $DB->delete_records('event', array('id' => $slot->eventid));
            $ok &= $DB->delete_records('organizer_slots', array('id' => $slot->id));
        }

        foreach ($appointments as $appointment) {
            $DB->delete_records('event', array('id' => $appointment->eventid));
            $ok &= $DB->delete_records('organizer_slot_appointments', array('id' => $appointment->id));
        }

    }

    $status[] = array('component' => $componentstr, 'item' => 'Deleting slots, appointments and related events',
            'error' => !$ok);

    if (isset($data->reset_gradebook_grades) && isset($data->delete_organizer_grades)) {
        organizer_reset_gradebook($data->courseid); // not sure if working!
    }

    if ($data->timeshift) {
        $ok = shift_course_mod_dates('organizer', array('enablefrom', 'enableuntil'), $data->timeshift, $data->courseid);

        $status[] = array('component' => $componentstr, 'item' => 'Shifting absolute deadline', 'error' => !$ok);
    }

    return $status;
}

function organizer_reset_gradebook($courseid) { // FIXME !!!
    global $CFG, $DB;

    $params = array('courseid' => $courseid);

    $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
              FROM {organizer} a, {course_modules} cm, {modules} m
             WHERE m.name='organizer' AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid";

    if ($assignments = $DB->get_records_sql($sql, $params)) {
        foreach ($assignments as $assignment) {
            organizer_grade_item_update($assignment, 'reset');
        }
    }
}

function organizer_get_user_grade($organizer, $userid = 0) {
    global $DB;

    $params = array('organizerid' => $organizer->id, 'userid' => $userid);
    if ($userid) {
        $query = "SELECT
                    a.id AS id,
                    a.userid AS userid,
                    a.grade AS rawgrade,
                    s.starttime AS dategraded,
                    s.starttime AS datesubmitted,
                    a.feedback AS feedback
                FROM {organizer_slot_appointments} a
                INNER JOIN {organizer_slots} s ON a.slotid = s.id
                WHERE s.organizerid = :organizerid AND a.userid = :userid
                ORDER BY id DESC";
        $result = reset($DB->get_records_sql($query, $params));
        return array($result->userid => $result);
    } else {
        $query = "SELECT
                a.id AS id,
                a.userid AS userid,
                a.grade AS rawgrade,
                s.starttime AS dategraded,
                s.starttime AS datesubmitted,
                a.feedback AS feedback
            FROM {organizer_slot_appointments} a
            INNER JOIN {organizer_slots} s ON a.slotid = s.id
            WHERE s.organizerid = :organizerid
            ORDER BY id DESC";
        return array(); // unused
    }
}

function organizer_update_grades($organizer, $userid = 0) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($organizer->grade == 0) {
        return organizer_grade_item_update($organizer);
    } else if ($grades = organizer_get_user_grade($organizer, $userid)) {
        foreach ($grades as $key => $value) {
            if ($value->rawgrade == -1) {
                $grades[$key]->rawgrade = null;
            }
        }
        return organizer_grade_item_update($organizer, $grades);
    } else {
        return organizer_grade_item_update($organizer);
    }
}

function organizer_grade_item_update($organizer, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($organizer->courseid)) {
        $organizer->courseid = $organizer->course;
    }

    if (isset($organizer->cmidnumber)) {
        $params = array('itemname' => $organizer->name, 'idnumber' => $organizer->cmidnumber);
    } else {
        $params = array('itemname' => $organizer->name);
    }

    if (isset($organizer->grade) && $organizer->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $organizer->grade;
        $params['grademin'] = 0;
    } else if (isset($organizer->grade) && $organizer->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$organizer->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/organizer', $organizer->courseid, 'mod', 'organizer', $organizer->id, 0, $grades, $params);
}

function display_grade($organizer, $grade) {
    global $DB;
    $nograde = get_string('nograde');
    static $scalegrades = array();   // Cache scales for each organizer - they might have different scales!!

    if ($organizer->grade >= 0) {    // Normal number
        if ($grade == -1 || $grade == null) {
            return $nograde;
        } else {
            return clean_num($grade) . ' / ' . clean_num($organizer->grade);
        }
    } else {    // Scale
        if (empty($scalegrades[$organizer->id])) {
            if ($scale = $DB->get_record('scale', array('id' => -($organizer->grade)))) {
                $scalegrades[$organizer->id] = make_menu_from_list($scale->scale);
            } else {
                return $nograde;
            }
        }
        if (isset($scalegrades[$organizer->id][intval($grade)])) {
            if ($grade == 0 || $grade == null) {
                return $nograde;
            } else {
                return $scalegrades[$organizer->id][intval($grade)];
            }
        }
        return $nograde;
    }
}

function make_grades_menu_organizer($gradingtype) {
    global $DB;

    $grades = array();
    if ($gradingtype < 0) {
        if ($scale = $DB->get_record('scale', array('id' => (-$gradingtype)))) {
            $menu = make_menu_from_list($scale->scale);
            $menu['0'] = get_string('nograde');
            return $menu;
        }
    } else if ($gradingtype > 0) {
        $grades['-1'] = get_string('nograde');
        for ($i = $gradingtype; $i >= 0; $i--) {
            $grades[$i] = clean_num($i) . ' / ' . clean_num($gradingtype);
        }
        return $grades;
    }
    return $grades;
}

function clean_num($num) {
    $pos = strpos($num, '.');
    if ($pos === false) { // it is integer number
        return $num;
    } else { // it is decimal number
        return rtrim(rtrim($num, '0'), '.');
    }
}

function get_last_group_appointment($organizer, $groupid) {
    global $DB;
    $params = array('groupid' => $groupid, 'organizerid' => $organizer->id);
    $groupapps = $DB->get_records_sql("SELECT a.* FROM {organizer_slot_appointments} a
            INNER JOIN {organizer_slots} s ON a.slotid = s.id
            WHERE a.groupid = :groupid AND s.organizerid = :organizerid
            ORDER BY a.id DESC", $params);

    $app = null;

    $appcount = 0;
    $someoneattended = 0;
    foreach ($groupapps as $groupapp) {
        if ($groupapp->groupid == $groupid) {
            $app = $groupapp;
        }
        if (isset($groupapp->attended)) {
            $appcount++;
            if ($groupapp->attended == 1) {
                $someoneattended = 1;
            }
        }
    }

    if ($app) {
        $app->attended = ($appcount == count($groupapps)) ? $someoneattended : null;
    }

    return $app;
}

function get_counters($organizer) {
    global $DB;
    if ($organizer->isgrouporganizer) {
        $params = array('groupingid' => $organizer->groupingid);
        $query = "SELECT {groups}.* FROM {groups}
                INNER JOIN {groupings_groups} ON {groups}.id = {groupings_groups}.groupid
                WHERE {groupings_groups}.groupingid = :groupingid
                ORDER BY {groups}.name ASC";
        $groups = $DB->get_records_sql($query, $params);

        $attended = 0;
        $registered = 0;
        foreach ($groups as $group) {
            $app = get_last_group_appointment($organizer, $group->id);
            if ($app && $app->attended == 1) {
                $attended++;
            } else if ($app && !isset($app->attended)) {
                $registered++;
            }
        }
        $total = count($groups);

        $a = new stdClass();
        $a->registered = $registered;
        $a->attended = $attended;
        $a->total = $total;
    } else {
        $course = $DB->get_record('course', array('id' => $organizer->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('organizer', $organizer->id, $course->id, false, MUST_EXIST);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id, MUST_EXIST);

        $students = get_enrolled_users($context, 'mod/organizer:register');

        $attended = 0;
        $registered = 0;
        foreach ($students as $student) {
            $app = get_last_user_appointment($organizer, $student->id);
            if ($app && $app->attended == 1) {
                $attended++;
            } else if ($app && !isset($app->attended)) {
                $registered++;
            }
        }
        $total = count($students);

        $a = new stdClass();
        $a->registered = $registered;
        $a->attended = $attended;
        $a->total = $total;
    }

    return $a;
}

function organizer_get_overview_teacher($organizer) {
    global $DB, $USER;

    $str = '<div class="assignment overview">';
    $str .= organizer_get_overview_link($organizer);

    $a = get_counters($organizer);

    if ($organizer->isgrouporganizer) {
        $reg = get_string('mymoodle_registered_group', 'organizer', $a);
        $att = get_string('mymoodle_attended_group', 'organizer', $a);

        $str .= "<div class=\"info organizerinfo\">$reg</div><div class=\"info organizerinfo\">$att</div>";
    } else {
        $reg = get_string('mymoodle_registered', 'organizer', $a);
        $att = get_string('mymoodle_attended', 'organizer', $a);

        $str .= "<div class=\"info organizerinfo\">$reg</div><div class=\"info organizerinfo\">$att</div>";
    }

    $now = time();

    $slot = $DB->get_records_sql("SELECT * FROM {organizer_slots} WHERE
            {organizer_slots}.teacherid = {$USER->id} AND
            {organizer_slots}.organizerid = {$organizer->id} AND
            {organizer_slots}.starttime > {$now}
            ORDER BY {organizer_slots}.starttime ASC");

    $nextslot = reset($slot);

    if ($nextslot) {
        $a = new stdClass();
        $a->date = userdate($nextslot->starttime, get_string('fulldatetemplate', 'organizer'));
        $a->time = userdate($nextslot->starttime, get_string('timetemplate', 'organizer'));
        $nextslot = get_string('mymoodle_next_slot', 'organizer', $a);
        $str .= "<div class=\"info organizerinfo\">$nextslot</div>";
    } else {
        $noslots = get_string('mymoodle_no_slots', 'organizer');
        $str .= "<div class=\"info organizerinfo\">$noslots</div>";
    }

    $str .= '</div>';

    return $str;
}

function fetch_group($organizer, $userid = null) {
    global $DB, $USER;

    if ($userid == null) {
        $userid = $USER->id;
    }

    if (is_number($organizer) && $organizer == intval($organizer)) {
        $organizer = $DB->get_record('organizer', array('id' => $organizer));
    }

    return groups_get_group(reset(reset(groups_get_user_groups($organizer->course, $userid))));
}

function organizer_get_overview_student($organizer, $forindex = false) {
    global $DB;

    if (!$forindex) {
        $str = '<div class="assignment overview">';
        $str .= organizer_get_overview_link($organizer);
		$class = "class=\"info organizerinfo\"";
		$element = "div";
    } else {
        $str = '';
		$class = "";
		$element = "p";
    }

    if ($organizer->isgrouporganizer) {
        $group = fetch_group($organizer);
        $app = get_last_user_appointment($organizer);

        if ($app && isset($app->attended) && (int) $app->attended === 1) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));
            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
            $a->groupname = $group->name;
            $completedapp = get_string('mymoodle_completed_app_group', 'organizer', $a) . ($forindex ? '' :
                "<br />(" . get_string('grade') . ": " . display_grade($organizer, $app->grade) . ")");
            if ($app->allownewappointments) {
                $completedapp .= "<br />" . get_string('can_reregister', 'organizer');
            }

            $str .= "<{$element} {$class}>$completedapp</{$element}>";
        } else if ($app && isset($app->attended) && (int) $app->attended === 0) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));

            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
            $a->groupname = $group->name;

            $missedapp = get_string('mymoodle_missed_app_group', 'organizer', $a) . ($forindex ? '' :
                "<br />(" . get_string('grade') . ": " . display_grade($organizer, $app->grade) . ")");
            if ($app->allownewappointments) {
                $missedapp .= "<br />" . get_string('can_reregister', 'organizer');
            }

            $str .= "<{$element} {$class}>$missedapp</{$element}>";

            if (isset($organizer->enableuntil)) {
                $a = new stdClass();
                $a->date = userdate($organizer->enableuntil, get_string('fulldatetemplate', 'organizer'));
                $a->time = userdate($organizer->enableuntil, get_string('timetemplate', 'organizer'));
                if ($organizer->enableuntil > time()) {
                    $orgexpires = get_string('mymoodle_organizer_expires', 'organizer', $a);
                } else {
                    $orgexpires = get_string('mymoodle_organizer_expired', 'organizer', $a);
                }
                $str .= "<{$element} {$class}>$orgexpires</{$element}>";
            }
        } else if ($app && !isset($app->attended)) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));

            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
            $a->groupname = $group->name;

            if (isset($slot->locationlink) && $slot->locationlink != '') {
                $a->location = html_writer::link($slot->locationlink, $slot->location, array('target' => '_blank'));
            } else {
                $a->location = $slot->location;
            }

            if ($slot->starttime > time()) {
                $upcomingapp = get_string('mymoodle_upcoming_app_group', 'organizer', $a);
                $str .= "<{$element} {$class}>$upcomingapp</{$element}>";
            } else {
                $pending = get_string('mymoodle_pending_app_group', 'organizer', $a);
                $str .= "<{$element} {$class}>$pending</{$element}>";
            }
        } else {
            $noregslot = get_string('mymoodle_no_reg_slot', 'organizer');
            $str .= "<{$element} {$class}>$noregslot</{$element}>";

            if (isset($organizer->enableuntil)) {
                $a = new stdClass();
                $a->date = userdate($organizer->enableuntil, get_string('fulldatetemplate', 'organizer'));
                $a->time = userdate($organizer->enableuntil, get_string('timetemplate', 'organizer'));
                if ($organizer->enableuntil > time()) {
                    $orgexpires = get_string('mymoodle_organizer_expires', 'organizer', $a);
                } else {
                    $orgexpires = get_string('mymoodle_organizer_expired', 'organizer', $a);
                }
                $str .= "<{$element} {$class}>$orgexpires</{$element}>";
            }
        }
    } else {
        $app = get_last_user_appointment($organizer);
        if ($app && isset($app->attended) && (int) $app->attended === 1) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));
            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
            $completedapp = get_string('mymoodle_completed_app', 'organizer', $a) . ($forindex ? '' :
                "<br />(" . get_string('grade') . ": " . display_grade($organizer, $app->grade) . ")");
            if ($app->allownewappointments) {
                $completedapp .= "<br />" . get_string('can_reregister', 'organizer');
            }

            $str .= "<{$element} {$class}>$completedapp</{$element}>";
        } else if ($app && isset($app->attended) && (int) $app->attended === 0) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));
            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
            $missedapp = get_string('mymoodle_missed_app', 'organizer', $a) . ($forindex ? '' :
                "<br />(" . get_string('grade') . ": " . display_grade($organizer, $app->grade) . ")");
            if ($app->allownewappointments) {
                $missedapp .= "<br />" . get_string('can_reregister', 'organizer');
            }

            $str .= "<{$element} {$class}>$missedapp</{$element}>";
            if (isset($organizer->enableuntil)) {
                $a = new stdClass();
                $a->date = userdate($organizer->enableuntil, get_string('fulldatetemplate', 'organizer'));
                $a->time = userdate($organizer->enableuntil, get_string('timetemplate', 'organizer'));
                if ($organizer->enableuntil > time()) {
                    $orgexpires = get_string('mymoodle_organizer_expires', 'organizer', $a);
                } else {
                    $orgexpires = get_string('mymoodle_organizer_expired', 'organizer', $a);
                }
                $str .= "<{$element} {$class}>$orgexpires</{$element}>";
            }
        } else if ($app && !isset($app->attended)) {
            $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));

            $a = new stdClass();
            $a->date = userdate($slot->starttime, get_string('fulldatetemplate', 'organizer'));
            $a->time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));

            if (isset($slot->locationlink) && $slot->locationlink != '') {
                $a->location = html_writer::link($slot->locationlink, $slot->location, array('target' => '_blank'));
            } else {
                $a->location = $slot->location;
            }

            if ($slot->starttime > time()) {
                $upcomingapp = get_string('mymoodle_upcoming_app', 'organizer', $a);
                $str .= "<{$element} {$class}>$upcomingapp</{$element}>";
            } else {
                $pending = get_string('mymoodle_pending_app', 'organizer', $a);
                $str .= "<{$element} {$class}>$pending</{$element}>";
            }
        } else {
            $noregslot = get_string('mymoodle_no_reg_slot', 'organizer');
            $str .= "<{$element} {$class}>$noregslot</{$element}>";

            if (isset($organizer->enableuntil)) {
                $a = new stdClass();
                $a->date = userdate($organizer->enableuntil, get_string('fulldatetemplate', 'organizer'));
                $a->time = userdate($organizer->enableuntil, get_string('timetemplate', 'organizer'));
                if ($organizer->enableuntil > time()) {
                    $orgexpires = get_string('mymoodle_organizer_expires', 'organizer', $a);
                } else {
                    $orgexpires = get_string('mymoodle_organizer_expired', 'organizer', $a);
                }
                $str .= "<{$element} {$class}>$orgexpires</{$element}>";
            }
        }
    }

    if (!$forindex) {
        $str .= '</div>';
    }

    return $str;
}

function organizer_print_overview($courses, &$htmlarray) {
    global $USER;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$organizers = get_all_instances_in_courses('organizer', $courses)) {
        return;
    }

    foreach ($organizers as $organizer) {
        if (is_student_in_course($organizer->course, $USER->id)) {
            $str = organizer_get_overview_student($organizer);
        } else {
            $str = organizer_get_overview_teacher($organizer);
        }

        if (empty($htmlarray[$organizer->course]['organizer'])) {
            $htmlarray[$organizer->course]['organizer'] = $str;
        } else {
            $htmlarray[$organizer->course]['organizer'] .= $str;
        }
    }
}

// FIXME replace this one with an alternative over capabilities
function is_student_in_course($courseid, $userid) {
    global $DB;

    $stud = $DB->get_records_sql("SELECT * FROM {role_assignments}
    		INNER JOIN {context} ON {role_assignments}.contextid = {context}.id
    		WHERE {role_assignments}.roleid = 5
    			AND {context}.instanceid = {$courseid}
    			AND {role_assignments}.userid = {$userid}");
    return count($stud) > 0;
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function organizer_cron() {
    require_once('messaging.php');
    global $DB;
    $now = time();

    $success = true;

    $params = array('now' => $now);
    $appsquery = "SELECT a.*, s.teacherid, s.location, s.starttime, s.organizerid FROM {organizer_slot_appointments} a
        INNER JOIN {organizer_slots} s ON a.slotid = s.id WHERE
        s.starttime - s.notificationtime < :now AND
        a.notified = 0";

    $apps = $DB->get_records_sql($appsquery, $params);
    foreach ($apps as $app) {
        $success &= organizer_send_message(intval($app->teacherid), intval($app->userid), $app,
                'appointment_reminder:student');
    }

    if (empty($apps)) {
        $ids = array(0);
    } else {
        $ids = array_keys($apps);
    }
    list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
    $DB->execute("UPDATE {organizer_slot_appointments} a SET a.notified = 1 WHERE a.id $insql", $inparams);

    $organizerconfig = get_config('organizer');

    $time = $organizerconfig->digest + mktime(0, 0, 0, date("m"), date("d"), date("Y"));

    // WATCH FOR IT!
    if (true || ($organizerconfig->digest != 'never' && abs(time() - $time) < 15)) {
        $params['tomorrowstart'] = mktime(0, 0, 0, date("m"), date("d") + 1, date("Y"));
        $params['tomorrowend'] = mktime(0, 0, 0, date("m"), date("d") + 2, date("Y"));

        $slotsquery = "SELECT DISTINCT s.teacherid FROM {organizer_slots} s
                WHERE s.starttime >= :tomorrowstart AND
                s.starttime < :tomorrowend AND
                s.notified = 0";

        $teacherids = $DB->get_fieldset_sql($slotsquery, $params);

        if (empty($teacherids)) {
            $teacherids = array(0);
        }

        list($insql, $inparams) = $DB->get_in_or_equal($teacherids, SQL_PARAMS_NAMED);

        $slotsquery = "SELECT *
            FROM {organizer_slots} s
            WHERE s.starttime >= :tomorrowstart AND
            s.starttime < :tomorrowend AND
            s.notified = 0 AND
            s.teacherid $insql";

        $params = array_merge($params, $inparams);

        $slots = $DB->get_records_sql($slotsquery, $params);

        foreach ($teacherids as $teacherid) {
            $digest = '';

            $found = false;
            foreach ($slots as $slot) {
                if ($slot->teacherid == $teacherid) {
                    $date = userdate($slot->starttime, get_string('datetemplate', 'organizer'));
                    $time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
                    $digest .= "$time @ $slot->location\n";
                    $found = true;
                }
            }

            if (empty($slots)) {
                $ids = array(0);
            } else {
                $ids = array_keys($slots);
            }

            if ($found) {
                $success &= $thissuccess = organizer_send_message(intval($teacherid), intval($teacherid), reset($slots),
                        'appointment_reminder:teacher', $digest);

                if ($thissuccess) {
                    list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                    $inparams['teacherid'] = $teacherid;
                    $DB->execute("UPDATE {organizer_slots} s SET s.notified = 1 WHERE s.teacherid = :teacherid AND s.id $insql",
                                $inparams);
                }
            }

        }
    }

    return $success;
}

function create_digest($teacherid) {
    require_once('messaging.php');
    global $DB;
    $now = time();

    $success = true;

    $params = array('now' => $now, 'teacherid' => $teacherid);

    $slotsquery = "SELECT * FROM {organizer_slots} s
            WHERE s.starttime - s.notificationtime < :now AND
            s.notified = 0 AND s.teacherid = :teacherid";

    $digest = '';

    $slots = $DB->get_records_sql($slotsquery, $params);
    foreach ($slots as $slot) {
        if (isset($slot)) {
            $date = userdate($slot->starttime, get_string('datetemplate', 'organizer'));
            $time = userdate($slot->starttime, get_string('timetemplate', 'organizer'));
        }
        $digest .= "$date, $time @ $slot->location; ";
        $slot->notified = 1;
        $DB->update_record('organizer_slots', $slot);
    }

    $success = organizer_send_message(intval($slot->teacherid), intval($slot->teacherid), $slot,
            'appointment_reminder:teacher:digest', $digest);

    return $success;
}

/**
 * Must return an array of users who are participants for a given instance
 * of organizer. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @param int $organizerid ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function organizer_get_participants($organizerid) {
    return false;
}

/**
 * This function returns if a scale is being used by one organizer
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $organizerid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function organizer_scale_used($organizerid, $scaleid) {
    $return = false;

    return $return;
}

/**
 * Checks if scale is being used by any instance of organizer.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any organizer
 */
function organizer_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('organizer', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function organizer_uninstall() {
    return true;
}

function organizer_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
                return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}