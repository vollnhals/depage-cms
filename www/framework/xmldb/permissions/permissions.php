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
    abstract public function is_element_allowed_in($element, $target);

    abstract public function is_unlink_allowed_of($element);

    abstract public function get_allowed_parents();
    abstract public function get_allowed_children();
    
    abstract public function get_allowed_unlinks();
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
