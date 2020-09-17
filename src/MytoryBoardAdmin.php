<?php

namespace Mytory\Board;

/**
 * Created by PhpStorm.
 * User: mytory
 * Date: 10/30/16
 * Time: 1:41 PM
 */
class MytoryBoardAdmin {

	private $mytory_board;

	function __construct( MytoryBoard $mytory_board ) {

		$this->mytory_board = $mytory_board;

		add_action( 'admin_menu', [ $this, 'stickyPostsMenu' ] );

		add_filter( "views_edit-{$this->mytory_board->postTypeKey}", [ $this, 'termsLinkInList' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'adminScripts' ] );
		add_action( "wp_ajax_{$this->mytory_board->postTypeKey}_search_post", [ $this, 'searchPost' ] );

		add_action( "wp_ajax_{$this->mytory_board->taxonomyKey}_save", [ $this, 'save' ] );
		add_action( "wp_ajax_nopriv_{$this->mytory_board->taxonomyKey}_save", [ $this, 'save' ] );

		add_action( "wp_ajax_{$this->mytory_board->taxonomyKey}_trash", [ $this, 'trash' ] );
		add_action( "wp_ajax_nopriv_{$this->mytory_board->taxonomyKey}_trash", [ $this, 'trash' ] );

		if ( $mytory_board->roleByBoard ) {
			add_action( 'admin_menu', [ $this, 'approveMemberMenu' ] );
			add_action( "wp_ajax_approve_member_{$this->mytory_board->taxonomyKey}", [ $this, 'approveMember' ] );

			add_action( "wp_ajax_apply_{$this->mytory_board->taxonomyKey}", [ $this, 'apply' ] );
			add_action( "wp_ajax_nopriv_apply_{$this->mytory_board->taxonomyKey}", [ $this, 'apply' ] );
		}
	}

	function stickyPostsMenu() {
		add_submenu_page(
			"edit.php?post_type={$this->mytory_board->postTypeKey}",
			'고정글',
			'고정글',
			'edit_others_posts',
			"sticky-posts",
			[ $this, 'stickyPosts' ]
		);
	}

	function approveMemberMenu() {
		add_submenu_page(
			"edit.php?post_type={$this->mytory_board->postTypeKey}",
			$this->mytory_board->taxonomyLabel . ' 신청 승인',
			$this->mytory_board->taxonomyLabel . ' 신청 승인',
			'edit_others_posts',
			"manage-board-member",
			[ $this, 'approveMemberView' ]
		);
	}

	function approveMemberView() {

		$terms = get_terms( [
			'taxonomy'   => $this->mytory_board->taxonomyKey,
			'hide_empty' => false,
		] );

		$applied_users = ( ( new \WP_User_Query( [
			'meta_key'     => "_{$this->mytory_board->taxonomyKey}_applied",
			'meta_compare' => 'EXISTS',
		] ) )->get_results() );

		$applied_users = array_unique( $applied_users, SORT_REGULAR );

		// 배열의 번호를 0, 1, 2로 순차적으로 재배열해야 한다. 안 그러면 json_encode했을 때 js object로 변환된다.
		sort($applied_users);

		$user_applied_list = [];
		foreach ( $applied_users as $applied_user ) {
			$user_applied_list[ $applied_user->ID ] = get_user_meta( $applied_user->ID, "_{$this->mytory_board->taxonomyKey}_applied" );
		}

		$roles = get_editable_roles();

		include 'templates/manage-board-member.php';
	}

	function approveMember() {
		$user_id  = $_POST['user_id'];
		$role_key = $_POST['role_key'];
		$board_id = $_POST['board_id'];

		$user = new \WP_User( $user_id );
		$user->add_role( $role_key );

		$result = wp_update_user( $user );

		if ( ! is_wp_error( $result ) ) {
			delete_user_meta( $user_id, "_{$this->mytory_board->taxonomyKey}_applied", $board_id );
			echo json_encode( [
				'result'  => 'success',
				'message' => '승인했습니다.',
				'user'    => $user,
			] );
		} else {
			$wp_error = $result;
			echo json_encode( [
				'result'  => 'fail',
				'message' => $wp_error->get_error_message(),
				'user'    => $user,
			] );
		}


		die();
	}

	function adminScripts() {
		$screen = get_current_screen();

		if ( $screen->id == "{$this->mytory_board->postTypeKey}_page_sticky-posts" ) {
			wp_enqueue_script( "{$this->mytory_board->taxonomyKey}-sticky-posts", Helper::url( 'sticky-posts.js' ),
				[ 'jquery-ui-autocomplete', 'underscore' ], false, true );
		}
	}

	function stickyPosts() {
		$result_message = "";
		if ( ! empty( $_POST ) ) {
			wp_verify_nonce( $_POST['_wpnonce'], "{$this->mytory_board->taxonomyKey}-sticky-posts" );
			$diff = array_diff( get_option( 'sticky_posts' ), explode( ',', $_POST['sticky_posts'] ) );
			if ( update_option( 'sticky_posts', explode( ',', $_POST['sticky_posts'] ) ) ) {
				$result_message = '저장했습니다.';
			} elseif ( empty( $diff ) ) {
				$result_message = '추가/제거한 글이 없어서 저장하지 않았습니다.';
			} else {
				$result_message = '저장중 오류가 있었습니다.';
			}
		}
		include __DIR__ . '/sticky-posts.php';
	}

	function searchPost() {
		global $wp_query;
		$args = [
			'post_type'      => 'any',
			's'              => $_GET['term'],
			'posts_per_page' => 50,
		];
		if ( ! empty( $_GET['selected'] ) ) {
			$args['post__not_in'] = explode( ',', $_GET['selected'] );
		}
		$wp_query = new \WP_Query( $args );

		$posts_for_autocomplete = [];
		while ( have_posts() ): the_post();
			$posts_for_autocomplete[] = [
				'id'    => get_the_ID(),
				'value' => get_the_title() . ' (' . get_the_date() . ')',
			];
		endwhile;
		if ( empty( $posts_for_autocomplete ) ) {
			$posts_for_autocomplete = [
				[
					'id'    => '',
					'value' => '검색 결과가 없습니다.'
				]
			];
		}
		echo json_encode( $posts_for_autocomplete );
		wp_die();
	}

	function save() {

		if ( ! check_ajax_referer( "{$this->mytory_board->taxonomyKey}-ajax-nonce", false, false ) ) {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '잘못된 호출입니다.',
			] );
			die();
		}

		if ( ! $this->checkOverallPermission() ) {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '권한이 없습니다.',
			] );
			die();
		}

		$postarr = $this->extractPostarr();

		if ( ! empty( $postarr['ID'] ) ) {

			// 글 수정 권한 검사

			if ( ! current_user_can( "edit_{$this->mytory_board->postTypeKey}", $postarr['ID'] ) ) {
				echo json_encode( [
					'result'  => 'fail',
					'message' => '권한이 없습니다.',
				] );
				die();
			}

			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			$wp_error = $post_id;
			echo json_encode( [
				'result'  => 'fail',
				'message' => $wp_error->get_error_message(),
			] );
			die();
		}

		if ( ! empty( $_POST['tax_input'] ) ) {
			// tax_input 값이 있으면.
			$terms = array_map( function ( $term_id ) {
				return (int) $term_id;
			}, $_POST['tax_input'][ $this->mytory_board->taxonomyKey ] );

			$affected_ids = wp_set_object_terms( $post_id, $terms, $this->mytory_board->taxonomyKey );
			if ( is_wp_error( $affected_ids ) ) {
				$wp_error = $affected_ids;
				echo json_encode( [
					'result'  => 'fail',
					'message' => "글은 저장했지만, {$this->mytory_board->taxonomyLabel} 입력이 되지 않았습니다. 에러 메시지: " . $wp_error->get_error_message(),
				] );
				die();
			}
		}

		echo json_encode( [
			'result'      => 'success',
			'message'     => '저장했습니다.',
			'redirect_to' => get_permalink( $post_id ),
		] );
		die();
	}

	function trash() {

		if ( ! check_ajax_referer( "{$this->mytory_board->taxonomyKey}-ajax-nonce", false, false ) ) {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '잘못된 호출입니다.',
			] );
			die();
		}

		$this->checkOverallPermission();

		if ( ! current_user_can( "delete_{$this->mytory_board->postTypeKey}", $_POST['ID'] ) ) {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '권한이 없습니다.',
			] );
			die();
		}


		$redirect_to = site_url();
		if ( $this->mytory_board->roleByBoard ) {
			$myBoards = $this->mytory_board->getMyBoards();
			if ( ! empty( $myBoards ) ) {
				$redirect_to = get_term_link( $myBoards[0] );
			}
		} else {
			if ( $this->mytory_board->defaultBoardId ) {
				$redirect_to = get_term_link( $this->mytory_board->defaultBoardId );
			}
		}

		if ( wp_trash_post( $_POST['ID'] ) ) {
			echo json_encode( [
				'result'      => 'success',
				'message'     => '삭제했습니다.',
				'redirect_to' => $redirect_to,
			] );
		} else {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '시스템 오류로 실패했습니다.',
			] );
		}

		die();
	}

	/**
	 * $_POST에서 WP_Post에 필요한 키값만 추려서 $postarr를 구성한 뒤 리턴.
	 *
	 * @return array
	 */
	private function extractPostarr() {
		$keys = [
			'ID',
			'post_author',
			'post_content',
			'post_content_filtered',
			'post_title',
			'post_excerpt',
			'post_status',
			'post_type',
			'comment_status',
			'ping_status',
			'post_password',
			'to_ping',
			'pinged',
			'post_parent',
			'menu_order',
			'guid',
			'import_id',
			'context'
		];

		$postdata = [];

		foreach ( $_POST as $k => $v ) {
			if ( in_array( $k, $keys ) ) {
				$postdata[ $k ] = $v;
			}
		}

		return $postdata;
	}

	/**
	 * canAnonymousWriting 값이 false인 경우에, 로그인해 있는지, 글 작성 권한이 있는지 검사한다.
	 *
	 * @return bool
	 */
	private function checkOverallPermission() {
		if ( ! $this->mytory_board->canAnonymousWriting ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			if ( ! current_user_can( "publish_{$this->mytory_board->postTypeKey}" ) ) {
				return false;
			}
		}

		return true;
	}

	public function termsLinkInList( $views ) {

		/**
		 * @var \WP_Term $term
		 */

		$wp_term_query = new \WP_Term_Query( [
			'taxonomy'   => $this->mytory_board->taxonomyKey,
			'hide_empty' => false,
			'orderby'    => 'name',
		] );

		if ( $wp_term_query->terms ) {
			foreach ( $wp_term_query->terms as $term ) {
				$url     = site_url( "/wp-admin/edit.php?post_type={$this->mytory_board->postTypeKey}&{$this->mytory_board->taxonomyKey}={$term->slug}" );
				$views[] = "<a href='{$url}'>{$term->name} <span class='count'>({$term->count})</span></a>";
			}
		}

		return $views;
	}

	public function apply() {
		if ( ! check_ajax_referer( "{$this->mytory_board->taxonomyKey}-ajax-nonce", false, false ) ) {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '잘못된 호출입니다.',
			] );
			die();
		}

		if (add_user_meta(get_current_user_id(), "_{$this->mytory_board->taxonomyKey}_applied", $_POST['term_id'])) {
			echo json_encode( [
				'result'  => 'success',
				'message' => '가입 신청 완료',
			] );
		} else {
			echo json_encode( [
				'result'  => 'fail',
				'message' => '서버 에러',
			] );
		}
		die();
	}
}