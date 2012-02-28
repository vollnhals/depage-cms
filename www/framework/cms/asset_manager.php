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


/**
 * asset manager
 *
 * asset code needs a "assets" xml doc with root node.
 *
 * DB Schema:
 *

CREATE TABLE `tt_proj_assets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `processed_filename` varchar(255) DEFAULT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `filetype` varchar(7) DEFAULT NULL,
  `width` mediumint(8) unsigned DEFAULT NULL,
  `height` mediumint(8) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `page_id` int(11) unsigned DEFAULT NULL,
  `node_id` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `processed_filename` (`processed_filename`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;


CREATE TABLE `tt_proj_assets_tags` (
  `asset_id` int(11) unsigned NOT NULL DEFAULT '0',
  `tag_id` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`asset_id`,`tag_id`),
  KEY `tag_id` (`tag_id`,`asset_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE `tt_proj_tags` (
  `id` int(11) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;

 *
 */

//    '*/depage-cms/assets/' => array(
//        'handler' => 'cms_asset',
//    )

// TODO: think about necessary indices again.
// TODO: testsuite


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
     */
    protected function basic_create($tmpfile, $original_filename, $processed_filename, $filetype = null, $width = null, $height = null, $created_at = null, $page_id = null, $tag_ids = array()) {
        // store additional data
        $query = $this->pdo->prepare("INSERT INTO {$this->assets_tbl} SET " .
            "original_filename = :original_filename," .
            "processed_filename = :processed_filename," .
            "filetype = :filetype," .
            "width = :width," .
            "height = :height," .
            "created_at = FROM_UNIXTIME(:created_at)," .
            "page_id = :page_id"
        );
        $query->execute(array(
            "original_filename" => $original_filename,
            "processed_filename" => $processed_filename,
            "filetype" => $filetype,
            "width" => $width,
            "height" => $height,
            "created_at" => $created_at,
            "page_id" => $page_id,
        ));
        $asset_id = $this->pdo->lastInsertId();

        $this->bind_tags($asset_id, $tag_ids);

        self::move_file($tmpfile, $created_at, $asset_id, $processed_filename, $filetype);

        return true;
    }

    // {{{ create
    /**
     * creates a new asset and associates this asset with an existing node
     *
     * @param $tmpfile (string)             path to file on disk
     * @param $original_filename (string)   basename of file as specified by user, for example "beautiful_flower.jpg"
     * @param $page_id (int)                page id in xml tree to associate this asset with a specific page,
     * @param $tag_ids (array)              tag ids
     */
    public function create($tmpfile, $original_filename, $page_id = null, $tag_ids = array()) {
        if (!file_exists($tmpfile))
            return false;

        $path_parts = pathinfo($original_filename);
        $processed_filename = self::process_filename($path_parts["filename"]);

        list($width, $height, $type) = getimagesize($tmpfile);
        $filetype = image_type_to_extension($type, false);
        if (empty($filetype))
            $filetype = $path_parts["extension"];

        $created_at = time();

        return $this->basic_create($tmpfile, $original_filename, $processed_filename, $filetype, $width, $height, $created_at, $page_id, $tag_ids);
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
     * @param $page_id (int)                page id in xml tree to associate this asset with a specific page,
     */
    public function create_legacy($file, $original_filename, $xml_path, $page_id = null) {
        $tag_ids = $this->create_tag_nodes($xml_path);

        return $this->create($file, $original_filename, $page_id, $tag_ids);
    }
    // }}}

    // {{{
    public function get_tag_ids_for_assets($asset_ids) {
        $param_list = ':id_' . implode(',:id_', array_keys($asset_ids));
        $query = $this->pdo->prepare("SELECT DISTINCT tag_id FROM {$this->assets_tags_tbl} WHERE asset_id IN ($param_list)");
        $query->execute(array_combine(explode(",", $param_list), $asset_ids));

        return $query->fetchAll(\PDO::FETCH_COLUMN);
    }
    // }}}

    // {{{ search_for_assets
    /**
     * search for $needle in filename and tags.
     * filter results by defined $filters.
     */
    public function search_for_assets($needle, $filters = array()) {
        $needle_params = array();
        if ($needle) {
            $processed_needle = self::process_filename($needle);
            $needle_where = "(MATCH(processed_filename) AGAINST(:needle IN BOOLEAN MODE) OR {$this->tags_tbl}.name = :needle)";
            $needle_params = array("needle" => $processed_needle);
        }

        $filter_params = array();
        if ($filters) {
            $query_filters = array();
            foreach ($filters as $filter => $value) {
                $query_filters[] = "{$filter} = :{$filter}";
            }

            $filter_where = implode(" AND ", $query_filters);
            $filter_params = $filters;
        }

        $where = ($needle || $filters) ? "WHERE " : "";
        $where .= implode (" AND ", array_filter(array($needle_where, $filter_where)));

        $query = $this->pdo->prepare("
                SELECT DISTINCT {$this->assets_tbl}.id,
                {$this->assets_tbl}.created_at,
                {$this->assets_tbl}.original_filename,
                {$this->assets_tbl}.processed_filename,
                {$this->assets_tbl}.filetype
                FROM {$this->assets_tbl}
                LEFT JOIN {$this->assets_tags_tbl} ON {$this->assets_tbl}.id = {$this->assets_tags_tbl}.asset_id
                LEFT JOIN {$this->tags_tbl} ON {$this->assets_tags_tbl}.tag_id = {$this->tags_tbl}.id
                {$where}");

        $query->execute(array_merge($needle_params, $filter_params));

        $assets = $query->fetchAll(\PDO::FETCH_OBJ);
        foreach ($assets as &$asset) {
            $asset->url = self::get_filepath(strtotime($asset->created_at), $asset->id, $asset->processed_filename, $asset->filetype);
        }

        return $assets;
    }
    // }}}

    // {{{
    /**
     * search for $needle in filename and tags.
     * filter results by defined $filters.
     *
     * if neither $needle nor $filters is present then all assets are returned.
     *
     * implementation strategy:
     * search for assets first and then load corresponding tags
     * 
     */
    public function search($needle, $filters = array()) {
        $assets = $this->search_for_assets($needle, $filters);

        if ($needle || $filters) {
            $asset_ids = array_map(function ($a) { return $a->id; }, $assets);
            $tag_ids = $this->get_tag_ids_for_assets($asset_ids);

            /*
             * try to minimize db queries,
             * only retrieve found tags from xml tree.
             */
            $tag_id_list = implode(",", $tag_ids);
            if (!$tag_id_list)
                $tag_id_list = "NULL";

            $xml_filter = "id in (" . $tag_id_list . ")";
        }

        $doc_info = $this->xmldb->get_doc_info($this->doc_id);
        $doc = $this->xmldb->get_subdoc_by_elementId($this->doc_id, $doc_info->rootid, true, PHP_INT_MAX, $xml_filter);

        $result = new \stdClass;
        $result->assets = $assets;
        $result->tags = $doc;

        return $result;
    }

    public function all() {
        return search(null);
    }

    public function get_asset_ids_for_tag($tag_id) {
        $query = $this->pdo->prepare("SELECT asset_id FROM {$this->assets_tags_tbl} WHERE tag_id = :tag_id");
        $query->execute(array(
            "tag_id" => $tag_id,
        ));

        return $query->fetchAll(\PDO::FETCH_COLUMN);
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
            SET original_filename = :original_filename, processed_filename = :processed_filename
            WHERE id = :asset_id
        ");
        $query->execute(array(
            "asset_id" => $asset_id,
            "original_filename" => $original_filename,
            "processed_filename" => $processed_filename,
        ));
    }

    public function reset_tags($asset_id, $tag_ids) {
        $this->unbind_tags($asset_id);
        $this->bind_tags($asset_id, $tag_ids);
    }

    public function unbind_tags($asset_id, $tag_ids = null) {
        if ($tag_ids) {
            $query = $this->pdo->prepare("DELETE FROM {$this->assets_tags_tbl} WHERE asset_id = :asset_id AND tag_id = :tag_id");
            foreach($tag_ids as $tag_id) {
                $query->execute(array(
                    "asset_id" => $asset_id,
                    "tag_id" => $tag_id,
                ));
            }
        } else {
            $query = $this->pdo->prepare("DELETE FROM {$this->assets_tags_tbl} WHERE asset_id = :asset_id");
            $query->execute(array(
                "asset_id" => $asset_id,
            ));
        }
    }

    /**
     * @param       $asset_id (int) asset database id.
     * @param       $tag_ids (array)   array of tag ids
     */
    public function bind_tags($asset_id, $tag_ids) {
        // associate tags and asset
        $query = $this->pdo->prepare("
            INSERT IGNORE INTO {$this->assets_tags_tbl}
            SET asset_id = :asset_id, tag_id = :tag_id");
        foreach ($tag_ids as $tag_id) {
            $query->execute(array(
                "asset_id" => $asset_id,
                "tag_id" => $tag_id,
            ));
        }
    }

    public function create_tag($node_id, $name) {
        // store new tag
        $query = $this->pdo->prepare("INSERT IGNORE INTO {$this->tags_tbl} SET id = :node_id, name = :name");
        $query->execute(array(
            "node_id" => $node_id,
            "name" => $name,
        ));
    }

    public function remove_tag($tag_id) {
        $query = $this->pdo->prepare("DELETE FROM {$this->assets_tags_tbl} WHERE tag_id = :tag_id");
        $query->execute(array(
            "tag_id" => $tag_id,
        ));

        $query = $this->pdo->prepare("DELETE FROM {$this->tags_tbl} WHERE id = :tag_id");
        $query->execute(array(
            "tag_id" => $tag_id,
        ));
    }

    public function rename_tag($tag_id, $name) {
        $query = $this->pdo->prepare("UPDATE {$this->tags_tbl} SET name = :name WHERE id = :tag_id");
        $query->execute(array(
            "tag_id" => $tag_id,
            "name" => $name,
        ));
    }
    private function create_tag_nodes($xml_path) {
        $doc_info = $this->xmldb->get_doc_info($this->doc_id);
        $parent_id = $doc_info->rootid;
        $dirs = array_filter(explode("/", $xml_path));
        $xpath = "/" . self::ROOT_TAG;
        $tag_ids = array();

        foreach ($dirs as $dir) {
            $xpath .= "/" . self::DIR_TAG . "[@name='{$dir}']";
            $element_ids = $this->xmldb->get_elementIds_by_xpath($this->doc_id, $xpath);

            if (empty($element_ids)) {
                $node = $this->xmldb->build_node($this->doc_id, self::DIR_TAG, array("name" => $dir));
                $node_id = $this->xmldb->add_node($this->doc_id, $node, $parent_id, -1);
                $this->create_tag($node_id, $dir);
                $tag_ids[] = $node_id;
                $parent_id = $node_id;
            } else {
                $tag_ids[] = $element_ids[0];
                $parent_id = $element_ids[0];
            }
        }

        return $tag_ids;
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
        // use a MySQL FULLTEXT search word delimiter character as replacement
        $repl = array('.', '.');
        $processed_filename = preg_replace ($find, $repl, $processed_filename);

        return trim($processed_filename, "_");
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
