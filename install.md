# Mecodify 
![N|Solid](images/logo3.png)

# Installation

Here you will find instructions for running Mecodify on your server.

### Interdepencies
Mecodify requires PHP, MySQL and Apache to run. Generally, most web servers already have them installed by default. But you can always double check in case. The three required interdepencies are PHP, MySQL and Apache.

1- **PHP 5.0 or higher:** If you are unsure if you have PHP 5.0 or higher, you can run the following command on the server:
```sh
$ php -v
```
In order to install PHP, you can refer to the official PHP installation instructions found here: [http://php.net/manual/en/install.php](http://php.net/manual/en/install.php)

2- **MySQL 5.5 or higher:** Mecodify relies extensively on MySQL tables to store data. You can see if you have MySQL by running the command:
```sh
$ mysql --version
```

3- **Apache 2.0 or higher:** Since Mecodify requires to run as a web service, your server needs to have Apache. You can check your Apache version by running the following:
```sh
$ httpd -v
```

# Installation steps

### 1- Download the latest release: 
You should ensure that you have the latest version of Mecodify by checking the latest release on GitHub at [https://github.com/wsaqaf/mecodify/releases/](https://github.com/wsaqaf/mecodify/releases/). You can click on "Source code (zip)" and upon downloading it to the folder where you wish to have it installed on your server, you can decompress it.

### 2- Configure Mecodify:
In the main folder, you will find the file named `configurations_empty.php`. With any text editor, go into the file and add the missing values for each of the following variables:

#### Performance-related
You may want to adjust the following variables 
```sh
$allow_new_cases=1;
$max_tweets_per_case=500000;

```
whereas:

- **allow_new_cases** indicates whether you would allow users to create cases. Can be handy when you reached your database storage limit or doing maintenance for example.
- **max_tweets_per_case** How many tweets could each case be. This depends on your capacity, API usage, user activity, etc. It is set to 500k by default.

#### Website-related
You need to fill in the following information
```sh
$website_url="";
$website_title="";
```
whereas:

- **website_url** is the exact URL that Mecodify will run on your website, e.g., https://yourwebsite.com/mymecodify.
- **website_title** is the title that will appear on top of each page. For example, if the title is "MECODEM Twitter Analysis", then that title will be shown as below with the addition of "(powered by Mecodify)".

#### Admin-related
```sh
$admin_email="";
$admin_name="";
```
whereas:
- **admin_email** is your email as the administrator of this instance of Mecodify. The admin enjoys a number of privilges including: Getting notified when a new user joins, a new case is created or edited, ability to view, edit, and delete any case (even those that are private). In the future, it will be possible to add a feature to approve new membership requests.*
- **admin_name** is your name as the administrator, which will be useful in communicating certain messages to regular users*

#### Database-related
You need to fill in the following MySQL database information
```sh
$mysql_db="";
$mysql_server = "";
$mysql_user = "";
$mysql_pw = "";
```
whereas
- **mysql_db** is the name of the MySQL database, which you can obtain from the MySQL setup.
- **mysql_server** is the name of the server, which is often `localhost` if you have root access to the server.
- **mysql_user** is the user name to access the database (must have all add and delete priviliges).
- **mysql_pw ** is the MySQL password for the above user.

#### Email-related
The below details are *optional* and are needed by the phpmailer library if you -as the admin- wish to receive emails about new signups or cases:
```sh
$smtp_host="";
$smtp_secure="";
$smtp_port="";
$smtp_user="";
$smtp_pw="";
```
whereas:
- **smtp_host** This is SMTP host, which usually starts with (smtp.)
- **smtp_secure** can be "ssl" or "tls"
- **smtp_port** this depends on the type of service (usually 465 for ssl)
- **smtp_user** The username required for SMTP authentication
- **smtp_pw** The SMTP password required for authentication

#### Your own logo
Mecodify comes with a default logo available at `./images/logo.png`. However, you can of course replace it with any logo you like provided that it is of a size that could fit the upper left corner (preferably 120x750 px or smaller).

### 3- Configure Twitter API credentials:
Since Mecodify relies heavily on the Twitter API, you need to set up its credentials. If you haven't yet created a Twitter API credentials before, then you are recommended to check the API tutorial here:
https://www.slickremix.com/docs/how-to-get-api-keys-and-tokens-for-twitter/

Once ready, you will need to fill in the values for the following variables:
`oauth_access_token`, `oauth_access_token_secret`,`consumer_key`,`consumer_secret`
which will need to be put directly in the below array. Note that you are free to  have as many API credentials as you wish. This helps you deal with a high demand of queries by rotating through the various credentials since each API has its own rate limitations.
```sh
$twitter_api_settings=array( 
         array(
          'oauth_access_token' => "/*access_token1*/",
          'oauth_access_token_secret' => "/*access_token_secret1*/",
          'consumer_key' => "/*consumer_key1*/",
          'consumer_secret' => "/*consumer_secret1*/"
          ),
         array(
          'oauth_access_token' => "/*access_token2*/",
          'oauth_access_token_secret' => "/*access_token_secret2*/",
          'consumer_key' => "/*consumer_key2*/",
          'consumer_secret' => "/*consumer_secret2*/"
          ),
/*here you can add as many additional entries as you want*/
        );
```
### 4- Rename configurations_empty.php to configurations.php

### 5- Ensure that temporary folder are writable
The tmp folder and its subfolders need to be writable by the webserver for caching, etc.:
From the command prompt, you can change ownership of the folders recursively using the command
```sh
sudo chown -R _www ./tmp
```
obviously, the name of the webserver account could be different (e.g., apache, www-data, nobody, www...). So make sure you apply the correct credentials.

### 6- Read the user manual, start experimenting:
You will need to check and confirm if all is working. The first step to do so is by reading the [manual](manual.md). If you succeed in creating an account and a case, then you have succeeded in your installation. If not, then you will need to go back to see where there may be a problem. Check the below troubleshooting tips and if all fails, you can reach out to us on [admin@mecodify.org](mailto:admin@mecodify.org) for help.
___

## Troubeleshooting
Depending on the problem you are trying to fix, there may be a host of things that you could do:

###### 1- Check if the installation steps are done exactly as required
###### 2- Ensure that PHP, MySQL and Apache are all functioning normally
###### 3- Ensure that folder permissions are in their original status except for the folders that  require to be writable (step 5)
###### 4- See if your server configurations allow PHP to execute binary files, which is a requirement in `fetch_process.php`
###### 5- If all fails, email us on [admin@mecodify.org](mailto:admin@mecodify.org) to investigate further.

