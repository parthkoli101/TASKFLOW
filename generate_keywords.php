<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $cmd = "python keyword_generator.py \"" . addslashes($title) . "\" \"" . addslashes($description) . "\"";
    $output = shell_exec($cmd);
    echo $output;
}
?>
