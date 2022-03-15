## Install on cloud services AWS LightSail or DigitalOcean:

Here you can find instructions on how to install Mecodify on two of the most popular scalable cloud services. This is not an endorsement of the two services. However, since they are among the most popular, scripts were created and tested on each of them to facilitate installing the tool on either of them.

### Twitter API credentials:
Since Mecodify relies heavily on the Twitter API, you need to set up its credentials. If you haven't yet created a Twitter App and get a Bearer token, you need to do so before continuing. This tutorial would be helpful:
[https://developer.twitter.com/en/docs/authentication/oauth-2-0/bearer-tokens](https://developer.twitter.com/en/docs/authentication/oauth-2-0/bearer-tokens).

####1) DigitalOcean

1- Upon logging into your account, go to [https://cloud.digitalocean.com/droplets](https://cloud.digitalocean.com/droplets)

2- Click on Create ->Droplets

3- Under Choose an image, click on Marketplace

4- Select the image "LAMP on Ubuntu 20.04"

5- Under "Select additional options", select "User data" and in the text box that appears, paste the code you find in the digitalocean *[digitalocean_user_data.txt](https://raw.githubusercontent.com/wsaqaf/mecodify/master/cloud_configs/digitalocean_user_data.txt)* file

6- Copy and paste the Twitter API BEARER token you have created at developer.twitter.com into the correct location in the code (twitter_api_bearer=''). If you are not using an academic license from Twitter, change is_premium=true to is_premium=false

7- Click on the button 'Create Droplet' and give it around 5-10 minutes to finish.

8- You can then open copy the IP (ip4) address of the droplet and open it in a browser, after which you can sign up and start working on your own Mecodify setup.

####2) LightSail (AWS)

1- Upon logging into your AWS account, go to [https://lightsail.aws.amazon.com/ls/webapp/home/instances](https://lightsail.aws.amazon.com/ls/webapp/home/instances)

2- Click on Create instance

3- Under Select a blueprint, click on LAMP (PHP 7)

4- Under "Optional", click on "Add launch script" and in the text box that appears, paste the code you find in the lightsail *[lightsail_launch_script.txt](https://raw.githubusercontent.com/wsaqaf/mecodify/master/cloud_configs/lightsail_launch_script.txt)* file

5- Copy and paste the Twitter API BEARER token you have created at developer.twitter.com into the correct location in the code (twitter_api_bearer=''). If you are not using an academic license from Twitter, change is_premium=true to is_premium=false

6- Click on the button 'Create instance' and give it around 5-10 minutes to finish.

7- You can then open copy the IP (ip4) address of the instance and open it in a browser, after which you can sign up and start working on your own Mecodify setup.

**Notes:**
- Depending on your budget, you have several plan options, but you can leave everything as default at the start and can scale up later without trouble
- You can always go into the server through the console link provided to you by the cloud service you choose. Doing so would require some technical skills, however.
- It is highly recommended that you buy a domain name and secure it with an SSL certificate for extra security or setup the firewall to only allow access from your IP address. If you are unsure, it may be a better option to use the Docker version of Mecodify since it is confined to your own device and not accessible externally.
