<?php

namespace depage\cms\xmldb_handler;

class xmldb_handler_assets extends xmldb_handler {
    // {{{ constructor
    public function __construct($doc_id, $prefix, $pdo, $xmldb, $options = array()) {
        parent::__construct($doc_id, $prefix, $pdo, $xmldb, $options);
        
        $this->asset_manager = new \depage\cms\asset_manager($this->prefix, $this->pdo, $this->xmldb, $this->doc_id);
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

        return array(true, null);
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
        $this->before_remove_ids = $this->get_descendant_node_ids($_REQUEST["id"], array($_REQUEST["id"]));

        return array(true, null);
    }
    // }}}

    // {{{ after_remove_node
    public function after_remove_node() {
        foreach ($this->before_remove_ids as $node_id) {
            $this->asset_manager->remove_tag($node_id);
        }
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

    // {{{
    protected function get_descendant_node_ids($node_id, $additional_descendant_ids = array()) {
        $descendant_ids = $additional_descendant_ids;

        $child_ids = $this->xmldb->get_childIds_by_name($this->doc_id, $node_id);
        $descendant_ids = array_merge($descendant_ids, $child_ids);

        foreach ($child_ids as $child_id) {
            $further_child_ids = $this->get_descendant_node_ids($child_id);
            $descendant_ids = array_merge($descendant_ids, $further_child_ids);
        }

        return $descendant_ids;
    }
    // }}}
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
