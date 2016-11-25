<?php
/**
 * Version    : 1.0.0
 * Author     : inc2734
 * Author URI : http://2inc.org
 * Created    : September 24, 2015
 * Modified   :
 * License    : GPLv2 or later
 * License URI: license.txt
 */
?>
<?php do_action( 'habakiri_before_site_branding' ); ?>

<script>
jQuery(function($){
    $(document).ready(function () {
        $('.sameHeightHeader div').equalHeight();
    });
});
</script>
<div class="row sameHeightHeader">
	<div class="col-xs-2 hidden-xs hidden-sm sameHeightHeader bg-color-purple" style="text-align:center;"><img src="http://sptc.shikumilab.jp/wp-content/uploads/2016/08/rikkyo_webB-2.png"></div>
	<div class="col-xs-10 hidden-xs hidden-sm bg-color-white">
		<div class="sameHeightHeader" style="padding-left:25px;vertical-align:middle;">
			<div class="sameHeightHeader bg-color-purple">
				&nbsp;<img src="http://sptc.shikumilab.jp/wp-content/uploads/2016/08/rikkyo_webB-3.png">&nbsp;
			</div>
		</div>
	</div>
	<div class="visible-xs visible-sm">
		<img src="http://sptc.shikumilab.jp/wp-content/uploads/2016/08/rikkyo_webA-640x155.png">
	</div>
</div>
<?php do_action( 'habakiri_after_site_branding' ); ?>
