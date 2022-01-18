<?php

require '../vendor/autoload.php';

$project = $_GET['project'] ?? '';
$projects = [];

$db = DatabaseFactory::create('metawiki.web.db.svc.wikimedia.cloud', 'meta_p');

$stmt = $db->prepare('SELECT url FROM wiki WHERE url LIKE ? OR dbname LIKE ?');
$stmt->execute(['https://' . $project . '%', $project . '%']);

foreach ($stmt->fetchAll() as $row) {
	$projects[] = substr($row[0], 8);
}

header('Content-Type: application/json');
echo json_encode($projects);
