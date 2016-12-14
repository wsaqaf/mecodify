### Prototype of a Docker container for Mecodify


This is a first attempt at writing a [Docker](https://www.docker.com/) container that will deploy the Mecodify application. 
To build this image use a command like (replace `myusername` with your Docker Hub username):

    docker build -t myusername/mecodify:1.21 .

To run (with a persistent database in `/opt/mecodify/mysql` and site configuration in `/opt/mecodify/config/configuration.php`) run:

    docker run --rm -p 8080:80 --name mecodify -v /opt/mecodify/mysql:/var/lib/mysql -v /opt/mecodify/config:/app/site_config myusername/mecodify:1.21

To make the application usable you need to add your own configuration in `configuration.php` that you provide as shown above:


    $allow_new_cases=0; //allow adding new cases (can be set when you wish to prevent altering the DB
    $max_tweets_per_case=500000; //maximum tweets per case (can be exceeded by 100 records max)
    
    $mysql_db="mecodify";
    $mysql_server = "localhost";
    $mysql_user = "mecodify";
    $mysql_pw = "thisisastrangepasswordforourdatabase";
    
    $website_url=""; //e.g., https://mecodify.org . Don't end with '/'
    $website_title="";
    
    $admin_email="";
    $admin_name="";
    
    $smtp_host="";
    $smtp_secure=""; //can be "ssl" or "tls"
    $smtp_port="";
    $smtp_user="";
    $smtp_pw="";
    
    $twitter_api_settings=array( // you need at least one set, there is no max!
             array(
              'oauth_access_token' => "",
              'oauth_access_token_secret' => "",
              'consumer_key' => "",
              'consumer_secret' => ""
              )
            );

