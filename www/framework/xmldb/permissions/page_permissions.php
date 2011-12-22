<?php

/**
 * @file    framework/xmldb/permissions/page_permissions.php
 *
 * depage permissions module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

namespace depage\xmldb\permissions;

class page_permissions extends static_permissions {
    public function __construct() {
        $allowed_parents = array(
            "pg:page" => array("dpg:pages", "pg:page", "pg:folder"),
            "pg:folder" => array("dpg:pages", "pg:page", "pg:folder"),
            "pg:separator" => array("dpg:pages", "pg:page", "pg:folder"),
            "pg:redirect" => array("dpg:pages", "pg:page", "pg:folder"),
        );
        $allowed_unlinks = array("pg:page", "pg:folder", "pg:separator", "pg:redirect");

        parent::__construct($allowed_parents, $allowed_unlinks);
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
