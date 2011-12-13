<?php
/**
 * @file    framework/cms/cms_asset.php
 *
 * depage cms asset module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

class cms_asset extends cms_jstree {
    protected $html_options = array();

    // {{{ __construct
    public function __construct($options = NULL) {
        parent::__construct($options);

        $this->doc_id = $this->get_doc_id("assets");
        // force accesses to be for assets doc
        $_REQUEST["doc_id"] = $this->doc_id;

        $this->asset_manager = new depage\cms\asset_manager($this->prefix, $this->pdo, $this->xmldb, $this->doc_id);
    }
    // }}}

    // {{{ use destructor from cms_jstree
    // }}}

    // {{{ index
    public function index() {
        $this->auth->enforce();

        $query = $_GET["query"];
        $filters = $_GET["filters"];

        $doc_info = $this->xmldb->get_doc_info($this->doc_id);

        $h = new html("assets.tpl", array(
            'project_name' => $this->project,
            'doc_id' => $this->doc_id,
            'root_id' => $doc_info->rootid, 
            'seq_nr' => $this->get_current_seq_nr($this->doc_id),
            'nodes' => $this->get_html_nodes_by_search($query, $filters),
        ), $this->html_options); 

        return $h;
    }
    // }}}

    // {{{ upload
    /**
     * upload a file and associate with an existing asset
     *
     * @param $_REQUEST["id"] (int)                 node id
     *
     * if uploaded via regular form post:
     *   @param $_FILES["qqfile"] (array)           file info
     *
     * else if uploaded via XMLHttpRequest:
     *   @param $_REQUEST["qqfile"] (string)        user defined name of file
     *   @param $_SERVER["CONTENT_LENGTH"] (int)    size of file
     *   @param php://input (octet-stream)          file data
     */
    public function upload() {
        $this->auth->enforce();

        if (isset($_REQUEST["qqfile"])) {
            // open tmpfile here, so that it exists while running upload()
            $tmpfile = tmpfile();
            $file = $this->handle_xhr_upload($tmpfile);
        } else {
            $file = $this->handle_form_upload();
        }

        // TODO: $page_id and $additional_tags
        if ($this->asset_manager->create($file->tmpfile, $file->filename, $_REQUEST["id"], null, null)) {
            return new json(array("success" => true));
        } else {
            return new json(array("error" => "could not create asset"));
        }
    }
    // }}}

    // {{{ assets_for_tag
    public function assets_for_tag() {
        $this->auth->enforce();

        $query = $_GET["query"];
        $filters = $_GET["filters"];
        $filters["tag_id"] = $_GET["tag_id"];

        $h = new html("assets_for_tag.tpl", array(
            "assets" => $this->asset_manager->search_for_assets($query, $filters),
        ), $this->html_options);

        return $h;
    }
    // }}}

    // {{{ after_rename_node
    public function after_rename_node() {
        $this->asset_manager->rename_tag($_REQUEST["id"], $_REQUEST["name"]);
    }
    // }}}

    // {{{ before_move_node
    public function before_move_node() {
        // record parent ids before move for later tag rebinding
        $this->before_move_parent_ids = $this->get_parent_node_ids($_REQUEST["id"]);
    }
    // }}}

    // {{{ after_move_node
    public function after_move_node() {
        $new_parent_ids = $this->get_parent_node_ids($_REQUEST["id"]);
        $asset_ids = $this->asset_manager->get_asset_ids_for_tag($_REQUEST["id"]);

        foreach($asset_ids as $asset_id) {
            $this->asset_manager->unbind_tags($asset_id, $this->before_move_parent_ids);
            $this->asset_manager->bind_tags($asset_id, $new_parent_ids);
        }
    }
    // }}}

    // {{{ before_remove_node
    public function before_remove_node() {
        // record node ids for later removal
        $this->before_remove_ids = $this->xmldb->get_childIds_by_name($this->doc_id, $_REQUEST["id"]);
        $this->before_remove_ids[] = $_REQUEST["id"];
    }
    // }}}

    // {{{ after_remove_node
    public function after_remove_node() {
        foreach ($this->before_remove_ids as $node_id) {
            $this->asset_manager->remove_tag($node_id);
        }
    }
    // }}}

    // {{{ handle_xhr_upload
    protected function handle_xhr_upload($tmpfile) {
        $input = fopen("php://input", "r");
        $real_size = stream_copy_to_stream($input, $tmpfile);
        fclose($input);

        if ($real_size != $_SERVER["CONTENT_LENGTH"])
            throw new Exception("size of uploaded file does not match header");

        $meta_data = stream_get_meta_data($tmpfile);
        $path = $meta_data["uri"];

        return (object)array("tmpfile" => $path, "filename" => $_REQUEST["qqfile"]);
    }
    // }}}

    // {{{ handle_form_upload
    protected function handle_form_upload() {
        return (object)array("tmpfile" => $_FILES["qqfile"]["tmp_name"], "filename" => $_FILES["qqfile"]["name"]);
    }
    // }}}

    // {{{ get_html_nodes
    protected function get_html_nodes_by_search($query, $filters) {
        $search = $this->asset_manager->search($query, $filters);
        $doc = $search->tags;
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($doc));

        return current($html);
    }
    // }}}

    // {{{
    protected function get_parent_node_ids($node_id) {
        $parent_ids = array();

        while ($parent_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $node_id)) {
            $parent_ids[] = $parent_id;
        }

        return $parent_ids;
    }
    // }}}
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
