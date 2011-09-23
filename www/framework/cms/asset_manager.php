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
    const ASSET_PATH = "lib/assets";
    
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
        // TODO: does not work!
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

        // move file to destination
        // TODO: use move_uploaded_file instead?
        //rename($original_file, self::get_filepath($node_id, $processed_filename));
    }

    /*
     * filter:
     *  page_id
     *  filetype
     *  ...
     */
    public function basic_search($needle, $filters = array(), $select = "node_id", $fetch_style = \PDO::FETCH_COLUMN) {
        // search for $needle in filename and tags
        $query_str =
            "SELECT DISTINCT {$this->assets_tbl}.$select " .
            "FROM {$this->assets_tbl} " .
            "LEFT JOIN {$this->assets_tags_tbl} ON {$this->assets_tbl}.id = {$this->assets_tags_tbl}.asset_id " .
            "LEFT JOIN {$this->tags_tbl} ON {$this->assets_tags_tbl}.tag_id = {$this->tags_tbl}.id " .
            "WHERE " .
                "(MATCH(processed_filename) AGAINST(:needle) " .
                "OR {$this->tags_tbl}.name = :needle) ";

        foreach ($filters as $filter => $value) {
            $query_str .= "AND {$filter} = :{$filter}";
        }

        $query = $this->pdo->prepare($query_str);
        $query->execute(array_merge(array(
            "needle" => $needle,
        ), $filters));

        return $query->fetchAll($fetch_style);
    }

    public function search($needle, $filters = array()) {
        $ids = array_filter($this->basic_search($needle, $filters));
        if (empty($ids))
            return false;

        /* try to minimize db queries,
         * only retrieve found assets from xml tree.
         * all tags are retrieved as there is currently no way to restrict that.
         * uneccesary tags without assets are filtered later.
         */

        $xml_filter = "id in (" . implode(",", $ids) . ") OR name != 'asset'";
        $doc_info = $this->xmldb->get_doc_info($this->doc_id);
        
        $doc = $this->xmldb->get_subdoc_by_elementId($this->doc_id, $doc_info->rootid, true, PHP_INT_MAX, $xml_filter);

        // remove all tags that have no assets as descendants
        $xpath = new DOMXPath($doc);
        $remove_nodes = $xpath->query("//tag[not(descendant::asset)]");
        foreach ($remove_nodes as $node) {
            $node->parentNode->removeChild($node);
        }

        return $doc;
    }

    static private function get_filepath($id, $processed_filename) {
        return DEPAGE_PATH . "/" . ASSET_PATH . "/" . $id . "." . $processed_filename;
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
