<?php
/**
 * @file    lib_sitemap.php
 *
 * Sitemap Generator Library
 *
 * This implements a class to generate "Google"-Sitemap
 *
 *
 * copyright (c) 2002-2010 Frank Hellenkamp [jonas@depagecms.net]
 *
 * @author    Frank Hellenkamp [jonas@depagecms.net]
 */

require_once('lib_project.php');
require_once('lib_publish.php');

class sitemap {
    var $project_name;
    var $xmlstr;
    var $languages;

    /* {{{ constructor */
    function sitemap($project_name, $mod_rewrite = false) {
        $this->project_name = $project_name;
        $this->mod_rewrite = $mod_rewrite;
    }
    /* }}} */
    /* {{{ generate */
    function generate($publish_id, $baseurl) {
        global $project;

        if (substr($baseurl, -1) == "/") {
            $this->baseurl = substr($baseurl, 0, -1);
        } else {
            $this->baseurl = $baseurl;
        }

        $this->pb = new publish($this->project_name, $publish_id);
        $this->languages = array_keys($project->get_languages($this->project_name));
        
        // get available pages
        $this->pages = $project->get_visible_urls($this->project_name, $this->mod_rewrite);

        $this->xmlstr = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $this->xmlstr .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($this->pages as $page) {
            $pathinfo = pathinfo($page);

            $file = new publish_file("{$pathinfo['dirname']}", $pathinfo['basename']);
            $lastmod = date("Y-m-d", $this->pb->get_lastmod($file));

            $this->xmlstr .= "<url>";
                $this->xmlstr .= "<loc>" . htmlentities($this->baseurl . $page) . "</loc>";
                $this->xmlstr .= "<lastmod>" . htmlentities($lastmod) . "</lastmod>";
            $this->xmlstr .= "</url>\n";
        }

        $this->xmlstr .= "</urlset>";

        return $this->xmlstr;
    }
    /* }}} */
}

/* vim:set ft=php sw=4 sts=4 fdm=marker : */
