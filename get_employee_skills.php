<?php
session_start();

if (!isset($_SESSION['admin_email'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(array('error' => 'Unauthorized access'));
    exit();
}

require 'vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$servername = "localhost";
$username = "root";
$password = "Parth@23102025";
$dbname = "taskflow1";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(array('error' => 'Database connection failed'));
    exit();
}

header('Content-Type: application/json');

if (!isset($_GET['employee_email']) || !isset($_GET['company_id'])) {
    echo json_encode(array('error' => 'Missing parameters'));
    exit();
}

$employee_email = $_GET['employee_email'];
$company_id = $_GET['company_id'];

try {
    // First, get employee name
    $name_sql = "SELECT employee_firstname, employee_lastname 
                 FROM employees_in_department 
                 WHERE employee_email = ? AND company_id = ?";
    $name_stmt = $conn->prepare($name_sql);
    $name_stmt->bind_param("ss", $employee_email, $company_id);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    
    $employee_name = '';
    if ($name_result->num_rows > 0) {
        $employee = $name_result->fetch_assoc();
        $employee_name = $employee['employee_firstname'] . ' ' . $employee['employee_lastname'];
    } else {
        // Fallback to employee_registration table
        $fallback_sql = "SELECT employee_firstname, employee_lastname 
                         FROM employee_registration 
                         WHERE employee_email = ? AND company_id = ?";
        $fallback_stmt = $conn->prepare($fallback_sql);
        $fallback_stmt->bind_param("ss", $employee_email, $company_id);
        $fallback_stmt->execute();
        $fallback_result = $fallback_stmt->get_result();
        
        if ($fallback_result->num_rows > 0) {
            $employee = $fallback_result->fetch_assoc();
            $employee_name = $employee['employee_firstname'] . ' ' . $employee['employee_lastname'];
        } else {
            $employee_name = $employee_email;
        }
        $fallback_stmt->close();
    }
    $name_stmt->close();
    
    // Get employee skills
    $skills_sql = "SELECT skills FROM employee_skills WHERE employee_email = ? AND company_id = ?";
    $skills_stmt = $conn->prepare($skills_sql);
    $skills_stmt->bind_param("ss", $employee_email, $company_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    
    $skills = array();
    if ($skills_result->num_rows > 0) {
        $skills_row = $skills_result->fetch_assoc();
        if (!empty($skills_row['skills'])) {
            // Split comma-separated skills and trim each one
            $raw_skills = explode(',', $skills_row['skills']);
            foreach ($raw_skills as $skill) {
                $trimmed_skill = trim($skill);
                if (!empty($trimmed_skill)) {
                    $skills[] = $trimmed_skill;
                }
            }
        }
    }
    
    $skills_stmt->close();
    
    // Return the response
    echo json_encode(array(
        'employee_name' => $employee_name,
        'skills' => $skills
    ));
    
} catch (Exception $e) {
    error_log("Error fetching employee skills: " . $e->getMessage());
    echo json_encode(array('error' => 'Failed to fetch employee skills'));
}

$conn->close();
?>