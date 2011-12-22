<?php

/**
 * @file    framework/xmldb/permissions/static_permissions.php
 *
 * depage permissions module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

namespace depage\xmldb\permissions;

class static_permissions extends permissions {
    /**
     * associative array. keys are element names, values are allowed parent element names
     */
    protected $allowed_parents = array();
    protected $allowed_unlinks = array();

    public function __construct($allowed_parents = array(), $allowed_unlinks = array()) {
        $this->allowed_parents = $allowed_parents;
        $this->allowed_unlinks = $allowed_unlinks;
    }

    public function is_element_allowed_in($element, $parent) {
        return isset($this->allowed_parents[$element]) && in_array($parent, $this->allowed_parents[$element]);
    }

    public function is_unlink_allowed_of($element) {
        return in_array($element, $this->allowed_unlinks);
    }

    public function get_allowed_parents() {
        return $this->allowed_parents;
    }

    public function get_allowed_unlinks() {
        return $this->allowed_unlinks;
    }
    
    public function get_allowed_children() {
        $allowed_children = array();

        foreach ($this->allowed_parents as $element => $parents) {
            foreach ($parents as $parent) {
                if (!isset($allowed_children[$parent])) {
                    $allowed_children[$parent] = array();
                }

                $allowed_children[$parent][] = $element;
            }
        }

        return $allowed_children;
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
