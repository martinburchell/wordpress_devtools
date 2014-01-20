<?php
/**
 * Backup the database of a given WordPress site.
 * Adapted from http://www.bin-co.com/blog/wp-content/uploads/2008/10/backup.txt
 *
 * See:
 * http://www.bin-co.com/blog/2008/10/remote-database-backup-wordpress-plugin/
 */

if ($argc == 13)
{
    $sync_with_local = true;
}
else if ($argc == 6)
{
    $sync_with_local = false;
}
else
{
    print "Unexpected number of arguments ($argc != 13 and $argc != 6) - exiting...\n";
    exit(1);
}

$arg_counter = 1;

$site_url = $argv[$arg_counter++]; //The URL of the online wordpress site
$username = $argv[$arg_counter++]; // Admin username
$password = $argv[$arg_counter++]; // Admin password

$debug_dir = $argv[$arg_counter++];
$remote_backups_dir = $argv[$arg_counter++];


if ($sync_with_local)
{
    $local_url = $argv[$arg_counter++]; //The url of the local site
    $local_db_server = $argv[$arg_counter++]; // Mysql database server
    $local_db_username = $argv[$arg_counter++]; // Mysql username
    $local_db_password = $argv[$arg_counter++]; // Mysql password
    $local_db = $argv[$arg_counter++]; // The database name for the local wordpress.

    $local_backups_dir = $argv[$arg_counter++];
    $prompt = ($argv[$arg_counter++] == 1);
}


$remote_backup_file_name = log_in_and_back_up($remote_backups_dir, $site_url, 
                                              $username, $password, 
                                              $debug_dir);

if($sync_with_local)
{
    print "Syncing with Local database ...\n";
        
    $remote_tmp_file = tempnam(sys_get_temp_dir(), 'remote');
    `gzip -cd $remote_backup_file_name > $remote_tmp_file`;

    $local_tmp_file = tempnam(sys_get_temp_dir(), 'local');

    $local_backup_file_name = log_in_and_back_up($local_backups_dir, 
                                                 $local_url, 
                                                 $username, $password, 
                                                 $debug_dir);

    $escaped_local_url = str_replace("/", "\/", $local_url);
    $escaped_site_url = str_replace("/", "\/", $site_url);
    $sed_command = "sed 's/$escaped_local_url/$escaped_site_url/g'";

    `gzip -cd $local_backup_file_name | $sed_command > $local_tmp_file`;

    $overwrite = true;

    if ($prompt)
    {
        print "Comparing local and remote databases...\n\n";
        print `diff -i -b $local_tmp_file $remote_tmp_file`;

        print "\nApply remote changes? [y/n] :";

        $handle = fopen ("php://stdin","r");
        $user_input = trim(strtolower(fgets($handle)));

        if ($user_input != 'y' && $user_input != 'yes')
        {
            $overwrite = false;
        }
    }

    if ($overwrite)
    {
        print "Importing...\n";
        $output = array();

        exec("mysql -h $local_db_server -u $local_db_username -p$local_db_password $local_db < $remote_tmp_file", $output, $return_code);

        if ($return_code != 0)
        {
            print_r($output); 
            exit($return_code);
        }

        unlink($remote_tmp_file);
        unlink($local_tmp_file);        

        $pdo = get_database_connection(
            $local_db_server,
            $local_db,
            $local_db_username,
            $local_db_password);

        update_urls($pdo, $local_url, $site_url);
        print "Import Done\n";
    }
    else
    {
        print "Changes not applied\n";
        print "Remote dump is in the file $remote_tmp_file\n";
        print "Modified local dump is in the file $local_tmp_file\n";
    }
}

print "All Done\n";

function update_urls($pdo, $local_url, $site_url)
{
    $updates = array(
        "UPDATE wp_options SET option_value = replace(option_value, '$site_url', '$local_url')",
        "UPDATE wp_posts SET guid = replace(guid, '$site_url', '$local_url')",
        "UPDATE wp_posts SET post_content = replace(post_content, '$site_url', '$local_url')",
        );

    foreach($updates as $update)
    {
        echo $update;
        $statement = $pdo->prepare($update);
        $ok = $statement->execute();
        if ($ok === false)
        {
            echo "Query failed:\n$update";
            
            echo "\nPDOStatement::errorInfo():\n";
            print_r($statement->errorInfo());

            exit(1);
        }
    }
}

function get_database_connection($host, $db_name, $username, $password)
{
    try
    {
        $connection = "mysql:host=$host;dbname=$db_name";
        echo $connection;

        $pdo = new PDO(
            $connection,
            $username,
            $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    catch (PDOException $e)
    {
        echo "Failed to connect to database $local_db_server";
        echo $connection;
        echo $e->getMessage();
        exit(1);            
    }

    print_r($pdo);

    return $pdo;
}

function log_in_and_back_up($remote_backups_dir, $wordpress_url, 
                            $username, $password, $debug_dir)
{
    $log_in_result = log_in($wordpress_url, $username, $password, $debug_dir);
    $backup_file_name = back_up($remote_backups_dir, $wordpress_url, 
                                $log_in_result, $debug_dir);

    return $backup_file_name;
}

function log_in($wordpress_url, $username, $password, $debug_dir)
{
    // Login to wordpress
    print "Logging into WordPress ... ";

    $result = load($wordpress_url."/wp-login.php?log=$username&pwd=" . urlencode($password) ."&wp-submit=Log In", array('method'=>'post','return_info'=>true, 'curl_handle'=>false));

    if (!$result['body'])
    { // The returned page was empty
	file_put_contents("$debug_dir/login.html", $result['body']);
	print "WordPress not found at the specified location($wordpress_url) - exiting...\n";
	exit(1);
    }
    if ($result['info']['url'] != $wordpress_url . '/wp-admin/')
    { //The redirection was not proper.
	file_put_contents("$debug_dir/login.html", $result['body']);
	print "Invalid Username or Password given for $wordpress_url. Please correct it.\n";
	exit(1);
    }

    return $result;
}

function back_up($remote_backups_dir, $wordpress_url, $log_in_result, $debug_dir)
{
    $extra_tables = array(
        'wp_blc_filters',
        'wp_blc_instances',
        'wp_blc_links',
        'wp_blc_sync',
        'wp_usermeta', 
        'wp_users',
    );

    $prefix = "&other_tables[]=";
    $extra_tables_params = $prefix . implode($prefix, $extra_tables);

    print "done. Creating a backup ... \n";

    $url = $wordpress_url . '/wp-admin/edit.php?page=remote-database-backup/backup.php' . $extra_tables_params . '&action=Backup';

    $backup = load($url, array('method'=>'get', 'return_info'=>true, 'curl_handle'=>$log_in_result['curl_handle']));

    curl_close($backup['curl_handle']);

    if (preg_match('/id="download-url" href="([^"]+)">/', $backup['body'], $download_url))
    {
	print "Done. Downloading $download_url[1] ...\n";
    }
    else 
    { // Backup file's Download link was not found.
	file_put_contents("$debug_dir/backup.html", $backup['body']);
	print "Download file not found - exiting...\n";
	exit(1);
    }

    $database_dump = load($download_url[1]); //Download the backup file.
    $parts = array_reverse(explode('/', $download_url[1]));
    $backup_file_name = "$remote_backups_dir/${parts[0]}"; //Gets the last part - we reversed the array.

    print "Done. Saving it to file ... ";
    file_put_contents($backup_file_name, $database_dump); //Save it to file
    print "Done\n";

    return $backup_file_name;
}



/**
 * Link: http://www.bin-co.com/php/scripts/load/
 * Version : 2.00.A
 */
function load($url,$options=array())
{
	if(!isset($options['method'])) $options['method'] = 'get';
	if(!isset($options['return_info'])) $options['return_info'] = false;
	if(!isset($options['cache'])) $options['cache'] = false;

    $url_parts = parse_url($url);
    $ch = false;
    $info = array(//Currently only supported by curl.
        'http_code'    => 200
    );
    $response = '';
    
    $send_header = array(
        'Accept' => 'text/*',
        'User-Agent' => 'BinGet/1.00.A (http://www.bin-co.com/php/scripts/load/)'
    );
    
    if ($options['cache'])
    {
    	$cache_folder = '/tmp/php-load-function/';
    	if (!file_exists($cache_folder))
            mkdir($cache_folder, 0777);
    	if (isset($options['cache_folder']))
            $cache_folder = $options['cache_folder'];
    	
    	$cache_file_name = str_replace(array('http://', 'https://'),'', $url);
    	$cache_file_name = str_replace(
    		array('/','\\',':','?','&','='), 
    		array('_','_','-','.','-','_'), $cache_file_name);
    	$cache_file = joinPath($cache_folder, $cache_file_name); //Don't change the variable name - used at the end of the function.
    	
    	if (file_exists($cache_file))
        { // Cached file exists - return that.
            if(!$options['return_info'])
                return file_get_contents($cache_file);
            else
                return array('headers' => array('cached'=>true), 'body' => file_get_contents($cache_file), 'info' => array('cached'=>true));
    	}
    }

    ///////////////////////////// Curl /////////////////////////////////////
    //If curl is available, use curl to get the data.
    if(function_exists("curl_init") 
                and (!(isset($options['use']) and $options['use'] == 'fsocketopen'))) { //Don't use curl if it is specifically stated to use fsocketopen in the options
        
        if (isset($options['post_data']))
        { //There is an option to specify some data to be posted.
        	$page = $url;
        	$options['method'] = 'post';
        	
        	if (is_array($options['post_data']))
                { //The data is in array format.
                    $post_data = array();
                    foreach($options['post_data'] as $key=>$value)
                    {
                        $post_data[] = "$key=" . urlencode($value);
                    }
                    $url_parts['query'] = implode('&', $post_data);
			
                }
                else
                { //Its a string
                    $url_parts['query'] = $options['post_data'];
                }
        }
        else
        {
            if (isset($options['method']) and $options['method'] == 'post')
            {
                $page = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];
            }
            else
            {
                $page = $url;
            }
        }

        if (!isset($options['curl_handle']) or !$options['curl_handle'])
            $ch = curl_init($url_parts['host']);
        else
            $ch = $options['curl_handle'];
        
        curl_setopt($ch, CURLOPT_URL, $page) or die("Invalid cURL Handle Resouce");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Just return the data - not print the whole thing.
        curl_setopt($ch, CURLOPT_HEADER, true); //We need the headers
        curl_setopt($ch, CURLOPT_NOBODY, false); //The content - if true, will not download the contents
        if(isset($options['method']) and $options['method'] == 'post' and isset($url_parts['query']))
        {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $url_parts['query']);
        }
        //Set the headers our spiders sends
        curl_setopt($ch, CURLOPT_USERAGENT, $send_header['User-Agent']); //The Name of the UserAgent we will be using ;)
        $custom_headers = array("Accept: " . $send_header['Accept'] );
        if(isset($options['modified_since']))
            array_push($custom_headers,"If-Modified-Since: ".gmdate('D, d M Y H:i:s \G\M\T',strtotime($options['modified_since'])));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);

        curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/binget-cookie.txt"); //If ever needed...
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        if(isset($url_parts['user']) and isset($url_parts['pass']))
        {
            $custom_headers = array("Authorization: Basic ".base64_encode($url_parts['user'].':'.$url_parts['pass']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $custom_headers);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch); //Some information on the fetch
        if(!isset($options['curl_handle'])) curl_close($ch); //Dont close the curl session if the curl handle is passed. We may need it later.

    //////////////////////////////////////////// FSockOpen //////////////////////////////
    }
    else
    { //If there is no curl
    	print "Curl not installed - this script needs curl to work.\n";
    	exit(1);
    }

    //Get the headers in an associative array
    $headers = array();

    if($info['http_code'] == 404)
    {
        $body = "";
        $headers['Status'] = 404;
    }
    else
    {
        //Seperate header and content
        $separator_position = strpos($response,"\r\n\r\n");
        $header_text = substr($response,0,$separator_position);
        $body = substr($response,$separator_position+4);
        
        foreach(explode("\n",$header_text) as $line)
        {
            $parts = explode(": ",$line);
            if(count($parts) == 2) $headers[$parts[0]] = chop($parts[1]);
        }
    }
    
    if(isset($cache_file))
    { //Should we cache the URL?
    	file_put_contents($cache_file, $body);
    }

    if($options['return_info'])
        return array('headers' => $headers, 'body' => $body, 'info' => $info, 'curl_handle'=>$ch);

    return $body;
}
