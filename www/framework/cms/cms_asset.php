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

        $search = $this->asset_manager->search($query, $filters);
        $nodes = $search->tags;
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($nodes));

        $h = new html("assets.tpl", array(
            'project_name' => $this->project,
            'doc_id' => $this->doc_id,
            'root_id' => $doc_info->rootid, 
            'seq_nr' => $this->get_current_seq_nr($this->doc_id),
            'nodes' => current($html),
            'assets' => new html("assets_for_tag.tpl", array(
                "assets" => $search->assets,
            )),
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


        $tag_ids = $this->get_parent_node_ids($_REQUEST["id"], array($_REQUEST["id"]));

        // TODO: $page_id
        if ($this->asset_manager->create($file->tmpfile, $file->filename, null, $tag_ids)) {
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

    // {{{
    protected function get_parent_node_ids($node_id, $additional_parent_ids = array()) {
        $parent_ids = $additional_parent_ids;
        $doc_info = $this->xmldb->get_doc_info($this->doc_id);

        while ($node_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $node_id)) {
            if ($node_id != $doc_info->rootid) {
                $parent_ids[] = $node_id;
            }
        }

        return $parent_ids;
    }
    // }}}
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
