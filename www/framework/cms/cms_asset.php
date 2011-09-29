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

    // {{{ use constructor from cms_jstree
    public function __construct($options = NULL) {
        parent::__construct($options);

        $this->doc_id = $this->get_doc_id("assets");
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

    // {{{ create_node
    /**
     * @param $doc_id document id
     * @param $node child node data
     * @param $position position for new child in parent
     */
    public function create_node() {
        $this->auth->enforce();

        $node = $this->xmldb->build_node($this->doc_id, $_REQUEST["node"]["_type"], $_REQUEST["node"]);
        $id = $this->xmldb->add_node($this->doc_id, $node, $_REQUEST["target_id"], $_REQUEST["position"]);
        $status = $id !== false;
        if ($status) {
            $this->recordChange($this->doc_id, array($_REQUEST["target_id"]));
        }

        return new json(array("status" => $status, "id" => $id));
    }
    // }}}

    // {{{ rename_node
    public function rename_node() {
        $this->auth->enforce();

        $this->xmldb->set_attribute($this->doc_id, $_REQUEST["id"], "name", $_REQUEST["name"]);
        $parent_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $_REQUEST["id"]);
        $this->recordChange($this->doc_id, array($parent_id));

        $asset_id = $this->asset_manager->get_asset_id_for_node_id($_REQUEST["id"]);
        $this->asset_manager->rename_asset($asset_id, $_REQUEST["name"]);

        return new json(array("status" => 1));
    }
    // }}}

    // {{{ move_node
    public function move_node() {
        $this->auth->enforce();

        $old_parent_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $_REQUEST["id"]);
        $status = $this->xmldb->move_node($this->doc_id, $_REQUEST["id"], $_REQUEST["target_id"], $_REQUEST["position"]);
        if ($status) {
            $this->recordChange($this->doc_id, array($old_parent_id, $_REQUEST["target_id"]));
            $this->reset_xml_tags($_REQUEST["id"], $_REQUEST["target_id"]);
        }

        return new json(array("status" => $status));
    }
    // }}}

    // {{{ remove_node
    public function remove_node() {
        $this->auth->enforce();

        $parent_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $_REQUEST["id"]);
        $ids = $this->xmldb->unlink_node($this->doc_id, $_REQUEST["id"]);
        $status = $ids !== false;
        if ($status) {
            $this->recordChange($this->doc_id, array($parent_id));

            $asset_id = $this->asset_manager->get_asset_id_for_node_id($_REQUEST["id"]);
            $this->asset_manager->remove_asset($asset_id);
        }

        return new json(array("status" => $status));
    }
    // }}}

    // {{{ get_html_nodes
    protected function get_html_nodes_by_search($query, $filters) {
        $doc = $this->asset_manager->search($query, $filters);
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($doc));

        return current($html);
    }
    // }}}

    protected function reset_xml_tags($node_id, $parent_id) {
        // change dir tags
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($node_id);
        $new_xml_path = $this->xmldb->get_ambiguous_xpath_by_elementId($this->doc_id, $parent_id);
        $path_tags = array_filter(explode("/", $new_xml_path));
        $this->asset_manager->reset_tags($asset_id, $path_tags, \depage\cms\asset_manager::TAG_TYPE_XML);
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
