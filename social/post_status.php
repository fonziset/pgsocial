<?php
/**
*
* Social extension for the phpBB Forum Software package.
*
* @copyright (c) 2017 Antonio PGreca (PGreca)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace pgreca\pg_social\social;

class post_status {
	/* @var \phpbb\template\template */
	protected $template;

	/* @var \phpbb\user */
	protected $user;

	/* @var \phpbb\controller\helper */
	protected $helper;

	/* @var \phpbb\config\config */
	protected $config;
	
	/* @var string phpBB root path */
	protected $root_path;	
	
	/* @var string phpEx */
	protected $php_ext;	

	/**
	* Constructor
	*
	* @param \phpbb\template\template  $template
	* @param \phpbb\user				$user	 
	* @param \phpbb\controller\helper		$helper
	* @param \pg_social\\controller\helper $pg_social_helper
	* @param \wall\controller\notifyhelper $notifyhelper Notification helper.	
	* @param \phpbb\config\config			$config
	* @param \phpbb\db\driver\driver_interface	$db 		
	*/
	
	public function __construct($template, $user, $helper, $pg_social_helper, $notifyhelper, $social_photo, $social_tag, $social_zebra, $config, $db, $root_path, $php_ext, $table_prefix) {
		$this->template					= $template;
		$this->user						= $user;
		$this->helper					= $helper;
		$this->pg_social_helper 		= $pg_social_helper;
		$this->notify 					= $notifyhelper;
		$this->social_photo				= $social_photo;
		$this->social_tag				= $social_tag;
		$this->social_zebra				= $social_zebra;
		$this->config 					= $config;
		$this->db 						= $db;	
	    $this->root_path				= $root_path;	
		$this->php_ext 					= $php_ext;
        $this->table_prefix 			= $table_prefix;
	}
	
	public function getStatus($wall_id, $lastp, $type, $order, $template = false){
		$user_id = (int) $this->user->data['user_id'];
		$user_avatar = $this->pg_social_helper->social_avatar($this->user->data['user_avatar'], $this->user->data['user_avatar_type']);
		
		switch($type) {
			case "profile":
				$where = "(w.wall_id = '".$wall_id."') AND ";
			break;
			case "all":	
				$where = "(w.user_id = u.user_id) AND ";
			break;
		}
		
		switch($lastp) {
			case 0:
				$limit = 5; 
				$orderby = "DESC"; 
			break;
			default:
				$limit = 1; 
				$orderby = "ASC";
			break;
		}
		
		switch($order) {
			case 'prequel':
				$order_vers = '<';
				$orderby = "DESC";
				$limit = 1;
			break;
			case 'seguel':
				$order_vers = '>';
			break;
		}
		
		$sql = "SELECT w.*, u.user_id, u.username, u.username_clean, u.user_avatar, u.user_avatar_type, u.user_colour 
		FROM ".$this->table_prefix."pg_social_wall_post as w, ".USERS_TABLE." as u	
		WHERE ".$where." (w.user_id = u.user_id) AND (u.user_type != '2' AND w.post_ID ".$order_vers." '".$lastp."')
		GROUP BY post_ID 
		ORDER BY w.time ".$orderby;
		$result = $this->db->sql_query_limit($sql, $limit);
		while($row = $this->db->sql_fetchrow($result)){	
			if($row['wall_id'] == $user_id || $row['post_privacy'] == 0 && $row['wall_id'] == $user_id || $row['post_privacy'] == 1 && $this->social_zebra->friendStatus($row['wall_id'])['status'] == 'PG_SOCIAL_FRIENDS' || $row['post_privacy'] == 2) {
				if(($row['user_id'] != $row['wall_id']) && $type != "profile") {
					$sqla = "SELECT user_id, username, username_clean, user_colour FROM ".USERS_TABLE."
					WHERE user_id = '".$row['wall_id']."'";
					$resulta = $this->db->sql_query($sqla);
					$wall = $this->db->sql_fetchrow($resulta);					
					$wall_action = $this->user->lang("HAS_WRITE_IN");
				} else {
					$wall['user_id'] = '';
					$wall['username'] = '';
					$wall['user_colour'] = '';
					$wall_action = '';
				}
					
				switch($row['post_type']) {
					case '1':
						$author_action = $this->user->lang("HAS_UPLOADED_AVATAR");
						$photo = $this->photo($row['post_extra']);
						$msg = $photo['msg'];
						$msg .= '<div class="status_photos">'.$photo['img'].'</div>';
					break;
					case '2':
						$author_action = $this->user->lang("HAS_UPLOADED_COVER");
						$photo = $this->photo($row['post_extra']);
						$msg = $photo['msg'];
						$msg .= '<div class="status_photos">'.$photo['img'].'</div>';
					break;
					case '4':
						$posts = explode("#p", $row['post_extra']);
						$sql_post = "SELECT * FROM ".TOPICS_TABLE." WHERE topic_id = '".$posts[0]."'";
						$res = $this->db->sql_query($sql_post);
						$post = $this->db->sql_fetchrow($res);
						
						$author_action = 'ha scritto un post in <a href="'.append_sid(generate_board_url()).'/viewtopic.php?t='.$post['topic_id'].'#p'.$posts[1].'">'.$post['topic_title'].'</a>';
						$msg = '';
						$msg_align = '';						
					break;
					case '3':
					default:
						$author_action = "";
						if($row['post_extra'] != "") {
							$photo = $this->photo($row['post_extra']);
							$msg = $photo['msg'];
							$msg .= '<div class="status_photos">'.$photo['img'].'</div>';
						} else {
							$allow_bbcode = $this->config['pg_social_bbcode'];
							$allow_urls = $this->config['pg_social_url'];
							$allow_smilies = $this->config['pg_social_smilies'];
							$flags = (($allow_bbcode) ? OPTION_FLAG_BBCODE : 0) + (($allow_smilies) ? OPTION_FLAG_SMILIES : 0) + (($allow_urls) ? OPTION_FLAG_LINKS : 0);
		
							$msg = generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags);
							$msg .= $this->pg_social_helper->extraText($row['message']);
						}		
						$msg_align = '';
					break;
				}	
				
				$comment = "<span>".$this->pg_social_helper->countAction("comments", $row['post_ID'])."</span> ";
				if($this->pg_social_helper->countAction("comments", $row['post_ID']) == 0 || $this->pg_social_helper->countAction("comments", $row['post_ID']) > 1) {
					$comment .= $this->user->lang('COMMENTS');
				} else {
					$comment .= $this->user->lang('COMMENT');
				}
			
				if($row['wall_id'] == $user_id || $user_id == $row['user_id']) $action = "yes";
				$this->template->assign_block_vars('post_status', array(
					'USER_AVATAR'				=> $user_avatar,				
					"POST_STATUS_ID"            => $row['post_ID'],
					"AUTHOR_ACTION"				=> $author_action,
					"AUTHOR_PROFILE"			=> get_username_string('profile', $row['user_id'], $row['username'], $row['user_colour']),		
					"AUTHOR_ID"					=> $row['user_id'],
					"AUTHOR_USERNAME"			=> $row['username'],
					"AUTHOR_AVATAR"				=> $this->pg_social_helper->social_avatar($row['user_avatar'], $row['user_avatar_type']),
					"AUTHOR_COLOUR"				=> "#".$row['user_colour'],
					"WALL_ACTION"				=> $wall_action,
					"WALL_PROFILE"				=> get_username_string('profile', $wall['user_id'], $wall['username'], $wall['user_colour']),	
					"WALL_ID"					=> $row['wall_id'],	
					"WALL_USERNAME"				=> $wall['username'],
					"WALL_COLOUR"				=> "#".$wall['user_colour'],
					"POST_TYPE"					=> $row['post_type'],
					"POST_URL"					=> $this->helper->route("status_page", array("id" => $row['post_ID'])),
					"POST_DATE"					=> $this->pg_social_helper->time_ago($row['time']),
					"MESSAGE"					=> $msg,
					"MESSAGE_ALIGN"				=> $msg_align,
					"POST_PRIVACY"				=> $this->user->lang($this->pg_social_helper->social_privacy($row['post_privacy'])),
					"ACTION"					=> $action,
					"LIKE"						=> $this->pg_social_helper->countAction("like", $row['post_ID']),
					"IFLIKE"					=> $this->pg_social_helper->countAction("iflike", $row['post_ID']),
					"COMMENT"					=> $comment,
				)); 	
			}			
		}
		$this->db->sql_freeresult($result);
		if($template) return $this->helper->render('activity_status.html',  $this->user->lang['ACTIVITY']);
	}
	
	public function addStatus($wall_id, $text, $privacy, $type = 0, $extra = NULL) {
		$user_id = (int) $this->user->data['user_id'];
		$time = time();
		
		$allow_bbcode = $this->config['pg_social_bbcode'];
		$allow_urls = $this->config['pg_social_url'];
		$allow_smilies = $this->config['pg_social_smilies'];
		$text_clear = urldecode($text);
		$time = time();
		if(!$extra) $extra = "";
		$asds = $this->social_tag->showTag($text_clear);
		
		generate_text_for_storage($text, $uid, $bitfield, $flags, $allow_bbcode, $allow_urls, $allow_smilies);
		
		$sql_arr = array(
			'wall_id'			=> $wall_id,
			'user_id'			=> $user_id,
			'message'			=> $asds,
			'time'				=> $time,
			'post_privacy'		=> $privacy,
			'post_type'			=> $type,
			'post_extra'		=> $extra,
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid,
			'tagged_user'		=> ''
		);
		
		$sql = "INSERT INTO " . $this->table_prefix . 'pg_social_wall_post' . $this->db->sql_build_array('INSERT', $sql_arr);
		if($this->db->sql_query($sql)) {	
			$last_status = "SELECT post_ID FROM ".$this->table_prefix."pg_social_wall_post WHERE time = '".$time."' AND user_id = '".$user_id."' AND wall_id = '".$wall_id."' ORDER BY time DESC LIMIT 0, 1";
			$last = $this->db->sql_query($last_status);
			$row = $this->db->sql_fetchrow();	
			if($wall_id == $user_id && $this->user->data['user_signature_replace'] && $privacy != 0) {
				$sql = "UPDATE ".USERS_TABLE." SET user_sig = '".$asds."<br /><a class=\"profile_signature_status\" href=\"".$this->helper->route("status_page", array("id" => $row['post_ID']))."\">#status</a>' WHERE user_id = '".$this->user->data['user_id']."'";
				$this->db->sql_query($sql);
			}
			if($wall_id != $user_id) $this->notify->notify('add_status', $row['post_ID'], $text, (int) $wall_id, (int) $user_id, 'NOTIFICATION_SOCIAL_STATUS_ADD');		
			$this->social_tag->addTag($row['post_ID'], $text_clear);
		}
		
		$this->template->assign_vars(array(
			"ACTION"	=> '',
		));
		$this->pg_social_helper->log($this->user->data['user_id'], $this->user->ip, "STATUS_NEW", "<a href='".$this->helper->route("status_page", array("id" => $row['post_ID']))."'>#".$row['post_ID']."</a>");
		if($type != 4) return $this->helper->render('activity_status_action.html', $this->user->lang['ACTIVITY']);	
	}
	
	public function deleteStatus($post) {
		$sql_status = "DELETE FROM ".$this->table_prefix."pg_social_wall_post WHERE ".$this->db->sql_in_set('post_ID', array($post));
		$sql_comment = "DELETE FROM ".$this->table_prefix."pg_social_wall_comment WHERE ".$this->db->sql_in_set('post_ID', array($post));
		$sql_like = "DELETE FROM ".$this->table_prefix."pg_social_wall_like WHERE ".$this->db->sql_in_set('post_ID', array($post));
		
		$this->db->sql_query($sql_status);
		$this->db->sql_query($sql_comment);
		$this->db->sql_query($sql_like);
		
		$this->template->assign_vars(array(
			"ACTION"	=> "delete",
		));
		return $this->helper->render('activity_status_action.html', "");
	}
	
	public function likeAction($post) {
		$post_info = "SELECT user_id, wall_id FROM ".$this->table_prefix."pg_social_wall_post WHERE post_ID = '".$post."'";
		$res = $this->db->sql_query($post_info);
		$post_info = $this->db->sql_fetchrow($res);
		
		$user_id = (int) $this->user->data['user_id'];
		$sql = "SELECT post_like_ID FROM ".$this->table_prefix."pg_social_wall_like
		WHERE post_ID = '".$post."'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		if($row['post_like_ID'] != "") {
			$sql = "DELETE FROM ".$this->table_prefix."pg_social_wall_like WHERE post_ID = '".$post."' AND user_id = '".$user_id."'";
			$action = "dislike";
			if($post_info['user_id'] != $user_id || $post_info['wall_id'] == $user_id) $this->notify->notify('remove_like', $post, '', (int) $post_info['user_id'], (int) $user_id, 'NOTIFICATION_SOCIAL_LIKE_ADD');		
		} else {
			$sql_arr = array(
				'post_ID'			=> $post,
				'user_id'			=> $user_id,
				'post_like_time'	=> time(),
			);
			$sql = "INSERT INTO ".$this->table_prefix.'pg_social_wall_like'.$this->db->sql_build_array('INSERT', $sql_arr);
			$action = "like";
			if($post_info['user_id'] != $user_id) $this->notify->notify('add_like', $post, '', (int) $post_info['user_id'], (int) $user_id, 'NOTIFICATION_SOCIAL_LIKE_ADD');		
			$this->pg_social_helper->log($this->user->data['user_id'], $this->user->ip, "LIKE_NEW", "<a href='".$this->helper->route("status_page", array("id" => $post))."'>#".$post."</a>");
		}
		if($this->db->sql_query($sql)) $this->db->sql_freeresult($result); 
		
		$this->template->assign_vars(array(
			"ACTION"	=> $action,
			"LIKE_TOT"	=> $this->pg_social_helper->countAction('like', $post),
		));
		return $this->helper->render('activity_status_action.html', '');
	}
	
	public function getComments($post, $type) {
		$user_id = (int) $this->user->data['user_id'];
						
		$sql = "SELECT *
		FROM ".$this->table_prefix."pg_social_wall_comment
		WHERE post_ID = '".$post."'
		ORDER BY time DESC";
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result)){
			$allow_bbcode = false; //$this->config['pg_social_bbcode'];
			$allow_urls = false; //$this->config['pg_social_url'];
			$allow_smilies = false; //$this->config['pg_social_smilies'];
			$flags = (($allow_bbcode) ? OPTION_FLAG_BBCODE : 0) + (($allow_smilies) ? OPTION_FLAG_SMILIES : 0) + (($allow_urls) ? OPTION_FLAG_LINKS : 0);
			
			$sql_use = "SELECT user_id, username, username_clean, user_colour, user_avatar, user_avatar_type FROM ".USERS_TABLE."
			WHERE user_id = '".$row['user_id']."'";
			$resulta = $this->db->sql_query($sql_use);
			$wall = $this->db->sql_fetchrow($resulta);
			if($row['user_id'] == $this->user->data['user_id']) $comment_action = true; else $comment_action = false;
			$this->template->assign_block_vars('post_comment', array(
				"COMMENT_ID"				=> $row['post_comment_ID'],
				"COMMENT_ACTION"			=> $comment_action,
				"AUTHOR_PROFILE"			=> get_username_string('profile', $wall['user_id'], $wall['username'], $wall['user_colour']),	
				"AUTHOR_ID"					=> $wall['user_id'],
				"AUTHOR_USERNAME"			=> $wall['username'],
				"AUTHOR_AVATAR"				=> $this->pg_social_helper->social_avatar($wall['user_avatar'], $wall['user_avatar_type']),
				"AUTHOR_COLOUR"				=> "#".$wall['user_colour'],
				'COMMENT_TEXT'				=> $this->pg_social_helper->social_smilies(generate_text_for_display($row['message'], $row['bbcode_uid'], $row['bbcode_bitfield'], $flags)),			
				'COMMENT_TIME'				=> $this->pg_social_helper->time_ago($row['time']),
			));		
		}
		//$this->db->sql_freeresult($result);	
		return $this->helper->render('activity_comment.html', '');				
	}
	
	public function addComment($post, $comment) {
		$post_info = "SELECT user_id, wall_id FROM ".$this->table_prefix."pg_social_wall_post WHERE post_ID = '".$post."'";
		$res = $this->db->sql_query($post_info);
		$post_info = $this->db->sql_fetchrow($res);
		
		$user_id = (int) $this->user->data['user_id'];
		$time = time();
		
		$allow_bbcode = false; //$this->config['pg_social_bbcode'];
		$allow_urls = false; //$this->config['pg_social_url'];
		$allow_smilies = false; //$this->config['pg_social_smilies'];
		  
		generate_text_for_storage($comment, $uid, $bitfield, $flags, $allow_bbcode, true, $allow_smilies);
		
		$sql_arr = array(
			'post_ID'	=> $post,
			'user_id'			=> $user_id,
			'time'				=> time(),
			'message'			=> $comment,
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $uid
		);
		$sql = "INSERT INTO " . $this->table_prefix . 'pg_social_wall_comment' . $this->db->sql_build_array('INSERT', $sql_arr);
		$this->db->sql_query($sql);
		if($post_info['wall_id'] != $user_id) $this->notify->notify('add_comment', $post, '', (int) $post_info['wall_id'], (int) $user_id, 'NOTIFICATION_SOCIAL_COMMENT_ADD');		
			
		$this->template->assign_vars(array(
			"ACTION"	=> var_dump($post_info),
		));
		$this->pg_social_helper->log($this->user->data['user_id'], $this->user->ip, "COMMENT_NEW", "<a href='".$this->helper->route("status_page", array("id" => $post))."'>#".$post."</a>");
		return $this->helper->render('activity_status_action.html', '');
	}

	public function removeComment($comment) {
		$sql = "DELETE FROM ".$this->table_prefix."pg_social_wall_comment WHERE post_comment_ID = '".$comment."' AND user_id = '".$this->user->data['user_id']."'";
		$this->db->sql_query($sql);		
		$this->template->assign_vars(array(
			"ACTION"	=> $sql,
		));
		return $this->helper->render('activity_status_action.html', '');
	}
		
	public function photo($photo) {
		$img = $this->social_photo->getPhoto($photo);
		
		return array(
			'img' => '<img src="'.$img['photo_file'].'" class="photo_popup" data-photo="'.$photo.'" />',
			'msg' => $img['photo_desc'],
		);
	}
}