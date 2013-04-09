<?php
/**
 * Jaws import plugin
 * by Omar Bazavilvazo - http://OmarBazavilvazo.com/
 * Version 0.5.1, updated on March 23, 2011
 * Tested with Jaws 0.8.14 & Wordpress 3.1 updated by vicm3 - http://blografia.net/vicm3 
 * Still broken the summary goes to excerpt, need a major rewrite :/
 * with bits from Oviedo http://oviedo.mx/wp-content/uploads/2010/08/jaws.txt
 **/

/**
	Add These Functions to make our lives easier
**/
if(!function_exists('get_catbynicename'))
{
	function get_catbynicename($category_nicename)
	{
	global $wpdb;

	$cat_id -= 0; 	// force numeric
	$name = $wpdb->get_var('SELECT cat_ID FROM '.$wpdb->categories.' WHERE category_nicename="'.$category_nicename.'"');

	return $name;
	}
}

if(!function_exists('get_comment_count'))
{
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT count(*) FROM '.$wpdb->comments.' WHERE comment_post_ID = '.$post_ID);
	}
}

if(!function_exists('link_exists'))
{
	function link_exists($linkname)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT link_id FROM '.$wpdb->links.' WHERE link_name = "'.$wpdb->escape($linkname).'"');
	}
}

/**
	The Main Importer Class
**/
class Jaws_Import {

	function header() 
	{
		echo '<div class="wrap">';
		echo '<h2>'.__('Import Jaws').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
	}

	function footer() 
	{
		echo '</div>';
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>'.__('Howdy! This imports categories, users, posts, comments, and links from any Jaws 0.7.1+ into this blog.').'</p>';
		echo '<p>'.__('This has not been tested on previous versions of Jaws.  Mileage may vary.').'</p>';
		echo '<p>'.__('Your Jaws Configuration settings are as follows:').'</p>';
		echo '<form action="admin.php?import=jaws&amp;step=1" method="post">';
		$this->db_form();
		echo '<p class="submit"><input type="submit" name="submit" value="'.__('Import Categories').' &raquo;" /></p>';
		echo '</form>';
		echo '</div>';
	}

	function get_jaws_cats()
	{
		global $wpdb;
		// General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Categories
		return $jawsdb->get_results('SELECT
			id,
			name
			FROM '.$prefix.'blog_category',
			ARRAY_A);
	}

	function get_jaws_users()
	{
		global $wpdb;
		// General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Users

		return $jawsdb->get_results('SELECT
			id, 
			username,
			passwd,
			dname,
			email,
			createtime
			FROM '.$prefix.'users WHERE username != "admin"', ARRAY_A);
	}

	function get_jaws_posts()
	{
		// General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Posts
		return $jawsdb->get_results('SELECT *, id as post_id FROM '.$prefix.'blog', ARRAY_A);
	}
	
	function get_jaws_post_categories($id_post)
	{
		// General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Categories
		return $jawsdb->get_results('SELECT category_id as cat_id FROM '.$prefix.'blog_entrycat
			WHERE entry_id='.$id_post, ARRAY_A);
		
	}

	function get_jaws_comments()
	{
		global $wpdb;
		// General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Comments
		return $jawsdb->get_results('SELECT *, id as comment_id FROM '.$prefix.'comments WHERE gadget="Blog"', ARRAY_A);
	}

		function get_jaws_links()
	{
		//General Housekeeping
		$jawsdb = new wpdb(get_option('jawsuser'), get_option('jawspass'), get_option('jawsname'), get_option('jawshost'));
		set_magic_quotes_runtime(0);
		$prefix = get_option('tpre');

		// Get Friends...
		return $jawsdb->get_results('SELECT * FROM '.$prefix.'friend', ARRAY_A);
	}

	function cat2wp($categories='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$jawscat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...').'<br /><br /></p>';
			foreach ($categories as $category)
			{
				$count++;
				extract($category);


				// Make Nice Variables
				$name = $wpdb->escape($name);
				$title = utf8_decode($wpdb->escape($name));

				if($cinfo = category_exists($name))
				{
					$ret_id = wp_insert_category(array('cat_ID' => $cinfo, 'category_nicename' => $name, 'cat_name' => $title));
				}
				else
				{
					$ret_id = wp_insert_category(array('category_nicename' => $name, 'cat_name' => $title));
				}
				$jawscat2wpcat[$id] = $ret_id;
			}

			// Store category translation for future use
			add_option('jawscat2wpcat',$jawscat2wpcat);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> categories imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!');
		return false;
	}

	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$jawsid2wpid = array();

		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...').'<br /><br /></p>';
			foreach($users as $user)
			{
				$count++;
				extract($user);

				// Make Nice Variables
				$username = $wpdb->escape($username);
			 	$name = utf8_decode($wpdb->escape($name));	

				if($uinfo = get_userdatabylogin($username))
				{

					$ret_id = wp_insert_user(array(
								'ID'			=> $uinfo->ID,
								'user_login'	=> $username,
								'user_nicename'	=> $name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $name)
								);
				}
				else
				{
					$ret_id = wp_insert_user(array(
								'user_login'	=> $username,
								'user_nicename'	=> $name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $name)
								);
				}
				$jawsid2wpid[$user_id] = $ret_id;

				// Set Jaws-to-WordPress permissions translation
				$transperms = array(0 => '10', 1 => '9', 2 => '4');

				// Update Usermeta Data
				$user = new WP_User($ret_id);
				if('10' == $transperms[$privs]) { $user->set_role('administrator'); }
				if('9'  == $transperms[$privs]) { $user->set_role('editor'); }
				if('5'  == $transperms[$privs]) { $user->set_role('editor'); }
				if('4'  == $transperms[$privs]) { $user->set_role('author'); }
				if('3'  == $transperms[$privs]) { $user->set_role('contributor'); }
				if('2'  == $transperms[$privs]) { $user->set_role('contributor'); }
				if('0'  == $transperms[$privs]) { $user->set_role('subscriber'); }

				update_usermeta( $ret_id, 'wp_user_level', $transperms[$privs] );
				update_usermeta( $ret_id, 'rich_editing', 'false');
			}// End foreach($users as $user)

			// Store id translation array for future use
			add_option('jawsid2wpid',$jawsid2wpid);

			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)

		echo __('No Users to Import!');
		return false;

	}// End function user2wp()

	function posts2wp($posts='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$jawsposts2wpposts = array();
		$jawscat2wpcat = get_option('jawscat2wpcat');
		$jawsid2wpid = get_option('jawsid2wpid');

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				extract($post);

				// Set Jaws-to-WordPress status translation
				$stattrans = array(0 => 'draft', 1 => 'publish');

				//Can we do this more efficiently?
				$uinfo = ( get_userdatabylogin( $user_id ) ) ? get_userdatabylogin( $user_id ) : 1;
				$authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

				$Title = utf8_decode($wpdb->escape($title));
				$Body = utf8_decode($wpdb->escape($text));
				$Excerpt = utf8_decode($wpdb->escape($Excerpt));
				$post_status = $stattrans[$published];

				$Body = str_replace('[terminal]','<pre>',$Body);
				$Body = str_replace('[/terminal]','</pre>',$Body);
				//$NewBody = isset($summary)?$summary:"" + $Body;

				// Import Post data into WordPress

				if($pinfo = post_exists($Title,$Body))

				{
					$ret_id = wp_insert_post(array(
						'ID'				=> $pinfo,
						'post_date'			=> $createtime,
						'post_date_gmt'		=> $createtime,
						'post_author'		=> $jawsid2wpid[$user_id],
						'post_modified'		=> $updatetime,
						'post_modified_gmt' => $updatetime,
						'post_title'		=> $Body,
						'post_content'		=> $text,
						'post_excerpt'		=> $summary,
						'post_status'		=> $post_status,
						'post_name'			=> $fast_url,
						'comment_count'		=> $comments)
						);
				}
				else
				{
					$ret_id = wp_insert_post(array(
						'post_date'			=> $createtime,
						'post_date_gmt'		=> $createtime,
						'post_author'		=> $jawsid2wpid[$user_id],
						'post_modified'		=> $updatetime,
						'post_modified_gmt' => $updatetime,
						'post_title'		=> $Title,
						'post_content'		=> $Body,
						'post_excerpt'		=> $summary,
						'post_status'		=> $post_status,
						'post_name'			=> $fast_url,
						'comment_count'		=> $comments)
						);
				}
				$jawsposts2wpposts[$post_id] = $ret_id;

				// Make Post-to-Category associations
				$categories = array();
				$cats = array();
				$categories = $this->get_jaws_post_categories($post_id);

				if(!empty($categories)) 
				{ 
					$cat_index = 0;
					foreach ($categories as $category) {
						$cats[$cat_index] = $jawscat2wpcat[$category['cat_id']];
						$cat_index++;
					}				
					
					wp_set_post_categories($ret_id, $cats); 
				}
			}
		}
		// Store ID translation for later use
		add_option('jawsposts2wpposts',$jawsposts2wpposts);

		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
		return true;
	}

	function comments2wp($comments='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$jawscm2wpcm = array();
		$postarr = get_option('jawsposts2wpposts');

		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);
				
				// WordPressify Data
				$comment_ID = ltrim($id, '0');
				$comment_post_ID = $postarr[$gadget_reference];
				//$comment_approved = ('approved' == $status) ? 1 : 0;
				$comment_approved = 1;	//TODO: check
				$name = utf8_decode($wpdb->escape($name));
				$email = $wpdb->escape($email);
				$web = $wpdb->escape($url);
				$message = utf8_decode($wpdb->escape($message));

				if($cinfo = comment_exists($name, $createtime))
				{
					// Update comments
					$ret_id = wp_update_comment(array(
						'comment_ID'			=> $cinfo,
						'comment_post_ID'		=> $comment_post_ID,
						'comment_author'		=> $name,
						'comment_author_email'	=> $email,
						'comment_author_url'	=> $web,
						'comment_date'			=> $createtime,
						'comment_content'		=> $msg_txt,
						'comment_approved'		=> $comment_approved,
						'comment_parent'		=> $postarr[$parent])
						);
				}
				else
				{
					// Insert comments
					$ret_id = wp_insert_comment(array(
						'comment_post_ID'		=> $comment_post_ID,
						'comment_author'		=> $name,
						'comment_author_email'	=> $email,
						'comment_author_url'	=> $web,
						'comment_author_IP'		=> $ip,
						'comment_date'			=> $createtime,
						'comment_content'		=> $msg_txt,
						'comment_approved'		=> $comment_approved,
						'comment_parent'		=> $postarr[$parent])
						);
				}
				$jawscm2wpcm[$comment_id] = $ret_id;
			}
			// Store Comment ID translation for future use
			add_option('jawscm2wpcm', $jawscm2wpcm);

			// Associate newly formed categories with posts
			get_comment_count($ret_id);


			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!');
		return false;
	}

	function links2wp($links='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;

		// Deal with the links
		if(is_array($links))
		{
			echo '<p>'.__('Importing Links (Friends)...').'<br /><br /></p>';
			foreach($links as $link)
			{
				$count++;
				extract($link);

				// Make nice vars
				$category = $wpdb->escape("Blogroll");
				$linkname = utf8_decode($wpdb->escape($friend));
				$description = "";

				if($linfo = link_exists($linkname))
				{
					$ret_id = wp_insert_link(array(
								'link_id'			=> $linfo,
								'link_url'			=> $url,
								'link_name'			=> $linkname,
								'link_category'		=> $category,
								'link_description'	=> $description,
								'link_updated'		=> time())
								);
				}
				else
				{
					$ret_id = wp_insert_link(array(
								'link_url'			=> $url,
								'link_name'			=> $linkname,
								'link_category'		=> $category,
								'link_description'	=> $description,
								'link_updated'		=> time())
								);
				}
				$jawslinks2wplinks[$id] = $ret_id;
			}
			add_option('jawslinks2wplinks',$jawslinks2wplinks);
			echo '<p>';
			printf(__('Done! <strong>%s</strong> Links (Friends) imported'), $count);
			echo '<br /><br /></p>';
			return true;
		}
		echo __('No Links to Import!');
		return false;
	}

	function import_categories()
	{
		// Category Import
		$cats = $this->get_jaws_cats();
		$this->cat2wp($cats);
		add_option('jaws_cats', $cats);



		echo '<form action="admin.php?import=jaws&amp;step=2" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Users'));
		echo '</form>';

	}

	function import_users()
	{
		// User Import
		$users = $this->get_jaws_users();
		$this->users2wp($users);

		echo '<form action="admin.php?import=jaws&amp;step=3" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Posts'));
		echo '</form>';
	}

	function import_posts()
	{
		// Post Import
		$posts = $this->get_jaws_posts();
		$this->posts2wp($posts);

		echo '<form action="admin.php?import=jaws&amp;step=4" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Comments'));
		echo '</form>';
	}

	function import_comments()
	{
		// Comment Import
		$comments = $this->get_jaws_comments();
		$this->comments2wp($comments);

		echo '<form action="admin.php?import=jaws&amp;step=5" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Links'));
		echo '</form>';
	}

	function import_links()
	{
		//Link Import
		$links = $this->get_jaws_links();
		$this->links2wp($links);
		add_option('jaws_links', $links);

		echo '<form action="admin.php?import=jaws&amp;step=6" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Finish'));
		echo '</form>';
	}

	function cleanup_txpimport()
	{
		delete_option('tpre');
		delete_option('jaws_cats');
		delete_option('jawsid2wpid');
		delete_option('jawscat2wpcat');
		delete_option('jawsposts2wpposts');
		delete_option('jawscm2wpcm');
		delete_option('jawslinks2wplinks');
		delete_option('jawsuser');
		delete_option('jawspass');
		delete_option('jawsname');
		delete_option('jawshost');
		$this->tips();
	}

	function tips()
	{
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from Jaws, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.').'</p>';
		echo '<h3>'.__('Users').'</h3>';
		echo '<p>'.sprintf(__('You have already setup WordPress and have been assigned an administrative login and password.  Forget it.  You didn\'t have that login in Jaws, why should you have it here?  Instead we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and Jaws uses a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.  <strong>Every user has the same username, but their passwords are reset to password123.</strong>  So <a href="%1$s">Login</a> and change it.'), '/wp-login.php').'</p>';
		echo '<h3>'.__('Preserving Authors').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.').'</p>';
		echo '<h3>'.__('Textile').'</h3>';
		echo '<p>'.__('Also, since you\'re coming from Jaws, you probably have been using Textile to format your comments and posts.  If this is the case, we recommend downloading and installing <a href="http://www.huddledmasses.org/category/development/wordpress/textile/">Textile for WordPress</a>.  Trust me... You\'ll want it.').'</p>';
		echo '<h3>'.__('WordPress Resources').'</h3>';
		echo '<p>'.__('Finally, there are numerous WordPress resources around the internet.  Some of them are:').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://www.wordpress.org">The official WordPress site</a>').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums</a>').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org">The Codex (In other words, the WordPress Bible)</a>').'</li>';
		echo '</ul>';
		echo '<p>'.sprintf(__('That\'s it! What are you waiting for? Go <a href="%1$s">login</a>!'), '/wp-login.php').'</p>';
	}

	function db_form()
	{
		echo '<table class="editform">';
		printf('<tr><th scope="row"><label for="dbuser">%s</label></th><td><input type="text" name="dbuser" id="dbuser" /></td></tr>', __('Jaws Database User:'));
		printf('<tr><th scope="row"><label for="dbpass">%s</label></th><td><input type="password" name="dbpass" id="dbpass" /></td></tr>', __('Jaws Database Password:'));
		printf('<tr><th scope="row"><label for="dbname">%s</label></th><td><input type="text" id="dbname" name="dbname" /></td></tr>', __('Jaws Database Name:'));
		printf('<tr><th scope="row"><label for="dbhost">%s</label></th><td><input type="text" id="dbhost" name="dbhost" value="localhost" /></td></tr>', __('Jaws Database Host:'));
		printf('<tr><th scope="row"><label for="dbprefix">%s</label></th><td><input type="text" name="dbprefix" id="dbprefix" value="jaws_"/></td></tr>', __('Jaws Table prefix (if any):'));
		echo '</table>';
	}

	function dispatch()
	{

		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
		$this->header();

		if ( $step > 0 )
		{
			if($_POST['dbuser'])
			{
				if(get_option('jawsuser'))
					delete_option('jawsuser');
				add_option('jawsuser',$_POST['dbuser']);
			}
			if($_POST['dbpass'])
			{
				if(get_option('jawspass'))
					delete_option('jawspass');
				add_option('jawspass',$_POST['dbpass']);
			}

			if($_POST['dbname'])
			{
				if(get_option('jawsname'))
					delete_option('jawsname');
				add_option('jawsname',$_POST['dbname']);
			}
			if($_POST['dbhost'])
			{
				if(get_option('jawshost'))
					delete_option('jawshost');
				add_option('jawshost',$_POST['dbhost']);
			}
			if($_POST['dbprefix'])
			{
				if(get_option('tpre'))
					delete_option('tpre');
				add_option('tpre',$_POST['dbprefix']);
			}


		}

		switch ($step)
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$this->import_posts();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->import_links();
				break;
			case 6 :
				$this->cleanup_txpimport();
				break;
		}

		$this->footer();
	}

	function Jaws_Import()
	{
		// Nothing.
	}
}

$jaws_import = new Jaws_Import();
register_importer('jaws', __('Jaws'), __('Import categories, users, posts, comments, and links from a Jaws blog'), array ($jaws_import, 'dispatch'));
?>
