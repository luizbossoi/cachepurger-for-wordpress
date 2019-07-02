# cachepurger-for-wordpress
This plugin purges your Cloudflare cache when you save or edit a post.

# Oficial plugin page
You can install this plugin by searching for "Cloudflare Cache Purger" in your Wordpress plugin store.
This plugin is free and OpenSouce

You can also download the ZIP release and unzip on your wp-content/plugins/ folder to manually install.

Plugin page: https://wordpress.org/plugins/cf-cachepurger/

# Why should I use this plugin?
If you use CloudFlare in your Wordpress website and you want to boost it's performance, you need to set your Page Rule to "Cache Everything". Unfortunately, this will cache your whole website, homepage, post page and everytime you post/edit new content you'll have to manually purge your cache on CloudFlare's dashboard.

Currently, CloudFlare's provides a plugin for Wordpress but it just purge your cache in case of theme changes, not post changes.
So, if you are planning to BOOST your performance with CloudFlare, this plugin is what you need to avoid caching issues.

Follow this article on CloudFlare: https://support.cloudflare.com/hc/en-us/articles/228503147-Speed-Up-WordPress-and-Improve-Performance

# Is this plugin REALLY necessary?
If you are running under CloudFlare default settings, you don't need this plugin since CloudFlare's won't cache dynamic content by default. But if you're planning to boost performance by caching dynamic content, you should use it to avoid caching issues.

# How to install?
Pretty easy to install. Clone / Zip this repository and upload it to wp-content/plugins/ folder.
After that, enable this plugin in your Wordpress Plugin Settings.

# How to Configure?
You just need to fill two fields in this plugin settings to make it Work.
After enabled, in your Wordpress Dashboard go to Settings > CF CachePurger and fill your e-mail and CloudFlare Global API Key.

# How to get my Cloudflare API Key?
  1. Login into your CloudFlare account
  2. Enter your Profile settings page (right icon on top)
  3. Scroll down and copy "Global API Key" 

# When the cache is purged?
Everytime you edit or post a new content the cache will be cleared.

# Which pages this plugin purges?
This plugin tries to clear all related paths to avoid dummy caching. This plugin purges:
  - the post Slug URL (yoursite.com/the-post-name)
  - the post Slug URL with and without WWW, with and without SSL (httpS/http) 
  - your wordpress home URL (get_home_url())
  - your wordpress home URL with ending slash (get_home_url()/)

# How to debug?
This plugin has a debug section under the Settings page where you can see a textarea box with some informations about what's happening.
Also, if necessary you can download this plugin logs inside wp-content/plugins/cachepurger-for-wordpress/purge.log

# What do I need to run this plugin properly?
  - PHP 5.6+
  - Server enabled cURL (to send purge requests to CloudFlare)
  - fopen enabled (to log and read debug content)
  - Website enabled and working on CloudFlare

# Is it enough?
Yes, probably it's enough :)

# Bugs, issues, questions
If you have any question or issue related to this plugin, please fell free to contribute reporting it.

# Docker - test it
You can use docker-run.sh to start a Wordpress + MySQL container on your computer to test this plugin.
Just remember - if you're planning to test the purge action, will be not able to do that because of localhost "domain".
Server starts at : http://localhost:80000

From: https://github.com/luizbossoi/cachepurger-for-wordpress