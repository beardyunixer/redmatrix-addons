<?php

/**
 * Name: WordPress Post Connector
 * Description: Post to WordPress (or anything else which uses the wordpress XMLRPC API)
 * Version: 1.0
 * Author: Mike Macgirvin <zot:mike@zothub.com>
 */

require_once('include/permissions.php');
require_once('library/IXR_Library.php');


function wppost_load () {
	register_hook('post_local',              'addon/wppost/wppost.php', 'wppost_post_local');
	register_hook('post_remote_end',         'addon/wppost/wppost.php', 'wppost_post_remote_end');
	register_hook('notifier_normal',         'addon/wppost/wppost.php', 'wppost_send');
	register_hook('jot_networks',            'addon/wppost/wppost.php', 'wppost_jot_nets');
	register_hook('feature_settings',        'addon/wppost/wppost.php', 'wppost_settings');
	register_hook('feature_settings_post',   'addon/wppost/wppost.php', 'wppost_settings_post');
	register_hook('drop_item',               'addon/wppost/wppost.php', 'wppost_drop_item');	
}

function wppost_unload () {
	unregister_hook('post_local',            'addon/wppost/wppost.php', 'wppost_post_local');
	unregister_hook('post_remote_end',       'addon/wppost/wppost.php', 'wppost_post_remote_end');
    unregister_hook('notifier_normal',       'addon/wppost/wppost.php', 'wppost_send');
    unregister_hook('jot_networks',          'addon/wppost/wppost.php', 'wppost_jot_nets');
    unregister_hook('feature_settings',      'addon/wppost/wppost.php', 'wppost_settings');
    unregister_hook('feature_settings_post', 'addon/wppost/wppost.php', 'wppost_settings_post');
    unregister_hook('drop_item',             'addon/wppost/wppost.php', 'wppost_drop_item');
}


function wppost_jot_nets(&$a,&$b) {
    if((! local_channel()) || (! perm_is_allowed(local_channel(),'','view_stream')))
        return;

    $wp_post = get_pconfig(local_channel(),'wppost','post');
    if(intval($wp_post) == 1) {
        $wp_defpost = get_pconfig(local_channel(),'wppost','post_by_default');
        $selected = ((intval($wp_defpost) == 1) ? ' checked="checked" ' : '');
        $b .= '<div class="profile-jot-net"><input type="checkbox" name="wppost_enable" ' . $selected . ' value="1" /> <img src="addon/wppost/wordpress-logo.png" /> ' . t('Post to WordPress') . '</div>';

    }
}


function wppost_settings(&$a,&$s) {

	if(! local_channel())
		return;

	/* Add our stylesheet to the page so we can make our settings look nice */

	//head_add_css('/addon/wppost/wppost.css');

	/* Get the current state of our config variables */

	$enabled = get_pconfig(local_channel(),'wppost','post');

	$checked = (($enabled) ? 1 : false);

	$fwd_enabled = get_pconfig(local_channel(), 'wppost','forward_comments');

	$fwd_checked = (($fwd_enabled) ? 1 : false);

	$def_enabled = get_pconfig(local_channel(),'wppost','post_by_default');

	$def_checked = (($def_enabled) ? 1 : false);

	$wp_username = get_pconfig(local_channel(), 'wppost', 'wp_username');
	$wp_password = z_unobscure(get_pconfig(local_channel(), 'wppost', 'wp_password'));
	$wp_blog = get_pconfig(local_channel(), 'wppost', 'wp_blog');


	/* Add some HTML to the existing form */

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('wppost', t('Enable WordPress Post Plugin'), $checked, '', array(t('No'),t('Yes'))),
	));

	$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
		'$field'	=> array('wp_username', t('WordPress username'), $wp_username, '')
	));

	$sc .= replace_macros(get_markup_template('field_password.tpl'), array(
		'$field'	=> array('wp_password', t('WordPress password'), $wp_password, '')
	));

	$sc .= replace_macros(get_markup_template('field_input.tpl'), array(
		'$field'	=> array('wp_blog', t('WordPress API URL'), $wp_blog, '')
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('wp_bydefault', t('Post to WordPress by default'), $def_checked, '', array(t('No'),t('Yes'))),
	));

	$sc .= replace_macros(get_markup_template('field_checkbox.tpl'), array(
		'$field'	=> array('wp_forward_comments', t('Forward comments (requires hubzilla_wp plugin)'), $fwd_checked, '', array(t('No'),t('Yes'))),
	));

	$s .= replace_macros(get_markup_template('generic_addon_settings.tpl'), array(
		'$addon' 	=> array('wppost', '<img src="addon/wppost/wordpress-logo.png" style="width:auto; height:1em; margin:-3px 5px 0px 0px;">' . t('WordPress Post Settings'), '', t('Submit')),
		'$content'	=> $sc
	));

}


function wppost_settings_post(&$a,&$b) {
	if(x($_POST,'wppost-submit')) {
		set_pconfig(local_channel(),'wppost','post',intval($_POST['wppost']));
		set_pconfig(local_channel(),'wppost','post_by_default',intval($_POST['wp_bydefault']));
		set_pconfig(local_channel(),'wppost','wp_username',trim($_POST['wp_username']));
		set_pconfig(local_channel(),'wppost','wp_password',z_obscure(trim($_POST['wp_password'])));
		set_pconfig(local_channel(),'wppost','wp_blog',trim($_POST['wp_blog']));
		set_pconfig(local_channel(),'wppost','forward_comments',trim($_POST['wp_forward_comments']));
		info( t('Wordpress Settings saved.') . EOL);
	}
}

function wppost_post_local(&$a,&$b) {

	// This can probably be changed to allow editing by pointing to a different API endpoint

	if($b['edit'])
		return;

	if((! local_channel()) || (local_channel() != $b['uid']))
		return;

	if($b['item_private'] || $b['parent'])
		return;

    $wp_post   = intval(get_pconfig(local_channel(),'wppost','post'));

	$wp_enable = (($wp_post && x($_REQUEST,'wppost_enable')) ? intval($_REQUEST['wppost_enable']) : 0);

	if($_REQUEST['api_source'] && intval(get_pconfig(local_channel(),'wppost','post_by_default')))
		$wp_enable = 1;

    if(! $wp_enable)
       return;

    if(strlen($b['postopts']))
       $b['postopts'] .= ',';
     $b['postopts'] .= 'wppost';
}




function wppost_send(&$a,&$b) {

    if((! is_item_normal($b)) || $b['item_private'])
        return;

	if(! perm_is_allowed($b['uid'],'','view_stream'))
		return;

    if(! strstr($b['postopts'],'wppost'))
        return;

	$edited = (($b['created'] !== $b['edited']) ? true : false);
		
    if($b['parent'] != $b['id'])
        return;

	logger('Wordpress xpost invoked', LOGGER_DEBUG);

	if($edited) {
		$r = q("select * from item_id where service = 'wordpress' and iid = %d and uid = %d limit 1",
			intval($b['id']),
			intval($b['uid'])
		);
		if(! $r)
			return;

		$wp_post_id = intval(basename($r[0]['sid']));
	}

	$tags = null;
	$categories = null;

	if(is_array($b['term']) && $b['term']) {
		foreach($b['term'] as $term) {
 			if($term['type'] == TERM_CATEGORY)
				$categories[] = $term['term'];
			if($term['type'] == TERM_HASHTAG)
				$tags[] = $term['term'];
		}
	}

	$terms_names = array();
	if($tags)
		$terms_names['post_tag'] = $tags;
	if($categories)
		$terms_names['category'] = $categories;
		


	$wp_username = get_pconfig($b['uid'],'wppost','wp_username');
	$wp_password = z_unobscure(get_pconfig($b['uid'],'wppost','wp_password'));
	$wp_blog     = get_pconfig($b['uid'],'wppost','wp_blog');

	if($wp_username && $wp_password && $wp_blog) {

		require_once('include/bbcode.php');

		$data = array(
			'post_title'     => trim($b['title']),
			'post_content'   => bbcode($b['body']),
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'comment_status' => 'open',
			'custom_fields'  => array(array('key' => 'post_from_red', 'value' => '1'))
		);
		if($terms_names)
			$data['terms_names'] = $terms_names;


		$client = new IXR_Client($wp_blog);


		if($edited)
			$res = $client->query('wp.editPost',1,$wp_username,$wp_password,$wp_post_id,$data);
		else
			$res = $client->query('wp.newPost',1,$wp_username,$wp_password,$data);

		if(! $res) {
			logger('wppost: failed.');
			return;
		}

		$post_id = $client->getResponse();

		logger('wppost: returns post_id: ' . $post_id, LOGGER_DEBUG);

		if($edited)
			return;

		if($post_id) {
			q("insert into item_id ( iid, uid, sid, service ) values ( %d, %d, '%s','%s' )",
				intval($b['id']),
				intval($b['uid']),
				dbesc(dirname($wp_blog) . '/' . $post_id),
				dbesc('wordpress')
			);
		}
	}
}


function wppost_post_remote_end(&$a,&$b) {

	// We are only looking for public comments

	logger('wppost_post_remote_end');

	if($b['mid'] === $b['parent_mid'])
		return;

    if((! is_item_normal($b)) || $b['item_private'])
        return;

	// Does the post owner have this plugin installed?

    $wp_post   = intval(get_pconfig($b['uid'],'wppost','post'));
	if(! $wp_post)
		return;

	// Are we allowed to forward comments?

    $wp_forward_comments = intval(get_pconfig($b['uid'],'wppost','forward_comments'));
	if(! $wp_forward_comments)
		return;

	// how about our stream permissions? 

	if(! perm_is_allowed($b['uid'],'','view_stream'))
		return;

	// Now we have to get down and dirty. Was the parent shared with wordpress?

	$r = q("select * from item_id where service = 'wordpress' and iid = %d and uid = %d limit 1",
		intval($b['parent']),
		intval($b['uid'])
	);
	if(! $r)
		return;

	$wp_parent_id = intval(basename($r[0]['sid']));

	$x = q("select * from xchan where xchan_hash = '%s' limit 1",
		dbesc($b['author_xchan'])
	);
	if(! $x)
		return;

	logger('Wordpress xpost comment invoked', LOGGER_DEBUG);

	$edited = (($b['created'] !== $b['edited']) ? true : false);
		
	if($edited) {
		$r = q("select * from item_id where service = 'wordpress' and iid = %d and uid = %d limit 1",
			intval($b['id']),
			intval($b['uid'])
		);
		if(! $r)
			return;

		$wp_comment_id = intval(basename($r[0]['sid']));
	}

	$wp_username = get_pconfig($b['uid'],'wppost','wp_username');
	$wp_password = z_unobscure(get_pconfig($b['uid'],'wppost','wp_password'));
	$wp_blog     = get_pconfig($b['uid'],'wppost','wp_blog');

	if($wp_username && $wp_password && $wp_blog) {

		require_once('include/bbcode.php');

		$data = array(
			'author' => $x[0]['xchan_name'],
			'author_email' => $x[0]['xchan_addr'],
			'author_url' => $x[0]['xchan_url'],
			'content' => bbcode($b['body']),
			'approved' => 1
		);
		if($edited)
			$data['comment_id'] = $wp_comment_id;
		else
			$data['red_avatar'] = $x[0]['xchan_photo_m'];

		$client = new IXR_Client($wp_blog);

		// this will fail if the post_to_red plugin isn't installed on the wordpress site

		$res = $client->query('red.Comment',1,$wp_username,$wp_password,$wp_parent_id,$data);

		if(! $res) {
			logger('wppost: comment failed.');
			return;
		}


		$post_id = $client->getResponse();

		logger('wppost: comment returns post_id: ' . $post_id, LOGGER_DEBUG);

		// edited just returns true

		if($edited)
			return;

		if($post_id) {
			q("insert into item_id ( iid, uid, sid, service ) values ( %d, %d, '%s','%s' )",
				intval($b['id']),
				intval($b['uid']),
				dbesc(dirname($wp_blog) . '/' . $post_id),
				dbesc('wordpress')
			);
		}
	}
}





function wppost_drop_item(&$a,&$b) {

    $wp_enabled = get_pconfig($b['item']['uid'],'wppost','post');
	if(! $wp_enabled)
		return;

	$r = q("select * from item_id where service = 'wordpress' and iid = %d and uid = %d limit 1",
		intval($b['item']['id']),
		intval($b['item']['uid'])
	);
	if(! $r)
		return;

	$post_id = basename($r[0]['sid']);

	$wp_username = get_pconfig($b['item']['uid'],'wppost','wp_username');
	$wp_password = z_unobscure(get_pconfig($b['item']['uid'],'wppost','wp_password'));
	$wp_blog     = get_pconfig($b['item']['uid'],'wppost','wp_blog');

	if($post_id && $wp_username && $wp_password && $wp_blog) {

		$client = new IXR_Client($wp_blog);

		if($b['item']['id'] == $b['item']['parent']) 
			$res = $client->query('wp.deletePost',1,$wp_username,$wp_password,$post_id);
		else	
			$res = $client->query('wp.deleteComment',1,$wp_username,$wp_password,$post_id);

		if(! $res) {
			logger('wppost: delete failed.');
			return;
		}

		$result = intval($client->getResponse());

		logger('wppost: delete post returns: ' . $result, LOGGER_DEBUG);
	
	}

}
