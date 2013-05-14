# SQL file to correct URLs in wordpress

UPDATE wp_options SET option_value = replace(option_value, 'http://www.castlestreet.org.uk', 'http://castlestreet.localhost');
UPDATE wp_posts SET guid = replace(guid, 'http://www.castlestreet.org.uk', 'http://castlestreet.localhost');
UPDATE wp_posts SET post_content = replace(post_content, 'http://www.castlestreet.org.uk', 'http://castlestreet.localhost');
