<?php

/**
 * @file    framework/xmldb/permissions/permissions.php
 *
 * depage permissions module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

namespace depage\xmldb\permissions;

abstract class permissions {
    const wildcard = "*";

    /**
     * @return boolean
     */
    abstract public function is_element_allowed_in($element, $target);

    /**
     * @return boolean
     */
    abstract public function is_unlink_allowed_of($element);

    /**
     * @return array    associative array. keys are element names, values are arrays of allowed parent element names
     */
    abstract public function get_allowed_parents();

    /**
     * @return array    inverse of get_allowed_parents. keys are parent element names, values are arrays of allowed children element names
     */
    abstract public function get_allowed_children();

    /**
     * @return array    array of element names that can be unlinked.
     */
    abstract public function get_allowed_unlinks();
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
