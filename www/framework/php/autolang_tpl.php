<?php
    // {{{ get_language_by_browser()
    function get_language_by_browser($available_languages) {
        $language = $available_languages[0];

        $browser_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);    
        foreach ($browser_languages as $lang) {
            $actual_language_array = explode(';', $lang);
            $actual_language_array = explode('-', $actual_language_array[0]);
            $actual_language = trim($actual_language_array[0]);
            if (in_array($actual_language, $available_languages)) {
                $language = $actual_language;
                break;
            }    
        }
        return $language;
    }
    // }}}
    // {{{ get_alternate_page()
    function get_alternate_page($available_pages, $base_location, $request) {
        $page = "";

        $base_location = parse_url($base_location);
        $base_location = $base_location['path'];

        $request = substr($request, strlen($base_location) - 1);
        if (strpos($request, "?") > 0) {
            $request = substr($request, 0, strpos($request, "?"));
        }

        $request = explode("/", $request);
        
        // remove last element from path
        array_pop($request);

        //search for pages>>
        while ($page == "" && count($request) > 1) {
            $tempurl = implode("/", $request) . "/";
            foreach ($available_pages as $apage) {
                if (substr($apage, 0, strlen($tempurl)) == $tempurl) {
                    $page = $apage;

                    break;
                }
            }
            array_pop($request);
        }

        if ($page == "") {
            $page = $available_pages[0];
        }

        $page = substr($page, 1);

        return $page;
    }
    // }}}

    /* vim:set ft=php sw=4 sts=4 fdm=marker : */
?>
