<?php

require '../includes/global.php';

$project = $_GET['project'] ?? '';
$projects = [];

$db = db_connect();

$stmt = $db->prepare('SELECT url FROM wiki WHERE url LIKE ?');
$projectSQL = 'https://' . $project . '%';
$stmt->bind_param('s', $projectSQL);

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

foreach ($res->fetch_all() as $row) {
    $projects[] = substr($row[0], 8);
}

header('Content-Type: application/json');
echo json_encode($projects);