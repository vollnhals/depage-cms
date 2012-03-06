<?php

namespace depage\cms\xmldb_handler;

/**
 * handler classes for jstree
 *
 * subclass and define callbacks for before and after actions.
 *
 * before callbacks have a return value. return array(true, $message) to allow action and
 * return array(false, $message) to cancel the action.
 *
 * the final json object will include the specified message.
 */
class xmldb_handler {
    // {{{ factory
    public static function factory($type, $doc_id, $prefix, $pdo, $xmldb, $options = array()) {
        $class = "\depage\cms\xmldb_handler\xmldb_handler_$type";
        if (!class_exists($class)) {
            $class = "\depage\cms\xmldb_handler\xmldb_handler";
        }

        return new $class($doc_id, $prefix, $pdo, $xmldb, $options);
    }
    // }}}

    // {{{ constructor
    public function __construct($doc_id, $prefix, $pdo, $xmldb, $options = array()) {
        $this->doc_id = $doc_id;
        $this->prefix = $prefix;
        $this->pdo = $pdo;
        $this->xmldb = $xmldb;
        $this->options = $options;
    }
    // }}}

    // {{{ before_create_node
    public function before_create_node() {
        return array(true, null);
    }
    // }}}

    // {{{ after_create_node
    public function after_create_node($id) {}
    // }}}

    // {{{ before_rename_node
    public function before_rename_node() {
        return array(true, null);
    }
    // }}}

    // {{{ after_rename_node
    public function after_rename_node() {}
    // }}}

    // {{{ before_move_node
    public function before_move_node() {
        return array(true, null);
    }
    // }}}

    // {{{ after_move_node
    public function after_move_node() {}
    // }}}

    // {{{ before_remove_node
    public function before_remove_node() {
        return array(true, null);
    }
    // }}}

    // {{{ after_remove_node
    public function after_remove_node() {}

    // {{{ build_node
    /*
     * build a new node from node data.
     * if there is a template for this node type then that is used.
     * variables can be used in the form of %variable_name%.
     *
     * overwrite this method if you want other behaviour.
     */
    public function build_node($type, $node_data) {
        // read template
        // TODO: think about template directories. maybe use other dirs.
        $default_template_dir = DEPAGE_FM_PATH . "/" . "xml/jstree";
        $project_template_dir = $default_template_dir . "/" . $this->project;

        $template = file_get_contents($project_template_dir . "/" . $type . ".xml");
        if (!$template) {
            $template = file_get_contents($default_template_dir . "/" . $type . ".xml");
            if (!$template) {
                // fallback to xmldb build node
                return $this->xmldb->build_node($this->doc_id, $type, $node_data);
            }

        }

        // inject variables
        $namespaces = $this->xmldb->get_namespaces_and_entities($this->doc_id)->namespaces;
        $patterns = array("/%__NS%/");
        $replacements = array($namespaces);
        foreach ($node_data as $key => $value) {
            $patterns[] = "/%$key%/";
            $replacements[] = $value;
        }

        $template = preg_replace($patterns, $replacements, $template);

        // return node
        $doc = new \DOMDocument;
        $doc->loadXML($template);

        return $doc->documentElement;
    }
    // }}}
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
