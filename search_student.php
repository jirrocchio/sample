

<?php
// Set timezone explicitly to ensure consistency
date_default_timezone_set('Asia/Manila'); // Adjust as per your local timezone

// Connect to the database
include 'db_connection.php';

$students = []; // Variable to hold all students
$student_data = null; // Variable to hold searched student data
$error_message = ''; // To store any error messages
$success_message = ''; // To store success messages

// Query to fetch all students
$query = "SELECT * FROM students";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_RFID = $_POST['student_RFID'];

    // Check if student exists and fetch necessary details
    $search_query = "
        SELECT 
            id, 
            first_name, 
            last_name, 
            class, 
            year, 
            block, 
            photo, 
            schedule_id 
        FROM students 
        WHERE student_RFID = ?
    ";
    $stmt = $conn->prepare($search_query);
    $stmt->bind_param("s", $student_RFID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
        $student_id = $student_data['id'];
        $class = $student_data['class'];
        $student_name = $student_data['first_name'] . ' ' . $student_data['last_name'];
        $schedule_id = $student_data['schedule_id'];

        // Fetch the class schedule
        $schedule_query = "
            SELECT start_time, end_time, day_of_week 
            FROM class_schedule 
            WHERE schedule_id = ?
        ";
        $stmt_schedule = $conn->prepare($schedule_query);
        $stmt_schedule->bind_param("i", $schedule_id);
        $stmt_schedule->execute();
        $schedule_result = $stmt_schedule->get_result();

        if ($schedule_result->num_rows > 0) {
            $schedule_data = $schedule_result->fetch_assoc();
            $start_time = $schedule_data['start_time'];
            $end_time = $schedule_data['end_time'];
            $scheduled_day = strtolower($schedule_data['day_of_week']);

            // Get the current time and day
            $current_time = date('H:i:s');
            $current_day = strtolower(date('l')); // e.g., 'monday', 'tuesday'

            // Log time details for debugging
            error_log("Current time: $current_time, Start time: $start_time, End time: $end_time");
            error_log("Current day: $current_day, Scheduled day: $scheduled_day");

            // Check if the current day matches the schedule
            if ($current_day === $scheduled_day) {
                $current_time_str = strtotime($current_time);
                $start_time_str = strtotime($start_time);
                $end_time_str = strtotime($end_time);

                if ($current_time_str < $start_time_str) {
                    $error_message = "Your class time has not begun yet. Please wait.";
                } elseif ($current_time_str > $end_time_str) {
                    $error_message = "Your class time has already ended. Attendance cannot be recorded.";
                } else {
                    // Check for duplicate attendance records for the same class
$duplicate_check_query = "
SELECT 1 
FROM attendance 
WHERE student_RFID = ? 
  AND class = ? 
  AND date = CURDATE()
";
$stmt_duplicate = $conn->prepare($duplicate_check_query);
$stmt_duplicate->bind_param("ss", $student_RFID, $class);
$stmt_duplicate->execute();
$duplicate_result = $stmt_duplicate->get_result();

if ($duplicate_result->num_rows > 0) {
$error_message = "Attendance already recorded for this class today.";
} else {
// Calculate the late threshold
$late_threshold = strtotime($start_time . ' +2 minutes');
$status = 'present'; // Default status

// Check if the student is late
if ($current_time_str > $late_threshold) {
    $status = 'late';
}

// Insert attendance record
$insert_query = "
    INSERT INTO attendance 
    (student_RFID, name, class, date, status, action, action_time) 
    VALUES (?, ?, ?, CURDATE(), ?, 'time-in', CURTIME())
";
$stmt_insert = $conn->prepare($insert_query);
$stmt_insert->bind_param("ssss", $student_RFID, $student_name, $class, $status);

if ($stmt_insert->execute()) {
    $success_message = ucfirst($status) . " recorded successfully.";
} else {
    $error_message = "Error recording attendance: " . $stmt_insert->error;
}
}

                }
            } else {
                $error_message = "It's not your class day.";
            }
        } else {
            $error_message = "Class schedule not found.";
        }
    } else {
        $error_message = "Student not found.";
    }
}

// Debug error messages
if ($error_message) {
    error_log("Error: $error_message");
}
if ($success_message) {
    error_log("Success: $success_message");
}

// Mark absent students at the end of each class schedule
$class_schedule_query = "
    SELECT schedule_id, end_time, day_of_week 
    FROM class_schedule
    WHERE day_of_week = ?
";
$stmt_schedule = $conn->prepare($class_schedule_query);
$current_day = date('l'); // Get the current day of the week
$stmt_schedule->bind_param("s", $current_day);
$stmt_schedule->execute();
$schedule_results = $stmt_schedule->get_result();

if ($schedule_results->num_rows > 0) {
    while ($schedule = $schedule_results->fetch_assoc()) {
        $end_time = $schedule['end_time'];
        $schedule_id = $schedule['schedule_id'];

        // Check if the current time is past the class end time
        if (strtotime(date('H:i:s')) >= strtotime($end_time)) {
            // Automatic time-out for students in this class who are present or late
            $timeout_query = "
                SELECT a.student_RFID, a.name, a.class
                FROM attendance a
                INNER JOIN students s ON a.student_RFID = s.student_RFID
                WHERE a.date = CURDATE()
                  AND s.schedule_id = ?
                  AND a.status IN ('present', 'late')
                  AND a.action = 'time-in'
                  AND NOT EXISTS (
                      SELECT 1
                      FROM attendance a2
                      WHERE a2.student_RFID = a.student_RFID
                        AND a2.date = CURDATE()
                        AND a2.action = 'time-out'
                  )
            ";

            $stmt_timeout = $conn->prepare($timeout_query);
            $stmt_timeout->bind_param("i", $schedule_id);
            $stmt_timeout->execute();
            $timeout_students = $stmt_timeout->get_result();

            if ($timeout_students->num_rows > 0) {
                while ($student = $timeout_students->fetch_assoc()) {
                    $timeout_insert_query = "
                        INSERT INTO attendance 
                        (student_RFID, name, class, date, status, action, action_time) 
                        VALUES (?, ?, ?, CURDATE(), ?, 'time-out', CURTIME())
                    ";
                    $stmt_timeout_insert = $conn->prepare($timeout_insert_query);
                    $student_RFID = $student['student_RFID'];
                    $student_name = $student['name'];
                    $class = $student['class'];
                    $status = 'present'; // Maintain the original status

                    $stmt_timeout_insert->bind_param("ssss", $student_RFID, $student_name, $class, $status);
                    if (!$stmt_timeout_insert->execute()) {
                        error_log("Error inserting time-out record: " . $stmt_timeout_insert->error);
                    }
                }
            }

            // Mark absent students for this class
            $absent_query = "
                SELECT s.student_RFID, s.first_name, s.last_name, s.class
                FROM students s
                WHERE s.schedule_id = ? 
                  AND NOT EXISTS (
                      SELECT 1 
                      FROM attendance a 
                      WHERE a.student_RFID = s.student_RFID AND a.date = CURDATE()
                  )
            ";

            $stmt_absent = $conn->prepare($absent_query);
            $stmt_absent->bind_param("i", $schedule_id);
            $stmt_absent->execute();
            $absent_students = $stmt_absent->get_result();

            if ($absent_students->num_rows > 0) {
                while ($student = $absent_students->fetch_assoc()) {
                    $absent_insert_query = "
                        INSERT INTO attendance 
                        (student_RFID, name, class, date, status, action, action_time) 
                        VALUES (?, ?, ?, CURDATE(), 'absent', 'no-show', CURTIME())
                    ";
                    $stmt_absent_insert = $conn->prepare($absent_insert_query);
                    $student_RFID = $student['student_RFID'];
                    $student_name = $student['first_name'] . ' ' . $student['last_name'];
                    $class = $student['class'];
                    $stmt_absent_insert->bind_param("sss", $student_RFID, $student_name, $class);
                    if (!$stmt_absent_insert->execute()) {
                        error_log("Error inserting absent record: " . $stmt_absent_insert->error);
                    }
                }
            }
        }
    }
}


?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Information</title>
    <style>
        /* General Reset */
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            color: #333;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            height: 100%;
        }

        h1 {
            background-color: #75140D;
            color: white;
            padding: 20px;
            margin: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            position: absolute;
            top: 0;
            font-size: 32px;
            font-weight: bold;
        }


        .h1-left, .h1-right {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .h1-left {
            justify-content: flex-start;
            gap: 10px;
        }

        .h1-center {
            flex: 1;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }

        .h1-right {
            justify-content: flex-end;
        }

        h1 form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        h1 input[type="text"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 20px;
        }

        h1 button {
            background-color: #75140D;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        h1 button:hover {
            background-color: #2e6230;
        }

        h1 a {
            color: #75140D;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            background-color: #fff;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        h1 a:hover {
            background-color: #75140D;
            color: white;
        }

         /* Full-screen centered container */
         .student-container {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            background-color: white;
            padding: 50px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 1200px;
            margin: 20px;
            text-align: left;
            gap: 40px;
        }

        .student-container h3 {
            margin-bottom: 20px;
            font-size: 50px;
        }

        .student-container p {
            margin: 10px 0;
            font-size: 50px;
        }

        /* Make photo larger */
        .student-container img {
            margin-top: 20px;
            border-radius: 8px;
            width: 600px;  /* Increase photo size */
            height: auto;
            max-width: 100%;  /* Ensure responsiveness */
        }

        /* Feedback Messages */
        .error, .success {
            text-align: center;
            font-size: 18px;
            margin-top: 20px;
            font-weight: bold;
        }

        .error {
            color: red;
        }

        .success {
            color: green;
        }

        /* Search form container */
        .search-form-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px;
        }


        .search-form-container form {
            display: flex;
            gap: 10px;
        }

        .search-form-container input[type="text"] {
            width: 300px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        .search-form-container button {
            background-color: #fff;
            color: #75140D;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-form-container button:hover {
            background-color: #75140D;
            color: white;
        }       

    </style>
</head>
<body>


    <h1>
        <!-- Search Form on the Left -->
        <div class="search-form-container">
        <form action="" method="post">
            <input type="text" id="student_RFID" name="student_RFID" placeholder="Enter RFID" required>
            <button type="submit">Search</button>
        </form>
        </div>

        <!-- Title in the Center -->
        <div class="h1-center">Student Attendance</div>

        <!-- "Back to student list" Button on the Right -->
        <div class="h1-right">
            <a href="dashboard.php">Go to Dashboard</a>
        </div>
    </h1>

    <?php
    if (!empty($error_message)) {
        echo "<p class='error'>$error_message</p>";
    }

    if (!empty($success_message)) {
        echo "<p class='success'>$success_message</p>";
    }

    if ($student_data) {
        echo "<div class='student-container'>";
        
        // Student photo on the left
        if (!empty($student_data['photo'])) {
            echo "<img src='data:image/jpeg;base64," . base64_encode($student_data['photo']) . "' alt='Student Photo'>";
        } else {
            echo "<p>No photo available for this student.</p>";
        }

        // Student info on the right
        echo "<div>";
        echo "<p><strong>Name:</strong> " . $student_data['first_name'] . " " . $student_data['last_name'] . "</p>";
        echo "<p><strong>Class:</strong> " . $student_data['class'] . "</p>";
        echo "<p><strong>Year/Block:</strong> " . $student_data['year'] . "-" . $student_data['block'] . "</p>";
        echo "</div>";

        echo "</div>";
    }
    ?>
</body>
</html>
