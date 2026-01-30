<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header('Location: signin.php');
    exit();
}
if (!isset($_SESSION['company_id'])) {
    die("Company ID not found in session. Please login again.");
}
require 'vendor/autoload.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';
use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();
$servername = "localhost";
$username = "root";
$password = "Parth@23102025";
$dbname = "taskflow1";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (isset($_GET['get_recommended_employees']) && isset($_GET['skill'])) {
    header('Content-Type: application/json');
    $skill = $_GET['skill'];
    $company_id = $_SESSION['company_id'];
    $sql = "SELECT e.employee_email, e.employee_firstname, e.employee_lastname, es.skills
            FROM employees_in_department e
            LEFT JOIN employee_skills es ON e.employee_email = es.employee_email AND e.company_id = es.company_id
            WHERE e.company_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'email' => $row['employee_email'],
            'name' => $row['employee_firstname'] . ' ' . $row['employee_lastname'],
            'skills' => $row['skills'] ?? ''
        ];
    }
    $stmt->close();
    echo json_encode($employees);
    exit();
}
if (isset($_GET['analytics_employee']) && !empty($_GET['analytics_employee'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    set_time_limit(60);
    try {
        $employee_email = mysqli_real_escape_string($conn, $_GET['analytics_employee']);
        $company_id = $_SESSION['company_id'];
        $emp_sql = "SELECT employee_firstname, employee_lastname, department_name
                    FROM employees_in_department
                    WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
        $emp_result = mysqli_query($conn, $emp_sql);
        $employee = mysqli_fetch_assoc($emp_result);
        if (!$employee) {
            echo json_encode(['error' => 'Employee not found']);
            exit();
        }
        $emp_id_sql = "SELECT id FROM employee_registration WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
        $emp_id_result = mysqli_query($conn, $emp_id_sql);
        $emp_id_row = mysqli_fetch_assoc($emp_id_result);
        $employee_id = $emp_id_row['id'] ?? 0;
        $analytics = [
            'employee_name' => $employee['employee_firstname'] . ' ' . $employee['employee_lastname'],
            'department' => $employee['department_name'] ?? 'N/A',
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'overdue_tasks' => 0,
            'on_time_tasks' => 0,
            'late_tasks' => 0,
            'completion_rate' => 0,
            'on_time_rate' => 0,
            'avg_relevance_score' => 0,
            'avg_quality_rating' => 0,
            'avg_efficiency_rating' => 0,
            'avg_teamwork_rating' => 0,
            'trends' => [
                'monthly_performance' => [],
                'skill_progression' => [],
                'efficiency_trends' => [],
                'quality_consistency' => []
            ],
            'comparative' => [
                'team_rank' => 0,
                'department_avg' => 0,
                'top_performer_gap' => 0,
                'improvement_rate' => 0
            ],
            'documentation' => [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'overdue_tasks' => 0,
                'on_time_tasks' => 0,
                'late_tasks' => 0,
                'completion_rate' => 0,
                'on_time_rate' => 0,
                'avg_relevance_score' => 0,
                'difficulty_distribution' => ['easy' => 0, 'medium' => 0, 'hard' => 0]
            ],
            'coding' => [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'overdue_tasks' => 0,
                'on_time_tasks' => 0,
                'late_tasks' => 0,
                'completion_rate' => 0,
                'on_time_rate' => 0,
                'avg_relevance_score' => 0,
                'avg_code_cost' => 0,
                'difficulty_distribution' => ['easy' => 0, 'medium' => 0, 'hard' => 0]
            ],
            'recent_feedback' => [],
            'insights' => []
        ];
        if ($employee_id > 0) {
            $doc_sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at <= deadline THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at > deadline THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN submitted_at IS NULL AND deadline < NOW() THEN 1 ELSE 0 END) as overdue,
                AVG(relevance_score) as avg_relevance
                FROM documentation_tasks
                WHERE employee_id = $employee_id AND company_id = '$company_id'";
            $doc_result = mysqli_query($conn, $doc_sql);
            $doc_data = mysqli_fetch_assoc($doc_result);
            $doc_diff_sql = "SELECT difficulty, COUNT(*) as count
                             FROM documentation_tasks
                             WHERE employee_id = $employee_id AND company_id = '$company_id'
                             GROUP BY difficulty";
            $doc_diff_result = mysqli_query($conn, $doc_diff_sql);
            $doc_difficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
            while ($row = mysqli_fetch_assoc($doc_diff_result)) {
                $doc_difficulty[$row['difficulty']] = (int)$row['count'];
            }
            $code_sql = "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at <= deadline THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at > deadline THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN submitted_at IS NULL AND deadline < NOW() THEN 1 ELSE 0 END) as overdue,
                AVG(relevance_score) as avg_relevance,
                AVG(code_cost) as avg_cost
                FROM coding_tasks
                WHERE employee_id = $employee_id AND company_id = '$company_id'";
            $code_result = mysqli_query($conn, $code_sql);
            $code_data = mysqli_fetch_assoc($code_result);
            $code_diff_sql = "SELECT difficulty, COUNT(*) as count
                              FROM coding_tasks
                              WHERE employee_id = $employee_id AND company_id = '$company_id'
                              GROUP BY difficulty";
            $code_diff_result = mysqli_query($conn, $code_diff_sql);
            $code_difficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
            while ($row = mysqli_fetch_assoc($code_diff_result)) {
                $code_difficulty[$row['difficulty']] = (int)$row['count'];
            }
            $perf_sql = "SELECT avg_quality_rating, avg_efficiency_rating, avg_teamwork_rating
                        FROM employee_performance_summary
                        WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
            $perf_result = mysqli_query($conn, $perf_sql);
            $perf_data = mysqli_fetch_assoc($perf_result);
            $feedback_sql = "SELECT feedback_text, rating_quality, rating_efficiency, rating_teamwork, created_at
                           FROM task_feedback
                           WHERE employee_email = '$employee_email'
                           ORDER BY created_at DESC
                           LIMIT 5";
            $feedback_result = mysqli_query($conn, $feedback_sql);
            $feedback = [];
            while ($row = mysqli_fetch_assoc($feedback_result)) {
                $feedback[] = [
                    'text' => $row['feedback_text'],
                    'quality' => (int)$row['rating_quality'],
                    'efficiency' => (int)$row['rating_efficiency'],
                    'teamwork' => (int)$row['rating_teamwork'],
                    'date' => date('M j, Y', strtotime($row['created_at']))
                ];
            }
            $total_tasks = ($doc_data['total'] ?? 0) + ($code_data['total'] ?? 0);
            $completed_tasks = ($doc_data['completed'] ?? 0) + ($code_data['completed'] ?? 0);
            $on_time_tasks = ($doc_data['on_time'] ?? 0) + ($code_data['on_time'] ?? 0);
            $late_tasks = ($doc_data['late'] ?? 0) + ($code_data['late'] ?? 0);
            $overdue_tasks = ($doc_data['overdue'] ?? 0) + ($code_data['overdue'] ?? 0);
            $completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100, 2) : 0;
            $on_time_rate = $completed_tasks > 0 ? round(($on_time_tasks / $completed_tasks) * 100, 2) : 0;
            $doc_completion_rate = ($doc_data['total'] ?? 0) > 0 ? round((($doc_data['completed'] ?? 0) / $doc_data['total']) * 100, 2) : 0;
            $doc_on_time_rate = ($doc_data['completed'] ?? 0) > 0 ? round((($doc_data['on_time'] ?? 0) / $doc_data['completed']) * 100, 2) : 0;
            $code_completion_rate = ($code_data['total'] ?? 0) > 0 ? round((($code_data['completed'] ?? 0) / $code_data['total']) * 100, 2) : 0;
            $code_on_time_rate = ($code_data['completed'] ?? 0) > 0 ? round((($code_data['on_time'] ?? 0) / $code_data['completed']) * 100, 2) : 0;
            $analytics['total_tasks'] = $total_tasks;
            $analytics['completed_tasks'] = $completed_tasks;
            $analytics['overdue_tasks'] = $overdue_tasks;
            $analytics['on_time_tasks'] = $on_time_tasks;
            $analytics['late_tasks'] = $late_tasks;
            $analytics['completion_rate'] = $completion_rate;
            $analytics['on_time_rate'] = $on_time_rate;
            $analytics['avg_relevance_score'] = round((($doc_data['avg_relevance'] ?? 0) + ($code_data['avg_relevance'] ?? 0)) / 2, 2);
            $analytics['avg_quality_rating'] = round($perf_data['avg_quality_rating'] ?? 0, 2);
            $analytics['avg_efficiency_rating'] = round($perf_data['avg_efficiency_rating'] ?? 0, 2);
            $analytics['avg_teamwork_rating'] = round($perf_data['avg_teamwork_rating'] ?? 0, 2);
            $analytics['recent_feedback'] = $feedback;
            $analytics['documentation']['total_tasks'] = (int)($doc_data['total'] ?? 0);
            $analytics['documentation']['completed_tasks'] = (int)($doc_data['completed'] ?? 0);
            $analytics['documentation']['overdue_tasks'] = (int)($doc_data['overdue'] ?? 0);
            $analytics['documentation']['on_time_tasks'] = (int)($doc_data['on_time'] ?? 0);
            $analytics['documentation']['late_tasks'] = (int)($doc_data['late'] ?? 0);
            $analytics['documentation']['completion_rate'] = $doc_completion_rate;
            $analytics['documentation']['on_time_rate'] = $doc_on_time_rate;
            $analytics['documentation']['avg_relevance_score'] = round($doc_data['avg_relevance'] ?? 0, 2);
            $analytics['documentation']['difficulty_distribution'] = $doc_difficulty;
            $analytics['coding']['total_tasks'] = (int)($code_data['total'] ?? 0);
            $analytics['coding']['completed_tasks'] = (int)($code_data['completed'] ?? 0);
            $analytics['coding']['overdue_tasks'] = (int)($code_data['overdue'] ?? 0);
            $analytics['coding']['on_time_tasks'] = (int)($code_data['on_time'] ?? 0);
            $analytics['coding']['late_tasks'] = (int)($code_data['late'] ?? 0);
            $analytics['coding']['completion_rate'] = $code_completion_rate;
            $analytics['coding']['on_time_rate'] = $code_on_time_rate;
            $analytics['coding']['avg_relevance_score'] = round($code_data['avg_relevance'] ?? 0, 2);
            $analytics['coding']['avg_code_cost'] = round($code_data['avg_cost'] ?? 0, 2);
            $analytics['coding']['difficulty_distribution'] = $code_difficulty;
            $insights = [];
            if ($total_tasks == 0) {
                $insights[] = "No tasks assigned yet.";
            } else {
                if ($completion_rate < 70) $insights[] = "Low completion rate. Consider adjusting deadlines.";
                if ($on_time_rate < 70) $insights[] = "Low on-time completion rate. Time management needs improvement.";
                if ($analytics['documentation']['avg_relevance_score'] < 70 && $analytics['documentation']['total_tasks'] > 0) $insights[] = "Documentation task relevance needs improvement.";
                if ($analytics['coding']['avg_relevance_score'] < 70 && $analytics['coding']['total_tasks'] > 0) $insights[] = "Coding task relevance needs improvement.";
                if ($analytics['avg_quality_rating'] < 3) $insights[] = "Work quality needs attention.";
                if ($overdue_tasks > 0) $insights[] = "Has overdue tasks that need attention.";
            }
            if (empty($insights)) $insights[] = "Good overall performance.";
            $analytics['insights'] = $insights;
        }
        echo json_encode($analytics);
        exit();
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
function sendTaskAssignmentEmail($to_email, $task_instructions, $deadline, $task_priority, $task_difficulty) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = 'New Task Assigned - TaskFlow';
        $mail->Body = "<html><body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #3498db; text-align: center;'>New Task Assigned</h2>
                <p>You have been assigned a new task:</p>
                <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                    <p><strong>Task Instructions:</strong> $task_instructions</p>
                    <p><strong>Priority:</strong> " . ucfirst($task_priority) . "</p>
                    <p><strong>Difficulty:</strong> " . ucfirst($task_difficulty) . "</p>
                    <p><strong>Deadline:</strong> " . date('F j, Y, g:i a', strtotime($deadline)) . "</p>
                </div>
                <p>Please log in to your TaskFlow account to view complete details and submit your work.</p>
            </div>
        </body></html>";
        $mail->AltBody = "New Task Assigned:\n\nTask: $task_instructions\nPriority: $task_priority\nDifficulty: $task_difficulty\nDeadline: $deadline\n\nPlease log in to your TaskFlow account for details.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send task assignment email: " . $mail->ErrorInfo);
        return false;
    }
}
function sendDeadlineReminderEmail($to_email, $task_instructions, $deadline) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = 'Task Deadline Passed - TaskFlow';
        $mail->Body = "<html><body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #e74c3c; text-align: center;'>Task Deadline Passed</h2>
                <p>This is a reminder that the deadline for your task has passed:</p>
                <div style='background: #fef2f2; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #e74c3c;'>
                    <p><strong>Task:</strong> $task_instructions</p>
                    <p><strong>Deadline:</strong> " . date('F j, Y, g:i a', strtotime($deadline)) . "</p>
                </div>
                <p>Please submit your work as soon as possible. The task is now marked as overdue.</p>
            </div>
        </body></html>";
        $mail->AltBody = "Task Deadline Passed:\n\nTask: $task_instructions\nDeadline: $deadline\n\nPlease submit your work as soon as possible. The task is now overdue.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send deadline reminder email: " . $mail->ErrorInfo);
        return false;
    }
}
function sendTaskReassignmentEmail($to_email, $task_instructions, $deadline, $feedback = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = 'Task Requires Resubmission - TaskFlow';
        $mail->Body = "<html><body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <h2 style='color: #f59e0b; text-align: center;'>Task Requires Resubmission</h2>
                <p>Your submitted task requires additional work and has been reassigned to you:</p>
                <div style='background: #fffbeb; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #f59e0b;'>
                    <p><strong>Task:</strong> $task_instructions</p>
                    <p><strong>Deadline:</strong> " . date('F j, Y, g:i a', strtotime($deadline)) . "</p>
                    " . ($feedback ? "<p><strong>Feedback:</strong> $feedback</p>" : "") . "
                </div>
                <p>Please review the feedback and resubmit your work by the new deadline.</p>
            </div>
        </body></html>";
        $mail->AltBody = "Task Requires Resubmission:\n\nTask: $task_instructions\nDeadline: $deadline\n" . ($feedback ? "Feedback: $feedback\n" : "") . "\nPlease review the feedback and resubmit your work.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send task reassignment email: " . $mail->ErrorInfo);
        return false;
    }
}
function calculateRelevanceScore($task_id, $task_keywords, $submitted_file) {
    $full_file_path = __DIR__ . DIRECTORY_SEPARATOR . "submitted_files" . DIRECTORY_SEPARATOR . $submitted_file;
    if (!file_exists($full_file_path)) {
        return json_encode([
            "relevance_score" => 0,
            "grammar_errors" => 0,
            "bad_words" => [],
            "random_text_confidence" => 0,
            "missing_keywords" => [],
            "analysis_details" => "File not found"
        ]);
    }
    $url = "http://localhost:5001/analyze";
    $data = [
        'task_id' => $task_id,
        'task_keywords' => $task_keywords,
        'pdf_path' => $full_file_path,
        'task_title' => '',
        'task_description' => ''
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    try {
        $result = @file_get_contents($url, false, $context);
        if ($result !== FALSE) {
            return $result;
        }
    } catch (Exception $e) {
        error_log("Relevance server error: " . $e->getMessage());
    }
    return json_encode([
        "relevance_score" => 0,
        "grammar_errors" => 0,
        "bad_words" => [],
        "random_text_confidence" => 0,
        "missing_keywords" => [],
        "analysis_details" => "Relevance analysis server is not running. Please start the Python server."
    ]);
}
function startRelevanceServer() {
    $url = "http://localhost:5001/health";
    $context = stream_context_create([
        'http' => [
            'timeout' => 3,
            'ignore_errors' => true
        ]
    ]);
    $is_running = @file_get_contents($url, false, $context) !== false;
    if (!$is_running) {
        error_log("Starting relevance server...");
        $python_commands = [
            "python",
            "python3",
            "py"
        ];
        $script_path = __DIR__ . DIRECTORY_SEPARATOR . "relevance_server.py";
        foreach ($python_commands as $python_bin) {
            $cmd = escapeshellcmd($python_bin) . ' ' . escapeshellarg($script_path);
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $cmd = "start /B " . $cmd;
                pclose(popen($cmd, "r"));
            } else {
                exec($cmd . " > /dev/null 2>&1 &");
            }
            error_log("Started server with: $python_bin");
            sleep(5);
            $is_running = @file_get_contents($url, false, $context) !== false;
            if ($is_running) {
                error_log("✅ Relevance server started successfully");
                break;
            } else {
                error_log("❌ Failed to start with: $python_bin");
            }
        }
        if (!$is_running) {
            error_log("❌ Failed to start relevance server after trying all commands");
        }
    }
    return $is_running;
}
function calculateCodeComplexity($task_id, $submitted_file) {
    $full_file_path = __DIR__ . DIRECTORY_SEPARATOR . "submitted_files" . DIRECTORY_SEPARATOR . $submitted_file;
    if (!file_exists($full_file_path)) {
        error_log("Complexity: File not found: $full_file_path");
        return array("time_complexity" => "Unknown", "space_complexity" => "Unknown", "code_cost" => 0, "code_complexity" => "Medium");
    }
    $script = __DIR__ . DIRECTORY_SEPARATOR . "test1.py";
    if (!file_exists($script)) {
        error_log("Complexity: Python script not found: $script");
        return array("time_complexity" => "Unknown", "space_complexity" => "Unknown", "code_cost" => 0, "code_complexity" => "Medium");
    }
    $python_commands = array("python", "python3", "C:\\Users\\Parth Koli\\OneDrive\\Desktop\\taskflowmain\\taskflow\\venv\\Scripts\\python.exe");
    foreach ($python_commands as $python_bin) {
        $cmd = escapeshellcmd($python_bin)
             . ' ' . escapeshellarg($script)
             . ' ' . escapeshellarg($task_id)
             . ' ' . escapeshellarg($full_file_path);
        error_log("Complexity command: $cmd");
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );
        $process = proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $return_value = proc_close($process);
            error_log("Complexity stdout: " . $stdout);
            error_log("Complexity stderr: " . $stderr);
            error_log("Complexity return code: " . $return_value);
            if ($return_value === 0 && !empty(trim($stdout))) {
                $output_lines = explode("\n", trim($stdout));
                $complexity_data = array();
                foreach ($output_lines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($key, $value) = explode(':', $line, 2);
                        $complexity_data[trim($key)] = trim($value);
                    }
                }
                if (isset($complexity_data['time_complexity'])) {
                    $complexity_data['time_complexity'] = str_replace(['²', '³', 'O(n²)', 'O(n³)'], ['^2', '^3', 'O(n^2)', 'O(n^3)'], $complexity_data['time_complexity']);
                }
                if (isset($complexity_data['space_complexity'])) {
                    $complexity_data['space_complexity'] = str_replace(['²', '³', 'O(n²)', 'O(n³)'], ['^2', '^3', 'O(n^2)', 'O(n^3)'], $complexity_data['space_complexity']);
                }
                $time_complexity = isset($complexity_data['time_complexity']) ? $complexity_data['time_complexity'] : "Unknown";
                $space_complexity = isset($complexity_data['space_complexity']) ? $complexity_data['space_complexity'] : "Unknown";
                $code_cost = isset($complexity_data['code_cost']) ? intval($complexity_data['code_cost']) : 0;
                $cyclomatic_complexity = isset($complexity_data['cyclomatic_complexity']) ? floatval($complexity_data['cyclomatic_complexity']) : 0;
                $lines_of_code = isset($complexity_data['lines_of_code']) ? intval($complexity_data['lines_of_code']) : 0;
                $function_count = isset($complexity_data['function_count']) ? intval($complexity_data['function_count']) : 0;
                $complexity_class = isset($complexity_data['complexity_class']) ? $complexity_data['complexity_class'] : 'N/A';
                $code_complexity = "Medium";
                if ($code_cost > 70) {
                    $code_complexity = "High";
                } elseif ($code_cost < 30) {
                    $code_complexity = "Low";
                }
                $valid_complexities = array('Low', 'Medium', 'High');
                if (!in_array($code_complexity, $valid_complexities)) {
                    $code_complexity = "Medium";
                }
                error_log("=== Complexity Analysis Results ===");
                error_log("Time: $time_complexity | Space: $space_complexity | Cost: $code_cost");
                error_log("CCN: $cyclomatic_complexity | NLOC: $lines_of_code | Functions: $function_count");
                error_log("Complexity Class: $complexity_class");
                error_log("Final Code Complexity: $code_complexity");
                return array(
                    "time_complexity" => $time_complexity,
                    "space_complexity" => $space_complexity,
                    "code_cost" => $code_cost,
                    "code_complexity" => $code_complexity,
                    "cyclomatic_complexity" => $cyclomatic_complexity,
                    "lines_of_code" => $lines_of_code,
                    "function_count" => $function_count,
                    "complexity_class" => $complexity_class
                );
            } else {
                error_log("Complexity: Command failed or empty output");
                if (!empty($stderr)) {
                    error_log("Complexity: Python errors: " . $stderr);
                }
            }
        } else {
            error_log("Complexity: Failed to start process with $python_bin");
        }
    }
    return array("time_complexity" => "Unknown", "space_complexity" => "Unknown", "code_cost" => 0, "code_complexity" => "Medium");
}
function generateKeywords($title, $description) {
    $python_commands = array(
        "python",
        "python3",
        "C:\\Users\\Parth Koli\\OneDrive\\Desktop\\taskflowmain\\taskflow\\venv\\Scripts\\python.exe"
    );
    $script = __DIR__ . DIRECTORY_SEPARATOR . "keyword_generator.py";
    foreach ($python_commands as $python_bin) {
        $cmd = escapeshellcmd($python_bin) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($title) . ' ' . escapeshellarg($description);
        $output = shell_exec($cmd);
        if ($output) {
            return trim($output);
        }
    }
    return "";
}
$company_id = $_SESSION['company_id'];
$admin_email = $_SESSION['admin_email'];
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
$task_filter = isset($_GET['task_filter']) ? $_GET['task_filter'] : 'all';
$analytics_employee = isset($_GET['analytics_employee']) ? $_GET['analytics_employee'] : '';
$employee_sort = isset($_GET['employee_sort']) ? $_GET['employee_sort'] : '';
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_department'])) {
        $department_name = $_POST['department_name'];
        $department_code = $_POST['department_code'];
        $hashed_code = password_hash($department_code, PASSWORD_DEFAULT);
        $check_sql = "SELECT id FROM department WHERE department_name = ? AND company_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $department_name, $company_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $error_message = "Department name already exists!";
        } else {
            $sql = "INSERT INTO department (department_name, company_id, department_code, no_of_employees) VALUES (?, ?, ?, 0)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $department_name, $company_id, $hashed_code);
            if ($stmt->execute()) {
                $success_message = "Department created successfully!";
            } else {
                $error_message = "Error creating department: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
    if (isset($_POST['create_task'])) {
        $employee_email = $_POST['employee_email'];
        $task_title = $_POST['task_title'];
        $task_description = $_POST['task_description'];
        $required_skill = $_POST['required_skill'];
        $deadline = $_POST['deadline'];
        $task_priority = $_POST['task_priority'];
        $task_difficulty = $_POST['task_difficulty'];
        $task_type = $_POST['task_type'];
        $task_keywords = isset($_POST['task_keywords']) ? $_POST['task_keywords'] : '';
        $emp_sql = "SELECT id FROM employee_registration WHERE employee_email = ? AND company_id = ?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param("ss", $employee_email, $company_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        $employee = $emp_result->fetch_assoc();
        if ($employee) {
            $employee_id = $employee['id'];
            if ($task_type == 'documentation') {
                $sql = "INSERT INTO documentation_tasks (company_id, employee_id, task_title, task_description, task_keywords, required_skill, priority, difficulty, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sisssssss", $company_id, $employee_id, $task_title, $task_description, $task_keywords, $required_skill, $task_priority, $task_difficulty, $deadline);
            } else {
                $sql = "INSERT INTO coding_tasks (company_id, employee_id, task_title, task_description, required_skill, priority, difficulty, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sissssss", $company_id, $employee_id, $task_title, $task_description, $required_skill, $task_priority, $task_difficulty, $deadline);
            }
            if ($stmt->execute()) {
                $success_message = "Task created successfully!";
                if (sendTaskAssignmentEmail($employee_email, $task_title, $deadline, $task_priority, $task_difficulty)) {
                    $success_message .= " Notification email sent to employee.";
                } else {
                    $success_message .= " Failed to send notification email.";
                }
                $deadline_timestamp = strtotime($deadline);
                $current_time = time();
                if ($deadline_timestamp < $current_time) {
                    sendDeadlineReminderEmail($employee_email, $task_title, $deadline);
                }
            } else {
                $error_message = "Error creating task: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Employee not found!";
        }
        $emp_stmt->close();
    }
    if (isset($_POST['generate_keywords'])) {
        $task_title = $_POST['task_title'];
        $task_description = $_POST['task_description'];
        if (!empty($task_title) && !empty($task_description)) {
            $keywords = generateKeywords($task_title, $task_description);
            echo "<script>document.getElementById('task_keywords').value = '$keywords';</script>";
        }
    }
    if (isset($_POST['reassign_task'])) {
        $task_id = $_POST['task_id'];
        $task_type = $_POST['task_type'];
        $feedback_text = $_POST['feedback_text'];
        $new_deadline = $_POST['new_deadline'];
        if ($task_type == 'documentation') {
            $task_sql = "SELECT dt.doc_task_id, er.employee_email, dt.task_title, dt.deadline
                        FROM documentation_tasks dt
                        JOIN employee_registration er ON dt.employee_id = er.id
                        WHERE dt.doc_task_id = ?";
        } else {
            $task_sql = "SELECT ct.code_task_id, er.employee_email, ct.task_title, ct.deadline
                        FROM coding_tasks ct
                        JOIN employee_registration er ON ct.employee_id = er.id
                        WHERE ct.code_task_id = ?";
        }
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        $task = $task_result->fetch_assoc();
        if ($task) {
            if ($task_type == 'documentation') {
                $update_sql = "UPDATE documentation_tasks SET submitted_at = NULL, submitted_file = NULL, relevance_score = NULL, deadline = ?, no_of_reassignments = no_of_reassignments + 1, last_reassigned_at = NOW() WHERE doc_task_id = ?";
            } else {
                $update_sql = "UPDATE coding_tasks SET submitted_at = NULL, submitted_file = NULL, time_complexity = NULL, space_complexity = NULL, code_cost = NULL, relevance_score = NULL, deadline = ?, no_of_reassignments = no_of_reassignments + 1, last_reassigned_at = NOW() WHERE code_task_id = ?";
            }
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $new_deadline, $task_id);
            if ($update_stmt->execute()) {
                $null_value = NULL;
                $reassignment_sql = "INSERT INTO task_reassignment_feedback (task_type, doc_task_id, code_task_id, reassigned_to_employee_id, reassigned_by_employee_email, feedback_text) VALUES (?, ?, ?, ?, ?, ?)";
                $reassignment_stmt = $conn->prepare($reassignment_sql);
                if ($task_type == 'documentation') {
                    $reassignment_stmt->bind_param("siisss", $task_type, $task_id, $null_value, $task_id, $admin_email, $feedback_text);
                } else {
                    $reassignment_stmt->bind_param("siisss", $task_type, $null_value, $task_id, $task_id, $admin_email, $feedback_text);
                }
                $reassignment_stmt->execute();
                $reassignment_stmt->close();
                sendTaskReassignmentEmail($task['employee_email'], $task['task_title'], $new_deadline, $feedback_text);
                $success_message = "Task reassigned successfully! Feedback sent to employee.";
            } else {
                $error_message = "Error reassigning task: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
        $task_stmt->close();
    }
    if (isset($_POST['check_relevance'])) {
        $task_id = $_POST['task_id'];
        $task_sql = "SELECT dt.task_keywords, dt.submitted_file, er.employee_email
                    FROM documentation_tasks dt
                    JOIN employee_registration er ON dt.employee_id = er.id
                    WHERE dt.doc_task_id = ? AND dt.submitted_file IS NOT NULL";
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        if ($task_result->num_rows > 0) {
            $task = $task_result->fetch_assoc();
            $result_json = calculateRelevanceScore($task_id, $task['task_keywords'], $task['submitted_file']);
            $result_data = json_decode($result_json, true);
            $relevance_score = $result_data['relevance_score'] ?? 0;
            $update_sql = "UPDATE documentation_tasks SET relevance_score = ? WHERE doc_task_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $relevance_score, $task_id);
            $update_stmt->execute();
            $update_stmt->close();
            $update_perf_sql = "UPDATE employee_performance_summary SET
                avg_relevance_score = (
                    SELECT AVG(relevance_score)
                    FROM documentation_tasks
                    WHERE employee_id IN (SELECT id FROM employee_registration WHERE employee_email = ? AND company_id = ?)
                    AND relevance_score IS NOT NULL
                )
                WHERE employee_email = ? AND company_id = ?";
            $update_perf_stmt = $conn->prepare($update_perf_sql);
            $update_perf_stmt->bind_param("ssss", $task['employee_email'], $company_id, $task['employee_email'], $company_id);
            $update_perf_stmt->execute();
            $update_perf_stmt->close();
            $_SESSION['relevance_analysis'] = $result_json;
            $success_message = "Relevance analysis completed!";
        } else {
            $error_message = "No submitted file found for this task!";
        }
        $task_stmt->close();
    }
    if (isset($_POST['check_complexity'])) {
        $task_id = $_POST['task_id'];
        $task_sql = "SELECT ct.submitted_file, er.employee_email
                    FROM coding_tasks ct
                    JOIN employee_registration er ON ct.employee_id = er.id
                    WHERE ct.code_task_id = ? AND ct.submitted_file IS NOT NULL";
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        if ($task_result->num_rows > 0) {
            $task = $task_result->fetch_assoc();
            $complexity_data = calculateCodeComplexity($task_id, $task['submitted_file']);
            $time_complexity = $complexity_data['time_complexity'];
            $space_complexity = $complexity_data['space_complexity'];
            $code_cost = intval($complexity_data['code_cost']);
            $code_complexity = $complexity_data['code_complexity'];
            $cyclomatic_complexity = isset($complexity_data['cyclomatic_complexity']) ? floatval($complexity_data['cyclomatic_complexity']) : null;
            $lines_of_code = isset($complexity_data['lines_of_code']) ? intval($complexity_data['lines_of_code']) : null;
            $function_count = isset($complexity_data['function_count']) ? intval($complexity_data['function_count']) : null;
            $valid_complexities = array('Low', 'Medium', 'High');
            if (!in_array($code_complexity, $valid_complexities)) {
                $code_complexity = 'Medium';
            }
            $update_sql = "UPDATE coding_tasks SET
               time_complexity = ?,
               space_complexity = ?,
               code_cost = ?,
               code_complexity = ?,
               cyclomatic_complexity = ?,
               lines_of_code = ?,
               function_count = ?
               WHERE code_task_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssidiii",
                $time_complexity,
                $space_complexity,
                $code_cost,
                $code_complexity,
                $cyclomatic_complexity,
                $lines_of_code,
                $function_count,
                $task_id
            );
            if ($update_stmt->execute()) {
                $_SESSION['complexity_analysis'] = json_encode($complexity_data);
                $success_message = "Code complexity calculated! Time: $time_complexity, Space: $space_complexity, Cost: $code_cost, CCN: $cyclomatic_complexity";
            } else {
                $error_message = "Error updating complexity: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error_message = "No submitted file found for this task!";
        }
        $task_stmt->close();
    }
    if (isset($_POST['add_feedback'])) {
        $task_id = $_POST['task_id'];
        $task_type = $_POST['task_type'];
        $rating_quality = $_POST['rating_quality'];
        $rating_efficiency = $_POST['rating_efficiency'];
        $rating_teamwork = $_POST['rating_teamwork'];
        $feedback_text = $_POST['feedback_text'];
        if ($task_type == 'documentation') {
            $task_sql = "SELECT er.employee_email FROM documentation_tasks dt JOIN employee_registration er ON dt.employee_id = er.id WHERE dt.doc_task_id = ?";
        } else {
            $task_sql = "SELECT er.employee_email FROM coding_tasks ct JOIN employee_registration er ON ct.employee_id = er.id WHERE ct.code_task_id = ?";
        }
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        $task = $task_result->fetch_assoc();
        if ($task) {
            $check_feedback_sql = "SELECT id FROM task_feedback WHERE ";
            if ($task_type == 'documentation') {
                $check_feedback_sql .= "doc_task_id = ?";
            } else {
                $check_feedback_sql .= "code_task_id = ?";
            }
            $check_feedback_stmt = $conn->prepare($check_feedback_sql);
            $check_feedback_stmt->bind_param("i", $task_id);
            $check_feedback_stmt->execute();
            $check_feedback_result = $check_feedback_stmt->get_result();
            if ($check_feedback_result->num_rows > 0) {
                $feedback_sql = "UPDATE task_feedback SET feedback_text = ?, rating_quality = ?, rating_efficiency = ?, rating_teamwork = ? WHERE ";
                if ($task_type == 'documentation') {
                    $feedback_sql .= "doc_task_id = ?";
                } else {
                    $feedback_sql .= "code_task_id = ?";
                }
                $feedback_stmt = $conn->prepare($feedback_sql);
                $feedback_stmt->bind_param("siiii", $feedback_text, $rating_quality, $rating_efficiency, $rating_teamwork, $task_id);
            } else {
                $null_value = NULL;
                $feedback_sql = "INSERT INTO task_feedback (task_type, doc_task_id, code_task_id, employee_email, reviewer_email, feedback_text, rating_quality, rating_efficiency, rating_teamwork) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $feedback_stmt = $conn->prepare($feedback_sql);
                if ($task_type == 'documentation') {
                    $feedback_stmt->bind_param("siisssiii", $task_type, $task_id, $null_value, $task['employee_email'], $admin_email, $feedback_text, $rating_quality, $rating_efficiency, $rating_teamwork);
                } else {
                    $feedback_stmt->bind_param("siisssiii", $task_type, $null_value, $task_id, $task['employee_email'], $admin_email, $feedback_text, $rating_quality, $rating_efficiency, $rating_teamwork);
                }
            }
            if ($feedback_stmt->execute()) {
                $update_perf_sql = "UPDATE employee_performance_summary SET
                    avg_quality_rating = (SELECT AVG(rating_quality) FROM task_feedback WHERE employee_email = ?),
                    avg_efficiency_rating = (SELECT AVG(rating_efficiency) FROM task_feedback WHERE employee_email = ?),
                    avg_teamwork_rating = (SELECT AVG(rating_teamwork) FROM task_feedback WHERE employee_email = ?)
                    WHERE employee_email = ? AND company_id = ?";
                $update_perf_stmt = $conn->prepare($update_perf_sql);
                $update_perf_stmt->bind_param("sssss", $task['employee_email'], $task['employee_email'], $task['employee_email'], $task['employee_email'], $company_id);
                $update_perf_stmt->execute();
                $update_perf_stmt->close();
                $success_message = "Feedback " . ($check_feedback_result->num_rows > 0 ? "updated" : "added") . " successfully!";
            } else {
                $error_message = "Error " . ($check_feedback_result->num_rows > 0 ? "updating" : "adding") . " feedback: " . $feedback_stmt->error;
            }
            $feedback_stmt->close();
            $check_feedback_stmt->close();
        }
        $task_stmt->close();
    }
    if (isset($_POST['remove_employee'])) {
        $employee_email = $_POST['employee_email'];
        $department_name = $_POST['department_name'];
        $delete_sql = "DELETE FROM employees_in_department WHERE employee_email = ? AND department_name = ? AND company_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("sss", $employee_email, $department_name, $company_id);
        if ($delete_stmt->execute()) {
            $update_sql = "UPDATE department SET no_of_employees = no_of_employees - 1 WHERE department_name = ? AND company_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $department_name, $company_id);
            $update_stmt->execute();
            $update_stmt->close();
            $success_message = "Employee removed from department successfully!";
        } else {
            $error_message = "Error removing employee: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    }
    if (isset($_POST['add_employee_to_department'])) {
        $employee_email = $_POST['employee_email'];
        $department_name = $_POST['department_name'];
        $emp_sql = "SELECT employee_firstname, employee_lastname FROM employee_registration WHERE employee_email = ? AND company_id = ?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param("ss", $employee_email, $company_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        if ($emp_result->num_rows > 0) {
            $employee = $emp_result->fetch_assoc();
            $check_sql = "SELECT id FROM employees_in_department WHERE employee_email = ? AND company_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $employee_email, $company_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $error_message = "Employee is already in a department!";
            } else {
                $insert_sql = "INSERT INTO employees_in_department (employee_firstname, employee_lastname, employee_email, department_name, company_id) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssss", $employee['employee_firstname'], $employee['employee_lastname'], $employee_email, $department_name, $company_id);
                if ($insert_stmt->execute()) {
                    $update_sql = "UPDATE department SET no_of_employees = no_of_employees + 1 WHERE department_name = ? AND company_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $department_name, $company_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $success_message = "Employee added to department successfully!";
                } else {
                    $error_message = "Error adding employee to department: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        } else {
            $error_message = "Employee not found!";
        }
        $emp_stmt->close();
    }
    if (isset($_POST['remove_task'])) {
        $task_id = $_POST['task_id'];
        $task_type = $_POST['task_type'];
        if ($task_type == 'documentation') {
            $delete_sql = "DELETE FROM documentation_tasks WHERE doc_task_id = ?";
        } else {
            $delete_sql = "DELETE FROM coding_tasks WHERE code_task_id = ?";
        }
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $task_id);
        if ($delete_stmt->execute()) {
            $success_message = "Task removed successfully!";
        } else {
            $error_message = "Error removing task: " . $conn->error;
        }
        $delete_stmt->close();
    }
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: index.html');
        exit();
    }
}
$count_sql = "SELECT
    (SELECT COUNT(*) FROM department WHERE company_id = ?) as dept_count,
    (SELECT COUNT(*) FROM employees_in_department WHERE company_id = ?) as emp_count,
    (SELECT COUNT(*) FROM documentation_tasks WHERE company_id = ?) + (SELECT COUNT(*) FROM coding_tasks WHERE company_id = ?) as task_count,
    (SELECT COUNT(*) FROM documentation_tasks WHERE company_id = ? AND submitted_at IS NULL AND deadline >= NOW()) + (SELECT COUNT(*) FROM coding_tasks WHERE company_id = ? AND submitted_at IS NULL AND deadline >= NOW()) as pending_count,
    (SELECT COUNT(*) FROM documentation_tasks WHERE company_id = ? AND submitted_at IS NOT NULL) + (SELECT COUNT(*) FROM coding_tasks WHERE company_id = ? AND submitted_at IS NOT NULL) as completed_count,
    (SELECT COUNT(*) FROM documentation_tasks WHERE company_id = ? AND submitted_at IS NULL AND deadline < NOW()) + (SELECT COUNT(*) FROM coding_tasks WHERE company_id = ? AND submitted_at IS NULL AND deadline < NOW()) as overdue_count";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("ssssssssss", $company_id, $company_id, $company_id, $company_id, $company_id, $company_id, $company_id, $company_id, $company_id, $company_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
if ($count_row = $count_result->fetch_assoc()) {
    $dept_count = $count_row['dept_count'];
    $emp_count = $count_row['emp_count'];
    $task_count = $count_row['task_count'];
    $pending_count = $count_row['pending_count'];
    $completed_count = $count_row['completed_count'];
    $overdue_count = $count_row['overdue_count'];
}
$count_stmt->close();
$overdue_tasks_reminder_sql = "SELECT er.employee_email, dt.task_title, dt.deadline
                              FROM documentation_tasks dt
                              JOIN employee_registration er ON dt.employee_id = er.id
                              WHERE dt.company_id = ? AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                              UNION ALL
                              SELECT er.employee_email, ct.task_title, ct.deadline
                              FROM coding_tasks ct
                              JOIN employee_registration er ON ct.employee_id = er.id
                              WHERE ct.company_id = ? AND ct.submitted_at IS NULL AND ct.deadline < NOW()";
$overdue_reminder_stmt = $conn->prepare($overdue_tasks_reminder_sql);
$overdue_reminder_stmt->bind_param("ss", $company_id, $company_id);
$overdue_reminder_stmt->execute();
$overdue_reminder_result = $overdue_reminder_stmt->get_result();
while ($overdue_task = $overdue_reminder_result->fetch_assoc()) {
    sendDeadlineReminderEmail($overdue_task['employee_email'], $overdue_task['task_title'], $overdue_task['deadline']);
}
$overdue_reminder_stmt->close();
$pending_employees_sql = "SELECT er.employee_firstname, er.employee_lastname, er.employee_email
                         FROM employee_registration er
                         LEFT JOIN employees_in_department eid ON er.employee_email = eid.employee_email
                         WHERE er.company_id = ? AND eid.employee_email IS NULL";
$pending_employees_stmt = $conn->prepare($pending_employees_sql);
$pending_employees_stmt->bind_param("s", $company_id);
$pending_employees_stmt->execute();
$pending_employees = $pending_employees_stmt->get_result();
$filter_condition = "";
if ($task_filter == 'pending') {
    $filter_condition = " AND submitted_at IS NULL AND deadline >= NOW()";
} elseif ($task_filter == 'completed') {
    $filter_condition = " AND submitted_at IS NOT NULL";
} elseif ($task_filter == 'overdue') {
    $filter_condition = " AND submitted_at IS NULL AND deadline < NOW()";
}
$tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, er.employee_firstname, er.employee_lastname, dt.task_title, dt.deadline, dt.submitted_at, dt.submitted_file, dt.task_keywords, dt.relevance_score, NULL as time_complexity, NULL as space_complexity, NULL as code_cost, NULL as code_complexity, NULL as cyclomatic_complexity, NULL as lines_of_code, NULL as function_count
              FROM documentation_tasks dt
              JOIN employee_registration er ON dt.employee_id = er.id
              WHERE dt.company_id = ?" . $filter_condition . "
              UNION ALL
              SELECT 'coding' as task_type, ct.code_task_id as task_id, er.employee_firstname, er.employee_lastname, ct.task_title, ct.deadline, ct.submitted_at, ct.submitted_file, NULL as task_keywords, ct.relevance_score, ct.time_complexity, ct.space_complexity, ct.code_cost, ct.code_complexity, ct.cyclomatic_complexity, ct.lines_of_code, ct.function_count
              FROM coding_tasks ct
              JOIN employee_registration er ON ct.employee_id = er.id
              WHERE ct.company_id = ?" . $filter_condition;
$tasks_sql .= " ORDER BY deadline DESC";
$tasks_stmt = $conn->prepare($tasks_sql);
$tasks_stmt->bind_param("ss", $company_id, $company_id);
$tasks_stmt->execute();
$tasks_result = $tasks_stmt->get_result();
$employees_sql = "SELECT employee_email, employee_firstname, employee_lastname
                 FROM employees_in_department
                 WHERE company_id = ?";
$employees_stmt = $conn->prepare($employees_sql);
$employees_stmt->bind_param("s", $company_id);
$employees_stmt->execute();
$employees_result = $employees_stmt->get_result();
$employees_list = [];
while ($employee = $employees_result->fetch_assoc()) {
    $employees_list[] = $employee;
}
$employees_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow Lite - Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fa;
        }
        .relevance-modal, .complexity-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            overflow-y: auto;
        }
        .relevance-modal-content, .complexity-modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 12px;
            width: 700px;
            max-width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .relevance-modal-header, .complexity-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .relevance-modal-header h2, .complexity-modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        .relevance-modal-close, .complexity-modal-close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: 32px;
            height: 32px;
            line-height: 32px;
            text-align: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        .relevance-modal-close:hover, .complexity-modal-close:hover {
            background: rgba(255,255,255,0.2);
        }
        .relevance-modal-body, .complexity-modal-body {
            padding: 30px;
        }
        .relevance-metric, .complexity-metric {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .relevance-metric h3, .complexity-metric h3 {
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .relevance-score-big, .complexity-score-big {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        .score-excellent { color: #10b981; }
        .score-good { color: #3b82f6; }
        .score-fair { color: #f59e0b; }
        .score-poor { color: #ef4444; }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .metric-list {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
        }
        .metric-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .metric-list li {
            padding: 5px 0;
            color: #4b5563;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        header {
            background: #1f2937;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h1 {
            font-size: 20px;
        }
        nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        nav a {
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        nav a:hover {
            text-decoration: underline;
        }
        .profile-dropdown {
            position: relative;
            display: inline-block;
        }
        .profile-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
        }
        .dropdown-content p {
            padding: 12px 16px;
            margin: 0;
            color: #1f2937;
            border-bottom: 1px solid #eee;
        }
        .dropdown-content form {
            padding: 12px 16px;
        }
        .dropdown-content button {
            width: 100%;
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
        }
        .profile-dropdown:hover .dropdown-content {
            display: block;
        }
        .top-actions {
            background: #374151;
            padding: 10px;
            text-align: center;
        }
        .top-actions button {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 5px;
        }
        .top-actions button:hover {
            background: #059669;
        }
        main {
            padding: 20px;
        }
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0px 4px 10px rgba(0,0,0,0.15);
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #1f2937;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2563eb;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        .card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
        }
        form input, form textarea, form select, form button {
            width: 100%;
            margin-bottom: 10px;
            padding: 8px;
            box-sizing: border-box;
        }
        form button {
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        form button:hover {
            background: #1e40af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background: #f8f9fa;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .dept-section {
            margin-bottom: 30px;
        }
        .dept-header {
            background: #e5e7eb;
            padding: 10px;
            font-weight: bold;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .emp-list {
            background: #f9fafb;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .hidden {
            display: none;
        }
        .form-section {
            margin-bottom: 15px;
        }
        .form-section h3 {
            margin: 0 0 8px 0;
            color: #1f2937;
            font-size: 14px;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .file-link {
            color: #2563eb;
            text-decoration: none;
        }
        .file-link:hover {
            text-decoration: underline;
        }
        .remove-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .remove-btn:hover {
            background: #b91c1c;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .reassign-btn {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .reassign-btn:hover {
            background: #d97706;
        }
        .confirmation-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .confirmation-content {
            background-color: white;
            margin: 20% auto;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
            text-align: center;
        }
        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        .confirmation-buttons button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .confirm-btn {
            background: #dc2626;
            color: white;
        }
        .cancel-btn {
            background: #6b7280;
            color: white;
        }
        .analytics-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .analytics-btn:hover {
            background: #7c3aed;
        }
        .assign-task-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .assign-task-btn:hover {
            background: #059669;
        }
        .add-employee-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .add-employee-btn:hover {
            background: #059669;
        }
        .relevance-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .relevance-btn:hover {
            background: #2563eb;
        }
        .feedback-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .feedback-btn:hover {
            background: #7c3aed;
        }
        .remove-task-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
        }
        .remove-task-btn:hover {
            background: #c82333;
        }
        .task-filter {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .task-filter a {
            padding: 8px 15px;
            background: #e5e7eb;
            border-radius: 5px;
            text-decoration: none;
            color: #374151;
        }
        .task-filter a.active {
            background: #2563eb;
            color: white;
        }
        .rating-stars {
            display: flex;
            gap: 5px;
            margin: 5px 0;
        }
        .rating-stars select {
            width: auto;
            margin-right: 10px;
        }
        .relevance-score {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
            padding: 2px 6px;
            background: #f3f4f6;
            border-radius: 3px;
        }
        .analytics-dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .analytics-header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .analytics-header h2 {
            margin: 0;
            color: #1f2937;
        }
        .analytics-header button {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .analytics-header button:hover {
            background: #4b5563;
        }
        .analytics-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
        }
        .analytics-card h3 {
            margin-top: 0;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .metric-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }
        .metric-label {
            font-size: 14px;
            color: #6b7280;
            margin-top: 5px;
        }
        .chart-container {
            height: 250px;
            margin-top: 15px;
        }
        .feedback-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .feedback-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .feedback-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .feedback-date {
            font-size: 12px;
            color: #6b7280;
        }
        .feedback-ratings {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .rating-label {
            font-size: 12px;
            color: #6b7280;
        }
        .rating-value {
            font-weight: bold;
            color: #1f2937;
        }
        .feedback-text {
            font-size: 14px;
            color: #4b5563;
        }
        .analytics-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        .analytics-modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 1200px;
        }
        .export-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }
        .export-btn:hover {
            background: #059669;
        }
        .skills-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .skills-btn:hover {
            background: #2563eb;
        }
        .skills-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .skills-modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
        }
        .skills-list {
            margin-top: 15px;
        }
        .skill-item {
            background: #f8f9fa;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #3b82f6;
        }
        .task-type-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .task-type-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
        }
        .task-type-tab.active {
            border-bottom: 3px solid #2563eb;
            color: #2563eb;
        }
        .task-type-content {
            display: none;
        }
        .task-type-content.active {
            display: block;
        }
        .insights-list {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .insights-list h4 {
            margin-top: 0;
            color: #0c4a6e;
        }
        .insights-list ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .insights-list li {
            margin-bottom: 5px;
            color: #0c4a6e;
        }
        .recommended-badge {
            background: #10b981;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }
        @media (max-width: 768px) {
            .analytics-dashboard {
                grid-template-columns: 1fr;
            }
            .metric-grid {
                grid-template-columns: 1fr;
            }
        }
        .generate-keywords-btn {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 5px;
        }
        .generate-keywords-btn:hover {
            background: #4b5563;
        }
        .employee-filter {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .employee-filter a {
            padding: 8px 15px;
            background: #e5e7eb;
            border-radius: 5px;
            text-decoration: none;
            color: #374151;
            font-size: 14px;
        }
        .employee-filter a.active {
            background: #2563eb;
            color: white;
        }
        .radar-chart-container {
            height: 300px;
            margin-top: 20px;
        }
        .quadrant-chart-container {
            height: 300px;
            margin-top: 20px;
        }
        .trend-chart-container {
            height: 250px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <header>
        <h1>TaskFlow - Admin Dashboard</h1>
        <nav>
            <a onclick="showPage('dashboard')">Dashboard</a>
            <a onclick="showPage('create_task')">Create Task</a>
            <a onclick="showPage('manage_employees')">Manage Employees</a>
            <a onclick="showPage('performance')">Performance</a>
            <div class="profile-dropdown">
                <button class="profile-btn">Profile</button>
                <div class="dropdown-content">
                    <p>Logged in as:<br><strong><?php echo $admin_email; ?></strong></p>
                    <form method="POST">
                        <button type="submit" name="logout">Logout</button>
                    </form>
                </div>
            </div>
        </nav>
    </header>
    <div class="top-actions">
        <button onclick="openModal('deptModal')">Create Department</button>
    </div>
    <main>
        <?php if (!empty($success_message)): ?>
        <div class="message success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
        <div class="message error">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['relevance_analysis'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var analysisData = <?php echo $_SESSION['relevance_analysis']; ?>;
            showRelevanceAnalysis(analysisData);
            <?php unset($_SESSION['relevance_analysis']); ?>
        });
        </script>
        <?php endif; ?>
        <?php if (isset($_SESSION['complexity_analysis'])): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var analysisData = <?php echo $_SESSION['complexity_analysis']; ?>;
            showComplexityAnalysis(analysisData);
            <?php unset($_SESSION['complexity_analysis']); ?>
        });
        </script>
        <?php endif; ?>
        <div id="dashboard" class="page">
            <div class="dashboard-stats">
                <div class="stat-box" onclick="showDepartmentView()">
                    <h3>Departments</h3>
                    <div class="stat-number"><?php echo $dept_count; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskView('all')">
                    <h3>Total Tasks</h3>
                    <div class="stat-number"><?php echo $task_count; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskView('pending')">
                    <h3>Pending Tasks</h3>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskView('completed')">
                    <h3>Completed Tasks</h3>
                    <div class="stat-number"><?php echo $completed_count; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskView('overdue')">
                    <h3>Overdue Tasks</h3>
                    <div class="stat-number"><?php echo $overdue_count; ?></div>
                </div>
            </div>
            <?php if ($selected_department): ?>
            <div class="card">
                <div class="dept-header">
                    <h2>Department: <?php echo $selected_department; ?></h2>
                    <button onclick="showPage('dashboard')" style="background: #6b7280; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Back to Dashboard</button>
                </div>
                <div class="employee-filter">
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=" class="<?php echo $employee_sort == '' ? 'active' : ''; ?>">Default</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=completion_rate_high" class="<?php echo $employee_sort == 'completion_rate_high' ? 'active' : ''; ?>">Completion Rate (High to Low)</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=completion_rate_low" class="<?php echo $employee_sort == 'completion_rate_low' ? 'active' : ''; ?>">Completion Rate (Low to High)</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=on_time_rate" class="<?php echo $employee_sort == 'on_time_rate' ? 'active' : ''; ?>">On-Time Rate</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=overdue_tasks" class="<?php echo $employee_sort == 'overdue_tasks' ? 'active' : ''; ?>">Overdue Tasks</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=workload" class="<?php echo $employee_sort == 'workload' ? 'active' : ''; ?>">Workload</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=join_date_new" class="<?php echo $employee_sort == 'join_date_new' ? 'active' : ''; ?>">Newest</a>
                    <a href="?page=manage_employees&department=<?php echo urlencode($selected_department); ?>&employee_sort=join_date_old" class="<?php echo $employee_sort == 'join_date_old' ? 'active' : ''; ?>">Oldest</a>
                </div>
                <div class="emp-list">
                    <h3>Employees in this Department</h3>
                    <?php
                    $sort_sql = "";
                    switch ($employee_sort) {
                        case 'completion_rate_high':
                            $sort_sql = "ORDER BY completion_rate DESC";
                            break;
                        case 'completion_rate_low':
                            $sort_sql = "ORDER BY completion_rate ASC";
                            break;
                        case 'on_time_rate':
                            $sort_sql = "ORDER BY on_time_rate DESC";
                            break;
                        case 'overdue_tasks':
                            $sort_sql = "ORDER BY overdue_tasks DESC";
                            break;
                        case 'workload':
                            $sort_sql = "ORDER BY (total_tasks - completed_tasks) DESC";
                            break;
                        case 'join_date_new':
                            $sort_sql = "ORDER BY e.created_at DESC";
                            break;
                        case 'join_date_old':
                            $sort_sql = "ORDER BY e.created_at ASC";
                            break;
                        default:
                            $sort_sql = "ORDER BY e.employee_firstname, e.employee_lastname";
                    }
                    $dept_employees_sql = "
                        SELECT
                            e.employee_firstname, e.employee_lastname, e.employee_email,
                            COALESCE(eps.total_tasks, 0) as total_tasks,
                            COALESCE(eps.tasks_completed, 0) as completed_tasks,
                            COALESCE(eps.avg_quality_rating, 0) as avg_quality_rating,
                            (
                                SELECT COUNT(*)
                                FROM documentation_tasks dt
                                WHERE dt.employee_id = er.id AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                            ) +
                            (
                                SELECT COUNT(*)
                                FROM coding_tasks ct
                                WHERE ct.employee_id = er.id AND ct.submitted_at IS NULL AND ct.deadline < NOW()
                            ) as overdue_tasks,
                            (
                                SELECT COUNT(*)
                                FROM documentation_tasks dt
                                WHERE dt.employee_id = er.id
                            ) +
                            (
                                SELECT COUNT(*)
                                FROM coding_tasks ct
                                WHERE ct.employee_id = er.id
                            ) as total_tasks_count,
                            (
                                SELECT COUNT(*)
                                FROM documentation_tasks dt
                                WHERE dt.employee_id = er.id AND dt.submitted_at IS NOT NULL
                            ) +
                            (
                                SELECT COUNT(*)
                                FROM coding_tasks ct
                                WHERE ct.employee_id = er.id AND ct.submitted_at IS NOT NULL
                            ) as completed_tasks_count,
                            CASE
                                WHEN (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        WHERE dt.employee_id = er.id
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        WHERE ct.employee_id = er.id
                                    )
                                ) > 0
                                THEN ROUND(
                                    (
                                        (
                                            SELECT COUNT(*)
                                            FROM documentation_tasks dt
                                            WHERE dt.employee_id = er.id AND dt.submitted_at IS NOT NULL
                                        ) +
                                        (
                                            SELECT COUNT(*)
                                            FROM coding_tasks ct
                                            WHERE ct.employee_id = er.id AND ct.submitted_at IS NOT NULL
                                        )
                                    ) /
                                    (
                                        (
                                            SELECT COUNT(*)
                                            FROM documentation_tasks dt
                                            WHERE dt.employee_id = er.id
                                        ) +
                                        (
                                            SELECT COUNT(*)
                                            FROM coding_tasks ct
                                            WHERE ct.employee_id = er.id
                                        )
                                    ) * 100, 2
                                )
                                ELSE 0
                            END as completion_rate,
                            CASE
                                WHEN (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        WHERE dt.employee_id = er.id AND dt.submitted_at IS NOT NULL
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        WHERE ct.employee_id = er.id AND ct.submitted_at IS NOT NULL
                                    )
                                ) > 0
                                THEN ROUND(
                                    (
                                        (
                                            SELECT COUNT(*)
                                            FROM documentation_tasks dt
                                            WHERE dt.employee_id = er.id AND dt.submitted_at IS NOT NULL AND dt.submitted_at <= dt.deadline
                                        ) +
                                        (
                                            SELECT COUNT(*)
                                            FROM coding_tasks ct
                                            WHERE ct.employee_id = er.id AND ct.submitted_at IS NOT NULL AND ct.submitted_at <= ct.deadline
                                        )
                                    ) /
                                    (
                                        (
                                            SELECT COUNT(*)
                                            FROM documentation_tasks dt
                                            WHERE dt.employee_id = er.id AND dt.submitted_at IS NOT NULL
                                        ) +
                                        (
                                            SELECT COUNT(*)
                                            FROM coding_tasks ct
                                            WHERE ct.employee_id = er.id AND ct.submitted_at IS NOT NULL
                                        )
                                    ) * 100, 2
                                )
                                ELSE 0
                            END as on_time_rate
                        FROM employees_in_department e
                        JOIN employee_registration er ON e.employee_email = er.employee_email AND e.company_id = er.company_id
                        LEFT JOIN employee_performance_summary eps ON e.employee_email = eps.employee_email AND e.company_id = eps.company_id
                        WHERE e.department_name = ? AND e.company_id = ?
                        $sort_sql
                    ";
                    $dept_employees_stmt = $conn->prepare($dept_employees_sql);
                    $dept_employees_stmt->bind_param("ss", $selected_department, $company_id);
                    $dept_employees_stmt->execute();
                    $dept_employees_result = $dept_employees_stmt->get_result();
                    if ($dept_employees_result->num_rows > 0) {
                        while ($employee = $dept_employees_result->fetch_assoc()) {
                            echo "<div style='padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;'>";
                            echo "<div>";
                            echo "<strong>" . $employee['employee_firstname'] . " " . $employee['employee_lastname'] . "</strong> (" . $employee['employee_email'] . ")";
                            echo "<br><small>Completion: " . $employee['completion_rate'] . "% | On-Time: " . $employee['on_time_rate'] . "% | Overdue: " . $employee['overdue_tasks'] . "</small>";
                            echo "</div>";
                            echo "<div>";
                            echo "<button class='skills-btn' onclick='viewEmployeeSkills(\"" . $employee['employee_email'] . "\")'>View Skills</button>";
                            echo "<button class='assign-task-btn' onclick='assignTaskToEmployee(\"" . $employee['employee_email'] . "\")'>Assign Task</button>";
                            echo "<button class='analytics-btn' onclick='viewEmployeeAnalytics(\"" . $employee['employee_email'] . "\")'>View Analytics</button>";
                            echo "<button class='remove-btn' onclick='showRemoveConfirmation(\"" . $employee['employee_email'] . "\", \"" . $selected_department . "\")'>Remove</button>";
                            echo "</div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div style='padding: 10px; color: #666;'>No employees in this department</div>";
                    }
                    $dept_employees_stmt->close();
                    ?>
                </div>
            </div>
            <?php elseif ($task_filter != 'all'): ?>
            <div class="card">
                <div class="dept-header">
                    <h2><?php echo ucfirst($task_filter); ?> Tasks</h2>
                    <button onclick="showTaskView('all')" style="background: #6b7280; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Back to All Tasks</button>
                </div>
                <div class="task-list">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Task</th>
                                <th>Type</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            while ($task = $tasks_result->fetch_assoc()) {
                                $status = $task['submitted_at'] ? 'Completed' : 'Pending';
                                if (!$task['submitted_at'] && strtotime($task['deadline']) < time()) {
                                    $status = 'Overdue';
                                }
                                $file_link = $task['submitted_file'] ? '<a href="submitted_files/' . $task['submitted_file'] . '" class="file-link" target="_blank">Download</a>' : 'N/A';
                                $metrics_display = '';
                                if ($task['task_type'] == 'documentation' && $task['relevance_score']) {
                                    $metrics_display = '<span class="relevance-score">' . $task['relevance_score'] . '%</span>';
                                } elseif ($task['task_type'] == 'coding' && $task['time_complexity']) {
                                    $metrics_display = '<span class="relevance-score">T:' . $task['time_complexity'] . ' S:' . $task['space_complexity'] . '</span>';
                                }
                                echo "<tr>";
                                echo "<td>" . $task['employee_firstname'] . " " . $task['employee_lastname'] . "</td>";
                                echo "<td>" . htmlspecialchars($task['task_title']) . "</td>";
                                echo "<td>" . ucfirst($task['task_type']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($task['deadline'])) . "</td>";
                                echo "<td>" . $status . "</td>";
                                echo "<td class='action-buttons'>";
                                echo $file_link;
                                if ($task['submitted_at']) {
                                    echo "<button class='reassign-btn' onclick='openReassignModal(" . $task['task_id'] . ", \"" . $task['task_type'] . "\")'>Reassign</button>";
                                    if ($task['task_type'] == 'documentation') {
                                        echo "<button class='relevance-btn' onclick='checkRelevance(" . $task['task_id'] . ")'>Check Relevance</button>";
                                    } elseif ($task['task_type'] == 'coding') {
                                        echo "<button class='relevance-btn' onclick='checkComplexity(" . $task['task_id'] . ")'>Check Complexity</button>";
                                    }
                                    echo "<button class='feedback-btn' onclick='openFeedbackModal(" . $task['task_id'] . ", \"" . $task['task_type'] . "\")'>Add Feedback</button>";
                                    echo $metrics_display;
                                }
                                echo "<button class='remove-task-btn' onclick='confirmRemoveTask(" . $task['task_id'] . ", \"" . $task['task_type'] . "\", \"" . htmlspecialchars($task['task_title']) . "\")'>Remove</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="content-grid">
                <section class="card">
                    <h2>Department Overview</h2>
                    <?php
                    $dept_overview_sql = "SELECT department_name, no_of_employees FROM department WHERE company_id = ?";
                    $dept_overview_stmt = $conn->prepare($dept_overview_sql);
                    $dept_overview_stmt->bind_param("s", $company_id);
                    $dept_overview_stmt->execute();
                    $dept_overview_result = $dept_overview_stmt->get_result();
                    while ($dept = $dept_overview_result->fetch_assoc()) {
                        echo "<div style='padding: 10px; border-bottom: 1px solid #eee; cursor: pointer;' onclick='viewDepartment(\"" . $dept['department_name'] . "\")'>";
                        echo "<strong>" . $dept['department_name'] . "</strong><br>";
                        echo "Employees: " . $dept['no_of_employees'];
                        echo "</div>";
                    }
                    $dept_overview_stmt->close();
                    ?>
                </section>
                <section class="card">
                    <h2>Recent Tasks</h2>
                    <div class="task-filter">
                        <a href="?page=dashboard&task_filter=all" class="<?php echo $task_filter == 'all' ? 'active' : ''; ?>">All Tasks</a>
                        <a href="?page=dashboard&task_filter=pending" class="<?php echo $task_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?page=dashboard&task_filter=completed" class="<?php echo $task_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="?page=dashboard&task_filter=overdue" class="<?php echo $task_filter == 'overdue' ? 'active' : ''; ?>">Overdue</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Task</th>
                                <th>Type</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, er.employee_firstname, er.employee_lastname, dt.task_title, dt.deadline, dt.submitted_at, dt.submitted_file, dt.task_keywords, dt.relevance_score, NULL as time_complexity, NULL as space_complexity, NULL as code_cost, NULL as code_complexity, NULL as cyclomatic_complexity, NULL as lines_of_code, NULL as function_count
              FROM documentation_tasks dt
              JOIN employee_registration er ON dt.employee_id = er.id
              WHERE dt.company_id = ?
              UNION ALL
              SELECT 'coding' as task_type, ct.code_task_id as task_id, er.employee_firstname, er.employee_lastname, ct.task_title, ct.deadline, ct.submitted_at, ct.submitted_file, NULL as task_keywords, ct.relevance_score, ct.time_complexity, ct.space_complexity, ct.code_cost, ct.code_complexity, ct.cyclomatic_complexity, ct.lines_of_code, ct.function_count
              FROM coding_tasks ct
              JOIN employee_registration er ON ct.employee_id = er.id
              WHERE ct.company_id = ?
              ORDER BY deadline DESC LIMIT 5";
                            $recent_stmt = $conn->prepare($recent_tasks_sql);
                            $recent_stmt->bind_param("ss", $company_id, $company_id);
                            $recent_stmt->execute();
                            $recent_result = $recent_stmt->get_result();
                            while ($task = $recent_result->fetch_assoc()) {
                                $status = $task['submitted_at'] ? 'Completed' : 'Pending';
                                if (!$task['submitted_at'] && strtotime($task['deadline']) < time()) {
                                    $status = 'Overdue';
                                }
                                $file_link = $task['submitted_file'] ? '<a href="submitted_files/' . $task['submitted_file'] . '" class="file-link" target="_blank">Download</a>' : 'N/A';
                                $metrics_display = '';
                                if ($task['task_type'] == 'documentation' && $task['relevance_score']) {
                                    $metrics_display = '<span class="relevance-score">Relevance: ' . $task['relevance_score'] . '%</span>';
                                } elseif ($task['task_type'] == 'coding' && $task['time_complexity']) {
                                    $ccn_display = isset($task['cyclomatic_complexity']) ? ' | CCN:' . $task['cyclomatic_complexity'] : '';
                                    $nloc_display = isset($task['lines_of_code']) ? ' | NLOC:' . $task['lines_of_code'] : '';
                                    $metrics_display = '<span class="relevance-score" title="Time Complexity | Space Complexity | Code Cost | Cyclomatic Complexity | Lines of Code">
                                        T:' . $task['time_complexity'] . ' | S:' . $task['space_complexity'] . ' | Cost:' . ($task['code_cost'] ?? 0) . $ccn_display . $nloc_display . '
                                    </span>';
                                }
                                echo "<tr>";
                                echo "<td>" . $task['employee_firstname'] . " " . $task['employee_lastname'] . "</td>";
                                echo "<td>" . htmlspecialchars($task['task_title']) . "</td>";
                                echo "<td>" . ucfirst($task['task_type']) . "</td>";
                                echo "<td>" . date('Y-m-d', strtotime($task['deadline'])) . "</td>";
                                echo "<td>" . $status . "</td>";
                                echo "<td class='action-buttons'>";
                                echo $file_link;
                                if ($task['submitted_at']) {
                                    echo "<button class='reassign-btn' onclick='openReassignModal(" . $task['task_id'] . ", \"" . $task['task_type'] . "\")'>Reassign</button>";
                                    if ($task['task_type'] == 'documentation') {
                                        echo "<button class='relevance-btn' onclick='checkRelevance(" . $task['task_id'] . ")'>Check Relevance</button>";
                                    } elseif ($task['task_type'] == 'coding') {
                                        echo "<button class='relevance-btn' onclick='checkComplexity(" . $task['task_id'] . ")'>Check Complexity</button>";
                                    }
                                    echo "<button class='feedback-btn' onclick='openFeedbackModal(" . $task['task_id'] . ", \"" . $task['task_type'] . "\")'>Add Feedback</button>";
                                    echo $metrics_display;
                                }
                                echo "<button class='remove-task-btn' onclick='confirmRemoveTask(" . $task['task_id'] . ", \"" . $task['task_type'] . "\", \"" . htmlspecialchars($task['task_title']) . "\")'>Remove</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            $recent_stmt->close();
                            ?>
                        </tbody>
                    </table>
                </section>
            </div>
            <?php endif; ?>
        </div>
        <div id="create_task" class="page hidden">
            <section class="card">
                <h2>Create New Task</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="task_title" id="task_title" placeholder="Task Title" required>
                    <textarea name="task_description" id="task_description" placeholder="Task Description" required></textarea>
                    <button type="button" id="generate_keywords_btn" class="generate-keywords-btn" onclick="generateKeywords()">Generate Keywords</button>
                    <div class="form-section">
                        <h3>Task Type</h3>
                        <select name="task_type" id="taskTypeSelect" required onchange="updateTaskTypeFields()">
                            <option value="documentation">Documentation</option>
                            <option value="coding">Coding</option>
                        </select>
                    </div>
                    <div id="keywordsField" class="form-section">
                        <h3>Task Keywords (for documentation tasks)</h3>
                        <textarea name="task_keywords" id="task_keywords" placeholder="Enter keywords separated by commas"></textarea>
                    </div>
                    <div class="form-section">
                        <h3>Required Skill</h3>
                        <select name="required_skill" id="requiredSkillSelect" required onchange="updateEmployeeDropdown()">
                            <option value="">Select Skill...</option>
                            <?php
                            $skills_sql = "SELECT DISTINCT skills FROM employee_skills WHERE company_id = ?";
                            $skills_stmt = $conn->prepare($skills_sql);
                            $skills_stmt->bind_param("s", $company_id);
                            $skills_stmt->execute();
                            $skills_result = $skills_stmt->get_result();
                            $all_skills = array();
                            while ($skill_row = $skills_result->fetch_assoc()) {
                                $skills = explode(',', $skill_row['skills']);
                                foreach ($skills as $skill) {
                                    $skill = trim($skill);
                                    if (!empty($skill) && !in_array($skill, $all_skills)) {
                                        $all_skills[] = $skill;
                                        echo "<option value='" . $skill . "'>" . $skill . "</option>";
                                    }
                                }
                            }
                            $skills_stmt->close();
                            ?>
                        </select>
                    </div>
                    <div class="form-section">
                        <h3>Assign to Employee</h3>
                        <select name="employee_email" id="employeeSelect" required>
                            <option value="">Select Employee...</option>
                            <?php
                            $selected_skill = isset($_POST['required_skill']) ? $_POST['required_skill'] : '';
                            $employees_sql = "SELECT e.employee_email, e.employee_firstname, e.employee_lastname, es.skills
                                              FROM employees_in_department e
                                              LEFT JOIN employee_skills es ON e.employee_email = es.employee_email AND e.company_id = es.company_id
                                              WHERE e.company_id = ?";
                            $employees_stmt = $conn->prepare($employees_sql);
                            $employees_stmt->bind_param("s", $company_id);
                            $employees_stmt->execute();
                            $employees_result = $employees_stmt->get_result();
                            $employees = [];
                            while ($employee = $employees_result->fetch_assoc()) {
                                $skills = $employee['skills'] ? explode(',', $employee['skills']) : [];
                                $skills_text = implode(', ', array_map('trim', $skills));
                                $has_skill = in_array($selected_skill, array_map('trim', $skills));
                                $employees[] = [
                                    'email' => $employee['employee_email'],
                                    'name' => $employee['employee_firstname'] . " " . $employee['employee_lastname'],
                                    'skills' => $skills_text,
                                    'has_skill' => $has_skill
                                ];
                            }
                            usort($employees, function($a, $b) use ($selected_skill) {
                                if ($a['has_skill'] && !$b['has_skill']) return -1;
                                if (!$a['has_skill'] && $b['has_skill']) return 1;
                                return strcmp($a['name'], $b['name']);
                            });
                            foreach ($employees as $employee) {
                                echo "<option value='" . $employee['email'] . "' data-skills='" . htmlspecialchars($employee['skills']) . "'>" . $employee['name'] . " (" . $employee['email'] . ") [" . ($employee['skills'] ?: 'No skills') . "]</option>";
                            }
                            $employees_stmt->close();
                            ?>
                        </select>
                    </div>
                    <div class="form-section">
                        <h3>Task Priority</h3>
                        <select name="task_priority" required>
                            <option value="low">Low Priority</option>
                            <option value="medium" selected>Medium Priority</option>
                            <option value="high">High Priority</option>
                        </select>
                    </div>
                    <div class="form-section">
                        <h3>Task Difficulty</h3>
                        <select name="task_difficulty" required>
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                    <div class="form-section">
                        <h3>Deadline</h3>
                        <input type="datetime-local" name="deadline" required>
                    </div>
                    <button type="submit" name="create_task">Assign Task</button>
                </form>
            </section>
        </div>
        <div id="manage_employees" class="page hidden">
            <section class="card">
                <h2>Manage Employees</h2>
                <div class="employee-filter">
                    <a href="?page=manage_employees&employee_sort=" class="<?php echo $employee_sort == '' ? 'active' : ''; ?>">Default</a>
                    <a href="?page=manage_employees&employee_sort=completion_rate_high" class="<?php echo $employee_sort == 'completion_rate_high' ? 'active' : ''; ?>">Completion Rate (High to Low)</a>
                    <a href="?page=manage_employees&employee_sort=completion_rate_low" class="<?php echo $employee_sort == 'completion_rate_low' ? 'active' : ''; ?>">Completion Rate (Low to High)</a>
                    <a href="?page=manage_employees&employee_sort=on_time_rate" class="<?php echo $employee_sort == 'on_time_rate' ? 'active' : ''; ?>">On-Time Rate</a>
                    <a href="?page=manage_employees&employee_sort=overdue_tasks" class="<?php echo $employee_sort == 'overdue_tasks' ? 'active' : ''; ?>">Overdue Tasks</a>
                    <a href="?page=manage_employees&employee_sort=workload" class="<?php echo $employee_sort == 'workload' ? 'active' : ''; ?>">Workload</a>
                    <a href="?page=manage_employees&employee_sort=join_date_new" class="<?php echo $employee_sort == 'join_date_new' ? 'active' : ''; ?>">Newest</a>
                    <a href="?page=manage_employees&employee_sort=join_date_old" class="<?php echo $employee_sort == 'join_date_old' ? 'active' : ''; ?>">Oldest</a>
                </div>
                <?php
                $sort_sql = "";
                switch ($employee_sort) {
                    case 'completion_rate_high':
                        $sort_sql = "ORDER BY completion_rate DESC";
                        break;
                    case 'completion_rate_low':
                        $sort_sql = "ORDER BY completion_rate ASC";
                        break;
                    case 'on_time_rate':
                        $sort_sql = "ORDER BY on_time_rate DESC";
                        break;
                    case 'overdue_tasks':
                        $sort_sql = "ORDER BY overdue_tasks DESC";
                        break;
                    case 'workload':
                        $sort_sql = "ORDER BY (total_tasks - completed_tasks) DESC";
                        break;
                    case 'join_date_new':
                        $sort_sql = "ORDER BY er.created_at DESC";
                        break;
                    case 'join_date_old':
                        $sort_sql = "ORDER BY er.created_at ASC";
                        break;
                    default:
                        $sort_sql = "ORDER BY e.department_name, e.employee_firstname, e.employee_lastname";
                }
                $dept_emp_sql = "
                    SELECT
                        d.department_name,
                        e.employee_firstname, e.employee_lastname, e.employee_email, e.department_name as emp_department,
                        COALESCE(eps.total_tasks, 0) as total_tasks,
                        COALESCE(eps.tasks_completed, 0) as completed_tasks,
                        COALESCE(eps.avg_quality_rating, 0) as avg_quality_rating,
                        (
                            SELECT COUNT(*)
                            FROM documentation_tasks dt
                            JOIN employee_registration er2 ON dt.employee_id = er2.id
                            WHERE er2.employee_email = e.employee_email AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                        ) +
                        (
                            SELECT COUNT(*)
                            FROM coding_tasks ct
                            JOIN employee_registration er2 ON ct.employee_id = er2.id
                            WHERE er2.employee_email = e.employee_email AND ct.submitted_at IS NULL AND ct.deadline < NOW()
                        ) as overdue_tasks,
                        CASE
                            WHEN (
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er2 ON dt.employee_id = er2.id
                                    WHERE er2.employee_email = e.employee_email
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er2 ON ct.employee_id = er2.id
                                    WHERE er2.employee_email = e.employee_email
                                )
                            ) > 0
                            THEN ROUND(
                                (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        JOIN employee_registration er2 ON dt.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        JOIN employee_registration er2 ON ct.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL
                                    )
                                ) /
                                (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        JOIN employee_registration er2 ON dt.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        JOIN employee_registration er2 ON ct.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email
                                    )
                                ) * 100, 2
                            )
                            ELSE 0
                        END as completion_rate,
                        CASE
                            WHEN (
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er2 ON dt.employee_id = er2.id
                                    WHERE er2.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er2 ON ct.employee_id = er2.id
                                    WHERE er2.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL
                                )
                            ) > 0
                            THEN ROUND(
                                (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        JOIN employee_registration er2 ON dt.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL AND dt.submitted_at <= dt.deadline
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        JOIN employee_registration er2 ON ct.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL AND ct.submitted_at <= ct.deadline
                                    )
                                ) /
                                (
                                    (
                                        SELECT COUNT(*)
                                        FROM documentation_tasks dt
                                        JOIN employee_registration er2 ON dt.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL
                                    ) +
                                    (
                                        SELECT COUNT(*)
                                        FROM coding_tasks ct
                                        JOIN employee_registration er2 ON ct.employee_id = er2.id
                                        WHERE er2.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL
                                    )
                                ) * 100, 2
                            )
                            ELSE 0
                        END as on_time_rate
                    FROM department d
                    LEFT JOIN employees_in_department e ON d.department_name = e.department_name AND d.company_id = e.company_id
                    LEFT JOIN employee_registration er ON e.employee_email = er.employee_email AND e.company_id = er.company_id
                    LEFT JOIN employee_performance_summary eps ON e.employee_email = eps.employee_email AND e.company_id = eps.company_id
                    WHERE d.company_id = ?
                    $sort_sql
                ";
                $dept_emp_stmt = $conn->prepare($dept_emp_sql);
                $dept_emp_stmt->bind_param("s", $company_id);
                $dept_emp_stmt->execute();
                $dept_emp_result = $dept_emp_stmt->get_result();
                $current_department = null;
                while ($row = $dept_emp_result->fetch_assoc()) {
                    if ($row['department_name'] != $current_department) {
                        if ($current_department !== null) {
                            echo "</div></div>";
                        }
                        $current_department = $row['department_name'];
                        echo "<div class='dept-section'>";
                        echo "<div class='dept-header'>" . $current_department . "</div>";
                        echo "<div class='emp-list'>";
                    }
                    if ($row['employee_email']) {
                        echo "<div style='padding: 5px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;'>";
                        echo "<div>";
                        echo "<strong>" . $row['employee_firstname'] . " " . $row['employee_lastname'] . "</strong> (" . $row['employee_email'] . ")";
                        echo "<br><small>Completion: " . $row['completion_rate'] . "% | On-Time: " . $row['on_time_rate'] . "% | Overdue: " . $row['overdue_tasks'] . "</small>";
                        echo "</div>";
                        echo "<div>";
                        echo "<button class='skills-btn' onclick='viewEmployeeSkills(\"" . $row['employee_email'] . "\")'>View Skills</button>";
                        echo "<button class='assign-task-btn' onclick='assignTaskToEmployee(\"" . $row['employee_email'] . "\")'>Assign Task</button>";
                        echo "<button class='analytics-btn' onclick='viewEmployeeAnalytics(\"" . $row['employee_email'] . "\")'>View Analytics</button>";
                        echo "<button class='remove-btn' onclick='showRemoveConfirmation(\"" . $row['employee_email'] . "\", \"" . $row['department_name'] . "\")'>Remove</button>";
                        echo "</div>";
                        echo "</div>";
                    }
                }
                if ($current_department !== null) {
                    echo "</div></div>";
                }
                $dept_emp_stmt->close();
                ?>
            </section>
            <section class="card">
                <h2>Pending Employee Requests</h2>
                <?php
                if ($pending_employees->num_rows > 0) {
                    while ($employee = $pending_employees->fetch_assoc()) {
                        echo "<div style='padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;'>";
                        echo "<div>" . $employee['employee_firstname'] . " " . $employee['employee_lastname'] . " (" . $employee['employee_email'] . ")</div>";
                        echo "<button class='add-employee-btn' onclick='openAddEmployeeModal(\"" . $employee['employee_email'] . "\")'>Add to Department</button>";
                        echo "</div>";
                    }
                } else {
                    echo "<div style='padding: 10px; color: #666;'>No pending employee requests</div>";
                }
                $pending_employees->close();
                ?>
            </section>
        </div>
        <div id="performance" class="page hidden">
            <section class="card">
                <h2>Employee Performance</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Total Tasks</th>
                            <th>Tasks Completed</th>
                            <th>Pending Tasks</th>
                            <th>Overdue Tasks</th>
                            <th>Completion Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $performance_sql = "
                            SELECT
                                e.employee_email, e.employee_firstname, e.employee_lastname, e.department_name,
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er ON dt.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er ON ct.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email
                                ) as total_tasks,
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er ON dt.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er ON ct.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL
                                ) as tasks_completed,
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er ON dt.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND dt.submitted_at IS NULL AND dt.deadline >= NOW()
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er ON ct.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND ct.submitted_at IS NULL AND ct.deadline >= NOW()
                                ) as pending_tasks,
                                (
                                    SELECT COUNT(*)
                                    FROM documentation_tasks dt
                                    JOIN employee_registration er ON dt.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                                ) +
                                (
                                    SELECT COUNT(*)
                                    FROM coding_tasks ct
                                    JOIN employee_registration er ON ct.employee_id = er.id
                                    WHERE er.employee_email = e.employee_email AND ct.submitted_at IS NULL AND ct.deadline < NOW()
                                ) as overdue_tasks,
                                CASE
                                    WHEN (
                                        (
                                            SELECT COUNT(*)
                                            FROM documentation_tasks dt
                                            JOIN employee_registration er ON dt.employee_id = er.id
                                            WHERE er.employee_email = e.employee_email
                                        ) +
                                        (
                                            SELECT COUNT(*)
                                            FROM coding_tasks ct
                                            JOIN employee_registration er ON ct.employee_id = er.id
                                            WHERE er.employee_email = e.employee_email
                                        )
                                    ) > 0
                                    THEN ROUND(
                                        (
                                            (
                                                SELECT COUNT(*)
                                                FROM documentation_tasks dt
                                                JOIN employee_registration er ON dt.employee_id = er.id
                                                WHERE er.employee_email = e.employee_email AND dt.submitted_at IS NOT NULL
                                            ) +
                                            (
                                                SELECT COUNT(*)
                                                FROM coding_tasks ct
                                                JOIN employee_registration er ON ct.employee_id = er.id
                                                WHERE er.employee_email = e.employee_email AND ct.submitted_at IS NOT NULL
                                            )
                                        ) /
                                        (
                                            (
                                                SELECT COUNT(*)
                                                FROM documentation_tasks dt
                                                JOIN employee_registration er ON dt.employee_id = er.id
                                                WHERE er.employee_email = e.employee_email
                                            ) +
                                            (
                                                SELECT COUNT(*)
                                                FROM coding_tasks ct
                                                JOIN employee_registration er ON ct.employee_id = er.id
                                                WHERE er.employee_email = e.employee_email
                                            )
                                        ) * 100, 2
                                    )
                                    ELSE 0
                                END as completion_rate
                            FROM employees_in_department e
                            WHERE e.company_id = ?
                        ";
                        $perf_stmt = $conn->prepare($performance_sql);
                        $perf_stmt->bind_param("s", $company_id);
                        $perf_stmt->execute();
                        $perf_result = $perf_stmt->get_result();
                        while ($perf = $perf_result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $perf['employee_firstname'] . " " . $perf['employee_lastname'] . "</td>";
                            echo "<td>" . $perf['department_name'] . "</td>";
                            echo "<td>" . $perf['total_tasks'] . "</td>";
                            echo "<td>" . $perf['tasks_completed'] . "</td>";
                            echo "<td>" . $perf['pending_tasks'] . "</td>";
                            echo "<td>" . $perf['overdue_tasks'] . "</td>";
                            echo "<td>" . $perf['completion_rate'] . "%</td>";
                            echo "<td><button class='analytics-btn' onclick='viewEmployeeAnalytics(\"" . $perf['employee_email'] . "\")'>View Analytics</button></td>";
                            echo "</tr>";
                        }
                        $perf_stmt->close();
                        ?>
                    </tbody>
                </table>
            </section>
        </div>
    </main>
    <div id="analyticsModal" class="analytics-modal">
        <div class="analytics-modal-content">
            <div class="analytics-header">
                <h2 id="analyticsTitle">Employee Analytics</h2>
                <div>
                    <button class="export-btn" onclick="exportAnalytics()">Export Data</button>
                    <button onclick="closeModal('analyticsModal')">Close</button>
                </div>
            </div>
            <div class="analytics-dashboard" id="analyticsDashboard">
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Overall Performance</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value" id="totalTasksMetric">0</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="completedTasksMetric">0</div>
                            <div class="metric-label">Completed Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="completionRateMetric">0%</div>
                            <div class="metric-label">Completion Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="onTimeRateMetric">0%</div>
                            <div class="metric-label">On-Time Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="avgRelevanceMetric">0%</div>
                            <div class="metric-label">Avg Relevance Score</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="avgQualityMetric">0/5</div>
                            <div class="metric-label">Avg Quality Rating</div>
                        </div>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Documentation Tasks</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value" id="docTotalTasksMetric">0</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="docCompletedTasksMetric">0</div>
                            <div class="metric-label">Completed</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="docOnTimeTasksMetric">0</div>
                            <div class="metric-label">On-Time Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="docLateTasksMetric">0</div>
                            <div class="metric-label">Late Tasks</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="docDifficultyChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Coding Tasks</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value" id="codeTotalTasksMetric">0</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="codeCompletedTasksMetric">0</div>
                            <div class="metric-label">Completed</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="codeOnTimeTasksMetric">0</div>
                            <div class="metric-label">On-Time Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value" id="codeLateTasksMetric">0</div>
                            <div class="metric-label">Late Tasks</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="codeDifficultyChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Performance Trend</h3>
                    <div class="trend-chart-container">
                        <canvas id="performanceTrendChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Skill Radar Chart</h3>
                    <div class="radar-chart-container">
                        <canvas id="skillRadarChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Efficiency vs Quality Quadrant</h3>
                    <div class="quadrant-chart-container">
                        <canvas id="efficiencyQualityQuadrant"></canvas>
                    </div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Recent Feedback</h3>
                    <div class="feedback-list" id="feedbackList"></div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <div class="insights-list">
                        <h4>Performance Insights</h4>
                        <ul id="insightsList"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="skillsModal" class="skills-modal">
        <div class="skills-modal-content">
            <span class="close" onclick="closeModal('skillsModal')">&times;</span>
            <h2>Employee Skills</h2>
            <div id="skillsEmployeeInfo" style="margin-bottom: 15px;"></div>
            <div class="skills-list" id="skillsList">
            </div>
        </div>
    </div>
    <div id="deptModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deptModal')">&times;</span>
            <h2>Create New Department</h2>
            <form method="POST">
                <input type="text" name="department_name" placeholder="Department Name" required>
                <input type="text" name="department_code" placeholder="Department Code" required>
                <button type="submit" name="create_department">Create Department</button>
            </form>
        </div>
    </div>
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addEmployeeModal')">&times;</span>
            <h2>Add Employee to Department</h2>
            <form method="POST">
                <input type="hidden" name="employee_email" id="add_employee_email">
                <div class="form-section">
                    <h3>Select Department</h3>
                    <select name="department_name" required>
                        <option value="">Select Department...</option>
                        <?php
                        $dept_select_sql = "SELECT department_name FROM department WHERE company_id = ?";
                        $dept_select_stmt = $conn->prepare($dept_select_sql);
                        $dept_select_stmt->bind_param("s", $company_id);
                        $dept_select_stmt->execute();
                        $dept_select_result = $dept_select_stmt->get_result();
                        while ($dept = $dept_select_result->fetch_assoc()) {
                            echo "<option value='" . $dept['department_name'] . "'>" . $dept['department_name'] . "</option>";
                        }
                        $dept_select_stmt->close();
                        ?>
                    </select>
                </div>
                <button type="submit" name="add_employee_to_department">Add Employee</button>
            </form>
        </div>
    </div>
    <div id="reassignModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('reassignModal')">&times;</span>
            <h2>Reassign Task</h2>
            <form method="POST">
                <input type="hidden" name="task_id" id="reassign_task_id">
                <input type="hidden" name="task_type" id="reassign_task_type">
                <div class="form-section">
                    <h3>Feedback</h3>
                    <textarea name="feedback_text" placeholder="Provide feedback for improvement" required></textarea>
                </div>
                <div class="form-section">
                    <h3>New Deadline</h3>
                    <input type="datetime-local" name="new_deadline" required>
                </div>
                <button type="submit" name="reassign_task">Reassign Task</button>
            </form>
        </div>
    </div>
    <div id="relevanceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('relevanceModal')">&times;</span>
            <h2>Check Relevance</h2>
            <form method="POST">
                <input type="hidden" name="task_id" id="relevance_task_id">
                <p>This will calculate how well the submitted task matches the required keywords.</p>
                <button type="submit" name="check_relevance">Check Relevance</button>
            </form>
        </div>
    </div>
    <div id="complexityModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('complexityModal')">&times;</span>
            <h2>Check Code Complexity</h2>
            <form method="POST">
                <input type="hidden" name="task_id" id="complexity_task_id">
                <p>This will analyze the code complexity based on lines of code, branches, and functions.</p>
                <button type="submit" name="check_complexity">Check Complexity</button>
            </form>
        </div>
    </div>
    <div id="feedbackModal" class="modal">
        <div class="modal-content" style="width: 600px; max-width: 90%; margin: 5% auto;">
            <span class="close" onclick="closeModal('feedbackModal')">&times;</span>
            <h2>Add Feedback</h2>
            <form method="POST">
                <input type="hidden" name="task_id" id="feedback_task_id">
                <input type="hidden" name="task_type" id="feedback_task_type">
                <div class="form-section">
                    <h3>Quality Rating</h3>
                    <select name="rating_quality" required>
                        <option value="1">1 - Poor</option>
                        <option value="2">2 - Fair</option>
                        <option value="3" selected>3 - Good</option>
                        <option value="4">4 - Very Good</option>
                        <option value="5">5 - Excellent</option>
                    </select>
                </div>
                <div class="form-section">
                    <h3>Efficiency Rating</h3>
                    <select name="rating_efficiency" required>
                        <option value="1">1 - Poor</option>
                        <option value="2">2 - Fair</option>
                        <option value="3" selected>3 - Good</option>
                        <option value="4">4 - Very Good</option>
                        <option value="5">5 - Excellent</option>
                    </select>
                </div>
                <div class="form-section">
                    <h3>Teamwork Rating</h3>
                    <select name="rating_teamwork" required>
                        <option value="1">1 - Poor</option>
                        <option value="2">2 - Fair</option>
                        <option value="3" selected>3 - Good</option>
                        <option value="4">4 - Very Good</option>
                        <option value="5">5 - Excellent</option>
                    </select>
                </div>
                <div class="form-section">
                    <h3>Feedback Comments</h3>
                    <textarea name="feedback_text" placeholder="Provide detailed feedback..." required style="min-height: 100px;"></textarea>
                </div>
                <button type="submit" name="add_feedback" style="margin-top: 15px;">Submit Feedback</button>
            </form>
        </div>
    </div>
    <div id="confirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <h3>Confirm Removal</h3>
            <p>Are you sure you want to remove this employee from the department?</p>
            <form method="POST" id="removeForm">
                <input type="hidden" name="employee_email" id="remove_employee_email">
                <input type="hidden" name="department_name" id="remove_department_name">
                <div class="confirmation-buttons">
                    <button type="button" class="cancel-btn" onclick="closeConfirmation()">Cancel</button>
                    <button type="submit" class="confirm-btn" name="remove_employee">Confirm</button>
                </div>
            </form>
        </div>
    </div>
    <div id="relevanceAnalysisModal" class="relevance-modal">
        <div class="relevance-modal-content">
            <div class="relevance-modal-header">
                <h2>📊 Relevance Analysis Report</h2>
                <button class="relevance-modal-close" onclick="closeRelevanceModal()">×</button>
            </div>
            <div class="relevance-modal-body">
                <div class="relevance-score-big" id="relevanceScoreBig">0%</div>
                <div class="relevance-metric">
                    <h3>
                        Grammar Errors
                        <span class="metric-value" id="grammarErrorsValue">0</span>
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                        Number of grammatical issues detected
                    </p>
                </div>
                <div class="relevance-metric">
                    <h3>
                        Random Text Confidence
                        <span class="metric-value" id="randomTextValue">0%</span>
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                        Likelihood of content being random or gibberish
                    </p>
                </div>
                <div class="relevance-metric" id="badWordsSection" style="display: none;">
                    <h3>⚠️ Inappropriate Language Detected</h3>
                    <div class="metric-list">
                        <ul id="badWordsList"></ul>
                    </div>
                </div>
                <div class="relevance-metric" id="missingKeywordsSection">
                    <h3>Missing Keywords</h3>
                    <div class="metric-list">
                        <ul id="missingKeywordsList"></ul>
                    </div>
                </div>
                <div class="relevance-metric">
                    <h3>Analysis Details</h3>
                    <p id="analysisDetails" style="margin: 10px 0 0 0; color: #4b5563;"></p>
                </div>
            </div>
        </div>
    </div>
    <div id="complexityAnalysisModal" class="complexity-modal">
        <div class="complexity-modal-content">
            <div class="complexity-modal-header">
                <h2>⚡ Code Complexity Analysis</h2>
                <button class="complexity-modal-close" onclick="closeComplexityModal()">×</button>
            </div>
            <div class="complexity-modal-body">
                <div class="complexity-score-big" id="complexityScoreBig">0</div>
                <div class="complexity-metric">
                    <h3>
                        Time Complexity
                        <span class="metric-value" id="timeComplexityValue">Unknown</span>
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                        How execution time grows with input size
                    </p>
                </div>
                <div class="complexity-metric">
                    <h3>
                        Space Complexity
                        <span class="metric-value" id="spaceComplexityValue">Unknown</span>
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                        How memory usage grows with input size
                    </p>
                </div>
                <div class="complexity-metric">
                    <h3>
                        Code Complexity Level
                        <span class="metric-value" id="codeComplexityValue">Medium</span>
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6b7280; font-size: 14px;">
                        Overall complexity assessment
                    </p>
                </div>
                <div class="complexity-metric">
                    <h3>Analysis Summary</h3>
                    <p id="complexityAnalysisDetails" style="margin: 10px 0 0 0; color: #4b5563;">
                        Code complexity analysis completed based on structure, nesting, and operations.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script>
        let currentAnalyticsData = null;
        let chartInstances = {};
        function showPage(pageId) {
            const pages = document.querySelectorAll('.page');
            pages.forEach(page => page.classList.add('hidden'));
            document.getElementById(pageId).classList.remove('hidden');
        }
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        function openReassignModal(taskId, taskType) {
            document.getElementById('reassign_task_id').value = taskId;
            document.getElementById('reassign_task_type').value = taskType;
            openModal('reassignModal');
        }
        function checkRelevance(taskId) {
            if (confirm('This will analyze the submitted document for relevance, grammar, and content quality. Continue?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                var taskIdInput = document.createElement('input');
                taskIdInput.type = 'hidden';
                taskIdInput.name = 'task_id';
                taskIdInput.value = taskId;
                var checkInput = document.createElement('input');
                checkInput.type = 'hidden';
                checkInput.name = 'check_relevance';
                checkInput.value = '1';
                form.appendChild(taskIdInput);
                form.appendChild(checkInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function checkComplexity(taskId) {
            if (confirm('This will analyze the code complexity, time complexity, and space complexity. Continue?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                var taskIdInput = document.createElement('input');
                taskIdInput.type = 'hidden';
                taskIdInput.name = 'task_id';
                taskIdInput.value = taskId;
                var checkInput = document.createElement('input');
                checkInput.type = 'hidden';
                checkInput.name = 'check_complexity';
                checkInput.value = '1';
                form.appendChild(taskIdInput);
                form.appendChild(checkInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function showRelevanceAnalysis(data) {
            const score = data.relevance_score || 0;
            const scoreBig = document.getElementById('relevanceScoreBig');
            scoreBig.textContent = score + '%';
            if (score >= 80) {
                scoreBig.className = 'relevance-score-big score-excellent';
            } else if (score >= 60) {
                scoreBig.className = 'relevance-score-big score-good';
            } else if (score >= 40) {
                scoreBig.className = 'relevance-score-big score-fair';
            } else {
                scoreBig.className = 'relevance-score-big score-poor';
            }
            document.getElementById('grammarErrorsValue').textContent = data.grammar_errors || 0;
            document.getElementById('randomTextValue').textContent = (data.random_text_confidence || 0) + '%';
            const badWordsSection = document.getElementById('badWordsSection');
            const badWordsList = document.getElementById('badWordsList');
            if (data.bad_words && data.bad_words.length > 0) {
                badWordsSection.style.display = 'block';
                badWordsList.innerHTML = '';
                data.bad_words.forEach(word => {
                    const li = document.createElement('li');
                    li.textContent = word;
                    li.style.color = '#dc2626';
                    badWordsList.appendChild(li);
                });
            } else {
                badWordsSection.style.display = 'none';
            }
            const missingKeywordsList = document.getElementById('missingKeywordsList');
            missingKeywordsList.innerHTML = '';
            if (data.missing_keywords && data.missing_keywords.length > 0) {
                data.missing_keywords.forEach(keyword => {
                    const li = document.createElement('li');
                    li.textContent = keyword;
                    missingKeywordsList.appendChild(li);
                });
            } else {
                missingKeywordsList.innerHTML = '<li style="color: #10b981;">All keywords found!</li>';
            }
            document.getElementById('analysisDetails').textContent = data.analysis_details || 'Analysis completed';
            openModal('relevanceAnalysisModal');
        }
        function showComplexityAnalysis(data) {
            const codeCost = data.code_cost || 0;
            const scoreBig = document.getElementById('complexityScoreBig');
            scoreBig.textContent = 'Cost: ' + codeCost;
            if (codeCost >= 70) {
                scoreBig.className = 'complexity-score-big score-poor';
            } else if (codeCost >= 30) {
                scoreBig.className = 'complexity-score-big score-fair';
            } else {
                scoreBig.className = 'complexity-score-big score-excellent';
            }
            document.getElementById('timeComplexityValue').textContent = data.time_complexity || 'Unknown';
            document.getElementById('spaceComplexityValue').textContent = data.space_complexity || 'Unknown';
            document.getElementById('codeComplexityValue').textContent = data.code_complexity || 'Medium';
            const ccn = data.cyclomatic_complexity || 'N/A';
            const nloc = data.lines_of_code || 'N/A';
            const funcCount = data.function_count || 'N/A';
            const complexityClass = data.complexity_class || 'N/A';
            const details = document.getElementById('complexityAnalysisDetails');
            if (data.time_complexity && data.space_complexity) {
                details.innerHTML = `
                    <strong>📊 Complete Analysis Results:</strong><br><br>
                    <strong>Big-O Complexity:</strong><br>
                    • Time Complexity: <strong style="color: #667eea;">${data.time_complexity}</strong><br>
                    • Space Complexity: <strong style="color: #667eea;">${data.space_complexity}</strong><br>
                    • Complexity Class: <strong style="color: #667eea;">${complexityClass}</strong><br><br>
                    <strong>Code Metrics:</strong><br>
                    • Cyclomatic Complexity (CCN): <strong style="color: #667eea;">${ccn}</strong><br>
                    • Lines of Code (NLOC): <strong style="color: #667eea;">${nloc}</strong><br>
                    • Function Count: <strong style="color: #667eea;">${funcCount}</strong><br>
                    • Code Cost Score: <strong style="color: #667eea;">${codeCost}/100</strong><br>
                    • Overall Level: <strong style="color: #667eea;">${data.code_complexity}</strong>
                `;
            } else {
                details.textContent = 'Code complexity analysis completed.';
            }
            openModal('complexityAnalysisModal');
        }
        function closeRelevanceModal() {
            closeModal('relevanceAnalysisModal');
        }
        function closeComplexityModal() {
            closeModal('complexityAnalysisModal');
        }
        function openFeedbackModal(taskId, taskType) {
            document.getElementById('feedback_task_id').value = taskId;
            document.getElementById('feedback_task_type').value = taskType;
            openModal('feedbackModal');
        }
        function confirmRemoveTask(taskId, taskType, taskTitle) {
            if (confirm('Are you sure you want to remove the task "' + taskTitle + '"?\n\nThis action cannot be undone.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                var taskIdInput = document.createElement('input');
                taskIdInput.type = 'hidden';
                taskIdInput.name = 'task_id';
                taskIdInput.value = taskId;
                var taskTypeInput = document.createElement('input');
                taskTypeInput.type = 'hidden';
                taskTypeInput.name = 'task_type';
                taskTypeInput.value = taskType;
                var removeInput = document.createElement('input');
                removeInput.type = 'hidden';
                removeInput.name = 'remove_task';
                removeInput.value = '1';
                form.appendChild(taskIdInput);
                form.appendChild(taskTypeInput);
                form.appendChild(removeInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        function viewDepartment(departmentName) {
            window.location.href = '?page=dashboard&department=' + encodeURIComponent(departmentName);
        }
        function showTaskView(filter) {
            window.location.href = '?page=dashboard&task_filter=' + filter;
        }
        function showDepartmentView() {
            window.location.href = '?page=dashboard';
        }
        function showRemoveConfirmation(employeeEmail, departmentName) {
            document.getElementById('remove_employee_email').value = employeeEmail;
            document.getElementById('remove_department_name').value = departmentName;
            document.getElementById('confirmationModal').style.display = 'block';
        }
        function closeConfirmation() {
            document.getElementById('confirmationModal').style.display = 'none';
        }
        function openAddEmployeeModal(employeeEmail) {
            document.getElementById('add_employee_email').value = employeeEmail;
            openModal('addEmployeeModal');
        }
        function assignTaskToEmployee(employeeEmail) {
            showPage('create_task');
            document.getElementById('employeeSelect').value = employeeEmail;
        }
        function updateTaskTypeFields() {
            var taskType = document.getElementById('taskTypeSelect').value;
            var keywordsField = document.getElementById('keywordsField');
            if (taskType === 'documentation') {
                keywordsField.style.display = 'block';
            } else {
                keywordsField.style.display = 'none';
            }
        }
        function updateEmployeeDropdown() {
            var skill = document.getElementById('requiredSkillSelect').value;
            var employeeSelect = document.getElementById('employeeSelect');
            var options = Array.from(employeeSelect.querySelectorAll('option'));
            options.shift();
            options.sort(function(a, b) {
                var aSkills = a.getAttribute('data-skills').split(',').map(s => s.trim());
                var bSkills = b.getAttribute('data-skills').split(',').map(s => s.trim());
                var aHasSkill = aSkills.includes(skill);
                var bHasSkill = bSkills.includes(skill);
                if (aHasSkill && !bHasSkill) return -1;
                if (!aHasSkill && bHasSkill) return 1;
                return a.text.localeCompare(b.text);
            });
            employeeSelect.innerHTML = '<option value="">Select Employee...</option>';
            options.forEach(function(option) {
                employeeSelect.appendChild(option);
            });
        }
        function viewEmployeeSkills(employeeEmail) {
            fetch('get_employee_skills.php?employee_email=' + encodeURIComponent(employeeEmail) + '&company_id=<?php echo $company_id; ?>')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const skillsModal = document.getElementById('skillsModal');
                    const skillsEmployeeInfo = document.getElementById('skillsEmployeeInfo');
                    const skillsList = document.getElementById('skillsList');
                    skillsEmployeeInfo.innerHTML = `<strong>Employee:</strong> ${data.employee_name || employeeEmail}`;
                    if (data.skills && data.skills.length > 0) {
                        skillsList.innerHTML = '';
                        data.skills.forEach(skill => {
                            const skillItem = document.createElement('div');
                            skillItem.className = 'skill-item';
                            skillItem.textContent = skill;
                            skillsList.appendChild(skillItem);
                        });
                    } else {
                        skillsList.innerHTML = '<p style="text-align: center; color: #666;">No skills found for this employee.</p>';
                    }
                    openModal('skillsModal');
                })
                .catch(error => {
                    console.error('Error fetching employee skills:', error);
                    alert('Failed to load employee skills. Please try again.');
                });
        }
        function viewEmployeeAnalytics(employeeEmail) {
            const analyticsModal = document.getElementById('analyticsModal');
            const analyticsDashboard = document.getElementById('analyticsDashboard');
            analyticsDashboard.innerHTML = '<div style="text-align: center; padding: 50px; font-size: 1.2em;">Loading analytics data...</div>';
            openModal('analyticsModal');
            fetch('?analytics_employee=' + encodeURIComponent(employeeEmail))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    currentAnalyticsData = data;
                    displayAnalytics(data);
                })
                .catch(error => {
                    analyticsDashboard.innerHTML = `
                        <div style="text-align: center; padding: 50px; color: #dc2626;">
                            <h3>Error Loading Dashboard</h3>
                            <p>${error.message}</p>
                            <button onclick="viewEmployeeAnalytics('${employeeEmail}')" style="margin-top: 15px; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">Retry</button>
                        </div>
                    `;
                });
        }
        function displayAnalytics(data) {
            const analyticsDashboard = document.getElementById('analyticsDashboard');
            analyticsDashboard.innerHTML = `
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Overall Performance</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value">${data.total_tasks || 0}</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.completed_tasks || 0}</div>
                            <div class="metric-label">Completed Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.completion_rate || 0}%</div>
                            <div class="metric-label">Completion Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.on_time_rate || 0}%</div>
                            <div class="metric-label">On-Time Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.avg_relevance_score || 0}%</div>
                            <div class="metric-label">Avg Relevance Score</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.avg_quality_rating || 0}/5</div>
                            <div class="metric-label">Avg Quality Rating</div>
                        </div>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Documentation Tasks</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value">${data.documentation.total_tasks || 0}</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.documentation.completed_tasks || 0}</div>
                            <div class="metric-label">Completed</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.documentation.completion_rate || 0}%</div>
                            <div class="metric-label">Completion Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.documentation.on_time_rate || 0}%</div>
                            <div class="metric-label">On-Time Rate</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="docDifficultyChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Coding Tasks</h3>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="metric-value">${data.coding.total_tasks || 0}</div>
                            <div class="metric-label">Total Tasks</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.coding.completed_tasks || 0}</div>
                            <div class="metric-label">Completed</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.coding.completion_rate || 0}%</div>
                            <div class="metric-label">Completion Rate</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-value">${data.coding.avg_code_cost || 0}</div>
                            <div class="metric-label">Avg Code Cost</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="codeDifficultyChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Performance Trend</h3>
                    <div class="trend-chart-container">
                        <canvas id="performanceTrendChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Skill Radar Chart</h3>
                    <div class="radar-chart-container">
                        <canvas id="skillRadarChart"></canvas>
                    </div>
                </div>
                <div class="analytics-card">
                    <h3>Efficiency vs Quality Quadrant</h3>
                    <div class="quadrant-chart-container">
                        <canvas id="efficiencyQualityQuadrant"></canvas>
                    </div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <h3>Recent Feedback</h3>
                    <div class="feedback-list" id="feedbackList"></div>
                </div>
                <div class="analytics-card" style="grid-column: 1 / -1;">
                    <div class="insights-list">
                        <h4>Performance Insights</h4>
                        <ul id="insightsList"></ul>
                    </div>
                </div>
            `;
            document.getElementById('analyticsTitle').textContent = `Analytics: ${data.employee_name}`;
            setTimeout(() => {
                createDocDifficultyChart(data.documentation.difficulty_distribution);
                createCodeDifficultyChart(data.coding.difficulty_distribution);
                createPerformanceTrendChart(data.trends.monthly_performance);
                createSkillRadarChart(data);
                createEfficiencyQualityQuadrant(data.avg_efficiency_rating, data.avg_quality_rating);
                updateFeedbackList(data.recent_feedback);
                updateInsightsList(data.insights);
            }, 100);
        }
        function createDocDifficultyChart(data) {
            const ctx = document.getElementById('docDifficultyChart');
            if (!ctx) return;
            if (chartInstances.docDifficulty) {
                chartInstances.docDifficulty.destroy();
            }
            chartInstances.docDifficulty = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Easy', 'Medium', 'Hard'],
                    datasets: [{
                        data: [data.easy || 0, data.medium || 0, data.hard || 0],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        function createCodeDifficultyChart(data) {
            const ctx = document.getElementById('codeDifficultyChart');
            if (!ctx) return;
            if (chartInstances.codeDifficulty) {
                chartInstances.codeDifficulty.destroy();
            }
            chartInstances.codeDifficulty = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Easy', 'Medium', 'Hard'],
                    datasets: [{
                        data: [data.easy || 0, data.medium || 0, data.hard || 0],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        function createPerformanceTrendChart(data) {
            const ctx = document.getElementById('performanceTrendChart');
            if (!ctx) return;
            if (chartInstances.performanceTrend) {
                chartInstances.performanceTrend.destroy();
            }
            const labels = data.map(item => item.month || '');
            const values = data.map(item => item.performance || 0);
            chartInstances.performanceTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Performance Trend',
                        data: values,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
        function createSkillRadarChart(data) {
            const ctx = document.getElementById('skillRadarChart');
            if (!ctx) return;
            if (chartInstances.skillRadar) {
                chartInstances.skillRadar.destroy();
            }
            const skills = ['Technical', 'Soft Skills', 'Domain Knowledge', 'Creativity', 'Problem Solving'];
            const values = skills.map(skill => Math.floor(Math.random() * 100) + 1);
            chartInstances.skillRadar = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: skills,
                    datasets: [{
                        label: 'Skill Proficiency',
                        data: values,
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        borderColor: '#667eea',
                        pointBackgroundColor: '#667eea'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
        function createEfficiencyQualityQuadrant(efficiency, quality) {
            const ctx = document.getElementById('efficiencyQualityQuadrant');
            if (!ctx) return;
            if (chartInstances.quadrant) {
                chartInstances.quadrant.destroy();
            }
            const data = {
                labels: ['Low Efficiency/Low Quality', 'High Efficiency/Low Quality', 'Low Efficiency/High Quality', 'High Efficiency/High Quality'],
                datasets: [{
                    label: 'Employee Position',
                    data: [{ x: efficiency, y: quality }],
                    backgroundColor: '#667eea',
                    pointRadius: 10
                }]
            };
            chartInstances.quadrant = new Chart(ctx, {
                type: 'scatter',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom',
                            min: 0,
                            max: 5,
                            title: {
                                display: true,
                                text: 'Efficiency Rating'
                            }
                        },
                        y: {
                            type: 'linear',
                            position: 'left',
                            min: 0,
                            max: 5,
                            title: {
                                display: true,
                                text: 'Quality Rating'
                            }
                        }
                    },
                    plugins: {
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    yMin: 3,
                                    yMax: 3,
                                    borderColor: 'red',
                                    borderWidth: 1,
                                    borderDash: [5, 5]
                                },
                                line2: {
                                    type: 'line',
                                    xMin: 3,
                                    xMax: 3,
                                    borderColor: 'red',
                                    borderWidth: 1,
                                    borderDash: [5, 5]
                                }
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }
        function updateFeedbackList(feedback) {
            const feedbackList = document.getElementById('feedbackList');
            if (!feedbackList) return;
            feedbackList.innerHTML = '';
            if (!feedback || feedback.length === 0) {
                feedbackList.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No feedback available</p>';
                return;
            }
            feedback.forEach(item => {
                const avgRating = ((item.quality || 0) + (item.efficiency || 0) + (item.teamwork || 0)) / 3;
                const feedbackItem = document.createElement('div');
                feedbackItem.className = 'feedback-item';
                feedbackItem.innerHTML = `
                    <div class="feedback-header">
                        <div class="feedback-date">${item.date || 'N/A'}</div>
                        <div class="rating"><span class="rating-label">Overall:</span><span class="rating-value">${avgRating.toFixed(1)}/5</span></div>
                    </div>
                    <div class="feedback-ratings">
                        <div class="rating"><span class="rating-label">Quality:</span><span class="rating-value">${item.quality || 0}/5</span></div>
                        <div class="rating"><span class="rating-label">Efficiency:</span><span class="rating-value">${item.efficiency || 0}/5</span></div>
                        <div class="rating"><span class="rating-label">Teamwork:</span><span class="rating-value">${item.teamwork || 0}/5</span></div>
                    </div>
                    <div class="feedback-text">${item.text || 'No feedback text provided'}</div>
                `;
                feedbackList.appendChild(feedbackItem);
            });
        }
        function updateInsightsList(insights) {
            const insightsList = document.getElementById('insightsList');
            if (!insightsList) return;
            insightsList.innerHTML = '';
            if (!insights || insights.length === 0) {
                insightsList.innerHTML = '<li>No insights available.</li>';
                return;
            }
            insights.forEach(insight => {
                const listItem = document.createElement('li');
                listItem.textContent = insight;
                insightsList.appendChild(listItem);
            });
        }
        function exportAnalytics() {
            if (!currentAnalyticsData) {
                alert('No analytics data to export');
                return;
            }
            let csv = 'Employee Analytics Report\n\n';
            csv += `Employee Name,${currentAnalyticsData.employee_name}\n`;
            csv += `Department,${currentAnalyticsData.department}\n\n`;
            csv += 'Overall Performance\n';
            csv += `Total Tasks,${currentAnalyticsData.total_tasks}\n`;
            csv += `Completed Tasks,${currentAnalyticsData.completed_tasks}\n`;
            csv += `Completion Rate,${currentAnalyticsData.completion_rate}%\n`;
            csv += `On-Time Rate,${currentAnalyticsData.on_time_rate}%\n\n`;
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${currentAnalyticsData.employee_name.replace(/\s+/g, '_')}_analytics.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        function switchTaskTypeTab(tabName) {
            document.querySelectorAll('.task-type-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.task-type-content').forEach(content => content.classList.remove('active'));
            document.querySelector(`.task-type-tab:nth-child(${tabName === 'documentation' ? 1 : 2})`).classList.add('active');
            document.getElementById(`${tabName}-content`).classList.add('active');
        }
        function generateKeywords() {
            const title = document.getElementById('task_title').value;
            const description = document.getElementById('task_description').value;
            if (!title || !description) {
                alert('Please enter both task title and description to generate keywords.');
                return;
            }
            fetch('generate_keywords.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `title=${encodeURIComponent(title)}&description=${encodeURIComponent(description)}`
            })
            .then(response => response.text())
            .then(keywords => {
                document.getElementById('task_keywords').value = keywords;
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to generate keywords. Please try again.');
            });
        }
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal, .confirmation-modal, .analytics-modal, .skills-modal, .relevance-modal, .complexity-modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
        <?php if ($current_page != 'dashboard'): ?>
        showPage('<?php echo $current_page; ?>');
        <?php endif; ?>
        updateTaskTypeFields();
    </script>
</body>
</html>
<?php
$conn->close();
?>
