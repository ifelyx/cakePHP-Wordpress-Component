<?php

/**
 * Wordpress Component
 *
 * Loads the latest posts from a wordpress database so that you can use them
 * in your cakePHP application.
 *
 * Do not use tag, category or author in your permalinks because this
 * Helper won't replace those tags for now. It's a huge database slowdown
 * to fetch all the records for categories, tags or authors just to display
 * a short feed of posts on another site.
 *
 * @author    Henning Stein, www.atomtigerzoo.com
 * @copyright Copyright (c) 2010, Henning Stein
 * @version   0.4
 */
class WordpressComponent extends Object {
	var $name = 'WordpressComponent';

	/**
	 * PRIVATE WP Server
	 * Enter the domain or IP of your database server here.
	 * Mostly it will be 'localhost'. You can add a port if needed
	 * by appending ':[PORT]' to the domain, name or IP.
	 *
	 * @var string
	 */
	var $__wp_server = 'database_url_or_ip';

	/**
	 * PRIVATE WP Database
	 * Enter the name of the database in which your wordpress resides.
	 *
	 * @var string
	 */
	var $__wp_database = 'the_database_name';

	/**
	 * PRIVATE WP Username
	 * The username for the wordpress database.
	 *
	 * @var string
	 */
	var $__wp_username = 'your_database_username';

	/**
	 * PRIVATE WP Password
	 * Database password for the wordpress database
	 *
	 * @var string
	 */
	var $__wp_password = 'your_database_password';

	/**
	 * Limit
	 * Enter the maximum number of blog posts you want to fetch
	 * from the database.
	 *
	 * @var int
	 */
	var $limit = 5;

	/**
	 * Nice-URLs
	 * Setting for displaying nice-urls (true) or not (false).
	 *
	 * If you want to link to your blog with pretty URls set this value
	 * to true. Otherwise if you want to use ID-based URls set it to false.
	 *
	 * @var bool
	 */
	var $niceurls = true;

	/**
	 * Thumbnails
	 * Should thumbnails from the posts be queried or not?
	 *
	 * @var thumbnails
	 */
	var $thumbnails = true;

	/**
	 * Clean
	 * Strips the content of the posts from their HTML tags.
	 *
	 * Set this value to true if you want to strip all HTML tags from the
	 * posts content. Otherwise change to false if you want to keep all
	 * HTML tags used in the posts content.
	 *
	 * @var bool
	 */
	var $clean = true;

	/**
	 * Allowed Tags
	 * Which tags to keep while $clean is true.
	 *
	 * If the above value of $clean is set to true you can enter HTML tags
	 * which you want to keep in the posts content:
	 * ie. '<img><strong><em>'
	 *
	 * @var string
	 */
	var $allowed_tags = null;


	###   -------------------   ###
	###    STOP EDITING HERE    ###
	###   -------------------   ###
	

	/**
	 * Blog Settings
	 * @var array
	 */
	var $settings;
	/**
	 * Placeholder for post thumbnails
	 */
	var $post_thumbnails;

	/**
	 * PUBLIC Get Latest
	 *
	 * @return array
	 */
	function getLatest() {
		// Get settings
		$this->__get_settings();
		// Get posts
		$posts = $this->__query_posts();

		if($posts && $this->clean) {
			$posts = $this->__strip_html($posts);
		}

		foreach($posts as $key => $post):
			if(isset($post['thumbnail']) && $this->__check_if_thumbnail_exists($post['thumbnail'], '100x100') ){
				$posts[$key]['thumbnail_100x100'] = $this->settings['siteurl'] . $this->settings['upload_path'] . '/' . str_replace('.jpg', '-100x100.jpg', $post['thumbnail']);
			}
		endforeach;

		return $posts;
	}


	/**
	 * PRIVATE check if thumbnail exists
	 * Check via curl if the image exists - needs a speedy server ;)
	 * If you encounter slow loading try to disable this check. Otherwise think
	 * about caching the result of the component in your controller/view.
	 *
	 * @return bool
	 */
	function __check_if_thumbnail_exists($thumbnail, $size = '100x100') {
		$thumbnail = str_replace('.jpg', '-' . $size . '.jpg', $thumbnail);
		$ch = curl_init($this->settings['siteurl'] . $this->settings['upload_path'] . "/" . $thumbnail);

		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_exec($ch);
		$return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// $return_code > 400 -> not found, $retcode = 200, found.
		if($return_code != '200') {
			return false;
		}
		return true;
	}


	/**
	 * PRIVATE connect
	 * Connect to the database
	 *
	 * The @ is suppressing errors because we don't want to drop
	 * knowledge to users in case of errors.
	 *
	 * @return bool
	 */
	function __connect() {
		$db_conn = @mysql_connect($this->__wp_server, $this->__wp_username, $this->__wp_password);
		if(!$db_conn) {
			return false;
		}
		// Since newer WP databases are utf8 we force the char setting here,
		// to get correct curly-quotes and umlauts
		@mysql_query("SET NAMES 'utf8'");

		$db_selected = @mysql_select_db($this->__wp_database);
		if(!$db_selected) {
			return false;
		}

		return true;
	}


	/**
	 * PRIVATE query_posts
	 * Fetches the posts from the database
	 *
	 * @return array
	 */
	function __query_posts() {
		if(!$this->__connect()) {
			#debug('Database connection failed. Please check your settings!');
			return false;
		}

		$db_query = "SELECT id, post_date, post_content, post_title, post_name, guid
                FROM wp_posts
                WHERE post_type='post' AND post_status='publish' AND post_password=''
                ORDER BY post_date DESC, id DESC
                LIMIT " . $this->limit;
		$result['query'] = mysql_query($db_query);
		$result['num_rows'] = mysql_numrows($result['query']);
		mysql_close();

		if($result['num_rows'] == 0) {
			return false;
		}

		if($this->thumbnails) {
			// Get the thumbnails
			$this->__get_post_thumbnails($result);
		}

		// Process the query results
		$posts = $this->__process_posts($result);

		return $posts;
	}


	/**
	 * PRIVATE get_post_thumbnails
	 *
	 * @param array $post_query
	 * @return array
	 */
	function __get_post_thumbnails($post_query) {
		if(!$this->__connect()) {
			return false;
		}

		for($i = 0; $i < $post_query['num_rows']; $i++) {
			$post_id = mysql_result($post_query['query'], $i, "id");
			$db_query = "SELECT meta_value
					FROM wp_postmeta
					WHERE post_id = " . $post_id . " AND meta_key = '_thumbnail_id'
					LIMIT 1";
			$thumb['query'] = mysql_query($db_query);

			if(mysql_numrows($thumb['query']) != 0) {
				// Thumbnail exists
				$db_query = "SELECT meta_value
					FROM wp_postmeta
					WHERE post_id = " . mysql_result($thumb['query'], 0, "meta_value") . "
					LIMIT 1";
				$thumb_query = mysql_query($db_query);

				if(mysql_numrows($thumb_query) != 0) {
					$this->post_thumbnails[$post_id] = mysql_result($thumb_query, 0, "meta_value");
				}
			}
		}
		mysql_close();

		return $this->post_thumbnails;
	}


	/**
	 * PRIVATE Get Settings
	 * Retrieves the settings from the blog
	 *  - siteurl
	 *  - blogname
	 *  - date_format
	 *  - permalink_structure
	 *  - upload_path
	 */
	function __get_settings() {
		if(!$this->__connect()) {
			return false;
		}

		$db_query = "SELECT option_name, option_value
                FROM wp_options
                WHERE option_name='siteurl' OR option_name='blogname' OR option_name='date_format' OR option_name='permalink_structure' OR option_name='upload_path'
                ";
		$result['query'] = mysql_query($db_query);
		$result['num_rows'] = mysql_numrows($result['query']);
		mysql_close();

		for($i = 0; $i < $result['num_rows']; $i++) {
			$this->settings[mysql_result($result['query'], $i, "option_name")] = mysql_result($result['query'], $i, "option_value");
		}
	}


	/**
	 * PRIVATE Process Posts
	 * Put the posts-results into an array
	 *
	 * @param array posts_query
	 * @return array
	 */
	function __process_posts($posts_query) {
		for($i = 0; $i < $posts_query['num_rows']; $i++) {
			// Texts
			$posts[$i]['title'] = mysql_result($posts_query['query'], $i, "post_title");
			$posts[$i]['content'] = mysql_result($posts_query['query'], $i, "post_content");
			// Date
			$posts[$i]['date'] = mysql_result($posts_query['query'], $i, "post_date");
			$posts[$i]['date'] = date($this->settings['date_format'], strtotime($posts[$i]['date']));
			// Postname
			$postname = mysql_result($posts_query['query'], $i, "post_name");
			// Post ID
			$post_id = mysql_result($posts_query['query'], $i, "id");

			// Permalinks
			if($this->niceurls) {
				$wp_permalink_tags = array(
					"%year%",
					"%monthnum%",
					"%day%",
					"%hour%",
					"%minute%",
					"%second%",
					"%postname%",
					"%post_id%"
				);

				$replace = array(
					date("Y", strtotime($posts[$i]['date'])),
					date("m", strtotime($posts[$i]['date'])),
					date("d", strtotime($posts[$i]['date'])),
					date("H", strtotime($posts[$i]['date'])),
					date("i", strtotime($posts[$i]['date'])),
					date("s", strtotime($posts[$i]['date'])),
					$postname,
					$post_id
				);
				// Replace the wordpress tags
				$permalink = str_replace($wp_permalink_tags, $replace, $this->settings['permalink_structure']);

				$siteurl = $this->settings['siteurl'];
				if(substr($siteurl, -1) == '/') {
					$siteurl = substr($siteurl, 0, -1);
				}
				$posts[$i]['link'] = $siteurl . $permalink;
			}
			else {
				$posts[$i]['link'] = mysql_result($posts_query['query'], $i, "guid");
			}

			// Add thumbnails to posts
			if(isset($this->post_thumbnails[$post_id])) {
				$posts[$i]['thumbnail'] = $this->post_thumbnails[$post_id];
			}
		}

		return $posts;
	}


	/**
	 * PRIVATE Strip HTML
	 * Strips the HTML tags from the posts content string
	 *
	 * @param array posts
	 * @return array
	 */
	function __strip_html($posts) {
		foreach($posts as $key => $post) {
			$posts[$key]['content'] = strip_tags($post['content'], $this->allowed_tags);
		}
		return $posts;
	}


}

?>