<?php

require('config.php');
require('mod/attendance/classes/attendance_webservices_handler.php');

function getUsers()
{
    global $DB;

    return $DB->get_records('user');
}

function getUser($userId)
{
    global $DB;

    return $DB->get_record('user', ['id' => $userId]);
}

function getUserCourses($userId)
{
    global $DB;

    $user_enrolments = $DB->get_records('user_enrolments', ['userid' => $userId]);

    $enrols = array();

    foreach ($user_enrolments as $user_enrolment) {
        $enrol = $DB->get_record('enrol', ['id' => $user_enrolment->enrolid]);

        array_push($enrols, $enrol);
    }

    $courses = array();

    foreach ($enrols as $enrolment) {
        $course = $DB->get_record('course', ['id' => $enrolment->courseid]);

        array_push($courses, $course);
    }

    return $courses;
}

function getTodaySessionsForUser($userId)
{
    global $DB;

    $courses = getUserCourses($userId);

    $courses_ids = array();

    foreach ($courses as $course) {
        array_push($courses_ids, $course->id);
    }

    $todaySessions = getTodaySessions();

    $attendance_ids = array();

    foreach ($todaySessions as $todaySession) {
        if (!in_array($todaySession->attendanceid, $attendance_ids)) {
            array_push($attendance_ids, $todaySession->attendanceid);
        }
    }


    $attendances = $DB->get_records_list('attendance', 'id', $attendance_ids);




    $user_attendances_ids = array();

    foreach ($attendances as $attendance) {
        if (in_array($attendance->course, $courses_ids)) {
            array_push($user_attendances_ids, $attendance->id);
        }
    }


    $user_sessions_today = array();

    foreach ($todaySessions as $todaySession) {
        if (in_array($todaySession->attendanceid, $user_attendances_ids)) {
            array_push($user_sessions_today, $todaySession);
        }
    }

    return $user_sessions_today;
}

function getTodaySessions()
{
    global $DB;

    $sessions = $DB->get_records('attendance_sessions');

    $todaySessions = array();

    $startOfDay = strtotime('today midnight');

    $endOfDay = strtotime('tomorrow midnight') - 1;

    if ($sessions) {
        foreach ($sessions as $session) {
            if ($session->sessdate >= $startOfDay && $session->sessdate <= $endOfDay) {
                array_push($todaySessions, $session);
            }
        }
    }

    return $todaySessions;
}


function getStatusesForSession($attendanceid)
{
    global $DB;

    return $DB->get_records('attendance_statuses', ['attendanceid' => $attendanceid]);
}


function getSessionLogsOfUser($userId, $sessionId)
{
    global $DB;

    return $DB->get_record('attendance_log', ['studentid' => $userId, 'sessionid' => $sessionId]);
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Here's how we can get the user id from the RFID card

    // The body of the POST request is stored in the $json_data variable
    $json_data = json_decode(file_get_contents("php://input", true));

    $id = $json_data->userID;

    $post_time = time();

    $user = getUser($id);

    if ($user) {
        
        $todaySessionsForUser = getTodaySessionsForUser($user->id);

        if ($todaySessionsForUser) {

            foreach ($todaySessionsForUser as $session) {
                //echo json_encode($session);
                $logs = getSessionLogsOfUser($user->id, $session->id);


                // To do that I have to get the statuses depending on the attendanceid
                $statuses = getStatusesForSession($session->attendanceid);

                $presentStatus;
                $lateStatus;
                $absentStatus;

                foreach ($statuses as $status) {
                    if ($status->acronym === 'P') {
                        $presentStatus = $status;
                    } elseif ($status->acronym === 'L') {
                        $lateStatus = $status;
                    } elseif ($status->acronym === 'A') {
                        $absentStatus = $status;
                    }
                }

                // Now I have to get the status id depending on the $post_time

                // Which means that I need to get the sessdate and check:
                //    1.- If $post_time is minor than the $sessdate + 5 minutes ======================> Present (P)
                if ($post_time <= $session->sessdate + 300) {
                    // Comprobar si ya existe log de esa sesión
                    if (!$logs) {

                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $presentStatus->id, $session->statusset);

                        echo $presentStatus->description;
                    } else {
                        if ($logs->statusid === $absentStatus->id) {
                            attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $presentStatus->id, $session->statusset);
                        }
                    }
                    //    2.- If $post_time is between $sessdate + 5min and $sessdate + 10min ============> Late (L)
                } elseif ($post_time > $session->sessdate + 300 && $post_time <= $session->sessdate + 600) {
                    if (!$logs) {

                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $lateStatus->id, $session->statusset);

                        echo $lateStatus->description;
                    } else {
                        if ($logs->statusid === $absentStatus->id) {
                            attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $lateStatus->id, $session->statusset);
                        }
                    }
                    //    3.- If $post_time is bigger than $sessdate + 10min =============================> Absent (A)
                } elseif ($post_time > $session->sessdate + 600) {
                    if (!$logs) {

                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $absentStatus->id, $session->statusset);

                        echo $absentStatus->description;
                    } else {
                        echo 'Ya está registrado. No has llegado.';
                    }
                }

                // Check if the RFID swipe is a check-out and the user leaves before the session ends
                if ($post_time < $session->sessdate + $session->duration - 300) {
                    // If the user has already been marked as present and now they are leaving early
                    if ($logs && ($logs->statusid === $presentStatus->id || $logs->statusid === $lateStatus->id)) {
                        // Update the status to absent
                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $absentStatus->id, $session->statusset);
                        echo 'Has salido antes de que terminara la sesión. Estado actualizado a ausente.';
                    }
                }

                echo "\n--------------------\n";
            }
        } else {
            echo "No sessions for today.";
        }
    }
}
