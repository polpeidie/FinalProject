# Plugin Moodle

### Description
This project tries to implement a check-in system for students that integrates with Moodle, in a simple and concise way. To do so, we implement a system where students swipe a RFID card to check in and out of class.

This system is divided into three parts:
- Retrieving data from RFID card and generating/editing a .json file.
- _'Observing'_ any changes in the .json file and sending information to PHP script in Moodle.
- Managing attendance inside Moodle and automatically updating database.

From now on we will call the second and third parts [Observer](#observer) and [PHP script](#php-script) respectively. These parts will be described bellow.

# Observer

## Overview

This Node.js script uses the `chokidar` library to monitor changes to a specific JSON file (`mock.json`). When changes are detected, the script reads the updated file, processes its contents, and sends the data to a specified PHP endpoint via a POST request.

## How It Works

### Dependencies

- **chokidar**: A library for watching file changes.
- **fs**: Node.js file system module for reading files.
- **fetch**: A method to make HTTP requests, similar to the browser `fetch` API.

### File Path and Endpoint

- **filePath**: The path to the JSON file being watched (`mock.json`).
- **URL_ENDPOINT**: The URL of the PHP endpoint that will receive the JSON data.

### Script Flow

1. **Initialize Watcher**
   - The script initializes a `chokidar` watcher to monitor the specified file (`mock.json`).

2. **Event Listeners**
   - **change**: Triggered when the file is modified.
     - Logs a message indicating the file has changed.
     - Reads the updated file using `fs.readFile`.
     - Parses the JSON data.
     - Logs the parsed JSON data.
     - Sends the JSON data to the PHP endpoint via a POST request.
   - **add**: Triggered when the file is initially added.
     - Logs a message indicating the file has been added.
     - Logs a message that the app is listening for changes.

## Script Details

### Initialization

```javascript
const chokidar = require('chokidar')
const fs = require('fs')

const filePath = 'mock.json'
const URL_ENDPOINT = 'http://192.168.233.171:8080/test.php'

// Initialize watcher
const watcher = chokidar.watch(filePath)
```

- Import necessary modules: `chokidar` for file watching and `fs` for file operations.
- Define the file path and the URL endpoint.

### Event Handlers

#### File Change Handler

```javascript
watcher.on('change', path => {
    console.log(`File ${path} has been changed`)

    // Read the updated JSON file
    fs.readFile(path, 'utf-8', (err, data) => {
        if (err) {
            console.error('Error reading file:', err)
            return
        }

        const jsonData = JSON.parse(data)

        // Process JSON data as needed
        console.log(jsonData)
        console.log('\n')

        // Send data to PHP file
        fetch(URL_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
    })
})
```

- Logs a message when the file changes.
- Reads the updated file content.
- Parses the file content as JSON.
- Logs the JSON data.
- Sends the JSON data to the PHP endpoint using a POST request.

#### File Add Handler

```javascript
watcher.on('add', path => {
    console.log('\nThe app is now listening for changes...\n')

    console.log(`File ${path} has been added`)
})
```

- Logs a message when the file is added.
- Indicates that the app is now listening for changes.

## Usage

1. Ensure `node`, `npm`, and the required dependencies (`chokidar` and `node-fetch`) are installed.
2. Place the script in your project directory.
3. Run the script using `node script-name.js`.
4. Modify the `mock.json` file to see the script in action.

This script will watch for changes to the `mock.json` file and automatically send the updated data to the specified PHP endpoint.

---

# PHP script
This script is in the **`moodle_files/attendance_manager.php`** file. Since this script uses methods like **`get_records()`**, **`get_records_list()`** or **`update_user_status()`**, this script should be placed in the Moodle root directory for it to work correctly, since this methods are imported from the [Moodle DML API](https://moodledev.io/docs/4.4/apis/core/dml).

This file consists of two parts which we will describe down bellow:

### Methods declarations
The first part of the script consists of a series of methods that help refactoring the logic of the script. The methods are the following:
- **`getUsers():`** Lists all users in moodle database.
- **`getUser($userId):`** Retrieves a user record from the database by user ID.
- **`getUserCourses($userId):`** Retrieves all courses of a user from database by user ID.
- **`getTodaySessionsForUser($userId):`** Retrieves all the sessions of today for a user from database by the users ID.
- **`getTodaySessions():`** Retrieves all the sessions that are taking place today.
- **`getStatusesForSession($attendanceid):`** Retrieves the available statuses for an attendance instance.
- **`getSessionLogsOfUser($userId, $sessionId):`** Retrieves the logs of a user in a specific session.

### POST API
The second part of the script starts with the following lines and from now on the following code will only be executed when a POST HTTP request is recieved.

#### Overview

This PHP script handles POST requests to record and update the attendance status of users based on RFID card swipes. It processes the input data, validates the user, retrieves relevant sessions, and updates the attendance status accordingly.

## How It Works

### Request Handling

- The script checks if the incoming request method is POST.
- It retrieves and decodes the JSON data from the request body, extracting the `userID` field.
- It takes a timestamp (`$post_time`) of when the POST request is received.

### User Validation

- The script validates if the user exists by calling the `getUser` function with the extracted `userID`.
- If the user exists, it retrieves today's sessions for the user using the `getTodaySessionsForUser` function.

### Session Processing

- For each session:
  - It retrieves the logs of the session using the `getSessionLogsOfUser` function.
  - It retrieves the statuses for the session using the `getStatusesForSession` function and categorizes them as Present (P), Late (L), or Absent (A).
  - It compares the current timestamp (`$post_time`) with the session start time (`$sessdate`) and updates the attendance status based on the following criteria:
    1. **Present**: If the POST time is within 5 minutes of the session start.
    2. **Late**: If the POST time is between 5 and 10 minutes after the session start.
    3. **Absent**: If the POST time is more than 10 minutes after the session start.
  - If there are no logs and the user swipes within the present or late timeframe, it updates the status accordingly.
  - If the user was marked absent but swipes within the present or late timeframe, it updates the status to present or late.
  - If the user checks out early (before the session ends), it updates the status to absent if the user was previously marked present or late.

### Response

- The script echoes the corresponding status description (Present, Late, Absent) or specific messages based on the attendance status updates and conditions met.

## Functions Used

- **`getUser($id)`**: Validates if the user exists based on the provided user ID.
- **`getTodaySessionsForUser($userId)`**: Retrieves today's sessions for the user.
- **`getSessionLogsOfUser($userId, $sessionId)`**: Retrieves the logs of the session for the user.
- **`getStatusesForSession($attendanceId)`**: Retrieves the attendance statuses for the session.
- **`attendance_handler::update_user_status($sessionId, $userId, $lastTakenBy, $statusId, $statusSet)`**: Updates the user's attendance status for the session.

## Script Flow

1. **Receive POST request**.
2. **Extract and decode JSON data**.
3. **Validate user**.
4. **Retrieve today's sessions for the user**.
5. **Process each session**:
   - Retrieve logs and statuses.
   - Compare current time with session start time.
   - Update attendance status based on conditions.
6. **Handle early checkout**.
7. **Output status updates and messages**.

## Messages and Outputs

- "Present", "Late", "Absent": Based on the attendance status.
- "Ya está registrado. No has llegado.": If the user was already marked and tries to update status after the absent timeframe.
- "Has salido antes de que terminara la sesión. Estado actualizado a ausente.": If the user leaves before the session ends.
- "No sessions for today.": If there are no sessions for the user today.