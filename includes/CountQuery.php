<?php

enum CountQueryMode {
    case Redirect;
    case Link;
    case Transclusion;
}

class CountQuery {
    public const SINGLE_NS = 1;
    public const NO_FROM_NS = 2;
    public const NO_LINK_TARGET = 4;

	private $fromNamespaces;
    private $db;
    private $title;

    public function __construct(string $fromNamespaces, PDO $db, Title $title) {
		$this->fromNamespaces = $fromNamespaces;
        $this->db = $db;
        $this->title = $title;
    }

    private function createCond(
        string $prefix,
        string $titleSQL,
        string $namespaceSQL,
        int $flags,
        $joins = [],
        $wheres = []
    ) {
        $hasFromNS = ~$flags & CountQuery::NO_FROM_NS;
        $usesLinkTarget = ~$flags & CountQuery::NO_LINK_TARGET;
        $hasNS = $usesLinkTarget || (~$flags & CountQuery::SINGLE_NS);

        if ($this->fromNamespaces !== '' && $hasFromNS) {
            array_push($wheres, "{$prefix}_from_namespace IN ({$this->fromNamespaces})");
        }

        if ($this->fromNamespaces !== '' && !$hasFromNS) {
            array_push(
                $joins,
                <<<SQL
                    JOIN page AS source ON
                        source.page_id = {$prefix}_from
                        AND source.page_namespace IN ({$this->fromNamespaces})
                SQL
            );
        }

        $linkInfoPrefix = $usesLinkTarget ? 'lt' : $prefix;
		$titleColumn = $linkInfoPrefix . '_' . ($hasNS ? 'title' : 'to');

        array_push($wheres, "$titleColumn = $titleSQL");

        if ($hasNS) {
            array_push($wheres, "{$linkInfoPrefix}_namespace = $namespaceSQL");
        }

        if ($usesLinkTarget) {
            array_push($joins, "JOIN linktarget ON {$prefix}_target_id = lt_id");
        }

        return implode(' ', $joins) . " WHERE " . implode(' AND ', $wheres);
    }

    private function createDirectCond(string $prefix, int $flags) {
        return $this->createCond(
            $prefix,
            $this->db->quote($this->title->getDBKey()),
            $this->title->getNamespaceId(),
            $flags
        );
    }

    private function createIndirectCond(string $table, string $prefix, int $flags) {
        $joins = [
            'JOIN page AS target ON target.page_id = rd_from',
            "JOIN $table"
        ];

        $wheres = [
            "rd_title = {$this->db->quote($this->title->getDBKey())}",
            "rd_namespace = {$this->title->getNamespaceId()}",
            "(rd_interwiki IS NULL OR rd_interwiki = {$this->db->quote('')})"
        ];

        return $this->createCond(
            $prefix,
            'target.page_title',
            'target.page_namespace',
            $flags,
            $joins,
            $wheres
        );
    }

    private function createQuery(string $table, string $prefix, CountQueryMode $mode, int $flags) {
        return match ($mode) {
            CountQueryMode::Redirect => <<<SQL
                SELECT COUNT(rd_from) FROM $table
                {$this->createDirectCond($prefix, $flags)}
                AND ({$prefix}_interwiki is NULL or {$prefix}_interwiki = {$this->db->quote('')})
            SQL,
			// Transclusions of a redirect that follow the redirect are also added as a transclusion of the redirect target.
			// There is no way to differentiate from a page with a indirect link and a page with a indirect and a direct link
			// in this case, only the indirect link is recorded. Pages can also transclude a page with a redirect without
			// following the redirect, so a valid indirect link must have an associated direct link.
            CountQueryMode::Transclusion => <<<SQL
                SELECT
                    COUNT({$prefix}_from),
                    COUNT({$prefix}_from) - COUNT(indirect_link),
                    COUNT(indirect_link)
                FROM $table
                LEFT JOIN (
                    SELECT DISTINCT {$prefix}_from AS indirect_link
                    FROM redirect
                    {$this->createIndirectCond($table, $prefix, $flags)}
                ) AS temp ON {$prefix}_from = indirect_link
                {$this->createDirectCond($prefix, $flags)}
            SQL,
            CountQueryMode::Link => <<<SQL
                SELECT
                    COUNT(DISTINCT COALESCE(direct_link, indirect_link)),
                    COUNT(direct_link),
                    COUNT(indirect_link)
                FROM (
                    SELECT {$prefix}_from AS direct_link, NULL AS indirect_link
                    FROM $table
                    {$this->createDirectCond($prefix, $flags)}
                    UNION ALL
                    SELECT DISTINCT NULL AS direct_link, {$prefix}_from AS indirect_link
                    FROM redirect
                    {$this->createIndirectCond($table, $prefix, $flags)}
                ) AS temp
            SQL
        };
    }

    public function runQuery(string $table, string $prefix, CountQueryMode $mode, $flags = 0) {
        $query = $this->createQuery($table, $prefix, $mode, $flags);
        $res = $this->db->query($query)->fetch();

		return $mode == CountQueryMode::Redirect ? (int) $res[0] : [
			'all' => (int) $res[0],
			'direct' => (int) $res[1],
			'indirect' => (int) $res[2]
		];
    }
}