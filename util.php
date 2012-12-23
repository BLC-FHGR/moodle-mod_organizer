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

function get_appointment_status($app) {
    global $DB;

    if (is_number($app) && $app == intval($app)) {
        $app = $DB->get_record('organizer_slot_appointmentss', array('id' => $app));
    }
    if (!$app) {
        return 0;
    }

    $slot = $DB->get_record('organizer_slots', array('id' => $app->slotid));

    $evaluated = isset($app->attended);
    $attended = $evaluated && ((int)$app->attended === 1);
    $pending = !$evaluated && $slot->stattime < time();
    $reapp = $app->allownewappointments;

    return ($evaluated) & ($attended << 1) & ($pending << 2) & ($reapp << 3);
}

define('APP_STATUS_EVALUATED', 1);
define('APP_STATUS_PENDING', 2);
define('APP_STATUS_ATTENDED', 4);
define('APP_STATUS_REAPPOINTMENT_ALLOWED', 8);

define('APP_STATUS_INVALID', 0);
define('APP_STATUS_ATTENDED_REAPP', APP_STATUS_ATTENDED & APP_STATUS_REAPPOINTMENT_ALLOWED);
define('APP_STATUS_REGISTERED', APP_STATUS_PENDING);
define('APP_STATUS_NOT_ATTENDED', 4);
define('APP_STATUS_NOT_ATTENDED_REAPP', 5);
define('APP_STATUS_NOT_REGISTERED', 6);

function check_appointment_status($app, $status) {
    return $status & get_appointment_status($app);
}