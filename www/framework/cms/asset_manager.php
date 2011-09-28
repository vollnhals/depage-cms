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
    const ROOT_TAG = "dir";
    const DIR_TAG = "dir";
    const ASSET_TAG = "asset";
    
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

    public function basic_create($original_file, $original_filename, $processed_filename, $parent_id, $position = -1, $filetype = null, $width = null, $height = null, $created_at = null, $page_id = null, $tags = array()) {
        // insert into xml doc
        $node = $this->xmldb->build_node($this->doc_id, self::ASSET_TAG, array("name" => $original_filename));
        $node_id = $this->xmldb->add_node($this->doc_id, $node, $parent_id, $position);

        // store additional data
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tbl} SET " .
            "node_id = :node_id," .
            "processed_filename = :processed_filename," .
            "filetype = :filetype," .
            "width = :width," .
            "height = :height" .
            "created_at = :created_at," .
            "page_id = :page_id"
        );
        $query->execute(array(
            "node_id" => $node_id,
            "processed_filename" => $processed_filename,
            "filetype" => $filetype,
            "width" => $width,
            "height" => $height,
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

    // {{{ create
    /*
     * creates a new asset
     *
     * @param       $original_file (string)     path to file on disk
     * @param       $xml_path (string)          path in xml tree to structure assets. for example "/photos/new/good".
     *                                          path is split on "/". each part will automatically become a tag.
     * @param       $additional tags            additional tags for this asset
     */
    public function create($original_file, $xml_path, $page_id = null, $additional_tags = array()) {
        $path_parts = pathinfo($original_file);
        $original_filename = $path_parts["basename"];
        $filetype = $path_parts["extension"];
        $processed_filename = self::process_filename($path_parts["filename"]);
        list($width, $height) = getimagesize($original_file);
        $created_at = time();

        $parent_id = $this->create_xml_dirs($xml_path);
        $path_tags = array_filter(explode("/", $xml_path));

        return $this->basic_create($original_file, $original_filename, $processed_filename, $parent_id, -1, $filetype, $width, $height, $created_at, $page_id, array_merge($additional_tags, $path_tags));
    }
    // }}}

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

    private function create_xml_dirs($xml_path) {
        $doc_info = $this->xmldb->get_doc_info($this->doc_id);
        $parent_id = $doc_info->rootid;
        $dirs = array_filter(explode("/", $xml_path));
        $xpath = "/" . self::ROOT_TAG;

        foreach ($dirs as $dir) {
            $xpath .= "/" . self::DIR_TAG . "[@name='{$dir}']";
            $element_ids = $this->xmldb->get_elementIds_by_xpath($this->doc_id, $xpath);

            if (empty($element_ids)) {
                $node = $this->xmldb->build_node($this->doc_id, self::DIR_TAG, array("name" => $dir));
                $parent_id = $this->xmldb->add_node($this->doc_id, $node, $parent_id, -1);
            } else {
                $parent_id = $element_ids[0];
            }
        }

        return $parent_id;
    }

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
    }

    static private function process_filename($original_filename) {
        // inspired by http://neo22s.com/slug/
        $accents = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
        $repl = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
        $processed_filename = str_replace($accents, $repl, $original_filename);

        $processed_filename = strtolower(trim($processed_filename));

        // adding - for spaces and union characters
        $find = array(' ', '&', '\r\n', '\n', '+', ',');
        $processed_filename = str_replace ($find, '-', $processed_filename);

        // delete and replace rest of special chars
        $find = array('/[^a-z0-9\-<>]/', '/[\-]+/', '/<[^>]*>/');
        $repl = array('', '-', '');
        $processed_filename = preg_replace ($find, $repl, $processed_filename);

        return $processed_filename;
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
