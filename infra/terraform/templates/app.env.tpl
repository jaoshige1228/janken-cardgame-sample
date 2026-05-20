APP_NAME=JankenCard
APP_ENV=production
APP_DEBUG=false
APP_KEY=${app_key}
APP_URL=${app_url}

LOG_CHANNEL=stderr
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=${db_host}
DB_PORT=3306
DB_DATABASE=janken
DB_USERNAME=janken
DB_PASSWORD=${db_password}

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${reverb_app_id}
REVERB_APP_KEY=${reverb_app_key}
REVERB_APP_SECRET=${reverb_app_secret}
REVERB_HOST=${reverb_host}
REVERB_BROADCASTING_HOST=${reverb_broadcasting_host}
REVERB_PORT=8080
REVERB_SCHEME=http

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
