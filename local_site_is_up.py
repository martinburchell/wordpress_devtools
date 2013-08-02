#!/usr/bin/python

from website_config import env
from web_automation.website import Website

website = Website(env.local_domain)

if not website.is_up():
    exit(1)
