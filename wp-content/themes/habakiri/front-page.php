<?php
/**
 * Version    : 1.2.0
 * Author     : inc2734
 * Author URI : http://2inc.org
 * Created    : April 17, 2015
 * Modified   : August 30, 2015
 * License    : GPLv2 or later
 * License URI: license.txt
 */
?>
<?php get_header(); ?>

<?php get_template_part( 'modules/page-header' ); ?>
<div class="sub-page-contents">

	<div class="container">
		<div class="row bg-color-purple">
			<div class="col-md-10 col-md-push-2 bg-color-white">
				<main id="main" role="main" style="margin-left:25px;">

					<?php get_template_part( 'modules/breadcrumbs' ); ?>
					<?php while ( have_posts() ) : the_post(); ?>
						<?php get_template_part( 'content', 'page' ); ?>
					<?php endwhile; ?>
					<div class="row" style="margintop:15px;">
                                                <div class="col-md-4 col-xs-12"><a href="/100th/" class="btn btn-lg btn-block btn-purple">100周年にあたって</a></div>
						<div class="col-md-4 col-xs-12"><a href="/data/types/greeting/" class="btn btn-lg btn-block btn-purple">ご祝辞</a></div>
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/texts/" class="btn btn-lg btn-block btn-purple">寄稿文</a></div>
					</div>
					<div class="row" style="margin-top:15px;">
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/chronology/" class="btn btn-lg btn-block btn-purple">年表</a></div>
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/results/" class="btn btn-lg btn-block btn-purple">戦績</a></div>
						<div class="col-md-4 col-xs-12"><a href="/data/types/art/" class="btn btn-lg btn-block btn-purple">季刊誌『ART』</a></div>
					</div>

                                        <div class="row" style="margin-top:15px;">
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/photos/" class="btn btn-lg btn-block btn-purple">写真</a></div>
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/historicaldata/" class="btn btn-lg btn-block btn-purple">歴史的資料</a></div>
                                                <div class="col-md-4 col-xs-12"><a href="/data/types/papermedia/" class="btn btn-lg btn-block btn-purple">新聞・雑誌記事</a></div>
                                        </div>
                                        <div class="row" style="margin-top:15px;">
                                                <div class="col-md-4 col-xs-12"><a href="/data/" class="btn btn-lg btn-block btn-purple">全体検索</a></div>
                                        </div>
				<!-- end #main --></main>
			<!-- end .col-md-9 --></div>
			<div class="col-md-2 col-md-pull-10 sidebar">
				<?php get_sidebar(); ?>
			<!-- end .col-md-3 --></div>
		<!-- end .row --></div>
	<!-- end .container --></div>

<!-- end .sub-page-contents --></div>
<?php get_footer(); ?>
