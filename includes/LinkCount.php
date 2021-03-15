<?php

require __DIR__ . '/Database.php';

define('SINGLE_NAMESPACE', 1);
define('NO_INDIRECTS', 2);
define('NO_FROM_NAMESPACE', 4);

class LinkCount {
    private $counts;
    private $error;
    private $project_url;

    private $db;
    private $namespace;
    private $title;
    private $namespaces;
    
    public function __construct() {
        $page = $_GET['page'] ?? '';
        $project = $_GET['project'] ?? '';
        $namespaces = $_GET['namespaces'] ?? '';
        
        if (!$page && !$project) {
            return [];
        }
        
        if (!$page) {
            $this->error = 'Page name is required.';
            return;
        }
        
        if (!$project) {
            $project = 'en.wikipedia.org';
        }

        $this->namespaces = $namespaces ? explode(',', $namespaces) : [];
        $this->title = str_replace(' ', '_', $page);
        $this->title = ucfirst($this->title);

        if (substr($project, -2) === '_p') {
            $project = substr($project, 0, -2);
        } elseif (substr($project, 0, 8) === 'https://') {
            $project = substr($project, 8);
        } elseif (substr($project, 0, 7) === 'http://') {
            $project = substr($project, 7);
        }
        
        $maybe_project_url = 'https://' . $project;
        $this->db = new Database();

        $stmt = $this->db->prepare('SELECT dbname, url FROM wiki WHERE dbname=? OR url=? LIMIT 1');
        $stmt->bind_param('ss', $project, $maybe_project_url);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        if (!$res->num_rows) {
            $this->error = 'That project does not exist..';
            return;
        }

        list($dbname, $this->project_url) = $res->fetch_row();
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->project_url . '/w/api.php?action=query&prop=info&format=json&formatversion=2&titles=' . rawurlencode($this->title),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $info = json_decode(curl_exec($curl));
        curl_close($curl);
        
        $this->namespace = $info->query->pages[0]->ns;

        if ($this->namespace != 0) {
            $this->title = explode(':', $this->title, 2)[1];
        }

        $this->db->select_db("{$dbname}_p");

        $redirects = $this->fetch('redirect', 'rd', NO_INDIRECTS | NO_FROM_NAMESPACE);
        $wikilinks = $this->fetch('pagelinks', 'pl', 0, $redirects);
        $transclusions = $this->fetch('templatelinks', 'tl');
        # Images links from redirects are also added to the imagelinks table under the redirect target
        $filelinks = $this->namespace == 6 ? $this->fetch('imagelinks', 'il', NO_INDIRECTS | SINGLE_NAMESPACE) : null;
        $categorylinks = $this->namespace == 14 ? $this->fetch('categorylinks', 'cl', SINGLE_NAMESPACE | NO_FROM_NAMESPACE) : null;

        $this->counts = [
            'filelinks' => $filelinks,
            'categorylinks' => $categorylinks,
            'wikilinks' => $wikilinks,
            'redirects' => $redirects,
            'transclusions' => $transclusions
        ];
    }
    
    private function fetch($table, $prefix, $flags = 0, $subtract = 0) {
        $title_column = $prefix . '_' . ($flags & SINGLE_NAMESPACE ? 'to' : 'title');
        
        $d_tables = [$table];
        $d_where = ["$title_column=?"];
        $d_types = 's';
        $d_params = [$this->title];
        
        $i_tables = [$table, 'redirect', 'page p1'];
        $i_where = ['rd_from=p1.page_id', "p1.page_title=$title_column", 'rd_namespace=?', 'rd_title=?'];
        $i_types = 'is';
        $i_params = [$this->namespace, $this->title];
        
        # Check if the link targets the namespace of this page
        if (~$flags & SINGLE_NAMESPACE) {
            $d_where[] = "{$prefix}_namespace=?";
            $d_params[] = $this->namespace;
            $d_types .= 'i';

            $i_where[] = "{$prefix}_namespace=p1.page_namespace";
        }
        
        # Check if link comes from one of the selected namespaces
        if ($this->namespaces) {
            $ns_match = implode(',', array_fill(0, count($this->namespaces), '?'));
            $ns_type = str_repeat('i', count($this->namespaces));
            
            $d_types .= $ns_type;
            $i_types .= $ns_type;
            
            foreach ($this->namespaces as $namespace) {
                $d_params[] = $i_params[] = $namespace;
            }
            
            if ($flags & NO_FROM_NAMESPACE) {
                $d_tables[] = $i_tables[] = 'page p2';
                $d_where[] = $i_where[] = "{$prefix}_from=p2.page_id";
                $d_where[] = $i_where[] = "p2.page_namespace IN ($ns_match)";
            } else {
                $d_where[] = $i_where[] = "{$prefix}_from_namespace IN ($ns_match)";
            }
        }
        
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ' . implode(',', $d_tables) . ' WHERE ' . implode(' AND ', $d_where));
        $stmt->bind_param($d_types, ...$d_params);
        $stmt->execute();
        $d_count = $stmt->get_result()->fetch_row()[0] - $subtract;
        $stmt->close();
        
        if (~$flags & NO_INDIRECTS) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ' . implode(',', $i_tables) . ' WHERE ' . implode(' AND ', $i_where));
            $stmt->bind_param($i_types, ...$i_params);
            $stmt->execute();
            $i_count = $stmt->get_result()->fetch_row()[0];
            $stmt->close();
            
            return [
                'direct' => $d_count,
                'indirect' => $i_count,
                'all' => $d_count + $i_count
            ];
        }
        
        return $d_count;
    }
    
    private function create_out($label, $num, $class = '') {
        $formatted = number_format($num);
        return "<div class=\"out $class\"><div>$label </div><div class=\"num\">$formatted</div></div>";
    }
    
    public function html() {
        $out = '';
        
        if (isset($this->error)) {
            $out = "<div class=\"error\">{$this->error}</div>";
        }
        
        if (isset($this->counts)) {
            foreach ($this->counts as $type => $count) {
                if ($count === null) continue;
                
                if (is_int($count)) {
                    $out .= $this->create_out(ucfirst($type), $count);
                    continue;
                }
                
                $out .= $this->create_out("Direct $type", $count['direct'], 'left');
                $out .= $this->create_out("All $type", $count['all'], 'right');
            }
        
            $link = $this->project_url . '/wiki/Special:WhatLinksHere/' . rawurlencode($_GET['page']);
            $out .= "<div class=\"links\"><a href=\"$link\">What links here</a></div>";
        }
        
        return $out;
    }
    
    public function json() {
        $out = [];
        
        if (isset($this->error)) {
            $out['error'] = $this->error;
        }
        
        if (isset($this->counts)) {
            foreach ($this->counts as $type => $count) {
                if ($count === null) continue;

                $out[$type] = $count;
            }
        }
    
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        return json_encode($out);
    }
}