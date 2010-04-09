## cakePHP Wordpress Helper

The cakePHP Wordpress Helper can be used to fetch posts from a Wordpress 
database to display them inside a cakePHP application. You can choose how to 
display the content and are free to use the pretty-urls configured in your
blog or the ugly ID links.


### USAGE
- Copy the helper file into your application helpers.

- Change the server and database settings to your needs directly inside the
  helper file.
  
- Include 'Wordpress' in the helper array in your controller

- The post limit, niceurls, clean and allowed tags can be changed on the fly:
    
    $wordpress->limit = 5;
    $wordpress->niceurls = true;
    $wordpress->clean = true;
    $wordpress->allowed_tags = null;
    

- In your view use the following to grab the posts:
    
    $posts = $wordpress->getLatest();
    


**Note about using permalinks:**
Do not use %tag%, %category% or %author% in your permalinks because this 
Helper won't replace those tags for now. It's a huge database slowdown
to fetch all the records for categories, tags or authors just to display
a short feed of posts on another site.




##### AUTHOR
Henning Stein, www.atomtigerzoo.com

##### WEBSITE & REPOSITORY
http://github.com/atomtigerzoo/cakePHP-Wordpress-Helper

##### LICENSE
Please see included LICENSE file
