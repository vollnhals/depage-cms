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

namespace depage\cms;


// DB SCHEMA:
//
// asset code needs a "assets" xml doc with root node.
//
//
//INSERT INTO `tt_proj_xmldocs` (`id`, `name`, `ns`, `entities`, `rootid`, `permissions`)
//VALUES
//	(1, 'assets', '', '', 1, 'a:2:{i:0;a:2:{s:5:\"asset\";a:1:{i:0;s:3:\"dir\";}s:3:\"dir\";a:1:{i:0;s:3:\"dir\";}}i:1;a:0:{}}');
//INSERT INTO `tt_proj_xmltree` (`id`, `id_parent`, `id_doc`, `pos`, `name`, `value`, `type`)
//VALUES
//	(1, NULL, 1, 0, 'dir', '', 'ELEMENT_NODE');
//
//
//CREATE TABLE `tt_proj_assets` (
//  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
//  `processed_filename` varchar(255) DEFAULT NULL,
//  `filetype` varchar(7) DEFAULT NULL,
//  `width` mediumint(8) unsigned DEFAULT NULL,
//  `height` mediumint(8) unsigned DEFAULT NULL,
//  `created_at` datetime DEFAULT NULL,
//  `page_id` int(11) unsigned DEFAULT NULL,
//  `node_id` int(11) unsigned DEFAULT NULL,
//  PRIMARY KEY (`id`),
//  FULLTEXT KEY `processed_filename` (`processed_filename`)
//) ENGINE=MyISAM AUTO_INCREMENT=30 DEFAULT CHARSET=latin1;
//
//CREATE TABLE `tt_proj_assets_tags` (
//  `asset_id` int(11) unsigned NOT NULL DEFAULT '0',
//  `tag_id` int(11) unsigned NOT NULL DEFAULT '0',
//  `type` tinyint(2) unsigned NOT NULL DEFAULT '0',
//  PRIMARY KEY (`asset_id`,`tag_id`),
//  KEY `tag_id` (`tag_id`,`asset_id`)
//) ENGINE=MyISAM DEFAULT CHARSET=latin1;
//
//CREATE TABLE `tt_proj_tags` (
//  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
//  `name` varchar(255) DEFAULT NULL,
//  PRIMARY KEY (`id`),
//  UNIQUE KEY `name` (`name`)
//) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;


//    '*/depage-cms/assets/' => array(
//        'handler' => 'cms_asset',
//    )

//
//indexes:
//    FULL TEXT index on processed filename
//    INDEX (file_id, tag_id) and INDEX(tag_id, file_id)
//    index for page, size, filetype, date
//

// TODO: tags are never removed from the tag table. think about that.
// TODO: think about necessary indices again.
// TODO: testsuite
// TODO: document type bitmask

class asset_manager {
    const PARTIAL_ASSET_PATH = "lib/assets";

    const ROOT_TAG = "dir";
    const DIR_TAG = "dir";
    const ASSET_TAG = "asset";

    const TAG_TYPE_XML = 1;
    const TAG_TYPE_ADDITIONAL = 2;
    const TAG_TYPE_ALL = 3;

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

    // {{{ basic_create
    /*
     * create a new asset. this is a primitive function. better use create.
     *
     * @param   $tags (array)   array of arrays consisting of tag names (string) and tag types (int).
     */
    protected function basic_create($tmpfile, $processed_filename, $node_id, $filetype = null, $width = null, $height = null, $created_at = null, $page_id = null, $tags = array()) {
        // store additional data
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tbl} SET " .
            "node_id = :node_id," .
            "processed_filename = :processed_filename," .
            "filetype = :filetype," .
            "width = :width," .
            "height = :height," .
            "created_at = FROM_UNIXTIME(:created_at)," .
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

        $this->set_tags($asset_id, $tags);

        self::move_file($tmpfile, $created_at, $asset_id, $processed_filename, $filetype);

        return true;
    }

    // {{{ create
    /**
     * creates a new asset and associates this asset with an existing node
     *
     * @param $tmpfile (string)             path to file on disk
     * @param $original_filename (string)   basename of file as specified by user, for example "beautiful_flower.jpg"
     * @param $node_id (int)                node id in xml tree
     * @param $page_id (int)                page id in xml tree to associate this asset with a specific page,
     * @param $additional_tags (array)      additional tags for this asset. each entry may be a tag name (string)
     *                                      or an array consisting of a tag name (string) and a type (int).
     */
    public function create($tmpfile, $original_filename, $node_id, $page_id = null, $additional_tags = array()) {
        if (!file_exists($tmpfile))
            return false;

        $path_parts = pathinfo($original_filename);
        $processed_filename = self::process_filename($path_parts["filename"]);

        list($width, $height, $type) = getimagesize($tmpfile);
        $filetype = image_type_to_extension($type, false);
        if (empty($filetype))
            $filetype = $path_parts["extension"];

        $created_at = time();

        $path_tags = $this->normalize_tags($this->get_parent_name_attributes($node_id), self::TAG_TYPE_XML);
        $tags = array_merge($this->normalize_tags($additional_tags), $path_tags);

        return $this->basic_create($tmpfile, $processed_filename, $node_id, $filetype, $width, $height, $created_at, $page_id, $tags);
    }
    // }}}

    // {{{ create_with_new_node
    /**
     * creates a new asset and a corresponding new node. for options also see create
     *
     * @param $parent_id (int)      node id of parent in xml tree
     * @param $position (int)       position of new node. -1 for default
     */
    public function create_with_new_node($tmpfile, $original_filename, $parent_id, $position = -1, $page_id = null, $additional_tags = array()) {
        $node = $this->xmldb->build_node($this->doc_id, self::ASSET_TAG, array("name" => $original_filename));
        $node_id = $this->xmldb->add_node($this->doc_id, $node, $parent_id, $position);

        return $this->create($tmpfile, $original_filename, $node_id, $page_id, $additional_tags);
    }
    // }}}

    // {{{ create_legacy
    /*
     * creates a new asset, a new node and if necessary new parent nodes
     *
     * @param $file (string)                path to file on disk
     * @param $original_filename (string)   filename
     * @param $xml_path (string)            path in xml tree to structure assets. for example "/photos/new/good".
     *                                      path is split on "/". each part will automatically become a tag.
     */
    public function create_legacy($file, $original_filename, $xml_path, $page_id = null, $additional_tags = array()) {
        $parent_id = $this->create_parent_nodes($xml_path);

        return $this->create_with_new_node($file, $original_filename, $parent_id, -1, $page_id, $additional_tags);
    }
    // }}}

    // {{{ basic_search
    /**
     * search for $needle in filename and tags.
     * filter results by defined $filters.
     *
     * either $needle or $filters needs to be present.
     */
    public function basic_search($needle, $filters = array(), $select = "node_id", $fetch_style = \PDO::FETCH_COLUMN) {
        $query_str =
            "SELECT DISTINCT {$this->assets_tbl}.$select " .
            "FROM {$this->assets_tbl} ";
        $params = array();

        if ($needle) {
            $query_str .=
                "LEFT JOIN {$this->assets_tags_tbl} ON {$this->assets_tbl}.id = {$this->assets_tags_tbl}.asset_id " .
                "LEFT JOIN {$this->tags_tbl} ON {$this->assets_tags_tbl}.tag_id = {$this->tags_tbl}.id " .
                "WHERE " .
                    "(MATCH(processed_filename) AGAINST(:needle) " .
                    "OR {$this->tags_tbl}.name = :needle) ";
            $params = array("needle" => $needle);
        }

        if ($filters) {
            $query_filters = array();
            foreach ($filters as $filter => $value) {
                $query_filters[] = "{$filter} = :{$filter}";
            }

            if ($needle)
                $query_str .= "AND ";
            else
                $query_str .= "WHERE ";

            $query_str .= implode(" AND ", $query_filters);
            $params = array_merge($params, $filters);
        }

        $query = $this->pdo->prepare($query_str);
        $query->execute($params);

        return $query->fetchAll($fetch_style);
    }
    // }}}

    // {{{
    /**
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

            $xpath = new \DOMXPath($doc);
            $remove_nodes = $xpath->query("//" . self::DIR_TAG . "[not(descendant::asset)]");
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

    public function remove_asset($asset_id) {
        $this->unbind_tags($asset_id);
        $query = $this->pdo->prepare("DELETE FROM {$this->assets_tbl} WHERE id = :asset_id");
        $query->execute(array(
            "asset_id" => $asset_id,
        ));
    }

    public function rename_asset($asset_id, $original_filename) {
        $path_parts = pathinfo($original_filename);
        $processed_filename = self::process_filename($path_parts["filename"]);

        $query = $this->pdo->prepare("UPDATE {$this->assets_tbl}
            SET processed_filename = :processed_filename
            WHERE id = :asset_id
        ");
        $query->execute(array(
            "asset_id" => $asset_id,
            "processed_filename" => $processed_filename,
        ));
    }

    public function get_asset_id_for_node_id($node_id) {
        $query = $this->pdo->prepare("SELECT id FROM {$this->assets_tbl} WHERE node_id = :node_id LIMIT 1");
        $query->execute(array(
            "node_id" => $node_id,
        ));

        $result = $query->fetchObject();
        if ($result)
            return $result->id;
        else
            return false;
    }

    public function reset_tags($asset_id, $tags, $type = self::TAG_TYPE_ALL) {
        $this->unbind_tags($asset_id, $type);
        $this->set_tags($asset_id, $tags, $type);
    }

    public function unbind_tags($asset_id, $type = self::TAG_TYPE_ALL) {
        $query = $this->pdo->prepare("UPDATE {$this->assets_tags_tbl} SET type = type & ~:type WHERE asset_id = :asset_id");
        $query->execute(array(
            "asset_id" => $asset_id,
            "type" => $type,
        ));

        $query = $this->pdo->prepare("DELETE FROM {$this->assets_tags_tbl} WHERE asset_id = :asset_id AND type = 0");
        $query->execute(array(
            "asset_id" => $asset_id,
        ));
    }

    /**
     * @param       $asset_id (int) asset database id.
     * @param       $tags (array)   array of tags. each entry may be a tag name (string)
     *                              or an array consisting of a tag name (string) and a type (int).
     * @param       $type (int)     default tag type.
     */
    public function set_tags($asset_id, $tags, $type = self::TAG_TYPE_ADDITIONAL) {
        $tags = $this->normalize_tags($tags, $type);

        // store new tags
        $query = $this->pdo->prepare("INSERT IGNORE INTO {$this->tags_tbl} SET name = :name");
        foreach ($tags as $tag) {
            list($name) = $tag;
            $query->execute(array(
                "name" => $name,
            ));
        }

        // associate tags and asset
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tags_tbl} (asset_id, tag_id, type)
            SELECT :asset_id, id, :type FROM {$this->tags_tbl} WHERE name = :name
            ON DUPLICATE KEY UPDATE type = type | :type");
        foreach ($tags as $tag) {
            list($name, $type) = $tag;
            $query->execute(array(
                "asset_id" => $asset_id,
                "name" => $name,
                "type" => $type,
            ));
        }
    }

    // {{{ normalize_tags
    /**
     * @param       $tags (array)   array of tags. each entry may be a tag name (string)
     *                              or an array consisting of a tag name (string) and a type (int).
     * @param       $type (int)     default tag type.
     * @return      (array)         returns an array of arrays containing tag names and types.
     *                              type is set to TAG_TYPE_ADDITIONAL by default.
     */
    private function normalize_tags($tags, $type = self::TAG_TYPE_ADDITIONAL) {
        foreach($tags as &$tag) {
            if (!is_array($tag))
                $tag = array($tag, $type);
        }

        return $tags;
    }
    // }}}

    private function create_parent_nodes($xml_path) {
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

    public function get_parent_name_attributes($node_id) {
        $names = array();
        $node_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $node_id);

        while ($node_id) {
            $names[] = $this->xmldb->get_attribute($this->doc_id, $node_id, "name");
            $node_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $node_id);
        }

        return array_filter($names);
    }

    static private function move_file($original_file, $created_at, $asset_id, $processed_filename, $filetype) {
        mkdir(self::full_asset_path($created_at), 0777, true);
        rename($original_file, self::get_filepath($created_at, $asset_id, $processed_filename, $filetype));
    }

    static private function get_filepath($created_at, $id, $processed_filename, $filetype) {
        return self::full_asset_path($created_at) . "/{$id}.{$processed_filename}.{$filetype}";
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

        // delete and replace anything but a-z and 0-9
        $find = array('/[^a-z0-9]/', '/[\_]+/');
        $repl = array('_', '_');
        $processed_filename = preg_replace ($find, $repl, $processed_filename);

        return trim($processed_filename, "_");
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
