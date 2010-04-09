<?php
/**
 * Wordpress Helper
 * 
 * Loads the latest posts from a wordpress database and returns them
 * for displaying inside a cakePHP application.
 * 
 * Do not use tag, category or author in your permalinks because this 
 * Helper won't replace those tags for now. It's a huge database slowdown
 * to fetch all the records for categories, tags or authors just to display
 * a short feed of posts on another site.
 * 
 * 
 * @author    Henning Stein, www.atomtigerzoo.com 
 * @version   0.3
 *  
 */
class WordpressHelper extends AppHelper {
  
  /**
   * WP Server
   * Enter the domain or IP of your database server here.
   * Mostly it will be 'localhost'. You can add a port if needed
   * by appending ':[PORTNUMBER]' to the domain, name or IP.      
   * 
   * @var string
   */
  private $wp_server = '';
  
  /**
   * WP Database
   * Enter the name of the database in which your wordpress resides.
   * 
   * @var string
   */
  private $wp_database = '';
  
  /**
   * WP Username
   * The username for the wordpress database.
   * 
   * @var string
   */
  private $wp_username = '';
  
  /**
   * WP Password
   * Database password for the wordpress database
   * 
   * @var string   
   */
  private $wp_password = '';
  
  /**
   * Limit
   * Enter the maximum number of blog posts you want to fetch 
   * from the database.   
   * 
   * @var int
   */
  public $limit = 5;
  
  /**
   * Nice-URLs
   * Setting for displaying nice-urls (true) or not (false).
   * 
   * If you want to link to your blog with pretty URls set this value
   * to true. Otherwise if you want to use ID-based URls set it to false.   
   * 
   * @var bool
   */
  public $niceurls = true;
  
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
  public $clean = true;
  
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
  public $allowed_tags = null;
  
  
  ###   -----------------   ###
  ###   STOP EDITING HERE   ###
  ###   -----------------   ###
  
  
  /**
   * Settings
   * Placeholder for the blogs settings
   * 
   * @var array
   */
  public $settings;
  
  
  /**
   * PUBLIC Get Latest
   * 
   * @return array
   */
  public function getLatest(){
    // Get settings
    $this->__get_settings();
    
    // Get posts
    $posts = $this->__query_posts();
    
    if( $posts && $this->clean ){
      $posts = $this->__strip_html($posts);
    }
    
    return $posts;
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
  private function __connect(){
    $db_conn = @mysql_connect($this->wp_server, $this->wp_username, $this->wp_password);
    if(!$db_conn){
      return false;
    }
    $db_selected = @mysql_select_db($this->wp_database);
    if(!$db_selected){
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
  private function __query_posts(){
    if( !$this->__connect() ){
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
    
    if( $result['num_rows']==0 ){
      return false;
    }
    
    // Process the query results
    $posts = $this->__process_posts($result);
    
    return $posts;
  }
  
  
  /**
   * PRIVATE Get Settings
   * Retrieves the settings from the blog
   *  - siteurl
   *  - blogname
   *  - date_format
   *  - permalink_structure
   * 
   */
  private function __get_settings(){
    if( !$this->__connect() ){
      return false;
    }
    
    $db_query = "SELECT option_name, option_value 
                FROM wp_options 
                WHERE option_name='siteurl' OR option_name='blogname' OR option_name='date_format' OR option_name='permalink_structure'
                ";
    $result['query'] = mysql_query($db_query);
    $result['num_rows'] = mysql_numrows($result['query']);
    mysql_close();
    
    for($i=0; $i<$result['num_rows']; $i++){
      $this->settings[ mysql_result($result['query'], $i, "option_name") ] = mysql_result($result['query'], $i, "option_value");
    }
  }
  
  
  /**
   * PRIVATE Process Posts
   * Put the posts-results into an array
   * 
   * @params array posts_query
   * @return array
   */
  private function __process_posts($posts_query){
    for( $i = 0; $i < $posts_query['num_rows']; $i++ ){
      // Texts
      $posts[$i]['title'] = utf8_encode( mysql_result( $posts_query['query'], $i, "post_title" ) );
      $posts[$i]['content'] = utf8_encode( mysql_result( $posts_query['query'], $i, "post_content" ) );
      
      // Date
      $posts[$i]['date'] = mysql_result( $posts_query['query'], $i, "post_date" );
      $posts[$i]['date'] = date( $this->settings['date_format'], strtotime( $posts[$i]['date'] ) );
      
      // Postname
      $postname = mysql_result( $posts_query['query'], $i, "post_name" );
      
      // Permalinks
      if( $this->niceurls ){
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
          mysql_result( $posts_query['query'], $i, "id" );
        );
        // Replace the wordpress tags
        $permalink = str_replace( $wp_permalink_tags, $replace, $this->settings['permalink_structure'] );
        
        $posts[$i]['link'] = $this->settings['siteurl'] . $permalink;
      }
      else{
        $posts[$i]['link'] = mysql_result( $posts_query['query'], $i, "guid" );
      }
    }
    
    return $posts;
  }
  
  
  /**
   * PRIVATE Strip HTML
   * Strips the HTML tags from the posts content string
   * 
   * @params array posts
   * @return array
   */
  private function __strip_html($posts){
    foreach($posts as $key => $post){
      $posts[$key]['content'] = strip_tags($post['content'], $this->allowed_tags);
    }
    return $posts;
  }
  
  
  
}
?>