<?php

/* from http://scurker.com/blog/2010/02/migration-from-drupal-6-x-to-wordpress-2-9x/
Mopst probably after migrating yout posts to wordpress you can end with post_name not sanitized, empty or with garbage so this script takes your post_title pass around WP sanitize function and gives you a post_name usable for permalinks I add here in hope if it need changes as drupal and WP changes you can fork easily. vicm3 9/4/2013
* / 

  require_once('wp-load.php');
 
  $posts = $wpdb->get_results(
    "SELECT ID, post_title, post_name FROM $wpdb->posts"
  );
 
  $count = 0;
  $ignored = 0;
  $errors = 0;
  foreach($posts as $post) {
    if(strcmp($slug = sanitize_title($post->post_title), $post->post_name) !== 0) {
      $wpdb->show_errors();
      if(($result = $wpdb->query("UPDATE $wpdb->posts SET post_name='$slug' WHERE ID=$post->ID")) === false) {
        $errors++;
      } elseif($result === 0) {
        $ignore++;
      } else {
        $count++;
      }
    } else {
       $ignored++;
    }
  }
 
  echo "<strong>$count post slug(s) sanitized.</strong><br />";
  echo "$ignored post(s) ignored.<br />";
  echo "$errors error(s).<br />";
