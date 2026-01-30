<?php
session_start();

if (!isset($_SESSION['employee_email'])) {
    header('Location: login.php');
    exit();
}

$servername = "localhost";
$username = "root";
$password = "Parth@23102025";
$dbname = "taskflow1";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$company_id = $_SESSION['company_id'];
$employee_email = $_SESSION['employee_email'];
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

$error_message = '';
$success_message = '';
$grammar_errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['check_grammar'])) {
        $task_id = $_POST['task_id'];
        
        if (isset($_FILES['grammar_file']) && $_FILES['grammar_file']['error'] == 0) {
            $file_extension = strtolower(pathinfo($_FILES['grammar_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_extension == 'pdf') {
                $temp_path = sys_get_temp_dir() . '/' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['grammar_file']['tmp_name'], $temp_path);
                
                $python_script = __DIR__ . '/grammar_checker.py';
                
                $python_command = 'python';
                
                $command = '"' . $python_command . '" "' . $python_script . '" "' . $temp_path . '" 2>&1';
                $output = shell_exec($command);
                
                $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
                
                $lines = explode("\n", trim($output));
                $json_output = '';
                
                for ($i = count($lines) - 1; $i >= 0; $i--) {
                    $trimmed_line = trim($lines[$i]);
                    if (!empty($trimmed_line) && ($trimmed_line[0] == '{' || $json_output != '')) {
                        $json_output = $trimmed_line . $json_output;
                        if ($trimmed_line[0] == '{') {
                            break;
                        }
                    }
                }
                
                if (!empty($json_output)) {
                    $result = json_decode($json_output, true);
                    $json_error = json_last_error();
                    
                    if ($result && isset($result['success'])) {
                        if ($result['success']) {
                            $grammar_errors = $result;
                        } else {
                            $error_message = "Error: " . ($result['error'] ?? 'Unknown error');
                        }
                    } else {
                        if ($json_error !== JSON_ERROR_NONE) {
                            $error_message = "JSON Error: " . json_last_error_msg();
                        } else {
                            $error_message = "Invalid response structure";
                        }
                    }
                } else {
                    $error_message = "No JSON found in output";
                }
                
                if (file_exists($temp_path)) {
                    unlink($temp_path);
                }
            } else {
                $error_message = "Only PDF files are supported for grammar checking.";
            }
        } else {
            $error_message = "Please upload a file for grammar checking.";
        }
    }
    
    if (isset($_POST['submit_task'])) {
        $task_id = $_POST['task_id'];
        $task_type = $_POST['task_type'];
        $submitted_file = '';
        
        if (!file_exists('submitted_files')) {
            mkdir('submitted_files', 0777, true);
        }
        
        if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] == 0) {
            $file_extension = pathinfo($_FILES['task_file']['name'], PATHINFO_EXTENSION);
            $submitted_file = uniqid() . '.' . $file_extension;
            move_uploaded_file($_FILES['task_file']['tmp_name'], 'submitted_files/' . $submitted_file);
        }
        
        $employee_sql = "SELECT id FROM employee_registration WHERE employee_email = ? AND company_id = ?";
        $employee_stmt = $conn->prepare($employee_sql);
        $employee_stmt->bind_param("ss", $employee_email, $company_id);
        $employee_stmt->execute();
        $employee_result = $employee_stmt->get_result();
        $employee = $employee_result->fetch_assoc();
        $employee_id = $employee['id'];
        $employee_stmt->close();
        
        if ($task_type == 'documentation') {
            $sql = "UPDATE documentation_tasks SET submitted_at = NOW(), submitted_file = ? WHERE doc_task_id = ? AND employee_id = ?";
        } else {
            $sql = "UPDATE coding_tasks SET submitted_at = NOW(), submitted_file = ? WHERE code_task_id = ? AND employee_id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $submitted_file, $task_id, $employee_id);
        
        if ($stmt->execute()) {
            $success_message = "Task submitted successfully!";
            
            if ($task_type == 'documentation') {
                $task_info_sql = "SELECT deadline FROM documentation_tasks WHERE doc_task_id = ?";
            } else {
                $task_info_sql = "SELECT deadline FROM coding_tasks WHERE code_task_id = ?";
            }
            
            $task_info_stmt = $conn->prepare($task_info_sql);
            $task_info_stmt->bind_param("i", $task_id);
            $task_info_stmt->execute();
            $task_info = $task_info_stmt->get_result()->fetch_assoc();
            $task_info_stmt->close();
            
            $submitted_on_time = (strtotime($task_info['deadline']) >= time()) ? 'yes' : 'no';
            
            $update_perf_sql = "UPDATE employee_performance_summary SET 
                tasks_completed = tasks_completed + 1,
                tasks_on_time = tasks_on_time + ?,
                total_tasks = total_tasks + 1
                WHERE employee_email = ? AND company_id = ?";
            $update_perf_stmt = $conn->prepare($update_perf_sql);
            $on_time_value = $submitted_on_time == 'yes' ? 1 : 0;
            $update_perf_stmt->bind_param("iss", $on_time_value, $employee_email, $company_id);
            $update_perf_stmt->execute();
            $update_perf_stmt->close();
            
        } else {
            $error_message = "Error submitting task: " . $stmt->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['add_skill'])) {
        $skill = trim($_POST['skill']);
        
        if (!empty($skill)) {
            $check_sql = "SELECT skills FROM employee_skills WHERE employee_email = ? AND company_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $employee_email, $company_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_skills = $check_result->fetch_assoc()['skills'];
                $skills_array = explode(',', $existing_skills);
                
                if (in_array($skill, $skills_array)) {
                    $error_message = "Skill already exists!";
                } else {
                    $new_skills = $existing_skills . ',' . $skill;
                    $update_sql = "UPDATE employee_skills SET skills = ? WHERE employee_email = ? AND company_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sss", $new_skills, $employee_email, $company_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Skill added successfully!";
                    } else {
                        $error_message = "Error adding skill: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            } else {
                $insert_sql = "INSERT INTO employee_skills (employee_email, company_id, skills) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sss", $employee_email, $company_id, $skill);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Skill added successfully!";
                } else {
                    $error_message = "Error adding skill: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            }
            $check_stmt->close();
        } else {
            $error_message = "Please enter a skill!";
        }
    }
    
    if (isset($_POST['remove_skill'])) {
        $skill_to_remove = $_POST['skill_to_remove'];
        
        $get_sql = "SELECT skills FROM employee_skills WHERE employee_email = ? AND company_id = ?";
        $get_stmt = $conn->prepare($get_sql);
        $get_stmt->bind_param("ss", $employee_email, $company_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        
        if ($get_result->num_rows > 0) {
            $existing_skills = $get_result->fetch_assoc()['skills'];
            $skills_array = explode(',', $existing_skills);
            $updated_skills = array_filter($skills_array, function($s) use ($skill_to_remove) {
                return trim($s) !== $skill_to_remove;
            });
            
            $new_skills = implode(',', $updated_skills);
            
            $update_sql = "UPDATE employee_skills SET skills = ? WHERE employee_email = ? AND company_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sss", $new_skills, $employee_email, $company_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Skill removed successfully!";
            } else {
                $error_message = "Error removing skill: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
        $get_stmt->close();
    }
    
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: index.html');
        exit();
    }
}

$employee_sql = "SELECT id FROM employee_registration WHERE employee_email = ? AND company_id = ?";
$employee_stmt = $conn->prepare($employee_sql);
$employee_stmt->bind_param("ss", $employee_email, $company_id);
$employee_stmt->execute();
$employee_result = $employee_stmt->get_result();
$employee = $employee_result->fetch_assoc();
$employee_id = $employee['id'];
$employee_stmt->close();

$total_tasks_sql = "SELECT COUNT(*) as total_tasks FROM (
                    SELECT doc_task_id FROM documentation_tasks WHERE employee_id = ? AND company_id = ?
                    UNION ALL
                    SELECT code_task_id FROM coding_tasks WHERE employee_id = ? AND company_id = ?
                    ) as all_tasks";
$total_stmt = $conn->prepare($total_tasks_sql);
$total_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$total_stmt->execute();
$total_tasks = $total_stmt->get_result()->fetch_assoc()['total_tasks'];
$total_stmt->close();

$pending_tasks_sql = "SELECT COUNT(*) as pending_tasks FROM (
                    SELECT doc_task_id FROM documentation_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NULL AND deadline > NOW()
                    UNION ALL
                    SELECT code_task_id FROM coding_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NULL AND deadline > NOW()
                    ) as all_tasks";
$pending_stmt = $conn->prepare($pending_tasks_sql);
$pending_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$pending_stmt->execute();
$pending_tasks = $pending_stmt->get_result()->fetch_assoc()['pending_tasks'];
$pending_stmt->close();

$completed_tasks_sql = "SELECT COUNT(*) as completed_tasks FROM (
                       SELECT doc_task_id FROM documentation_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NOT NULL
                       UNION ALL
                       SELECT code_task_id FROM coding_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NOT NULL
                       ) as all_tasks";
$completed_stmt = $conn->prepare($completed_tasks_sql);
$completed_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$completed_stmt->execute();
$completed_tasks = $completed_stmt->get_result()->fetch_assoc()['completed_tasks'];
$completed_stmt->close();

$overdue_tasks_sql = "SELECT COUNT(*) as overdue_tasks FROM (
                     SELECT doc_task_id FROM documentation_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NULL AND deadline < NOW()
                     UNION ALL
                     SELECT code_task_id FROM coding_tasks WHERE employee_id = ? AND company_id = ? AND submitted_at IS NULL AND deadline < NOW()
                     ) as all_tasks";
$overdue_stmt = $conn->prepare($overdue_tasks_sql);
$overdue_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$overdue_stmt->execute();
$overdue_tasks = $overdue_stmt->get_result()->fetch_assoc()['overdue_tasks'];
$overdue_stmt->close();

$all_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.submitted_at, dt.submitted_file, dt.required_skill, dt.priority, dt.difficulty
                  FROM documentation_tasks dt 
                  WHERE dt.employee_id = ? AND dt.company_id = ?
                  UNION ALL
                  SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.submitted_at, ct.submitted_file, ct.required_skill, ct.priority, ct.difficulty
                  FROM coding_tasks ct 
                  WHERE ct.employee_id = ? AND ct.company_id = ?
                  ORDER BY deadline DESC";
$all_tasks_stmt = $conn->prepare($all_tasks_sql);
$all_tasks_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$all_tasks_stmt->execute();
$all_tasks_result = $all_tasks_stmt->get_result();

$pending_tasks_detail_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.required_skill, dt.priority, dt.difficulty
                            FROM documentation_tasks dt 
                            WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NULL AND dt.deadline > NOW()
                            UNION ALL
                            SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.required_skill, ct.priority, ct.difficulty
                            FROM coding_tasks ct 
                            WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NULL AND ct.deadline > NOW()
                            ORDER BY deadline ASC";
$pending_detail_stmt = $conn->prepare($pending_tasks_detail_sql);
$pending_detail_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$pending_detail_stmt->execute();
$pending_tasks_detail = $pending_detail_stmt->get_result();

$completed_tasks_detail_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.submitted_at, dt.submitted_file, dt.required_skill
                              FROM documentation_tasks dt 
                              WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NOT NULL
                              UNION ALL
                              SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.submitted_at, ct.submitted_file, ct.required_skill
                              FROM coding_tasks ct 
                              WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NOT NULL
                              ORDER BY submitted_at DESC";
$completed_detail_stmt = $conn->prepare($completed_tasks_detail_sql);
$completed_detail_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$completed_detail_stmt->execute();
$completed_tasks_detail = $completed_detail_stmt->get_result();

$overdue_tasks_detail_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.required_skill, dt.priority, dt.difficulty
                            FROM documentation_tasks dt 
                            WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                            UNION ALL
                            SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.required_skill, ct.priority, ct.difficulty
                            FROM coding_tasks ct 
                            WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NULL AND ct.deadline < NOW()
                            ORDER BY deadline ASC";
$overdue_detail_stmt = $conn->prepare($overdue_tasks_detail_sql);
$overdue_detail_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
$overdue_detail_stmt->execute();
$overdue_tasks_detail = $overdue_detail_stmt->get_result();

$employee_info_sql = "SELECT er.employee_firstname, er.employee_lastname, eid.department_name 
                     FROM employee_registration er 
                     LEFT JOIN employees_in_department eid ON er.employee_email = eid.employee_email 
                     WHERE er.employee_email = ? AND er.company_id = ?";
$employee_info_stmt = $conn->prepare($employee_info_sql);
$employee_info_stmt->bind_param("ss", $employee_email, $company_id);
$employee_info_stmt->execute();
$employee_info = $employee_info_stmt->get_result()->fetch_assoc();

$skills_sql = "SELECT skills FROM employee_skills WHERE employee_email = ? AND company_id = ?";
$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->bind_param("ss", $employee_email, $company_id);
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();
$skills_data = $skills_result->fetch_assoc();
$skills_array = $skills_data ? explode(',', $skills_data['skills']) : [];
$skills_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow Lite - Employee Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fa;
        }

        header {
            background: #111827;
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

        main {
            padding: 20px;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0px 4px 15px rgba(0,0,0,0.15);
        }

        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #1f2937;
            font-size: 14px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
        }

        .total-tasks {
            color: #2563eb;
        }

        .pending-tasks {
            color: #f59e0b;
        }

        .completed-tasks {
            color: #10b981;
        }

        .overdue-tasks {
            color: #ef4444;
        }

        .card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        form input, form select, form textarea, form button {
            width: 100%;
            margin-bottom: 10px;
            padding: 8px;
            box-sizing: border-box;
        }

        form button {
            background: #16a34a;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        form button:hover {
            background: #15803d;
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

        .hidden {
            display: none;
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

        .info-box {
            background: #dbeafe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bfdbfe;
        }

        .info-box h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
        }

        .info-box p {
            margin: 5px 0;
            color: #374151;
        }

        .file-link {
            color: #2563eb;
            text-decoration: none;
        }

        .file-link:hover {
            text-decoration: underline;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .task-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .task-details h4 {
            margin: 0 0 10px 0;
            color: #1f2937;
        }

        .task-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #6b7280;
        }

        .back-btn {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .back-btn:hover {
            background: #4b5563;
        }

        #task-details-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .skills-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .skills-box h3 {
            margin: 0 0 10px 0;
            color: #92400e;
        }

        .skill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }

        .remove-skill-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-skill-btn:hover {
            background: #dc2626;
        }

        .add-skill-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .add-skill-form input {
            flex: 1;
            margin-bottom: 0;
        }

        .add-skill-form button {
            width: auto;
            margin-bottom: 0;
        }

        .submit-task-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .submit-task-btn:hover {
            background: #1d4ed8;
        }

        .grammar-result {
            background: #ffffff;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 25px;
            margin-top: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .grammar-result h3 {
            margin: 0 0 20px 0;
            color: #92400e;
            font-size: 20px;
            border-bottom: 2px solid #fbbf24;
            padding-bottom: 10px;
        }

        .grammar-error-item {
            background: #fffbeb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #f59e0b;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .grammar-error-item h4 {
            margin: 0 0 15px 0;
            color: #dc2626;
            font-size: 16px;
            font-weight: 700;
            background: #fee2e2;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
        }

        .grammar-info-row {
            margin: 12px 0;
            padding: 10px;
            background: white;
            border-radius: 5px;
        }

        .grammar-info-row strong {
            color: #1f2937;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grammar-info-row .content {
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }

        .error-badge {
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            display: inline-block;
        }

        .suggestion-badge {
            background: #dcfce7;
            color: #16a34a;
            padding: 6px 12px;
            border-radius: 5px;
            margin-right: 8px;
            display: inline-block;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .context-text {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #6b7280;
            color: #1f2937;
            font-size: 13px;
        }

        .no-errors-message {
            color: #166534;
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            border: 2px solid #86efac;
        }

        .error-count {
            background: #dc2626;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 18px;
        }

        .grammar-check-btn {
            background: #8b5cf6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            width: auto;
        }

        .grammar-check-btn:hover {
            background: #7c3aed;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .task-action-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .task-action-card h4 {
            margin: 0 0 10px 0;
            color: #1f2937;
        }

        .task-type-badge {
            background: #e0e7ff;
            color: #3730a3;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <header>
        <h1>TaskFlow - Employee Dashboard</h1>
        <nav>
            <a onclick="showPage('dashboard')">Dashboard</a>
            <a onclick="showPage('my_tasks')">My Tasks</a>
            <a onclick="showPage('submit_task')">Submit Task</a>
            <a onclick="showPage('skills')">My Skills</a>
            <div class="profile-dropdown">
                <button class="profile-btn">Profile</button>
                <div class="dropdown-content">
                    <p>Logged in as:<br><strong><?php echo $employee_email; ?></strong></p>
                    <?php if ($employee_info && isset($employee_info['department_name'])): ?>
                    <p>Department:<br><strong><?php echo $employee_info['department_name']; ?></strong></p>
                    <?php endif; ?>
                    <form method="POST">
                        <button type="submit" name="logout">Logout</button>
                    </form>
                </div>
            </div>
        </nav>
    </header>

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
        
        <div id="dashboard" class="page">
            <div class="dashboard-stats">
                <div class="stat-box" onclick="showTaskDetails('total')">
                    <h3>Total Tasks</h3>
                    <div class="stat-number total-tasks"><?php echo $total_tasks; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskDetails('pending')">
                    <h3>Pending Tasks</h3>
                    <div class="stat-number pending-tasks"><?php echo $pending_tasks; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskDetails('completed')">
                    <h3>Completed Tasks</h3>
                    <div class="stat-number completed-tasks"><?php echo $completed_tasks; ?></div>
                </div>
                <div class="stat-box" onclick="showTaskDetails('overdue')">
                    <h3>Overdue Tasks</h3>
                    <div class="stat-number overdue-tasks"><?php echo $overdue_tasks; ?></div>
                </div>
            </div>
            
            <div id="task-details-section" class="hidden">
                <button class="back-btn" onclick="hideTaskDetails()">← Back to Dashboard</button>
                <h3 id="task-details-title"></h3>
                <div id="task-details-content"></div>
            </div>
            
            <?php if ($employee_info && isset($employee_info['department_name'])): ?>
            <div class="info-box">
                <h3>Employee Information</h3>
                <p><strong>Name:</strong> <?php echo $employee_info['employee_firstname'] . ' ' . $employee_info['employee_lastname']; ?></p>
                <p><strong>Department:</strong> <?php echo $employee_info['department_name']; ?></p>
                <p><strong>Email:</strong> <?php echo $employee_email; ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($skills_array)): ?>
            <div class="skills-box">
                <h3>My Skills</h3>
                <?php foreach ($skills_array as $skill): ?>
                <div class="skill-item">
                    <span><?php echo htmlspecialchars($skill); ?></span>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="skill_to_remove" value="<?php echo $skill; ?>">
                        <button type="submit" name="remove_skill" class="remove-skill-btn">Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="my_tasks" class="page hidden">
            <section class="card">
                <h2>My Current Tasks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Type</th>
                            <th>Skill Required</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Submitted File</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($all_tasks_result->num_rows > 0) {
                            while ($task = $all_tasks_result->fetch_assoc()) {
                                $deadline = date('Y-m-d H:i', strtotime($task['deadline']));
                                $file_link = $task['submitted_file'] ? '<a href="submitted_files/' . $task['submitted_file'] . '" class="file-link" target="_blank">Download</a>' : 'N/A';
                                $task_type_badge = '<span class="task-type-badge">' . ucfirst($task['task_type']) . '</span>';
                                
                                if ($task['submitted_at']) {
                                    $status = '<span class="status-badge status-completed">Completed</span>';
                                    $action = '-';
                                } else if (strtotime($task['deadline']) < time()) {
                                    $status = '<span class="status-badge status-overdue">Overdue</span>';
                                    $action = '<button class="submit-task-btn" onclick="showSubmitForm(' . $task['task_id'] . ', \'' . $task['task_type'] . '\', \'' . htmlspecialchars(addslashes($task['task_title'])) . '\')">Submit Task</button>';
                                } else {
                                    $status = '<span class="status-badge status-pending">Pending</span>';
                                    $action = '<button class="submit-task-btn" onclick="showSubmitForm(' . $task['task_id'] . ', \'' . $task['task_type'] . '\', \'' . htmlspecialchars(addslashes($task['task_title'])) . '\')">Submit Task</button>';
                                }
                                
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($task['task_title']) . "</td>";
                                echo "<td>" . $task_type_badge . "</td>";
                                echo "<td>" . htmlspecialchars($task['required_skill']) . "</td>";
                                echo "<td>" . $status . "</td>";
                                echo "<td>" . $deadline . "</td>";
                                echo "<td>" . $file_link . "</td>";
                                echo "<td>" . $action . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7' style='text-align: center;'>No tasks assigned yet</td></tr>";
                        }
                        $all_tasks_stmt->close();
                        ?>
                    </tbody>
                </table>
            </section>
        </div>

        <div id="submit_task" class="page hidden">
            <section class="card">
                <h2>Submit Task</h2>
                
                <div id="submit-task-form-container">
                    <form method="POST" enctype="multipart/form-data">
                        <select name="task_id" id="task_select" required onchange="updateTaskInfo(this.value)">
                            <option value="">Select Task...</option>
                            <?php
                            $pending_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.required_skill 
                                                FROM documentation_tasks dt 
                                                WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NULL
                                                UNION ALL
                                                SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.required_skill 
                                                FROM coding_tasks ct 
                                                WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NULL";
                            $pending_stmt = $conn->prepare($pending_tasks_sql);
                            $pending_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
                            $pending_stmt->execute();
                            $pending_result = $pending_stmt->get_result();
                            
                            if ($pending_result->num_rows > 0) {
                                while ($task = $pending_result->fetch_assoc()) {
                                    echo "<option value='" . $task['task_id'] . "' data-type='" . $task['task_type'] . "' data-skill='" . htmlspecialchars($task['required_skill']) . "'>" . htmlspecialchars($task['task_title']) . " (" . ucfirst($task['task_type']) . ")</option>";
                                }
                            } else {
                                echo "<option value='' disabled>No pending tasks</option>";
                            }
                            $pending_stmt->close();
                            ?>
                        </select>
                        
                        <div id="task-info" style="display: none; background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                            <h4>Task Information</h4>
                            <p><strong>Type:</strong> <span id="task-type-display"></span></p>
                            <p><strong>Required Skill:</strong> <span id="task-skill-display"></span></p>
                        </div>
                        
                        <input type="hidden" name="task_type" id="task_type">
                        <input type="file" name="task_file" required accept=".pdf,.csv,.doc,.docx,.txt,.c,.java,.py,.js,.html,.css,.zip,.rar">
                        <button type="submit" name="submit_task">Upload Task</button>
                    </form>
                    
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
                        <h3 style="color: #7c3aed; margin-bottom: 15px;">Check Grammar Before Submitting (PDF Only)</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="task_id" id="grammar_task_id" value="">
                            <input type="file" name="grammar_file" required accept=".pdf">
                            <button type="submit" name="check_grammar" class="grammar-check-btn">Check Grammar</button>
                        </form>
                        
                        <?php if (!empty($grammar_errors) && isset($grammar_errors['success'])): ?>
                        <div class="grammar-result">
                            <h3>Grammar Check Results</h3>
                            <p style="margin-bottom: 20px;">
                                <strong>Total Errors Found:</strong> 
                                <span class="error-count"><?php echo $grammar_errors['total_errors']; ?></span>
                            </p>
                            
                            <?php if ($grammar_errors['total_errors'] > 0): ?>
                                <?php foreach ($grammar_errors['errors'] as $index => $error): ?>
                                <div class="grammar-error-item">
                                    <h4>Error #<?php echo $index + 1; ?></h4>
                                    
                                    <div class="grammar-info-row">
                                        <strong>Problem Description</strong>
                                        <div class="content"><?php echo htmlspecialchars($error['error_message']); ?></div>
                                    </div>
                                    
                                    <div class="grammar-info-row">
                                        <strong>Incorrect Text</strong>
                                        <div class="content">
                                            <span class="error-badge"><?php echo htmlspecialchars($error['incorrect_text']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="grammar-info-row">
                                        <strong>Context (where it appears)</strong>
                                        <div class="content context-text"><?php echo htmlspecialchars($error['context']); ?></div>
                                    </div>
                                    
                                    <div class="grammar-info-row">
                                        <strong>Suggested Corrections</strong>
                                        <div class="content">
                                            <?php 
                                            if (!empty($error['suggestions'])) {
                                                foreach ($error['suggestions'] as $suggestion) {
                                                    echo '<span class="suggestion-badge">' . htmlspecialchars($suggestion) . '</span>';
                                                }
                                            } else {
                                                echo '<span style="color: #6b7280; font-style: italic;">No suggestions available</span>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-errors-message">
                                    ✓ No grammatical errors found! Your document looks great.
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <div id="skills" class="page hidden">
            <section class="card">
                <h2>My Skills</h2>
                
                <?php if (!empty($skills_array)): ?>
                <div style="margin-bottom: 20px;">
                    <h3>Current Skills</h3>
                    <?php foreach ($skills_array as $skill): ?>
                    <div class="skill-item">
                        <span><?php echo htmlspecialchars($skill); ?></span>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="skill_to_remove" value="<?php echo $skill; ?>">
                            <button type="submit" name="remove_skill" class="remove-skill-btn">Remove</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p>No skills added yet.</p>
                <?php endif; ?>
                
                <h3>Add New Skill</h3>
                <form method="POST" class="add-skill-form">
                    <input type="text" name="skill" placeholder="Enter a skill (e.g., PHP, JavaScript, Design)" required>
                    <button type="submit" name="add_skill">Add Skill</button>
                </form>
            </section>
        </div>
    </main>

    <script>
        function showPage(pageId) {
            const pages = document.querySelectorAll('.page');
            pages.forEach(page => page.classList.add('hidden'));
            document.getElementById(pageId).classList.remove('hidden');
            
            if (pageId !== 'dashboard') {
                hideTaskDetails();
            }
        }

        function showTaskDetails(type) {
            const detailsSection = document.getElementById('task-details-section');
            const title = document.getElementById('task-details-title');
            const content = document.getElementById('task-details-content');
            
            detailsSection.classList.remove('hidden');
            
            detailsSection.scrollIntoView({ behavior: 'smooth' });
            
            let tasksData = [];
            let titleText = '';
            
            switch(type) {
                case 'total':
                    titleText = 'All Tasks';
                    tasksData = <?php 
                        $all_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.submitted_at, dt.submitted_file, dt.required_skill, dt.priority, dt.difficulty
                                        FROM documentation_tasks dt 
                                        WHERE dt.employee_id = ? AND dt.company_id = ?
                                        UNION ALL
                                        SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.submitted_at, ct.submitted_file, ct.required_skill, ct.priority, ct.difficulty
                                        FROM coding_tasks ct 
                                        WHERE ct.employee_id = ? AND ct.company_id = ?
                                        ORDER BY deadline DESC";
                        $all_tasks_stmt = $conn->prepare($all_tasks_sql);
                        $all_tasks_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
                        $all_tasks_stmt->execute();
                        $all_tasks_data = $all_tasks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $all_tasks_stmt->close();
                        echo json_encode($all_tasks_data);
                    ?>;
                    break;
                case 'pending':
                    titleText = 'Pending Tasks';
                    tasksData = <?php 
                        $pending_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.required_skill, dt.priority, dt.difficulty
                                            FROM documentation_tasks dt 
                                            WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NULL AND dt.deadline > NOW()
                                            UNION ALL
                                            SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.required_skill, ct.priority, ct.difficulty
                                            FROM coding_tasks ct 
                                            WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NULL AND ct.deadline > NOW()
                                            ORDER BY deadline ASC";
                        $pending_stmt = $conn->prepare($pending_tasks_sql);
                        $pending_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
                        $pending_stmt->execute();
                        $pending_tasks_data = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $pending_stmt->close();
                        echo json_encode($pending_tasks_data);
                    ?>;
                    break;
                case 'completed':
                    titleText = 'Completed Tasks';
                    tasksData = <?php 
                        $completed_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.submitted_at, dt.submitted_file, dt.required_skill
                                              FROM documentation_tasks dt 
                                              WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NOT NULL
                                              UNION ALL
                                              SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.submitted_at, ct.submitted_file, ct.required_skill
                                              FROM coding_tasks ct 
                                              WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NOT NULL
                                              ORDER BY submitted_at DESC";
                        $completed_stmt = $conn->prepare($completed_tasks_sql);
                        $completed_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
                        $completed_stmt->execute();
                        $completed_tasks_data = $completed_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $completed_stmt->close();
                        echo json_encode($completed_tasks_data);
                    ?>;
                    break;
                case 'overdue':
                    titleText = 'Overdue Tasks';
                    tasksData = <?php 
                        $overdue_tasks_sql = "SELECT 'documentation' as task_type, dt.doc_task_id as task_id, dt.task_title, dt.task_description, dt.deadline, dt.required_skill, dt.priority, dt.difficulty
                                            FROM documentation_tasks dt 
                                            WHERE dt.employee_id = ? AND dt.company_id = ? AND dt.submitted_at IS NULL AND dt.deadline < NOW()
                                            UNION ALL
                                            SELECT 'coding' as task_type, ct.code_task_id as task_id, ct.task_title, ct.task_description, ct.deadline, ct.required_skill, ct.priority, ct.difficulty
                                            FROM coding_tasks ct 
                                            WHERE ct.employee_id = ? AND ct.company_id = ? AND ct.submitted_at IS NULL AND ct.deadline < NOW()
                                            ORDER BY deadline ASC";
                        $overdue_stmt = $conn->prepare($overdue_tasks_sql);
                        $overdue_stmt->bind_param("isis", $employee_id, $company_id, $employee_id, $company_id);
                        $overdue_stmt->execute();
                        $overdue_tasks_data = $overdue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $overdue_stmt->close();
                        echo json_encode($overdue_tasks_data);
                    ?>;
                    break;
            }
            
            title.textContent = titleText;
            
            if (tasksData.length === 0) {
                content.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No tasks found in this category.</p>';
                return;
            }
            
            let html = '';
            tasksData.forEach(task => {
                let status = '';
                let statusClass = '';
                let actionButton = '';
                
                if (task.submitted_at) {
                    status = 'Completed';
                    statusClass = 'status-completed';
                    actionButton = '';
                } else if (new Date(task.deadline) < new Date()) {
                    status = 'Overdue';
                    statusClass = 'status-overdue';
                    actionButton = `<button class="submit-task-btn" onclick="showSubmitForm(${task.task_id}, '${task.task_type}', '${task.task_title.replace(/'/g, "\\'")}')">Submit Task</button>`;
                } else {
                    status = 'Pending';
                    statusClass = 'status-pending';
                    actionButton = `<button class="submit-task-btn" onclick="showSubmitForm(${task.task_id}, '${task.task_type}', '${task.task_title.replace(/'/g, "\\'")}')">Submit Task</button>`;
                }
                
                let fileLink = task.submitted_file ? 
                    `<a href="submitted_files/${task.submitted_file}" class="file-link" target="_blank">Download Submission</a>` : 
                    'No file submitted';
                
                html += `
                    <div class="task-details">
                        <h4>${task.task_title}</h4>
                        <div class="task-meta">
                            <span><strong>Type:</strong> <span class="task-type-badge">${task.task_type}</span></span>
                            <span><strong>Skill:</strong> ${task.required_skill}</span>
                            <span><strong>Status:</strong> <span class="status-badge ${statusClass}">${status}</span></span>
                            <span><strong>Deadline:</strong> ${new Date(task.deadline).toLocaleString()}</span>
                            ${task.submitted_at ? `<span><strong>Submitted:</strong> ${new Date(task.submitted_at).toLocaleString()}</span>` : ''}
                        </div>
                        ${task.task_description ? `<div><strong>Description:</strong> ${task.task_description}</div>` : ''}
                        ${task.submitted_file ? `<div><strong>File:</strong> ${fileLink}</div>` : ''}
                        ${actionButton ? `<div style="margin-top: 10px;">${actionButton}</div>` : ''}
                    </div>
                `;
            });
            
            content.innerHTML = html;
        }
        
        function hideTaskDetails() {
            document.getElementById('task-details-section').classList.add('hidden');
        }

        function showSubmitForm(taskId, taskType, taskTitle) {
            showPage('submit_task');
            const taskSelect = document.getElementById('task_select');
            taskSelect.value = taskId;
            updateTaskInfo(taskId);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function updateTaskInfo(taskId) {
            const taskSelect = document.getElementById('task_select');
            const selectedOption = taskSelect.options[taskSelect.selectedIndex];
            const taskType = selectedOption.getAttribute('data-type');
            const taskSkill = selectedOption.getAttribute('data-skill');
            const taskInfo = document.getElementById('task-info');
            const taskTypeDisplay = document.getElementById('task-type-display');
            const taskSkillDisplay = document.getElementById('task-skill-display');
            const taskTypeInput = document.getElementById('task_type');
            const grammarTaskId = document.getElementById('grammar_task_id');
            
            if (taskId) {
                taskInfo.style.display = 'block';
                taskTypeDisplay.textContent = taskType.charAt(0).toUpperCase() + taskType.slice(1);
                taskSkillDisplay.textContent = taskSkill;
                taskTypeInput.value = taskType;
                grammarTaskId.value = taskId;
            } else {
                taskInfo.style.display = 'none';
                taskTypeInput.value = '';
                grammarTaskId.value = '';
            }
        }

        <?php if ($current_page != 'dashboard'): ?>
        showPage('<?php echo $current_page; ?>');
        <?php endif; ?>
    </script>
</body>
</html>

<?php
$conn->close();
?>