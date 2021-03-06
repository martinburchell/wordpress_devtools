#!/usr/bin/python

from website_config import env
from wordpress_admin import WordpressAdmin

website = WordpressAdmin(env.remote_domain,
                         env.admin_user,
                         env.admin_password)
website.log_in()
website.refresh_calendars()
