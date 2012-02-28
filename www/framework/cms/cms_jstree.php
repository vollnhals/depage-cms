<?php
/**
 * @file    framework/cms/cms_jstree.php
 *
 * depage cms jstree module
 *
 * subclass cms_jstree to implement more specific behaviour.
 * callbacks can be used to hook into create, rename, move, remove.
 *
 *
 * TODO: xml document rights / access management. which user can edit which document?
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

class cms_jstree extends depage_ui {
    protected $html_options = array();

    // {{{ constructor
    public function __construct($options = NULL) {
        parent::__construct($options);

        // get database instance
        $this->pdo = new db_pdo (
            $this->options->db->dsn, // dsn
            $this->options->db->user, // user
            $this->options->db->password, // password
            array(
                'prefix' => $this->options->db->prefix, // database prefix
            )
        );

        // TODO: set project correctly
        $this->project = "proj";
        $this->prefix = "{$this->pdo->prefix}_{$this->project}";
        $this->xmldb = new \depage\xmldb\xmldb ($this->prefix, $this->pdo, \depage\cache\cache::factory($this->prefix));

        // get auth object
        $this->auth = auth::factory(
            $this->pdo, // db_pdo 
            $this->options->auth->realm, // auth realm
            DEPAGE_BASE, // domain
            $this->options->auth->method // method
        );

        // set html-options
        $this->html_options = array(
            'template_path' => __DIR__ . "/tpl/",
            'clean' => "space",
            'env' => $this->options->env,
        );
    }
    // }}}

    // {{{ destructor
    public function __destruct() {
        if (isset($_REQUEST["doc_id"])) {
            $delta_updates = new \depage\websocket\jstree\jstree_delta_updates($this->prefix, $this->pdo, $this->xmldb, $_REQUEST["doc_id"], 0);
            $delta_updates->discardOldChanges();
        }
    }
    // }}}

    // {{{ index
    public function index($doc_name = "pages") {
        $this->auth->enforce();

        $doc_id = $this->get_doc_id($doc_name);
        $doc_info = $this->xmldb->get_doc_info($doc_id);

        $h = new html("jstree.tpl", array(
            'project_name' => $this->project,
            'doc_id' => $doc_id,
            'root_id' => $doc_info->rootid, 
            'seq_nr' => $this->get_current_seq_nr($doc_id),
            'nodes' => $this->get_html_nodes($doc_id, $doc_info->rootid),
        ), $this->html_options); 

        return $h;
    }
    // }}}

    // {{{ create_node
    /**
     * @param $_REQUEST["doc_id"] (int)     document id
     * @param $_REQUEST["node"] (array)     child node data. key "_type" indicates node type.
     * @param $_REQUEST["target_id"] (int)  id of parent node
     * @param $_REQUEST["position"] (int)   position for new child in parent
     */
    public function create_node() {
        $this->auth->enforce();
        $this->set_xmldb_handler();

        return $this->do_create_node($_REQUEST["doc_id"], $_REQUEST["node"], $_REQUEST["target_id"], $_REQUEST["position"]);
    }
    // }}}

    // {{{ do_create_node
    protected function do_create_node($doc_id, $node_data, $target_id, $position) {
        $type = $node_data["_type"];
        unset($node_data["_type"]);

        $node = $this->xmldb_handler->build_node($type, $node_data);
        $id = $this->xmldb->add_node($doc_id, $node, $target_id, $position);
        $status = $id !== false;
        if ($status) {
            $this->recordChange($doc_id, array($target_id));
            // $node may have children so record an update for $id and all children
            if ($node->hasChildNodes()) {
                $this->recordChange($doc_id, array($id), PHP_INT_MAX);
            }

            $this->xmldb_handler->after_create_node($id);

            $children = current(\depage\cms\jstree_xml_to_html::toHTML(array($id => $node)));
        }

        return new json(array("status" => $status, "id" => $id, "children" => $children));
    }
    // }}}

    // {{{ rename_node
    /**
     * @param $_REQUEST["doc_id"] (int)     document id
     * @param $_REQUEST["id"] (int)         node id
     * @param $_REQUEST["name"] (string)    new name attribute
     */
    public function rename_node() {
        $this->auth->enforce();
        $this->set_xmldb_handler();

        return $this->do_rename_node($_REQUEST["doc_id"], $_REQUEST["id"], $_REQUEST["name"]);
    }
    // }}}

    // {{{ do_rename_node
    protected function do_rename_node($doc_id, $node_id, $name) {
        $this->xmldb->set_attribute($doc_id, $node_id, "name", $name);
        $parent_id = $this->xmldb->get_parentId_by_elementId($doc_id, $node_id);
        $this->recordChange($doc_id, array($parent_id));

        $this->xmldb_handler->after_rename_node();

        return new json(array("status" => 1));
    }
    // }}}

    // {{{ move_node
    /**
     * @param $_REQUEST["doc_id"] (int)     document id
     * @param $_REQUEST["id"] (int)         node id
     * @param $_REQUEST["target_id"] (int)  id of new parent node
     * @param $_REQUEST["position"] (int)   position for node in new parent
     */
    public function move_node() {
        $this->auth->enforce();
        $this->set_xmldb_handler();

        return $this->do_move_node($_REQUEST["doc_id"], $_REQUEST["id"], $_REQUEST["target_id"], $_REQUEST["position"]);
    }
    // }}}

    protected function do_move_node($doc_id, $node_id, $target_id, $position) {
        $this->xmldb_handler->before_move_node();

        $old_parent_id = $this->xmldb->get_parentId_by_elementId($doc_id, $node_id);
        $status = $this->xmldb->move_node($doc_id, $node_id, $target_id, $position);
        if ($status) {
            $this->recordChange($doc_id, array($old_parent_id, $target_id));

            $this->xmldb_handler->after_move_node();
        }

        return new json(array("status" => $status));
    }
    // }}}

    // {{{ remove_node
    /**
     * @param $_REQUEST["doc_id"] (int)     document id
     * @param $_REQUEST["id"] (int)         node id
     */
    public function remove_node() {
        $this->auth->enforce();
        $this->set_xmldb_handler();

        return $this->do_remove_node($_REQUEST["doc_id"], $_REQUEST["id"]);
    }
    // }}}

    protected function do_remove_node($doc_id, $node_id) {
        $this->xmldb_handler->before_remove_node();

        $parent_id = $this->xmldb->get_parentId_by_elementId($doc_id, $node_id);
        $ids = $this->xmldb->unlink_node($doc_id, $node_id);
        $status = $ids !== false;
        if ($status) {
            $this->recordChange($doc_id, array($parent_id));

            $this->xmldb_handler->after_remove_node();
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
        $allowed_children = $permissions->get_allowed_children();
        if (isset($allowed_children[$root_element_name])) {
            $root_children = self::allowed_children($allowed_children[$root_element_name]);
        } else {
            $root_children = self::allowed_children($allowed_children[\depage\xmldb\permissions\permissions::wildcard]);
        }

        $settings = array(
            "types_from_url" => array(
                "max_depth" => -2,
                "max_children" => -2,
                "valid_children" => $root_children,
                "types" => array(),
            ),
        );


        foreach ($allowed_children as $parent => $children) {
            if ($parent == \depage\xmldb\permissions\permissions::wildcard) {
                $parent = "default";
            }

            $settings["types_from_url"]["types"][$parent] = array();
            $settings["types_from_url"]["types"][$parent]["valid_children"] = self::allowed_children($children);
        }

        $allowed_unlinks = $permissions->get_allowed_unlinks();
        foreach ($allowed_unlinks as $element) {
            if ($element == \depage\xmldb\permissions\permissions::wildcard) {
                $element = "default";
            }

            if (!isset($settings["types_from_url"]["types"][$element])) {
                $settings["types_from_url"]["types"][$element] = array();
            }

            $settings["types_from_url"]["types"][$element]["delete_node"] = true;
            $settings["types_from_url"]["types"][$element]["remove"] = true;
        }

        /* TODO: disallow drags? is it better if every element is draggable even if it is not movable?
        if (!$permissions->is_element_allowed_in_any($element)) {
            $setting["start_drag"] = false;
            $setting["move_node"] = false;
        }
        */

        return new json($settings);
    }
    // }}}

    // {{{ recordChange
    protected function recordChange($doc_id, $parent_ids, $additional_children_depth = 0) {
        $delta_updates = new \depage\websocket\jstree\jstree_delta_updates($this->prefix, $this->pdo, $this->xmldb, $doc_id);

        $unique_parent_ids = array_unique($parent_ids);
        foreach ($unique_parent_ids as $parent_id) {
            $delta_updates->recordChange($parent_id, $additional_children_depth);
        }
    }
    // }}}

    // {{{ get_doc_id
    protected function get_doc_id($doc_name) {
        $doc_list = $this->xmldb->get_doc_list($doc_name);
        return $doc_list[$doc_name]->id;
    }
    // }}}

    // {{{ get_html_nodes
    protected function get_html_nodes($doc_id, $root_id) {
        $doc = $this->xmldb->get_subdoc_by_elementId($doc_id, $root_id);
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($doc));

        return current($html);
    }
    // }}}

    // {{{ get_current_seq_nr
    protected function get_current_seq_nr($doc_id) {
       $delta_updates = new \depage\websocket\jstree\jstree_delta_updates($this->prefix, $this->pdo, $this->xmldb, $doc_id);
       return $delta_updates->currentChangeNumber();
    }
    // }}}

    // {{{
    protected function set_xmldb_handler() {
        $doc_id = $_REQUEST["doc_id"];
        $doc_info = $this->xmldb->get_doc_info($doc_id);
        $type = $doc_info->type;

        $this->xmldb_handler = \depage\cms\xmldb_handler\xmldb_handler::factory($type, $doc_id, $this->prefix, $this->pdo, $this->xmldb);
    }
    // }}}

    // {{{ send_time
    protected function send_time($time) {
        // do nothing
    }
    // }}}

    // {{{ allowed_children
    // translate value for jstree types permissions
    static protected function allowed_children(&$children) {
        if (empty($children)) {
            return "none";
        } else if (in_array(\depage\xmldb\permissions\permissions::wildcard, $children)) {
            return "all";
        } else {
            return $children;
        }
    }
    // }}}

}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
