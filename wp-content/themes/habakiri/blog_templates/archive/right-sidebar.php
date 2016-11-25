<?php
/**
 * Version    : 1.3.0
 * Author     : inc2734
 * Author URI : http://2inc.org
 * Created    : April 17, 2015
 * Modified   : August 30, 2015
 * License    : GPLv2 or later
 * License URI: license.txt
 */
?>
<div class="container">
	<div class="row">
		<div class="col-md-9">
			<main id="main" role="main">

				<?php get_template_part( 'modules/breadcrumbs' ); ?>
				<?php
				$name = ( is_search() ) ? 'search' : 'archive';
?>

		<div class="panel panel-default">
			<div class="panel-heading">
				<h4 class="panel-title">
              <!-- 
                data-toggle : Collapseを起動させる
                data-parent : アコーディオン風に閉じたり開いたりするためのもの
                href : 指定した場所のパネルを開く
              -->
				<a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" class="collapsed">
               絞り込み検索(クリックすると開閉します)
				</a>
				</h4>
			</div>
			<div id="collapseOne" class="panel-collapse collapse" style="height: auto;">
				<div class="panel-body">
<?php dynamic_sidebar('search'); ?>
				</div>
			</div>
		</div>

<?php
				if ( have_posts() ) {
					get_template_part( 'content', $name );
				} else {
					get_template_part( 'content', 'none' );
				}
				?>
<?php dynamic_sidebar('search'); ?>
			<!-- end #main --></main>
		<!-- end .col-md-9 --></div>
		<div class="col-md-3">
			<?php get_sidebar(); ?>
		<!-- end .col-md-3 --></div>
	<!-- end .row --></div>
<!-- end .container --></div>
