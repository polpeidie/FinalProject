# Plugin Moodle

### Description
This project tries to implement a check-in system for students that integrates with Moodle, in a simple and concise way. To do so, we implement a system where students swipe a RFID card to check in and out of class.

This system is divided into three parts:
- Retrieving data from RFID card and generating/editing a .json file.
- _'Observing'_ any changes in the .json file and sending information to PHP script in Moodle.
- Managing attendance inside Moodle and automatically updating database.

From now on we will call the second and third parts [Observer](#observer) and [PHP script](#php-script) respectively. These parts will be described bellow.

### Observer
**Explain Observer here**

---

### PHP script
This script is in the **attendance_manager.php**. Since this script uses methods like **get_records()**, **get_records_list()** or **update_user_status()**, this script should be placed in the Moodle root directory for it to work correctly, since this methods are imported from the [Moodle DML API](https://moodledev.io/docs/4.4/apis/core/dml).

In the repository, and it consists of two parts:

#### Methods declarations
The first part of the script consists of a series of methods that help refactoring the logic of the script. The methods are the following:
- **getUsers():** Lists all users in moodle database.
- **getUser($userId):** Retrieves a user record from the database by user ID.
- **getUserCourses($userId):** Retrieves all courses of a user from database by user ID.
- **getTodaySessionsForUser($userId):** Retrieves all the sessions of today for a user from database by the users ID.
- **getTodaySessions():** Retrieves all the sessions that are taking place today.
- **getStatusesForSession($attendanceid):** Retrieves the available statuses for an attendance instance.
- **getSessionLogsOfUser($userId, $sessionId):** Retrieves the logs of a user in a specific session.

#### POST API
The second part of the script starts with the following lines and from now on the following code will only be executed when a POST HTTP request is recieved:
```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Here's how we get the user id from the RFID card

    // The body of the POST request is stored in the $json_data variable
    $json_data = json_decode(file_get_contents("php://input", true));

    // And we get the userID as a property of the $json_data object
    $id = $json_data->userID;

    // Taking a timestamp of the moment a POST request is recieved
    $post_time = time();
```

Following, we make sure the user ID we recieved belongs to a user in the Moodle database and if it does, we retrieve all it's sessions for today:
```php

    // We validate that the user exists
    $user = getUser($id);

    if ($user) {

        // Retrieving sessions and validating if there are sessions
        $todaySessionsForUser = getTodaySessionsForUser($user->id);
```