🧠 TaskFlow — Real-Time Task Tracking and Submission Platform
TaskFlow is a comprehensive web-based platform designed to improve organizational productivity through efficient task management, performance analytics, and automated quality verification using NLP and Machine Learning.

Built with HTML, CSS, PHP, JavaScript, and MySQL, TaskFlow integrates Python modules for intelligent text relevance checking, code complexity analysis, and smart analytics visualization — delivering an ML-NLP-enhanced workflow solution for modern teams.


🚀 Features:-
✅ Role-Based Access Control — Separate dashboards for Admins/Managers and Employees.
✅ Smart Task Assignment — Assign tasks based on employee skills and workload balance.
✅ NLP-Based Relevance Checker — Uses TF-IDF, cosine similarity, and BERT embeddings to validate task submissions.
✅ Performance Analytics Dashboard — Real-time visual insights using Chart.js.
✅ Code Complexity Analyzer — Evaluates programming tasks using Radon and Lizard libraries.
✅ Automated Email Notifications — PHPMailer with Gmail SMTP for task alerts and reminders.
✅ Secure File Management — Supports PDF, DOCX, TXT, and code file uploads.
✅ Smart Reminder System — Sends automated reminders for deadlines and updates.
✅ Grammar & Profanity Checker — Python-based validation for document quality.


🏗️ Tech Stack:-
Layer	Technologies Used
Frontend: HTML5, CSS3, JavaScript
Backend: PHP (with XAMPP/Apache), Python (for NLP & analytics)
Database: MySQL
Libraries (Python):spaCy, NLTK, Scikit-learn, Radon, Lizard, Pandas, Matplotlib, Sentence Transformers (BERT)
Email Service	PHPMailer (Gmail SMTP)
Analytics & Charts	Chart.js


⚙️ System Architecture:-
Three-Tier Architecture
Presentation Layer: Interactive dashboards built with HTML, CSS, and JavaScript.
Application Layer: PHP handles authentication, CRUD, and invokes Python scripts for NLP and analytics.
Data Layer: MySQL stores users, tasks, submissions, analytics, and logs.


🧩 Core Modules
🔐 1. User Authentication & Role-Based Access
Secure login using PHP sessions and hashed passwords.
Separate dashboards for Admins and Employees.

🧑‍💼 2. Admin Dashboard
Create, assign, and track tasks.
Manage employee records.
Create departments.
Add employees in departments.
Monitor employee performance analytics.

👷 3. Employee Dashboard
View assigned tasks.
Upload completed submissions.
Check for Grammatical Errors before submitting tasks.
Track deadlines and view feedback.
Add skills.

📂 4. Task Management
File upload/download with validation and virus scanning.
Real-time progress tracking and status updates.

🤖 5. NLP & ML-Based Relevance Checking
Text preprocessing via spaCy and NLTK.
TF-IDF + Cosine Similarity for surface matching.
BERT embeddings for semantic relevance analysis.

💻 6. Code Complexity Module
Uses Radon and Lizard to compute:
Cyclomatic Complexity
Maintainability Index
Lines of Code
Function Count
Time Complexity
Space Complexity

📈 7. Performance Analytics
Python scripts process MySQL data.
Chart.js visualizes employee productivity and completion rates.

📧 8. Email Notification System
PHPMailer sends automatic emails for:
Task Assignment


🧮 Database Design
Key Tables:
company_registration — Stores company details, admin credentials, and verification information.
employee_registration — Contains employee profiles linked to their respective companies.
department — Defines organizational departments with department codes and employee counts.
employees_in_department — Maps employees to departments for structured hierarchy.
documentation_tasks — Manages documentation-based tasks with NLP relevance scoring and deadlines.
coding_tasks — Handles programming tasks, code complexity metrics, and performance evaluation.
task_feedback — Stores admin feedback, quality ratings, and review comments for tasks.
employee_performance_summary — Aggregates employee performance data and overall productivity index.
task_reassignment_feedback — Logs task reassignments and related feedback for transparency.
employee_skills — Maintains employee skill records for intelligent, skill-based task allocation.


🧰 Installation & Setup
1️⃣ Clone the Repository
git clone https://github.com/parthkoli101/TaskFlow.git
cd TaskFlow

2️⃣ Set Up Local Server
Install XAMPP or WAMP.
Place the cloned TaskFlow folder inside the htdocs directory (for XAMPP users).

3️⃣ Import the Database
Open MySql Command Line Prompt.
Create a new database (e.g., taskflow1).
Copy the tables creation queries from sql1.txt and paste it on MySQL Command Line Prompt.

4️⃣ Configure Environment Variables
Create or edit the .env file and add the following configuration:
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=youremail@gmail.com
SMTP_PASS=yourapppassword
DB_HOST=localhost
DB_NAME=taskflow_db
DB_USER=root
DB_PASS=


5️⃣ Install Python Dependencies
Install all dependencies using the requirements file:
pip install spacy nltk scikit-learn radon lizard pandas matplotlib sentence-transformers language_tool_python better_profanity


6️⃣ Run the Application
Start Apache and MySQL from the XAMPP Control Panel.
Open your browser and navigate to:
👉 http://localhost/TaskFlow/


Content in sql1.txt(To Create Database/Tables):-

CREATE TABLE company_registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    company_id VARCHAR(100) NOT NULL UNIQUE,
    company_code VARCHAR(255) NOT NULL,  
    admin_firstname VARCHAR(100) NOT NULL,
    admin_lastname VARCHAR(100) NOT NULL,
    admin_email VARCHAR(255) NOT NULL UNIQUE,
    admin_password VARCHAR(255) NOT NULL, 
    is_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(255) NULL,
    reset_expiry DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employee_registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_firstname VARCHAR(100) NOT NULL,
    employee_lastname VARCHAR(100) NOT NULL,
    employee_email VARCHAR(255) NOT NULL UNIQUE,
    employee_password VARCHAR(255) NOT NULL,  
    company_id VARCHAR(100) NOT NULL,
    company_code VARCHAR(255) NOT NULL,  
    is_verified TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(255) NULL,
    reset_expiry DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE
);

CREATE TABLE department (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(50) UNIQUE,
    company_id VARCHAR(100) NOT NULL,
    department_code VARCHAR(255) NOT NULL,
    no_of_employees INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE
);

CREATE TABLE employees_in_department (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_firstname VARCHAR(100) NOT NULL,
    employee_lastname VARCHAR(100) NOT NULL,
    employee_email VARCHAR(255) NOT NULL UNIQUE,
    department_name VARCHAR(50) NOT NULL,
    company_id VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE,
    FOREIGN KEY (department_name) REFERENCES department(department_name) ON DELETE CASCADE
);

CREATE TABLE documentation_tasks (
    doc_task_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(100) NOT NULL,
    employee_id INT NOT NULL,
    task_title VARCHAR(255) NOT NULL,
    task_description TEXT,
    task_keywords TEXT,
    required_skill VARCHAR(255) NOT NULL,
    priority ENUM('low','medium','high') NOT NULL,
    difficulty ENUM('easy','medium','hard') NOT NULL,
    deadline TIMESTAMP NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_file VARCHAR(255),
    submitted_at TIMESTAMP NULL,
    submitted_on_time ENUM('yes','no'),
    relevance_score FLOAT,
    no_of_reassignments INT,
    last_reassigned_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_registration(id) ON DELETE CASCADE
);

CREATE TABLE coding_tasks (
    code_task_id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(100) NOT NULL,
    employee_id INT NOT NULL,
    task_title VARCHAR(255) NOT NULL,
    task_description TEXT,
    required_skill VARCHAR(255) NOT NULL,
    priority ENUM('low','medium','high') NOT NULL,
    difficulty ENUM('easy','medium','hard') NOT NULL,
    deadline TIMESTAMP NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_file VARCHAR(255),
    submitted_at TIMESTAMP NULL,
    submitted_on_time ENUM('yes','no'),
    code_complexity VARCHAR(20),
    time_complexity VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    space_complexity VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    code_cost INT,
    cyclomatic_complexity DECIMAL(10,2) DEFAULT NULL,
    lines_of_code INT DEFAULT NULL,
    function_count INT DEFAULT NULL,
    relevance_score FLOAT,
    no_of_reassignments INT,
    last_reassigned_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employee_registration(id) ON DELETE CASCADE
);

CREATE TABLE task_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_type ENUM('documentation','coding') NOT NULL,
    doc_task_id INT DEFAULT NULL,
    code_task_id INT DEFAULT NULL,
    employee_email VARCHAR(255) NOT NULL,
    reviewer_email VARCHAR(255) NOT NULL,
    feedback_text TEXT,
    rating_quality INT CHECK (rating_quality BETWEEN 1 AND 5),
    rating_efficiency INT CHECK (rating_efficiency BETWEEN 1 AND 5),
    rating_teamwork INT CHECK (rating_teamwork BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doc_task_id) REFERENCES documentation_tasks(doc_task_id) ON DELETE CASCADE,
    FOREIGN KEY (code_task_id) REFERENCES coding_tasks(code_task_id) ON DELETE CASCADE
);

CREATE TABLE employee_performance_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_email VARCHAR(255) NOT NULL,
    company_id VARCHAR(100) NOT NULL,
    total_tasks INT,
    tasks_completed INT,
    tasks_on_time INT,
    tasks_completed_low_priority INT,
    tasks_completed_medium_priority INT,
    tasks_completed_high_priority INT,
    tasks_completed_easy INT,
    tasks_completed_medium INT,
    tasks_completed_hard INT,
    avg_relevance_score FLOAT,
    avg_code_complexity VARCHAR(10),
    avg_quality_rating FLOAT,
    avg_efficiency_rating FLOAT,
    avg_teamwork_rating FLOAT,
    no_of_reassignments INT,
    performance_index FLOAT,
    summary_period DATE,
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE
);

CREATE TABLE task_reassignment_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_type ENUM('documentation','coding') NOT NULL,
    doc_task_id INT DEFAULT NULL,
    code_task_id INT DEFAULT NULL,
    reassigned_to_employee_id INT NOT NULL,
    reassigned_by_employee_email VARCHAR(255) NOT NULL,
    feedback_text TEXT,
    reassignment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doc_task_id) REFERENCES documentation_tasks(doc_task_id) ON DELETE CASCADE,
    FOREIGN KEY (code_task_id) REFERENCES coding_tasks(code_task_id) ON DELETE CASCADE,
    FOREIGN KEY (reassigned_to_employee_id) REFERENCES employee_registration(id) ON DELETE CASCADE
);

CREATE TABLE employee_skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_email VARCHAR(255) NOT NULL,
    company_id VARCHAR(100) NOT NULL,
    skills TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_employee_skills (employee_email, company_id),
    FOREIGN KEY (company_id) REFERENCES company_registration(company_id) ON DELETE CASCADE
);
