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
    }
    // }}}

    // {{{ use destructor from cms_jstree
    // }}}

    // {{{ index
    public function index() {
        $this->auth->enforce();

        $doc_info = $this->xmldb->get_doc_info($this->doc_id);

        $h = new html("jstree.tpl", array(
            'doc_id' => $this->doc_id,
            'root_id' => $doc_info->rootid, 
            'seq_nr' => $this->get_current_seq_nr($this->doc_id),
            'nodes' => $this->get_html_nodes($this->doc_id, $doc_info->rootid),
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
        }

        return new json(array("status" => $status));
    }
    // }}}

    // TODO: set icons?
    // {{{ types_settings
    public function types_settings($doc_id) {
        $this->auth->enforce();

        $doc_info = $this->xmldb->get_doc_info($doc_id);
        $root_element_name = $this->xmldb->get_nodeName_by_elementId($doc_id, $doc_info->rootid);

        $permissions = $this->xmldb->get_permissions($doc_id);
        $valid_children = $permissions->valid_children();
        $settings = array(
            "types_from_url" => array(
                "max_depth" => -2,
                "max_children" => -2,
                "valid_children" => self::valid_children_or_none($valid_children, $root_element_name),
                "types" => array(),
            ),
        );

        $known_elements = $permissions->known_elements();
        $types = &$settings["types_from_url"]["types"];
        foreach ($known_elements as $element) {
            if ($element != $root_element_name) {
                $setting = array();

                /* TODO: disallow drags? is it better if every element is draggable even if it is not movable?
                if (!$permissions->is_element_allowed_in_any($element)) {
                    $setting["start_drag"] = false;
                    $setting["move_node"] = false;
                }
                */

                if (!$permissions->is_unlink_allowed_of($element)) {
                    $setting["delete_node"] = false;
                    $setting["remove"] = false;
                }

                if (isset($valid_children[$element])) {
                    $setting["valid_children"] = $valid_children[$element];
                } else if (isset($valid_children[\depage\xmldb\permissions::default_element])) {
                    $setting["valid_children"] = self::valid_children_or_none($valid_children, \depage\xmldb\permissions::default_element);
                }

                $types[$element] = $setting;
            }
        }

        if (!isset($types[\depage\xmldb\permissions::default_element])) {
            $types[\depage\xmldb\permissions::default_element] = array(
                "valid_children" => self::valid_children_or_none($valid_children, \depage\xmldb\permissions::default_element),
            );
        }

        return new json($settings);
    }
    // }}}

}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
