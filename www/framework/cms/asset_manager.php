<?php
/**
 * @file    framework/cms/asset_manager.php
 *
 * depage asset manager module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

//schema:
//    assets:
//        id INT(11),
//        page INT(11),
//        size VARCHAR(31),
//        filetype VARCHAR(31),
//        date DATETIME,
//        original_filename VARCHAR(255),    TODO: is 255 enough? cutoff on upload?
//        processed_filename VARCHAR(255),
//
//    tags:
//        id INT(11,
//        name,
//
//    assets_tags:
//        asset_id INT(11),
//        tag_id INT(11),
//
//
//indexes:
//    FULL TEXT index on processed filename
//    INDEX (file_id, tag_id) and INDEX(tag_id, file_id)
//    index for page, size, filetype, date
//

class asset_manager {
    const PARTIAL_ASSET_PATH = "lib/assets";
    
    /* {{{ constructor */
    /**
     * @param       $prefix database table prefix
     * @param       $pdo (PDO) pdo object for database access
     * @param       $xmldb (xmldb) xmldb object for xml database access
     * @param       $doc_id (int) xml document id
     */
    public function __construct($prefix, $pdo, $xmldb, $doc_id) {
        $this->prefix = $prefix;
        $this->assets_tbl = "{$this->prefix}_assets";
        $this->tags_tbl = "{$this->prefix}_tags";
        $this->assets_tags_tbl = "{$this->prefix}_assets_tags";
        $this->pdo = $pdo;
        $this->xmldb = $xmldb;
        $this->doc_id = $doc_id;
    }
    /* }}} */

    public function basic_create($original_file, $original_filename, $processed_filename, $parent_id, $position = -1, $filetype = null, $size = null, $created_at = null, $page_id = null, $tags = array()) {
        // insert into xml doc
        $node = $this->xmldb->build_node($this->doc_id, "asset", array("name" => $original_filename));
        $node_id = $this->xmldb->add_node($this->doc_id, $node, $parent_id, $position);

        // store additional data
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tbl} SET " .
            "node_id = :node_id," .
            "processed_filename = :processed_filename," .
            "filetype = :filetype," .
            "size = :size," .
            "created_at = :created_at," .
            "page_id = :page_id"
        );
        $query->execute(array(
            "node_id" => $node_id,
            "processed_filename" => $processed_filename,
            "filetype" => $filetype,
            "size" => $size,
            "created_at" => $created_at,
            "page_id" => $page_id,
        ));
        $asset_id = $this->pdo->lastInsertId();

        // store new tags
        $query = $this->pdo->prepare("INSERT IGNORE INTO {$this->tags_tbl} SET name = :name");
        foreach ($tags as $tag) {
            $query->execute(array(
                "name" => $tag,
            ));
        }

        // associate tags and asset
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tags_tbl} (asset_id, tag_id) SELECT :asset_id, id FROM {$this->tags_tbl} WHERE name = :name");
        foreach ($tags as $tag) {
            $query->execute(array(
                "asset_id" => $asset_id,
                "name" => $tag,
            ));
        }

        self::move_file($original_file, $created_at, $asset_id, $processed_filename);
    }

    // {{{ basic_search
    /*
     * search for $needle in filename and tags.
     * filter results by defined $filters.
     *
     * either $needle or $filters needs to be present.
     */
    public function basic_search($needle, $filters = array(), $select = "node_id", $fetch_style = \PDO::FETCH_COLUMN) {

        $query_str =
            "SELECT DISTINCT {$this->assets_tbl}.$select " .
            "FROM {$this->assets_tbl} ";

        if ($needle) {
            $query_str .=
                "LEFT JOIN {$this->assets_tags_tbl} ON {$this->assets_tbl}.id = {$this->assets_tags_tbl}.asset_id " .
                "LEFT JOIN {$this->tags_tbl} ON {$this->assets_tags_tbl}.tag_id = {$this->tags_tbl}.id " .
                "WHERE " .
                    "(MATCH(processed_filename) AGAINST(:needle) " .
                    "OR {$this->tags_tbl}.name = :needle) ";
        } else if ($filters) {
            $query_filters = array();
            foreach ($filters as $filter => $value) {
                $query_filters[] = "{$filter} = :{$filter}";
            }

            $query_str .= "WHERE " . implode(" AND ", $query_filters);
        }

        $query = $this->pdo->prepare($query_str);
        $query->execute(array_merge(array(
            "needle" => $needle,
        ), $filters));

        return $query->fetchAll($fetch_style);
    }
    // }}}

    // {{{
    /*
     * search for $needle in filename and tags.
     * filter results by defined $filters.
     *
     * if neither $needle nor $filters is present then all assets are returned.
     */
    public function search($needle, $filters = array()) {
        if ($needle || $filters) {
            $ids = array_filter($this->basic_search($needle, $filters));
            if (empty($ids))
                return false;

            /*
             * try to minimize db queries,
             * only retrieve found assets from xml tree.
             * all tags are retrieved as there is currently no way to restrict that.
             * unneccesary tags without assets are filtered later.
             */

            $xml_filter = "id in (" . implode(",", $ids) . ") OR name != 'asset'";
        }

        $doc_info = $this->xmldb->get_doc_info($this->doc_id);
        $doc = $this->xmldb->get_subdoc_by_elementId($this->doc_id, $doc_info->rootid, true, PHP_INT_MAX, $xml_filter);

        if ($needle || $filters) {
            /*
             * remove all tags that have no assets as descendants
             */

            $xpath = new DOMXPath($doc);
            $remove_nodes = $xpath->query("//tag[not(descendant::asset)]");
            // TODO: maybe catch notFound Exceptions ??
            foreach ($remove_nodes as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        return $doc;
    }

    public function all() {
        return search(null);
    }

    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
    static private function move_file($original_file, $created_at, $asset_id, $processed_filename) {
        mkdir(self::full_asset_path($created_at), 0777, true);
        // TODO: use move_uploaded_file instead?
        rename($original_file, self::get_filepath($created_at, $asset_id, $processed_filename));
    }

    static private function get_filepath($created_at, $id, $processed_filename) {
        return self::full_asset_path($created_at) . "/" . $id . "." . $processed_filename;
    }

    static private function full_asset_path($timestamp) {
        return DEPAGE_PATH . "/" . self::PARTIAL_ASSET_PATH . "/" . date("Y/m", $timestamp);
