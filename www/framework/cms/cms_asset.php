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

    // {{{ after_rename_node
    public function after_rename_node() {
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($_REQUEST["id"]);
        $this->asset_manager->rename_asset($asset_id, $_REQUEST["name"]);
    }
    // }}}

    // {{{ after_move_node
    public function after_move_node() {
        $this->reset_xml_tags($_REQUEST["id"]);
    }
    // }}}

    // {{{ after_remove_node
    public function after_remove_node() {
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($_REQUEST["id"]);
        $this->asset_manager->remove_asset($asset_id);
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
        $doc = $this->asset_manager->search($query, $filters);
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($doc));

        return current($html);
    }
    // }}}

    protected function reset_xml_tags($node_id) {
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($node_id);
        if ($asset_id) {
            $path_tags = $this->asset_manager->get_parent_name_attributes($node_id);
            $this->asset_manager->reset_tags($asset_id, $path_tags, \depage\cms\asset_manager::TAG_TYPE_XML);
        } else {
            // this node is probably a dir node, reset tags for children
            $children_ids = $this->xmldb->get_childIds_by_name($this->doc_id, $node_id);
            foreach ($children_ids as $child_id) {
                $this->reset_xml_tags($child_id);
            }
        }
    }


}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
