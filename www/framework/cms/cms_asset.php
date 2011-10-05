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

    // {{{ after_create_node
    public function after_create_node($id) {
        // TODO: do something like that. maybe rework create to accept a parent id (optimization). handle file upload
        // $this->asset_manager->create($original_file, $xml_path, $page_id, $additional_tags);
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
        $this->reset_xml_tags($_REQUEST["id"], $_REQUEST["target_id"]);
    }
    // }}}

    // {{{ after_remove_node
    public function after_remove_node() {
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($_REQUEST["id"]);
        $this->asset_manager->remove_asset($asset_id);
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
        $asset_id = $this->asset_manager->get_asset_id_for_node_id($node_id);
        if ($asset_id) {
            $path_tags = $this->get_name_attributes($parent_id);
            $this->asset_manager->reset_tags($asset_id, $path_tags, \depage\cms\asset_manager::TAG_TYPE_XML);
        } else {
            // this node is probably a dir node, reset tags for children
            $children_ids = $this->xmldb->get_childIds_by_name($this->doc_id, $node_id);
            foreach ($children_ids as $child_id) {
                $this->reset_xml_tags($child_id, $node_id);
            }
        }
    }

    protected function get_name_attributes($node_id) {
        $names = array();

        while ($node_id) {
            $names[] = $this->xmldb->get_attribute($this->doc_id, $node_id, "name");
            $node_id = $this->xmldb->get_parentId_by_elementId($this->doc_id, $node_id);
        }

        return array_filter($names);
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
