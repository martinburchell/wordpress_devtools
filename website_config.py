#!/usr/bin/python

import os.path
import re

class Environment(object):
    pass

bin_dir = os.path.realpath(os.path.dirname(__file__))
config_file = os.path.join(bin_dir, "website_config")

env = Environment()
env.bin_dir = bin_dir

for line in open(config_file):
    line = line.strip()
    if line == '':
        continue
    
    name, value = line.split('=')

    regex = re.compile("\$\{(.*?)\}")
    vars = regex.findall(value)

    for var in vars:
        search = "${" + var + "}"
        value = value.replace(search, getattr(env, var))
        
    if not hasattr(env, name):
        setattr(env, name, value)
