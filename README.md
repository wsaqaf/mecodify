# Mecodify tool for Twitter data analysis & visualisation (v3)
![N|Solid](https://mecodify.org/images/logo3.png)

`In June 2023, Twitter appears to have ended its free GET SEARCH API feature as part of the academic license to access premium API services. If your search does not yield results on Mecodify, your account may have been affected by this change.`

## 
Mecodify is an open-source tool created as part of the Media Conflict and Democratization Project (http://mecodem.eu) to help gather, analyse and visualise Twitter data for use by social science scholars. The name describes what it does, i.e., Message Codification by converting messages to systematic structures, tables, graphs and quantifiable content.

The platform remains in constant development and the previous version (2.0) has been adapted to use Twitter's new V2 API and xscraper integration. This current version (V3.0) introduces significant stability and analysis improvements.

The software mainly uses PHP and Javascript and has used several open-source libraries including but not limited to HighCharts, D3Js for various components of the platform.

### What's new in Mecodify 3.0 (Latest)

- **Enhanced Mentions Analysis**: Redesigned mentions logic to accurately track account mentions across the entire dataset, distinguishing between accounts with and without tweets in the local database.
- **Improved Verification Metrics**: Updated the parsing engine to correctly extract and display `user_verified` status, distinguishing between legacy verification and the "blue check" status.
- **Optimized Data Ingestion**: Major performance improvements in handled large CSV/JSON imports from the [xscraper Chrome Extension](https://github.com/wsaqaf/xscraper).
- **Core Engine Refactoring**: Massive cleanup and optimization of `fetch_tweets.php` and associated frontend logic (`tweets.js`, `tweeters.js`) for faster data visualization.
- **Extended Docker Support**: Improved Docker configurations for smoother local environment setup.

### What's new in Mecodify 2.0

- **Docker Support (New!)**: Easily install and run Mecodify locally using Docker and Docker Compose. No more complex server setups!
- **xscraper Integration**: Since Twitter API access has become restricted, Mecodify now seamlessly integrates with the [xscraper Chrome Extension](https://github.com/wsaqaf/xscraper) to allow uploading case datasets directly via CSV imports.
- Compatible with the next generation Twitter API (2.0), which has a rich set of new functions and features
- Allows using API credentials for sandbox or premium API access
- Removed the web-based search mechanism in favor of sticking exclusively to the API data for enhanced reliability
- More flexible period options with the ability to add exact times to the second for each case
- Allows deciding whether to include retweets and/or asociated tweets (such as the original tweet, whose reply is among the results)
- Allows expanding the period even after it was created. However, limiting the period does not filter out fetched results
- If dates are not specified, it allows rerunning the case again to get fresh tweets since the last search
- Adding the ability to see the number of reply and quote tweets for each tweet
- Plus some other improvements in security and stability

**Note** New releases (minor and major) will continue to be pushed to this repo. Track changes by following this repo. Reports of bugs or issues are welcome...

### Installation
Installation instructions can be found [here](install.md)

### Documentation
The user manual can be found [here](manual.md). It is subject to updates and improvements.

### Academic research
Several scholars have already successfully used Mecodify in the past to extract and analyze Twitter data. You can look into some of the research through [Google Scholar](https://scholar.google.com/scholar?q=%22mecodify%22+-intitle:mecodify).

### Demo
To see a live and fully functional demo on our server, go [here](https://mecodify.org/demo) and enter 'demo@mecodify.org' as username and password. Mecodify has been tested using Chrome browser. We appreciate bug reports when using other browsers.

### Resources (mostly on the older version):
#####- [White paper](https://mecodify.org/mecodify-whitepaper.pdf)
#####- [Webinar and video demo](https://www.youtube.com/watch?v=_wWYm-kobLI) hosted by Oxfam
#####- [Brief article](http://datadrivenjournalism.net/resources/mecodify) published by DDJ


### NOTICE
The developer of Mecodify is not liable or responsible for anything that may result from its use. As in any piece of code, there may well be vulnerabilities or bugs. So do your due diligence please!

For more information, feel free to contact us on [admin@mecodify.org](mailto:admin@mecodify.org)

###### [Walid Al-Saqaf](http://al-saqaf.se)
developer
