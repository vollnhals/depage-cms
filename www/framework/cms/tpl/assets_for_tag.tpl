<?php foreach ($this->assets as $asset) { ?>
    <div>
        <b><?php echo $asset->original_filename; ?></b>
        <img src="<?php echo $asset->url ?>">
    </div>
<?php } ?>
