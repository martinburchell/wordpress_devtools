#!/usr/bin/python

"""
http://codex.wordpress.org/Upgrading_WordPress_Extended
 Delete the old WordPress files on your site, but DO NOT DELETE

    wp-config.php file;
    wp-content folder; Special Exception: the wp-content/cache and the wp-content/plugins/widgets folders should be deleted.
    wp-images folder;
    wp-includes/languages/ folder--if you are using a language file do not delete that folder;
    .htaccess file--if you have added custom rules to your .htaccess, do not delete it;
    robots.txt file--if your blog lives in the root of your site (ie. the blog is the site) and you have created such a file, do not delete it. 

"""

# TODO: Tidy up - use rsync?

from git import *
import os
import shutil
import sys
import tarfile

from website_config import env
from web_automation.website import Website


def delete_path(path):
    if os.path.isdir(path):
        shutil.rmtree(path)
    elif os.path.isfile(path):
        os.remove(path)

# Check everything checked in
# repo = Repo(env.wordpress_local_source)
# if repo.is_dirty():
#    print 'There are changes in the repository'
#    sys.exit()

# Download new zip
website = Website('wordpress.org')

print 'Downloading tar file...'
tar_file_name = website.download_to_file(website.insecure_domain + '/latest.tar.gz')

print 'Deleting old files...'
files_to_keep = ['wp-config.php',
                 'wp-config-remote.php',
                 '.htaccess', 
                 'robots.txt']
             
absolute_files_to_keep = [os.path.join(env.wordpress_local_source,
                                       path) for path in files_to_keep]

dirs_to_keep = ['wp-content',
                'wp-images',
                'wp-includes']

absolute_dirs_to_keep = [os.path.join(env.wordpress_local_source,
                                      path) for path in dirs_to_keep]

for entry in os.listdir(env.wordpress_local_source):
    path = os.path.join(env.wordpress_local_source, entry)

    if os.path.isfile(path):
        if path not in absolute_files_to_keep:
            delete_path(path)
    elif os.path.isdir(path):
        if path not in absolute_dirs_to_keep:
            delete_path(path)

relative_paths_to_delete = [os.path.join('wp-content', 'cache'),
                            os.path.join('wp-content', 'plugins', 'widgets')]

absolute_paths_to_delete = [os.path.join(env.wordpress_local_source,
                                         path) for path in relative_paths_to_delete]

for path in absolute_paths_to_delete:
    delete_path(path)

include_dir = os.path.join(env.wordpress_local_source, 'wp-includes')

for entry in os.listdir(include_dir):
    path = os.path.join(include_dir, entry)

    if entry not in ['languages']:
        delete_path(path)

print 'Installing...'
tar = tarfile.open(tar_file_name)
tar.extractall(env.website_dir)
tar.close()
