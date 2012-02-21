/*
 * upload handling
 */

$(function () {
    $("div.jstree-uploader").each(function() {
        var uploader_div = $(this);
        var button = uploader_div.children(".jstree-uploader-button");

        var uploader = new qq.FileUploaderBasic({
            element : uploader_div[0],
            button : button[0],
            action : uploader_div.attr("data-upload-action"),
            onSubmit : function(id, filename) {
                    uploader._onSubmit(id, filename);
                    // transmit node id as additional parameter
                    var tree = $(uploader_div.attr("data-jstree"));
                    var node = tree.jstree("get_selected");
                    if (!node.length) {
                        node = tree;
                    }

                    uploader._handler.upload(id, {"id" : node.attr("id").replace("node_", "")});

                    return false;
            },
            onComplete: function(id, filename, responseJSON) {
                // reselect node to trigger reload of asset div
                var tree = $(uploader_div.attr("data-jstree"));
                var node = tree.jstree("get_selected");
                tree.jstree("deselect_node", node);
                tree.jstree("select_node", node);
            }
        });
    });
});