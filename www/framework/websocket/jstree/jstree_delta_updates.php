<?php

namespace depage\websocket\jstree;

/**
 * delta updates for jstree

CREATE TABLE `tt_proj_delta_updates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `node_id` int(11) unsigned NOT NULL,
  `doc_id` int(11) unsigned NOT NULL,
  `depth` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `doc_id` (`doc_id`)
) ENGINE=MyISAM AUTO_INCREMENT=535 DEFAULT CHARSET=latin1;

 */
class jstree_delta_updates {
    // clients will update themselves about every 3 seconds maximum. retain enough updates to update partially.
    // if we estimate 10 updates per second, then retain at least 30 updates. some buffer on top and we should be good.
    const MAX_UPDATES_BEFORE_RELOAD = 50;

    function __construct($table_prefix, $db, $xmldb, $doc_id, $seq_nr = -1) {
        $this->table_name = $table_prefix . "_delta_updates";
        $this->db = $db;
        $this->xmldb = $xmldb;
        $this->doc_id = (int)$doc_id;

        $this->seq_nr = (int)$seq_nr;
        if ($this->seq_nr == -1)
            $this->seq_nr = $this->currentChangeNumber();
    }

    public function currentChangeNumber() {
        $query = $this->db->prepare("SELECT MAX(id) AS id FROM " . $this->table_name . " WHERE doc_id = ?");
        if ($query->execute(array($this->doc_id)))
            if ($row = $query->fetch())
                return (int)$row["id"];

        return -1;
    }

    /**
     * @param int $parent_id                    node id of parent element that is changed
     * @param int $additional_children_depth    how many level of children need to be updated? direct children are always updated.
     */
    public function recordChange($parent_id, $additional_children_depth = 0) {
        $query = $this->db->prepare("INSERT INTO " . $this->table_name . " (node_id, doc_id, depth) VALUES (?, ?, ?)");
        $query->execute(array((int)$parent_id, $this->doc_id, $additional_children_depth));
    }

    public function discardOldChanges() {
        $min_id_query = $this->db->prepare("SELECT id FROM " . $this->table_name . " WHERE doc_id = ? ORDER BY id DESC LIMIT " . (self::MAX_UPDATES_BEFORE_RELOAD - 1) . ", 1");
        $min_id_query->execute(array($this->doc_id));
        $row = $min_id_query->fetch();

        $delete_query = $this->db->prepare("DELETE FROM " . $this->table_name . " WHERE id < ? AND doc_id = ?");
        $delete_query->execute(array((int)$row["id"], $this->doc_id));
    }

    private function changedParentIds() {
        $parent_ids_and_depth = array();

        $query = $this->db->prepare("SELECT id, node_id, depth FROM " . $this->table_name . " WHERE id > ? AND doc_id = ? ORDER BY id ASC");
        if ($query->execute(array($this->seq_nr, $this->doc_id))) {
            while ($row = $query->fetch()) {
                $node_id = (int)$row["node_id"];
                $parent_ids_and_depth[$node_id] = max($parent_ids_and_depth[$node_id], (int)$row["depth"]);

                // set seq_nr to seq_nr of processed change
                $this->seq_nr = $row["id"];
            }
        }

        return $parent_ids_and_depth;
    }

    // returns an associative array of parent node id keys and children node values, that where involved in a recent change
    // do a partial update with only immediate children by default
    public function changedNodes() {
        $initial_seq_nr = $this->seq_nr;
        $parent_ids_and_depth = $this->changedParentIds();

        // very unlikely case that more delta updates happened than will be retained in db. reload whole document
        if ($this->seq_nr - $initial_seq_nr > self::MAX_UPDATES_BEFORE_RELOAD) {
            $doc_info = $this->xmldb->get_doc_info($this->doc_id);
            $parent_ids_and_depth = array($doc_info->rootid => PHP_INT_MAX);
        }

        $changed_nodes = array();
        foreach ($parent_ids_and_depth as $parent_id => $depth) {
            $changed_nodes[$parent_id] = $this->xmldb->get_subdoc_by_elementId($this->doc_id, $parent_id, true, $depth);
        }

        return $changed_nodes;
    }

    public function encodedDeltaUpdate() {
        $changed_notes = $this->changedNodes();
        if (empty($changed_notes))
            return "";

        $result = array(
            'nodes' => \depage\cms\jstree_xml_to_html::toHTML($changed_notes),
            'seq_nr' => $this->seq_nr,
        );

        return json_encode($result);
    }
}

?>
