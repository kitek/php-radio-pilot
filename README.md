# php-radio-pilot
Google App Engine app for fetching and serving news from [Autopilot Radio Gdańsk](http://radiogdansk.pl/autopilot) web page.

## Requirements

- [Google Cloud Platform account](https://console.cloud.google.com/) (free tier is enough)
- [Firebase account](https://console.firebase.google.com) (spark tier is enough)
- [gcloud tools](https://cloud.google.com/sdk/gcloud/)

## Installation

- [setup gcloud](https://cloud.google.com/sdk/docs/initializing) and create GAE app
- [create Firebase app](https://console.firebase.google.com)
- copy Firebase Cloud Messaging Server key (Project Settings -> Cloud Messaging)
- download source code
- copy `config/settings.yml.dist` to `config/settings.yml` and setup `fcm_server_key`
- install [composer](https://getcomposer.org/) dependencies: `php composer.phar update`

## Usage

To run app on [Local Development Server](https://cloud.google.com/appengine/docs/standard/python/tools/using-local-server) execute: 

`dev_appserver.py -A php-radio-pilot .`

To deploy app execute:

`gcloud app deploy --version 1`

`gcloud app deploy cron.yaml`

## More

- [REST API doc](http://kitek.pl/php-radio-pilot/)
- [Try it out](https://radio-pilot.appspot.com/news)
