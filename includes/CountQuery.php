<?php

enum CountQueryMode {
	case Redirect;
	case Link;
	case Transclusion;
}

enum CountQueryFromNS {
	case Column;
	case NoColumn;
}

class CountQuery {
	private $fromNamespaces;
	private $db;
	private $title;

	public function __construct(string $fromNamespaces, PDO $db, Title $title) {
		$this->fromNamespaces = $fromNamespaces;
		$this->db = $db;
		$this->title = $title;
	}

	private function joinClauses(array $joins) {
		return implode("\n", $joins);
	}

	private function joinConds(array $conds) {
		return implode(' AND ', $conds);
	}

	private function getPageConds(string $table, string $prefix, string|null $titleSql = null, string|null $namespaceSql = null) {
		$titleSql ??= $this->db->quote($this->title->getDBKey());
		$namespaceSql ??= $this->db->quote($this->title->getNamespaceId());

		$wheres = [
			"{$prefix}_title = {$titleSql}",
			"{$prefix}_namespace = {$namespaceSql}"
		];

		if ($table === 'redirect') {
			$wheres[] = "({$prefix}_interwiki IS NULL OR {$prefix}_interwiki = {$this->db->quote('')})";
		}

		return $wheres;
	}

	private function createCond(
		string $baseTable, string $basePrefix,
		string $linkTable, string $linkPrefix,
		bool $hasFromNS,
		array $joins = [], string $addl = ''
	) {
		$linkTableIsBase = $baseTable === $linkTable;
		$wheres = $this->getPageConds($baseTable, $basePrefix);

		if (!$linkTableIsBase) {
			$joins[] = "JOIN $linkTable ON {$linkPrefix}_target_id = lt_id";
		}

		if ($this->fromNamespaces !== '') {
			if ($hasFromNS) {
				$cond = "{$linkPrefix}_from_namespace IN ({$this->fromNamespaces})";

				if ($linkTableIsBase) {
					$wheres[] = $cond;
				} else {
					$joins[count($joins) - 1] .= " AND $cond";
				}
			} else {
				$joins[] = <<<SQL
					JOIN page AS source
						ON source.page_id = {$linkPrefix}_from
						AND source.page_namespace IN ({$this->fromNamespaces})
					SQL;
			}
		}

		if ($addl) {
			$joins[] = $addl;
		}

		return <<<SQL
			FROM $baseTable
			{$this->joinClauses($joins)}
			WHERE {$this->joinConds($wheres)}
			SQL;
	}

	private function createDirectNonLTCond(string $table, string $prefix, bool $hasFromNS, string $addl = '') {
		return $this->createCond($table, $prefix, $table, $prefix, $hasFromNS, addl: $addl);
	}

	private function createDirectLTCond(string $table, string $prefix, int $hasFromNS, string $addl = '') {
		return $this->createCond('linktarget', 'lt', $table, $prefix, $hasFromNS, addl: $addl);
	}

	private function createIndirectLTCond(string $table, string $prefix, bool $hasFromNS) {
		return $this->createCond('redirect', 'rd', $table, $prefix, $hasFromNS, [
			"JOIN page AS target ON target.page_id = rd_from",
			"JOIN linktarget ON {$this->joinConds($this->getPageConds('linktarget', 'lt', 'target.page_title', 'target.page_namespace'))}"
		]);
	}

	private function createQuery(string $table, string $prefix, CountQueryMode $mode, CountQueryFromNS $fromNS) {
		$hasFromNS = $fromNS === CountQueryFromNS::Column;

		return match ($mode) {
			CountQueryMode::Redirect => <<<SQL
				SELECT COUNT(*)
				{$this->createDirectNonLTCond($table, $prefix, $hasFromNS)}
				SQL,
			// Transclusions of a redirect that follow the redirect are also added as a transclusion of the redirect target.
			//
			// Caveat: There is no way to differentiate a page with an indirect link vs. a page with an indirect and a direct
			// link. In this case, only the indirect link is recorded.
			//
			// LEFT JOIN: Pages can also transclude a page with a redirect without following the redirect, so a valid indirect
			// link must also have an associated direct link.
			CountQueryMode::Transclusion => <<<SQL
				SELECT
					COUNT(*),
					COUNT(*) - COUNT(indirect_link),
					COUNT(indirect_link)
				{$this->createDirectLTCond($table, $prefix, $hasFromNS, <<<SQL2
				LEFT JOIN (
					SELECT DISTINCT {$prefix}_from AS indirect_link
					{$this->createIndirectLTCond($table, $prefix, $hasFromNS)}
				) AS temp ON {$prefix}_from = indirect_link
				SQL2)}
				SQL,
			CountQueryMode::Link => <<<SQL
				SELECT
					COUNT(DISTINCT COALESCE(direct_link, indirect_link)),
					COUNT(direct_link),
					COUNT(indirect_link)
				FROM (
					SELECT {$prefix}_from AS direct_link, NULL AS indirect_link
					{$this->createDirectLTCond($table, $prefix, $hasFromNS)}
					UNION ALL
					SELECT DISTINCT NULL AS direct_link, {$prefix}_from AS indirect_link
					{$this->createIndirectLTCond($table, $prefix, $hasFromNS)}
				) AS temp
				SQL
		};
	}

	public function runQuery(string $table, string $prefix, CountQueryMode $mode, CountQueryFromNS $fromNS = CountQueryFromNS::Column) {
		$query = $this->createQuery($table, $prefix, $mode, $fromNS);
		$res = $this->db->query($query)->fetch();

		return $mode == CountQueryMode::Redirect ? (int) $res[0] : [
			'all' => (int) $res[0],
			'direct' => (int) $res[1],
			'indirect' => (int) $res[2]
		];
	}
}
