<?php


require('config.php');
require('mod/attendance/classes/attendance_webservices_handler.php');

function getUsers () {
    global $DB;

    return $DB->get_records('user');
}

function getUser ($userId) {
    global $DB;

    return $DB->get_record('user', ['id' => $userId]);
}

function getUserCourses($userId) {
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

// Una vez tenemos todas las sesiones del dia
// TODO: 
//     -  Get attendance_id and course_id
//     -  Once we have that we can get the takenbyid
//     -  Get to know which session is the correct one

function getTodaySessionsForUser ($userId) {
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

function getTodaySessions () {
    global $DB;

    $sessions = $DB->get_records('attendance_sessions');

    $todaySessions = array();

    $startOfDay = strtotime('today midnight');

    $endOfDay = strtotime('tomorrow midnight') - 1;

    if ($sessions) {
        foreach ($sessions as $session) {
            if ($session->sessdate >= $startOfDay && $session->sessdate <= $endOfDay){
                array_push($todaySessions, $session);
            }
        }
    }

    return $todaySessions;
}


function getStatusesForSession ($attendanceid) {
    global $DB;

    return $DB->get_records('attendance_statuses', ['attendanceid' => $attendanceid]);
}


function getSessionLogsOfUser ($userId, $sessionId) {
    global $DB;

    return $DB->get_record('attendance_log', ['studentid' => $userId, 'sessionid' => $sessionId]);
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $post_time = time();

    $user = getUser(3);

    //foreach ($user as $key => $value) {
    //    echo $key. '    ===>    ' .$value;
    //}

    //$courses = getUserCourses($user->id);
//
    //foreach ($courses as $course) {
    //    echo $course->fullname;
    //}

    $todaySessionsForUser = getTodaySessionsForUser($user->id);

    
    if ($todaySessionsForUser) {

        foreach ($todaySessionsForUser as $session) {
            //echo json_encode($session);
            $logs = getSessionLogsOfUser($user->id, $session->id);

            
            // To do that I have to get the statuses depending on the attendanceid
            $statuses = getStatusesForSession($session->attendanceid);
            
            //foreach ($statuses as $status) {
            //    echo 'SESSION_ID: '.$status->id;
            //    echo json_encode($status);
            //}

            // Now I have to get the status id depending on the $post_time

            // Which means that I need to get the sessdate and check:
            //    1.- If $post_time is minor than the $sessdate + 5 minutes ======================> Present (P)
            if ($post_time <= $session->sessdate + 300) {
                // Comprobar si ya existe log de esa sessión
                if (!$logs) {
                    $statusSelected;

                    foreach ($statuses as $status) {
                        if ($status->acronym === 'P') {
                            $statusSelected = $status;
                        }
                    }

                    echo $statusSelected->description;
                }
            } elseif ($post_time > $session->sessdate + 300 && $post_time <= $session->sessdate + 600) {
                if (!$logs) {
                    $statusSelected;

                    foreach ($statuses as $status) {
                        if ($status->acronym === 'L') {
                            $statusSelected = $status;
                        }
                    }

                    echo $statusSelected->description;
                }
            } elseif ($post_time > $session->sessdate + 600) {
                if (!$logs) {
                    $statusSelected;

                    foreach ($statuses as $status) {
                        if ($status->acronym === 'A') {
                            $statusSelected = $status;
                        }
                    }

                    echo $statusSelected->description;
                }
            }
            //    2.- If $post_time is between $sessdate + 5min and $sessdate + 10min ============> Late (L)
            //    3.- If $post_time is bigger than $sessdate + 10min =============================> Absent (A)
            
            //attendance_handler::update_user_status($session->id, $user->id, $session->lasttakenby, , $session->statusset);


            
            echo "SEPARATOR";
        }
        

    } else {
        echo "No sessions for today.";
    }



/*
    foreach ($todaySessionsForUser as $a) {
        echo $a. ' ';
    }
*/
    /*  --------------------------------------------------------------  */


    // Here's how we can get the user_id from the RFID card

    // The body of the POST request is stored in the $json_data variable
    $json_data = json_decode(file_get_contents("php://input", true));

    // And we can access it's values like an object
    //echo json_encode($json_data->data);


    /*  --------------------------------------------------------------  */

    
    

    /*  --------------------------------------------------------------  */

    /*
    // Here's a demontration on how to determine de statusid depending on time
    
    // Arbitrary time
    $initial_time = 1716388200;
    
    // 2 minutes after that time
    $final_time = $initial_time + 120;
    
    // Current time
    $date = time();
    
    if ($date >= $initial_time && $date <= $final_time) {       // If current time is within arbitrary time and 2 min later
        echo 'You are in time';                                 // You're in time

    } elseif ($date < $initial_time) {                          // If current time is before arbitrary time
        echo 'You are early';                                   // You're early

    } elseif ($date > $final_time) {                            // If current time is after 2 minutes from arbitrary time
        echo 'You are late';                                    // You're late
    }
*/

    /*  ------------------------------------------------------------  */



/*
    $sessions = getTodaySessions();

    if ($sessions) {
        foreach ($sessions as $session) {
            echo json_encode($session);
            echo date("Y-m-d H:i:s", substr($session->sessdate, 0, 10));
            echo "SEPARATOR";
        }
    }
    */
}
