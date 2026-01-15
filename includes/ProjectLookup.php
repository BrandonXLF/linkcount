<?php

class ProjectLookup {
	private static array $cache = [];

	public static function stripPotentialURL(string $project): string {
		// Strip http(s):// for consistency
		$project = preg_replace('/^https?:\/\//', '', $project);

		// Strip any trailing / or wiki suffixes
		$project = preg_replace('/\/(wiki\/?)?$/', '', $project);

		return 'https://' . $project;
	}

	private static function queryDatabase(string $project): ?ProjectInfo {
		$maybeProjectURL = self::stripPotentialURL($project);

		$db = DatabaseFactory::create();
		$stmt = $db->prepare('SELECT dbname, url FROM wiki WHERE dbname = ? OR url = ? LIMIT 1');
		$stmt->execute([$project, $maybeProjectURL]);

		$res = $stmt->fetchObject(ProjectInfo::class);
		return $res ?: null;
	}

	public static function lookupProject(string $project): ?ProjectInfo {
		if (!isset(self::$cache[$project])) {
			self::$cache[$project] = self::queryDatabase($project);
		}

		return self::$cache[$project];
	}
}
