<style>
    .row {
        border-bottom: 1px solid #ddd;
        padding: .5em;
    }

    .row button {
        margin: 0;
        padding: 0;
        border: 0;
        background: transparent;
        font-size: inherit;
        cursor: pointer;
    }

    .row .dashicons-no-alt {
        float: right;
    }
</style>
<div class="wrap">
    <h1>고정글</h1>
	<?php if ( ! empty( $result_message ) ) { ?>
        <div id="message" class="notice notice-success">
            <p><?= $result_message ?></p>
        </div>
	<?php } ?>

    <p>글 제목으로 검색 후 엔터 치세요.</p>
    <input class="regular-text" type="text" id="search-post" title="검색어" placeholder="ex. 소식지">

    <form method="post">
		<?php
		wp_nonce_field( "{$this->mytory_board->taxonomyKey}-sticky-posts" );
		?>

        <input class="text-regular" type="hidden" name="sticky_posts"
               value="<?= implode( ',', (get_option( "{$this->mytory_board->taxonomyKey}-sticky-posts" ) ?: []) ) ?>">

        <div class="card" id="selected">
			<?php
            global $post;
			foreach ($this->mytory_board->getStickyPosts() as $sticky_post) {
			    $post = $sticky_post;
			    include 'template-row.php';
			} ?>
        </div>
        <p>고정글은 출력될 때 최근 글이 위로 나옵니다.</p>
		<?php submit_button() ?>
    </form>

    <template id="template-row" class="hidden">
		<?php include 'template-row.php' ?>
    </template>
</div>