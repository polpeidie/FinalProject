<?php

require('config.php');
require('mod/attendance/classes/attendance_webservices_handler.php');

/**
 * Lists all users in moodle database.
 * 
 * @return array|null List of users or null if there are no users.
 */
function getUsers()
{
    global $DB;

    return $DB->get_records('user');
}

/**
 * Retrieves a user record from the database by user ID.
 *
 * @param int $userId The ID of the user to retrieve.
 * @return object|null The user record as an object, or null if the user is not found.
 */
function getUser($userId)
{
    global $DB;

    return $DB->get_record('user', ['id' => $userId]);
}

/**
 * Retrieves all courses of a user from database by user ID.
 * 
 * @param int $userId The ID of the user we need to retrieve his/her courses.
 * @return array|null A list of the courses the user is erolled in or null if the user is not erolled in any course.
 */
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

/**
 * Retrieves all the sessions of today for a user from database by the users ID.
 * 
 * @param int $userId The ID of the user we need to retrieve his/her sessions.
 * @return array|null A list of the sessions the user has today or null if the user has no sessions today.
 */
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

/**
 * Retrieves all the sessions that are taking place today.
 * 
 * @return array|null A list of sessions that are taking place today or null if there are no sessions taking place today.
 */
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

/**
 * Retrieves the available statuses for an attendance instance.
 * 
 * @param int $attendanceid ID of the attendance instance from which we want to retrieve the statuses.
 * @return array|null A list of the statuses of the attendance instance or null if there are no statuses. 
 */
function getStatusesForSession($attendanceid)
{
    global $DB;

    return $DB->get_records('attendance_statuses', ['attendanceid' => $attendanceid]);
}

/**
 * Retrieves the logs of a user in a specific session.
 * 
 * @param int ID of the user from which we want to retrieve the logs.
 * @param int ID of the session from which we want to retrieve the logs.
 * @return object|null Log of user in the session or null if there is no log.
 */
function getSessionLogsOfUser($userId, $sessionId)
{
    global $DB;

    return $DB->get_record('attendance_log', ['studentid' => $userId, 'sessionid' => $sessionId]);
}


// If we recieve a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Here's how we get the user id from the RFID card

    // The body of the POST request is stored in the $json_data variable
    $json_data = json_decode(file_get_contents("php://input", true));

    // And we get the userID as a property of the $json_data object
    $id = $json_data->userID;

    // Taking a timestamp of the moment a POST request is recieved
    $post_time = time();

    // We validate that the user exists
    $user = getUser($id);

    if ($user) {

        // Retrieving sessions and validating if there are sessions
        $todaySessionsForUser = getTodaySessionsForUser($user->id);

        if ($todaySessionsForUser) {

            // Iterating over sessions
            foreach ($todaySessionsForUser as $session) {
                // Retrieving logs
                $logs = getSessionLogsOfUser($user->id, $session->id);

                // Retrieving statuses
                $statuses = getStatusesForSession($session->attendanceid);

                // Storing statuses for [Present, Late, Absent]
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

                // Now we compare the session date and the $post_time
                //    1.- If $post_time is minor than the $sessdate + 5 minutes ======================> Present (P)
                if ($post_time <= $session->sessdate + 300) {
                    // Checking if there are logs
                    if (!$logs) {

                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $presentStatus->id, $session->statusset);

                        echo $presentStatus->description;
                    } else {
                        // If the log status is absent we update the status to Present
                        if ($logs->statusid === $absentStatus->id) {
                            attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $presentStatus->id, $session->statusset);
                        }
                    }
                    //    2.- If $post_time is between $sessdate + 5min and $sessdate + 10min ============> Late (L)
                } elseif ($post_time > $session->sessdate + 300 && $post_time <= $session->sessdate + 600) {
                    // Checking if there are logs
                    if (!$logs) {

                        attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $lateStatus->id, $session->statusset);

                        echo $lateStatus->description;
                    } else {
                        // If the log status is absent we update the status to Late
                        if ($logs->statusid === $absentStatus->id) {
                            attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, $lateStatus->id, $session->statusset);
                        }
                    }
                    //    3.- If $post_time is bigger than $sessdate + 10min =============================> Absent (A)
                } elseif ($post_time > $session->sessdate + 600) {
                    // Checking if there are logs
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
