<?php

/**
 * @file    framework/xmldb/permissions/allow_everything.php
 *
 * depage permissions module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

namespace depage\xmldb\permissions;

class allow_everything extends permissions {
    public function is_element_allowed_in($element, $target) {
        return true;
    }

    public function is_unlink_allowed_of($element) {
        return true;
    }

    public function get_allowed_parents() {
        return array(self::wildcard => array(self::wildcard));
    }

    public function get_allowed_children() {
        return array(self::wildcard => array(self::wildcard));
    }

    public function get_allowed_unlinks() {
        return array(self::wildcard);
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
