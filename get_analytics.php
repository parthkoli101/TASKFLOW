<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email']) || !isset($_SESSION['company_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['employee_email'])) {
    echo json_encode(['error' => 'Employee email required']);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "Parth@23102025";
$dbname = "taskflow1";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$employee_email = $conn->real_escape_string($_GET['employee_email']);
$company_id = $_SESSION['company_id'];

// Get employee info
$emp_sql = "SELECT employee_firstname, employee_lastname, department_name 
            FROM employees_in_department 
            WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
$emp_result = $conn->query($emp_sql);

if (!$emp_result || $emp_result->num_rows == 0) {
    echo json_encode(['error' => 'Employee not found']);
    exit();
}

$employee = $emp_result->fetch_assoc();

// Get employee ID
$emp_id_sql = "SELECT id FROM employee_registration WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
$emp_id_result = $conn->query($emp_id_sql);
$emp_id_row = $emp_id_result->fetch_assoc();
$employee_id = $emp_id_row['id'] ?? 0;

// Initialize response
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
        'difficulty_distribution' => ['easy' => 0, 'medium' => 0, 'hard' => 0],
        'complexity_distribution' => ['Low' => 0, 'Medium' => 0, 'High' => 0]
    ],
    'recent_feedback' => [],
    'insights' => []
];

if ($employee_id > 0) {
    // Documentation tasks
    $doc_sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at <= deadline THEN 1 ELSE 0 END) as on_time,
        SUM(CASE WHEN submitted_at IS NOT NULL AND submitted_at > deadline THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN submitted_at IS NULL AND deadline < NOW() THEN 1 ELSE 0 END) as overdue,
        AVG(relevance_score) as avg_relevance
        FROM documentation_tasks 
        WHERE employee_id = $employee_id AND company_id = '$company_id'";
    $doc_result = $conn->query($doc_sql);
    $doc_data = $doc_result->fetch_assoc();
    
    // Doc difficulty
    $doc_diff_sql = "SELECT difficulty, COUNT(*) as count 
                     FROM documentation_tasks 
                     WHERE employee_id = $employee_id AND company_id = '$company_id'
                     GROUP BY difficulty";
    $doc_diff_result = $conn->query($doc_diff_sql);
    $doc_difficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
    while ($row = $doc_diff_result->fetch_assoc()) {
        $doc_difficulty[$row['difficulty']] = (int)$row['count'];
    }
    
    // Coding tasks
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
    $code_result = $conn->query($code_sql);
    $code_data = $code_result->fetch_assoc();
    
    // Code difficulty
    $code_diff_sql = "SELECT difficulty, COUNT(*) as count 
                      FROM coding_tasks 
                      WHERE employee_id = $employee_id AND company_id = '$company_id'
                      GROUP BY difficulty";
    $code_diff_result = $conn->query($code_diff_sql);
    $code_difficulty = ['easy' => 0, 'medium' => 0, 'hard' => 0];
    while ($row = $code_diff_result->fetch_assoc()) {
        $code_difficulty[$row['difficulty']] = (int)$row['count'];
    }
    
    // Code complexity
    $code_complex_sql = "SELECT code_complexity, COUNT(*) as count 
                         FROM coding_tasks 
                         WHERE employee_id = $employee_id AND company_id = '$company_id' AND code_complexity IS NOT NULL
                         GROUP BY code_complexity";
    $code_complex_result = $conn->query($code_complex_sql);
    $code_complexity = ['Low' => 0, 'Medium' => 0, 'High' => 0];
    while ($row = $code_complex_result->fetch_assoc()) {
        $code_complexity[$row['code_complexity']] = (int)$row['count'];
    }
    
    // Performance
    $perf_sql = "SELECT avg_quality_rating, avg_efficiency_rating, avg_teamwork_rating
                FROM employee_performance_summary 
                WHERE employee_email = '$employee_email' AND company_id = '$company_id'";
    $perf_result = $conn->query($perf_sql);
    $perf_data = $perf_result->fetch_assoc();
    
    // Feedback
    $feedback_sql = "SELECT feedback_text, rating_quality, rating_efficiency, rating_teamwork, created_at 
                   FROM task_feedback 
                   WHERE employee_email = '$employee_email' 
                   ORDER BY created_at DESC 
                   LIMIT 5";
    $feedback_result = $conn->query($feedback_sql);
    $feedback = [];
    while ($row = $feedback_result->fetch_assoc()) {
        $feedback[] = [
            'text' => $row['feedback_text'],
            'quality' => (int)$row['rating_quality'],
            'efficiency' => (int)$row['rating_efficiency'],
            'teamwork' => (int)$row['rating_teamwork'],
            'date' => date('M j, Y', strtotime($row['created_at']))
        ];
    }
    
    // Calculate metrics
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

    // Populate analytics
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
    $analytics['coding']['complexity_distribution'] = $code_complexity;
    
    // Insights
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
$conn->close();
?>