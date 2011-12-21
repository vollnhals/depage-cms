<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>assets</title>

    <base href="<?php html::base(); ?>" />

    <?php // TODO: use jquery 1.4.4
        $this->include_js("jquery", array(
        "../framework/shared/jquery-1.4.2.js",
        "../framework/shared/jquery.cookie.js",
        "../framework/shared/jquery.hotkeys.js",
    )); ?>
    <?php $this->include_js("jstree", array(
        "../framework/cms/js/fileuploader.js",
        "../framework/cms/js/jstree.js",
        "../framework/cms/js/jquery.jstree.js",
        "../framework/cms/js/jquery.jstree.plugins.js",
        "../framework/shared/jquery.json-2.2.js",
        "../framework/shared/jquery.gracefulWebSocket.js",
    )); ?>

</head>
<body>
<div id="container">

    <form action="index/" method="get" id="search_form">
        <input type="text" name="query" size="40" value="<?php echo $_GET["query"]; ?>"/><br />
        <?php foreach(array("jpg", "png") as $filetype): ?>
            <input type="checkbox" name="filters[filetype]" value="<?php echo $filetype; ?>" <?php echo ($filetype == $_GET["filters"]["filetype"] ? "checked" : "") ?> ><?php echo $filetype; ?></input>
        <?php endforeach; ?>
        <input type="submit" value="Suchen" />
    </form>

    <script type="text/javascript">
        // only allow one active filetype filter
        $(function() {
            $("#search_form input[type='checkbox']").click(function() {
                // test if this click checked the box
                if (this.checked) {
                    var current = this;
                    $("#search_form input[type='checkbox']").each(function() {
                        if (current != this)
                            this.checked = false;
                    });
                }
            })
        });
    </script>

    <!-- the tree container (notice NOT an UL node) -->
    <div
        id="node_<?php echo $this->root_id; ?>"
        class="jstree-container"
        data-project-name = "<?php echo $this->project_name; ?>"
        data-doc-id = "<?php echo $this->doc_id; ?>"
        data-seq-nr = "<?php echo $this->seq_nr; ?>"
        data-selected-nodes = ""
        data-open-nodes = "all"
        data-plugins = "themes pedantic_html_data ui crrm dnd_placeholder types_from_url hotkeys contextmenu span dblclick_rename tooltips select_created_nodes delta_updates add_marker create_with_upload ajax_load_div"
        data-theme = "../framework/cms/css/assets.css"
        data-delta-updates-websocket-url = "ws://127.0.0.1:8000/jstree/"
        data-delta-updates-fallback-poll-url = "./fallback/updates/"
        data-delta-updates-post-url = "./"
        data-types-settings-url = "./types_settings/"
        data-add-marker-special-children = "folder separator"
        data-create-with-upload-action = "./upload/"
        data-create-with-upload-elem = "asset"
        data-ajax-load-div-id = "assets"
        data-ajax-load-div-url = "./assets_for_tag/"
    >
        <?php echo $this->nodes; ?>
    </div>

    <div id="assets">
        <?php echo $this->assets; ?>
    </div>
</div>

</body>
</html>
