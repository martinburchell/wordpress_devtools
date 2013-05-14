# SQL file to correct URLs in wordpress

UPDATE wp_options SET option_value = replace(option_value, 'http://castlestreet.localhost', 'http://www.castlestreet.org.uk');
UPDATE wp_posts SET guid = replace(guid, 'http://castlestreet.localhost','http://www.castlestreet.org.uk');
UPDATE wp_posts SET post_content = replace(post_content, 'http://castlestreet.localhost', 'http://www.castlestreet.org.uk');
